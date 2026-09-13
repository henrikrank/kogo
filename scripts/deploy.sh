#!/usr/bin/env bash
set -euo pipefail
umask 077

mode=${1:-theme}
case "$mode" in theme|full) ;; *) echo 'Usage: deploy.sh [theme|full]' >&2; exit 2 ;; esac
repo=$(cd "$(dirname "$0")/.." && pwd)
ssh_target=${DEPLOY_SSH_TARGET:-rank@coolify.henrikrank.ee}
ssh_key=${DEPLOY_SSH_KEY:-$HOME/.ssh/id_kr6psik_ed25519}
stack=${DEPLOY_STACK:-zlsdoihenjgxmq6jkg1wrbj9}
target_url=${DEPLOY_URL:-https://kogo.henrikrank.ee}
local_wp=${LOCAL_WP_PATH:-$HOME/Local Sites/kogo/app/public}
wp_cli_phar=${WP_CLI_PHAR:-/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/wp-cli.phar}

for command in ssh scp tar pnpm curl; do
	command -v "$command" >/dev/null || { echo "Missing command: $command" >&2; exit 1; }
done
[[ -r "$ssh_key" ]] || { echo "SSH key is missing: $ssh_key" >&2; exit 1; }
[[ "$stack" =~ ^[a-z0-9]+$ ]] || { echo 'Invalid Coolify stack UUID.' >&2; exit 1; }
[[ "$target_url" =~ ^https://[a-zA-Z0-9.-]+$ ]] || { echo 'DEPLOY_URL must be an HTTPS origin, without a trailing slash.' >&2; exit 1; }
ssh_options=(-i "$ssh_key" -o IdentitiesOnly=yes -o BatchMode=yes -o ConnectTimeout=15)

echo "Checking access to $target_url..."
ssh "${ssh_options[@]}" "$ssh_target" "docker inspect --format '{{.State.Running}}' wordpress-$stack mariadb-$stack" | awk '
	$0 != "true" { failed = 1 }
	END { if (NR != 2 || failed) exit 1 }
'

staging=$(mktemp -d "${TMPDIR:-/tmp}/kogo-deploy.XXXXXXXX")
remote_staging=
cleanup() {
	if [[ -n "$remote_staging" ]]; then
		ssh "${ssh_options[@]}" "$ssh_target" "rm -rf -- '$remote_staging'" || true
	fi
	rm -rf -- "$staging"
}
trap cleanup EXIT

echo 'Building the Kogo theme...'
(cd "$repo/themes/kogo" && pnpm build)
[[ -s "$repo/themes/kogo/build/main.css" && -s "$repo/themes/kogo/build/main.js" ]] || {
	echo 'Theme build output is missing.' >&2; exit 1;
}

tar_options=(--exclude=node_modules --exclude=.git --exclude=.DS_Store --exclude='.env*')
archive_paths=(-C "$repo" themes)
table_prefix=wp_
if [[ "$mode" == full ]]; then
	[[ -f "$local_wp/wp-config.php" && -d "$local_wp/wp-content/plugins" && -d "$local_wp/wp-content/uploads" ]] || {
		echo "Local WordPress files are missing: $local_wp" >&2; exit 1;
	}
	[[ -r "$wp_cli_phar" ]] || { echo "WP-CLI PHAR is missing: $wp_cli_phar" >&2; exit 1; }
	# Local supplies the matching PHP runtime and MySQL socket via this file.
	if [[ -f "$(dirname "$local_wp")/.envrc" ]]; then source "$(dirname "$local_wp")/.envrc"; fi
	command -v wp >/dev/null || { echo 'WP-CLI is required for deploy-with-db.' >&2; exit 1; }
	wp_options=(--path="$local_wp" --skip-plugins --skip-themes)
	source_url=$(wp "${wp_options[@]}" option get home)
	source_siteurl=$(wp "${wp_options[@]}" option get siteurl)
	[[ "$source_url" == "$source_siteurl" ]] || { echo 'This script expects a single WordPress installation with matching home/siteurl.' >&2; exit 1; }
	table_prefix=$(wp "${wp_options[@]}" config get table_prefix)
	[[ "$table_prefix" =~ ^[a-zA-Z0-9_]+$ ]] || { echo 'Invalid local table prefix.' >&2; exit 1; }
	echo 'Exporting a copy of the local database with destination URLs...'
	wp "${wp_options[@]}" search-replace "$source_url" "$target_url" \
		--all-tables-with-prefix --skip-columns=guid --precise --export="$staging/database.sql" --quiet
	[[ -s "$staging/database.sql" ]] || { echo 'The database export is empty.' >&2; exit 1; }
	archive_paths+=(-C "$local_wp/wp-content" plugins uploads)
	cp "$wp_cli_phar" "$staging/wp-cli.phar"
	printf 'apache_modules:\n  - mod_rewrite\n' > "$staging/wp-cli.yml"
fi

echo 'Packaging and uploading site files...'
COPYFILE_DISABLE=1 tar --no-xattrs "${tar_options[@]}" -czf "$staging/content.tar.gz" "${archive_paths[@]}"
cp "$repo/scripts/deploy-remote.sh" "$staging/deploy-remote.sh"
candidate=$(ssh "${ssh_options[@]}" "$ssh_target" 'umask 077; mktemp -d /tmp/kogo-deploy.XXXXXXXX')
[[ "$candidate" =~ ^/tmp/kogo-deploy\.[a-zA-Z0-9]+$ ]] || { echo 'Unexpected remote staging path.' >&2; exit 1; }
remote_staging=$candidate
scp "${ssh_options[@]}" "$staging/"* "$ssh_target:$remote_staging/"
ssh "${ssh_options[@]}" "$ssh_target" \
	"bash '$remote_staging/deploy-remote.sh' '$mode' '$stack' '$target_url' '$remote_staging' '$table_prefix'"

echo "Checking $target_url..."
curl --fail --silent --show-error --location --max-redirs 3 --max-time 30 --output /dev/null "$target_url/"
echo "Deployment complete: $target_url"
