#!/usr/bin/env bash
# Invoked by deploy.sh over SSH, on the Coolify host.
set -euo pipefail
umask 077

mode=$1
stack=$2
target_url=$3
staging=$4
table_prefix=$5
case "$mode" in theme|full) ;; *) echo 'Invalid deployment mode.' >&2; exit 2 ;; esac
[[ "$stack" =~ ^[a-z0-9]+$ && "$table_prefix" =~ ^[a-zA-Z0-9_]+$ && "$staging" =~ ^/tmp/kogo-deploy\.[a-zA-Z0-9]+$ ]] || exit 2
wordpress="wordpress-$stack"
database="mariadb-$stack"
wp_root=/var/www/html
container_staging="$staging"
backup_root="$HOME/kogo-deploy-backups/$stack"
mkdir -p "$backup_root"
exec 9>"$backup_root/deploy.lock"
flock -n 9 || { echo 'Another Kogo deployment is running.' >&2; exit 1; }
backup=$(mktemp -d "$backup_root/$(date -u +%Y%m%dT%H%M%SZ).XXXXXXXX")
files_changed=false
database_changed=false
maintenance=false

mysql() {
	# Credentials remain inside the database container, never in logs or the repository.
	docker exec -i "$database" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mariadb --default-character-set=utf8mb4 -u root "$MYSQL_DATABASE"'
}
reset_database() {
	docker exec "$database" sh -c '
		case "$MYSQL_DATABASE" in ""|*[!a-zA-Z0-9_]*) exit 2 ;; esac
		MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mariadb -u root -e "DROP DATABASE \`$MYSQL_DATABASE\`; CREATE DATABASE \`$MYSQL_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
	'
}
wp() {
	docker exec -u www-data -e WP_CLI_CONFIG_PATH="$container_staging/wp-cli.yml" "$wordpress" php "$container_staging/wp-cli.phar" --path="$wp_root" --skip-plugins --skip-themes "$@"
}
cleanup() {
	status=$?
	trap - EXIT
	set +e
	recovery_failed=false
	if [[ "$status" -ne 0 && "$files_changed" == true ]]; then
		echo "Deployment failed. Restoring backup: $backup" >&2
		docker exec "$wordpress" php -r 'file_put_contents("/var/www/html/.maintenance", "<?php \$upgrading = " . time() . ";");'
		maintenance=true
		if ! docker exec "$wordpress" rm -rf "$wp_root/wp-content" ||
			! docker exec -i "$wordpress" tar --no-same-owner -xzf - -C "$wp_root" < "$backup/wp-content.tar.gz" ||
			! docker exec "$wordpress" chown -R www-data:www-data "$wp_root/wp-content"; then recovery_failed=true; fi
		if [[ -f "$backup/htaccess" ]]; then
			if ! docker cp "$backup/htaccess" "$wordpress:$wp_root/.htaccess" ||
				! docker exec "$wordpress" chmod 644 "$wp_root/.htaccess" ||
				! docker exec "$wordpress" chown www-data:www-data "$wp_root/.htaccess"; then recovery_failed=true; fi
		fi
	fi
	if [[ "$status" -ne 0 && "$database_changed" == true ]]; then
		if ! reset_database || ! mysql < "$backup/database.sql"; then recovery_failed=true; fi
	fi
	if [[ "$recovery_failed" == true ]]; then
		echo "Recovery failed. Site remains in maintenance mode. Backup: $backup" >&2
	elif [[ "$maintenance" == true ]]; then docker exec "$wordpress" rm -f "$wp_root/.maintenance"; fi
	docker exec "$wordpress" rm -rf "$container_staging" >/dev/null 2>&1
	exit "$status"
}
trap cleanup EXIT

echo 'Preparing files and backing up the destination...'
if docker exec "$wordpress" test -f "$wp_root/.maintenance"; then echo 'WordPress is already in maintenance mode.' >&2; exit 1; fi
docker exec "$wordpress" mkdir -p "$container_staging/incoming"
docker cp "$staging/content.tar.gz" "$wordpress:$container_staging/content.tar.gz"
docker exec "$wordpress" tar --no-same-owner -xzf "$container_staging/content.tar.gz" -C "$container_staging/incoming"
[[ -s "$staging/content.tar.gz" ]] || exit 1
docker exec "$wordpress" test -f "$container_staging/incoming/themes/kogo/functions.php"
docker exec "$wordpress" tar -czf - -C "$wp_root" wp-content > "$backup/wp-content.tar.gz"
if docker exec "$wordpress" test -f "$wp_root/.htaccess"; then docker exec "$wordpress" cat "$wp_root/.htaccess" > "$backup/htaccess"; fi

if [[ "$mode" == full ]]; then
	docker cp "$staging/wp-cli.phar" "$wordpress:$container_staging/wp-cli.phar"
	docker cp "$staging/wp-cli.yml" "$wordpress:$container_staging/wp-cli.yml"
	docker exec "$wordpress" chmod 755 "$container_staging"
	docker exec "$wordpress" chmod 644 "$container_staging/wp-cli.phar"
	docker exec "$wordpress" chmod 644 "$container_staging/wp-cli.yml"
	[[ "$(wp option get home)" == "$target_url" ]] || { echo 'Destination URL does not match DEPLOY_URL. Nothing was changed.' >&2; exit 1; }
	[[ "$(wp config get table_prefix)" == "$table_prefix" ]] || { echo 'Destination table prefix differs. Nothing was changed.' >&2; exit 1; }
	docker exec "$database" sh -c '
		MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mariadb-dump -u root --single-transaction --skip-lock-tables --default-character-set=utf8mb4 "$MYSQL_DATABASE"
	' > "$backup/database.sql"
	[[ -s "$backup/database.sql" && -s "$staging/database.sql" ]] || exit 1
fi
echo "Backup saved: $backup"

# Keep the short file/database swap hidden from visitors, including during rollback.
docker exec "$wordpress" php -r 'file_put_contents("/var/www/html/.maintenance", "<?php \$upgrading = " . time() . ";");'
maintenance=true
files_changed=true
docker exec "$wordpress" rm -rf "$wp_root/wp-content/themes/kogo"
docker exec "$wordpress" mv "$container_staging/incoming/themes/kogo" "$wp_root/wp-content/themes/kogo"
docker exec "$wordpress" chown -R www-data:www-data "$wp_root/wp-content/themes/kogo"

if [[ "$mode" == full ]]; then
	for directory in plugins uploads; do
		docker exec "$wordpress" rm -rf "$wp_root/wp-content/$directory"
		docker exec "$wordpress" mv "$container_staging/incoming/$directory" "$wp_root/wp-content/$directory"
		docker exec "$wordpress" chown -R www-data:www-data "$wp_root/wp-content/$directory"
	done
	echo 'Importing the database...'
	database_changed=true
	reset_database
	mysql < "$staging/database.sql"
	wp option update home "$target_url"
	wp option update siteurl "$target_url"
	wp theme activate kogo
	wp core update-db
	# Load the theme and plugins when rebuilding their artist/exhibition/product routes.
	docker exec -u www-data -e WP_CLI_CONFIG_PATH="$container_staging/wp-cli.yml" "$wordpress" php "$container_staging/wp-cli.phar" --path="$wp_root" rewrite flush --hard
	wp cache flush
	[[ "$(wp option get home)" == "$target_url" && "$(wp option get siteurl)" == "$target_url" ]] || exit 1
	wp plugin list --fields=name,status,version
fi

# Check the rendered site after maintenance ends; failures trigger recovery.
docker exec "$wordpress" rm -f "$wp_root/.maintenance"
maintenance=false
curl --fail --silent --show-error --location --max-redirs 3 --retry 2 --max-time 30 --output /dev/null "$target_url/"
echo "Remote deployment succeeded. Backup: $backup"
