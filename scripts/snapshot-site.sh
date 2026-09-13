#!/usr/bin/env bash
# Run locally or copied to the Coolify host by snapshot.sh.
set -euo pipefail
umask 077
export COPYFILE_DISABLE=1

action=$1
target=$2
staging=$3
[[ "$action" == snapshot || "$action" == apply ]] && [[ "$target" == local || "$target" == remote ]] || exit 2
maintenance=false
changed=false
backup=
local_lock=
container_staging=

if [[ "$target" == local ]]; then
	wp_root=${LOCAL_WP_PATH:-$HOME/Local Sites/kogo/app/public}
	[[ -f "$wp_root/wp-config.php" ]] || { echo "Local WordPress is missing: $wp_root" >&2; exit 1; }
	wp_root=$(cd "$wp_root" && pwd -P)
	if [[ -f "$(dirname "$wp_root")/.envrc" ]]; then source "$(dirname "$wp_root")/.envrc"; fi
	command -v wp >/dev/null || { echo 'Start the site in Local; WP-CLI is required.' >&2; exit 1; }
	# WP-CLI skips MySQL option files; use the same socket as Local's PHP runtime.
	if [[ -n "${PHPRC:-}" && -z "${MYSQL_UNIX_PORT:-}" ]]; then
		MYSQL_UNIX_PORT=$(php -r 'echo ini_get("mysqli.default_socket");')
		export MYSQL_UNIX_PORT
	fi
	backup_root=${SNAPSHOT_DIR:?}/local
	site_exec() { "$@"; }
	wp_cli() { command wp --path="$wp_root" "$@"; }
	export_database() { wp db export "$1" --single-transaction --skip-lock-tables --default-character-set=utf8mb4 --quiet; }
	import_database() {
		# MariaDB's sandbox header is not understood by Local's MySQL client.
		sed '1{/999999.*enable the sandbox mode/d;}' "$1" > "$staging/import.sql"
		wp db import "$staging/import.sql" --quiet
	}
	reset_database() { wp db reset --yes --quiet; }
else
	stack=$4
	target_url=$5
	[[ "$stack" =~ ^[a-z0-9]+$ && "$staging" =~ ^/tmp/kogo-snapshot\.[a-zA-Z0-9]+$ && "$target_url" =~ ^https://[a-zA-Z0-9.-]+$ ]] || exit 2
	wordpress="wordpress-$stack"
	database="mariadb-$stack"
	wp_root=/var/www/html
	backup_root="$HOME/kogo-snapshot-backups/$stack"
	site_exec() { docker exec "$wordpress" "$@"; }
	wp_cli() { docker exec -u www-data -e WP_CLI_CONFIG_PATH="$staging/wp-cli.yml" "$wordpress" php "$staging/wp-cli.phar" --path="$wp_root" "$@"; }
	export_database() {
		docker exec "$database" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mariadb-dump -u root --single-transaction --skip-lock-tables --default-character-set=utf8mb4 "$MYSQL_DATABASE"' > "$1"
	}
	import_database() {
		docker exec -i "$database" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mariadb --default-character-set=utf8mb4 -u root "$MYSQL_DATABASE"' < "$1"
	}
	reset_database() {
		docker exec "$database" sh -c '
			case "$MYSQL_DATABASE" in ""|*[!a-zA-Z0-9_]*) exit 2 ;; esac
			MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mariadb -u root -e "DROP DATABASE \`$MYSQL_DATABASE\`; CREATE DATABASE \`$MYSQL_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
		'
	}
fi
wp() { wp_cli --skip-plugins --skip-themes "$@"; }

# Configuration belongs to the destination. Everything else is replaced, so stale plugins/files disappear.
managed_files() {
	site_exec find "$wp_root" -mindepth 1 -maxdepth 1 \
		! -name wp-config.php ! -name nginx.conf ! -name local-xdebuginfo.php \
		! -name .maintenance ! -name '.env*' ! -name .git ! -name node_modules ! -name .pnpm-store \
		-exec "$@" '{}' +
}
restore_files() {
	managed_files rm -rf -- || return 1
	if [[ "$target" == remote ]]; then
		docker exec -i "$wordpress" tar --no-same-owner -xpzf - -C "$wp_root" < "$1" || return 1
		managed_files chown -R www-data:www-data
	else
		tar --no-same-owner -xpzf "$1" -C "$wp_root"
	fi
}
capture() {
	mkdir -p "$1"
	export_database "$1/database.sql"
	site_exec tar --dereference --exclude=wp-config.php --exclude=nginx.conf --exclude=local-xdebuginfo.php \
		--exclude=.maintenance --exclude='.env*' --exclude=.git --exclude=node_modules --exclude=.pnpm-store --exclude=.DS_Store \
		-czf - -C "$wp_root" . > "$1/files.tar.gz"
	[[ -s "$1/database.sql" && -s "$1/files.tar.gz" ]] || { echo 'Snapshot is empty.' >&2; return 1; }
	# Metadata marks a complete snapshot, including pre-restore backups.
	printf '%s\n' kogo-snapshot-v1 "$home_url" "$table_prefix" > "$1/metadata"
}
cleanup() {
	status=$?
	trap - EXIT
	set +e
	recovery_failed=false
	if [[ "$status" -ne 0 && "$changed" == true ]]; then
		echo "Restore failed. Recovering destination from: $backup" >&2
		if ! site_exec php -r 'if (file_put_contents($argv[1], "<?php \$upgrading = " . (time() + 86400) . ";") === false) exit(1);' "$wp_root/.maintenance"; then recovery_failed=true; fi
		maintenance=true
		if ! restore_files "$backup/files.tar.gz"; then recovery_failed=true; fi
		if ! reset_database || ! import_database "$backup/database.sql"; then recovery_failed=true; fi
	fi
	if [[ "$recovery_failed" == true ]]; then
		echo "Recovery failed. Site remains in maintenance mode. Backup: $backup" >&2
	elif [[ "$maintenance" == true ]]; then site_exec rm -f "$wp_root/.maintenance"; fi
	if [[ -n "$container_staging" ]]; then site_exec rm -rf "$container_staging" >/dev/null 2>&1; fi
	if [[ -n "$local_lock" ]]; then rmdir "$local_lock"; fi
	exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

mkdir -p "$backup_root"
backup_root=$(cd "$backup_root" && pwd -P)
[[ "$backup_root/" != "$wp_root/"* && "$staging/" != "$wp_root/"* ]] || { echo 'Snapshot storage/staging must be outside WordPress.' >&2; exit 2; }
if [[ "$target" == local ]]; then
	lock_path="$(dirname "$wp_root")/.kogo-snapshot.lock"
	mkdir "$lock_path" 2>/dev/null || { echo "Another local snapshot operation is running (lock: $lock_path)." >&2; exit 1; }
	local_lock=$lock_path
else
	# Share the deployment lock, so deploy and snapshot/restore cannot overlap.
	mkdir -p "$HOME/kogo-deploy-backups/$stack"
	exec 9>"$HOME/kogo-deploy-backups/$stack/deploy.lock"
	flock -n 9 || { echo 'Another Kogo deployment or snapshot operation is running.' >&2; exit 1; }
	container_staging=$staging
	site_exec mkdir -p "$staging"
	docker cp "$staging/wp-cli.phar" "$wordpress:$staging/wp-cli.phar"
	docker cp "$staging/wp-cli.yml" "$wordpress:$staging/wp-cli.yml"
	site_exec chmod 755 "$staging"
	site_exec chmod 644 "$staging/wp-cli.phar" "$staging/wp-cli.yml"
fi
if site_exec test -f "$wp_root/.maintenance"; then echo 'WordPress is already in maintenance mode.' >&2; exit 1; fi
wp core is-installed
wp eval 'if (is_multisite()) { WP_CLI::error("Snapshot commands require a single WordPress site."); }'
home_url=$(wp option get home)
[[ "$home_url" =~ ^https?://[a-zA-Z0-9.:/-]+$ && "$(wp option get siteurl)" == "$home_url" ]] || { echo 'Expected a single site with matching home/siteurl URLs.' >&2; exit 1; }
table_prefix=$(wp config get table_prefix)
[[ "$table_prefix" =~ ^[a-zA-Z0-9_]+$ ]] || { echo 'Invalid destination table prefix.' >&2; exit 1; }
if [[ "$target" == remote && "$home_url" != "$target_url" ]]; then echo 'Remote home URL does not match DEPLOY_URL.' >&2; exit 1; fi

if [[ "$action" == apply ]]; then
	{ read -r format; read -r source_url; read -r source_prefix; } < "$staging/snapshot/metadata"
	[[ "$format" == kogo-snapshot-v1 && "$source_url" =~ ^https?://[a-zA-Z0-9.:/-]+$ && "$source_prefix" == "$table_prefix" ]] || {
		echo 'Invalid snapshot metadata or table prefix differs from the destination. Nothing was changed.' >&2; exit 1;
	}
fi

# Keep visitors from writing between the database export and file capture/swap.
# A future timestamp keeps WordPress maintenance active for captures longer than ten minutes.
site_exec php -r 'if (file_put_contents($argv[1], "<?php \$upgrading = " . (time() + 86400) . ";") === false) exit(1);' "$wp_root/.maintenance"
maintenance=true
if [[ "$action" == snapshot ]]; then
	echo "Capturing $target database and WordPress files..."
	capture "$staging/snapshot"
else
	backup=$(mktemp -d "$backup_root/$(date -u +%Y%m%dT%H%M%SZ).XXXXXXXX")
	capture "$backup"
	echo "Destination backup saved: $backup"
	changed=true
	restore_files "$staging/snapshot/files.tar.gz"
	reset_database
	import_database "$staging/snapshot/database.sql"
	if [[ "$source_url" != "$home_url" ]]; then
		wp search-replace "$source_url" "$home_url" --all-tables-with-prefix --skip-columns=guid --precise --quiet
	fi
	wp option update home "$home_url" --quiet
	wp option update siteurl "$home_url" --quiet
	wp core update-db
	wp_cli rewrite flush --hard
	wp cache flush
	[[ "$(wp option get home)" == "$home_url" && "$(wp option get siteurl)" == "$home_url" ]] || exit 1
	site_exec rm -f "$wp_root/.maintenance"
	maintenance=false
	curl --fail --silent --show-error --location --max-redirs 3 --max-time 30 --output /dev/null "$home_url/"
fi
