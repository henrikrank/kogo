#!/usr/bin/env bash
# Local driver; both destinations use the same snapshot format and restore code.
set -euo pipefail
umask 077

action=${1:-}
target=${2:-}
[[ "$action" == snapshot || "$action" == apply ]] && [[ "$target" == local || "$target" == remote ]] && [[ $# -le 3 ]] || {
	echo 'Usage: snapshot.sh snapshot|apply local|remote [snapshot-directory|local|remote]' >&2; exit 2;
}
repo=$(cd "$(dirname "$0")/.." && pwd)
snapshot_root=${SNAPSHOT_DIR:-$repo/snapshots}
mkdir -p "$snapshot_root"
snapshot_root=$(cd "$snapshot_root" && pwd -P)
export SNAPSHOT_DIR="$snapshot_root"
snapshot=${3:-${SNAPSHOT:-}}

if [[ "$action" == apply ]]; then
	if [[ -z "$snapshot" || "$snapshot" == local || "$snapshot" == remote ]]; then
		choices=()
		for origin in local remote; do
			[[ -z "$snapshot" || "$snapshot" == "$origin" ]] || continue
			for directory in "$snapshot_root/$origin/"*; do
				[[ -f "$directory/metadata" && -s "$directory/database.sql" && -s "$directory/files.tar.gz" ]] || continue
				choices+=("$directory")
			done
		done
		[[ ${#choices[@]} -gt 0 ]] || { echo 'No matching snapshots. Run snapshot-local or snapshot-remote first.' >&2; exit 1; }
		echo "Choose a snapshot to restore to $target:"
		for ((i=0; i<${#choices[@]}; i++)); do printf '%d) %s\n' "$((i+1))" "${choices[i]#"$snapshot_root/"}"; done
		printf 'Snapshot number (Ctrl-C to cancel): '
		read -r selection || { echo 'Set SNAPSHOT to an exact snapshot directory for a noninteractive restore.' >&2; exit 2; }
		[[ "$selection" =~ ^[1-9][0-9]*$ && ${#selection} -le 6 ]] && ((selection <= ${#choices[@]})) || { echo 'Invalid snapshot number.' >&2; exit 2; }
		snapshot=${choices[selection-1]}
	elif [[ ! -d "$snapshot" && -d "$snapshot_root/$snapshot" ]]; then
		snapshot="$snapshot_root/$snapshot"
	fi
	[[ -d "$snapshot" ]] || { echo "Snapshot directory is missing: $snapshot" >&2; exit 1; }
	snapshot=$(cd "$snapshot" && pwd -P)
	for file in metadata database.sql files.tar.gz; do
		[[ -f "$snapshot/$file" && ! -L "$snapshot/$file" && -s "$snapshot/$file" ]] || { echo "Snapshot file is missing or invalid: $file" >&2; exit 1; }
	done
	# Validate before uploading/extracting. Snapshots contain code and SQL; use trusted snapshots only.
	python3 - "$snapshot/files.tar.gz" <<'PY'
import pathlib
import sys
import tarfile

def safe_path(name):
    path = pathlib.PurePosixPath(name)
    if path.is_absolute() or '..' in path.parts:
        raise ValueError('unsafe archive path: ' + name)
    return path

try:
    with tarfile.open(sys.argv[1], 'r:gz') as archive:
        members = archive.getmembers()
        paths = {}
        for member in members:
            path = safe_path(member.name)
            if path in paths:
                raise ValueError('duplicate archive path: ' + member.name)
            paths[path] = member
            if path.parts and (path.parts[0] in {'wp-config.php', 'nginx.conf', 'local-xdebuginfo.php', '.maintenance', '.git'} or path.parts[0].startswith('.env')):
                raise ValueError('environment configuration in archive: ' + member.name)
            if not (member.isfile() or member.isdir() or member.islnk()):
                raise ValueError('unsupported archive entry: ' + member.name)
        for member in members:
            if member.islnk():
                linked = paths.get(safe_path(member.linkname))
                if linked is None or not linked.isfile():
                    raise ValueError('unsafe archive hard link: ' + member.name)
        for name in ('wp-load.php', 'wp-settings.php', 'wp-admin', 'wp-includes', 'wp-content'):
            if pathlib.PurePosixPath(name) not in paths:
                raise ValueError('missing WordPress file/directory: ' + name)
except (OSError, tarfile.TarError, ValueError) as error:
    sys.exit('Invalid snapshot archive: ' + str(error))
PY
fi

staging=$(mktemp -d "${TMPDIR:-/tmp}/kogo-snapshot.XXXXXXXX")
remote_staging=
output=
cleanup() {
	status=$?
	trap - EXIT
	if [[ -n "$remote_staging" ]]; then
		ssh "${ssh_options[@]}" "$ssh_target" "rm -rf -- '$remote_staging'" || true
	fi
	if [[ "$status" -ne 0 && -n "$output" ]]; then rm -rf -- "$output"; fi
	rm -rf -- "$staging"
	exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
if [[ "$action" == apply ]]; then
	mkdir "$staging/snapshot"
	cp "$snapshot/"{metadata,database.sql,files.tar.gz} "$staging/snapshot/"
	echo "Restoring $snapshot to $target..."
else
	mkdir -p "$snapshot_root/$target"
	output=$(mktemp -d "$snapshot_root/$target/$(date -u +%Y%m%dT%H%M%SZ).XXXXXXXX")
fi

if [[ "$target" == local ]]; then
	bash "$repo/scripts/snapshot-site.sh" "$action" local "$staging"
else
	ssh_target=${DEPLOY_SSH_TARGET:-rank@coolify.henrikrank.ee}
	ssh_key=${DEPLOY_SSH_KEY:-$HOME/.ssh/id_kr6psik_ed25519}
	stack=${DEPLOY_STACK:-zlsdoihenjgxmq6jkg1wrbj9}
	target_url=${DEPLOY_URL:-https://kogo.henrikrank.ee}
	wp_cli_phar=${WP_CLI_PHAR:-/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/wp-cli.phar}
	[[ -r "$ssh_key" && -r "$wp_cli_phar" ]] || { echo 'SSH key or WP-CLI PHAR is missing.' >&2; exit 1; }
	[[ "$stack" =~ ^[a-z0-9]+$ && "$target_url" =~ ^https://[a-zA-Z0-9.-]+$ ]] || { echo 'Invalid DEPLOY_STACK or DEPLOY_URL.' >&2; exit 2; }
	ssh_options=(-i "$ssh_key" -o IdentitiesOnly=yes -o BatchMode=yes -o ConnectTimeout=15)
	ssh "${ssh_options[@]}" "$ssh_target" "docker inspect --format '{{.State.Running}}' wordpress-$stack mariadb-$stack" | awk '
		$0 != "true" { failed = 1 }
		END { if (NR != 2 || failed) exit 1 }
	'
	candidate=$(ssh "${ssh_options[@]}" "$ssh_target" 'umask 077; mktemp -d /tmp/kogo-snapshot.XXXXXXXX')
	[[ "$candidate" =~ ^/tmp/kogo-snapshot\.[a-zA-Z0-9]+$ ]] || { echo 'Unexpected remote staging path.' >&2; exit 1; }
	remote_staging=$candidate
	cp "$repo/scripts/snapshot-site.sh" "$staging/snapshot-site.sh"
	cp "$wp_cli_phar" "$staging/wp-cli.phar"
	printf 'apache_modules:\n  - mod_rewrite\n' > "$staging/wp-cli.yml"
	scp -r "${ssh_options[@]}" "$staging/"* "$ssh_target:$remote_staging/"
	ssh "${ssh_options[@]}" "$ssh_target" "bash '$remote_staging/snapshot-site.sh' '$action' remote '$remote_staging' '$stack' '$target_url'"
	if [[ "$action" == snapshot ]]; then
		scp -r "${ssh_options[@]}" "$ssh_target:$remote_staging/snapshot" "$staging/"
	fi
fi

if [[ "$action" == snapshot ]]; then
	for file in metadata database.sql files.tar.gz; do
		[[ -s "$staging/snapshot/$file" ]] || { echo "Snapshot is incomplete: $file" >&2; exit 1; }
	done
	mv "$staging/snapshot/"* "$output/"
	echo "Snapshot saved: $output"
else
	echo "Snapshot restored to $target."
fi
