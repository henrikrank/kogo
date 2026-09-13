#!/usr/bin/env bash
# Real archives, file swaps, chooser, transport, and recovery; fake WP/database/network commands.
set -euo pipefail
repo=$(cd "$(dirname "$0")/.." && pwd)
fixture=$(mktemp -d "${TMPDIR:-/tmp}/kogo-snapshot-test.XXXXXXXX")
cleanup() {
	if [[ -f "$fixture/remote-stage" ]]; then rm -rf -- "$(cat "$fixture/remote-stage")"; fi
	rm -rf -- "$fixture"
}
trap cleanup EXIT
export SNAPSHOT_DIR="$fixture/snapshots" LOCAL_WP_PATH="$fixture/local site/public"
export SNAPSHOT_TEST_ROOT="$fixture" SNAPSHOT_TEST_DB="$fixture/local-db" SNAPSHOT_TEST_LOG="$fixture/log"
export SNAPSHOT_TEST_REMOTE_DB="$fixture/remote-db" SNAPSHOT_TEST_REMOTE_ROOT="$fixture/remote/public"
export SNAPSHOT_TEST_REMOTE_SCRIPT="$fixture/remote-engine.sh"
export SNAPSHOT_TEST_REAL_TAR=$(command -v tar)
export DEPLOY_STACK=test123 DEPLOY_URL=https://remote.example DEPLOY_SSH_TARGET=test@host
export DEPLOY_SSH_KEY="$fixture/key" WP_CLI_PHAR="$fixture/wp-cli.phar"
mkdir -p "$fixture/bin" "$fixture/source-theme/node_modules" "$LOCAL_WP_PATH/"{wp-admin,wp-includes,wp-content/plugins,wp-content/uploads,wp-content/languages,wp-content/themes}
printf 'keep credentials' > "$LOCAL_WP_PATH/wp-config.php"
printf 'keep environment' > "$LOCAL_WP_PATH/.env"
printf 'keep nginx' > "$LOCAL_WP_PATH/nginx.conf"
printf 'keep debugger' > "$LOCAL_WP_PATH/local-xdebuginfo.php"
printf 'core fixture' > "$LOCAL_WP_PATH/wp-admin/index.php"
printf 'root fixture' > "$LOCAL_WP_PATH/index.php"
printf 'core bootstrap fixture' > "$LOCAL_WP_PATH/wp-load.php"
printf 'core settings fixture' > "$LOCAL_WP_PATH/wp-settings.php"
printf 'rewrite fixture' > "$LOCAL_WP_PATH/.htaccess"
printf 'original plugin' > "$LOCAL_WP_PATH/wp-content/plugins/example.php"
printf 'media fixture' > "$LOCAL_WP_PATH/wp-content/uploads/media.txt"
printf 'translation fixture' > "$LOCAL_WP_PATH/wp-content/languages/example.mo"
printf 'original theme' > "$fixture/source-theme/functions.php"
printf 'exclude dependency' > "$fixture/source-theme/node_modules/example.js"
ln -s "$fixture/source-theme" "$LOCAL_WP_PATH/wp-content/themes/kogo"
chmod 644 "$LOCAL_WP_PATH/wp-content/plugins/example.php"
touch "$fixture/key" "$fixture/wp-cli.phar"
printf '{"home":"http://kogo.local","siteurl":"http://kogo.local","content":"http://kogo.local/media","guid":"http://kogo.local/original"}\n' > "$SNAPSHOT_TEST_DB"
cp "$SNAPSHOT_TEST_DB" "$fixture/original-db"

cat > "$fixture/bin/wp" <<'PY'
#!/usr/bin/env python3
import json
import os
import pathlib
import sys

args = [arg for arg in sys.argv[1:] if not arg.startswith('--')]
db = pathlib.Path(os.environ['SNAPSHOT_TEST_DB'])
root = pathlib.Path(os.environ['SNAPSHOT_TEST_ROOT'])
with open(os.environ['SNAPSHOT_TEST_LOG'], 'a') as log:
    log.write('WP ' + ' '.join(sys.argv[1:]) + '\n')
    log.write('SOCKET ' + os.environ.get('MYSQL_UNIX_PORT', '') + '\n')
if args[:2] == ['db', 'export']:
    pathlib.Path(args[2]).write_bytes(b'' if os.environ.get('SNAPSHOT_TEST_EMPTY') else db.read_bytes())
elif args[:2] == ['db', 'reset']:
    db.write_text('{}')
elif args[:2] == ['db', 'import']:
    marker = root / 'import-failed'
    if os.environ.get('SNAPSHOT_TEST_RECOVERY_FAIL') or (os.environ.get('SNAPSHOT_TEST_IMPORT_FAIL') and not marker.exists()):
        marker.touch()
        sys.exit(1)
    data = pathlib.Path(args[2]).read_bytes()
    json.loads(data)  # Fails if a MariaDB sandbox header reached the MySQL importer.
    db.write_bytes(data)
elif args[:2] == ['option', 'get']:
    print(json.loads(db.read_text())[args[2]])
elif args[:2] == ['option', 'update']:
    data = json.loads(db.read_text())
    data[args[2]] = args[3]
    db.write_text(json.dumps(data))
elif args[:3] == ['config', 'get', 'table_prefix']:
    print('wp_')
elif args[0] == 'search-replace':
    assert '--all-tables-with-prefix' in sys.argv and '--skip-columns=guid' in sys.argv and '--precise' in sys.argv
    data = json.loads(db.read_text())
    for key, value in data.items():
        if key != 'guid':
            data[key] = value.replace(args[1], args[2])
    db.write_text(json.dumps(data))
elif args[:2] == ['rewrite', 'flush'] and os.environ.get('SNAPSHOT_TEST_REWRITE_FAIL'):
    sys.exit(1)
PY
cat > "$fixture/bin/curl" <<'SH'
#!/usr/bin/env bash
printf 'HTTP %s\n' "$*" >> "$SNAPSHOT_TEST_LOG"
[[ "${SNAPSHOT_TEST_HTTP_FAIL:-0}" != 1 ]]
SH
cat > "$fixture/bin/tar" <<'SH'
#!/usr/bin/env bash
if [[ "$*" == *'-xpzf'* && "${SNAPSHOT_TEST_EXTRACT_FAIL:-0}" == 1 && ! -f "$SNAPSHOT_TEST_ROOT/extract-failed" ]]; then
	touch "$SNAPSHOT_TEST_ROOT/extract-failed"
	exit 1
fi
exec "$SNAPSHOT_TEST_REAL_TAR" "$@"
SH
cat > "$fixture/bin/flock" <<'SH'
#!/usr/bin/env bash
[[ "${SNAPSHOT_TEST_LOCK_FAIL:-0}" != 1 ]]
SH
cat > "$fixture/bin/docker" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
printf 'DOCKER %s\n' "$*" >> "$SNAPSHOT_TEST_LOG"
if [[ "$1" == cp ]]; then exit 0; fi # Container staging is mapped to host staging in this fixture.
[[ "$1" == exec ]] || exit 2
shift
while [[ "$1" == -* ]]; do
	case "$1" in -i) shift ;; -u|-e) shift 2 ;; *) exit 2 ;; esac
done
container=$1
shift
if [[ "$container" == mariadb-* ]]; then
	case "$*" in
		*mariadb-dump*) printf '%s\n' '/*!999999\- enable the sandbox mode */'; cat "$SNAPSHOT_TEST_REMOTE_DB" ;;
		*'DROP DATABASE'*) printf '{}' > "$SNAPSHOT_TEST_REMOTE_DB" ;;
		*) sed '1{/999999.*enable the sandbox mode/d;}' > "$SNAPSHOT_TEST_REMOTE_DB" ;;
	esac
elif [[ "$1" == php && "$2" == */wp-cli.phar ]]; then
	shift 2
	SNAPSHOT_TEST_DB="$SNAPSHOT_TEST_REMOTE_DB" wp "$@"
elif [[ "$1" == find && "$*" == *'chown -R'* ]]; then
	exit 0 # Ownership is applied by real Docker; the fixture runs as the current user.
elif [[ "$1" == rm && "$*" == *'/tmp/kogo-snapshot.'* ]]; then
	exit 0 # Container cleanup must not remove the mapped host staging directory.
else
	"$@"
fi
SH
cat > "$fixture/bin/ssh" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
cmd=${*: -1}
printf 'SSH %s\n' "$cmd" >> "$SNAPSHOT_TEST_LOG"
case "$cmd" in
	*'docker inspect'*) printf 'true\ntrue\n' ;;
	*'mktemp -d'*) mktemp -d /tmp/kogo-snapshot.XXXXXXXX | tee "$SNAPSHOT_TEST_ROOT/remote-stage" ;;
	*'snapshot-site.sh'*)
		# Execute the uploaded engine with only its fixed filesystem roots redirected.
		python3 - "$cmd" <<'PY'
import os
import shlex
import sys
args = shlex.split(sys.argv[1])
args[1] = os.environ['SNAPSHOT_TEST_REMOTE_SCRIPT']
os.execvp(args[0], args)
PY
		;;
	*'rm -rf'*) bash -c "$cmd" ;;
	*) exit 2 ;;
esac
SH
cat > "$fixture/bin/scp" <<'PY'
#!/usr/bin/env python3
import os
import pathlib
import shutil
import sys

args = sys.argv[1:]
paths = []
while args:
    arg = args.pop(0)
    if arg in ('-i', '-o'):
        args.pop(0)
    elif arg != '-r':
        paths.append(arg.split(':', 1)[-1])
destination = pathlib.Path(paths[-1])
for value in paths[:-1]:
    source = pathlib.Path(value)
    with open(os.environ['SNAPSHOT_TEST_LOG'], 'a') as log:
        log.write('SCP ' + source.name + '\n')
    if source.is_dir():
        shutil.copytree(source, destination / source.name)
    else:
        shutil.copy2(source, destination / source.name)
PY
chmod +x "$fixture/bin/"*
export PATH="$fixture/bin:$PATH"

# Redirect the remote site's fixed root and backup locations without changing HOME or touching a server.
python3 - "$repo/scripts/snapshot-site.sh" "$SNAPSHOT_TEST_REMOTE_SCRIPT" <<'PY'
import os
import pathlib
import sys
root = os.environ['SNAPSHOT_TEST_ROOT']
script = pathlib.Path(sys.argv[1]).read_text()
script = script.replace('wp_root=/var/www/html', 'wp_root="$SNAPSHOT_TEST_REMOTE_ROOT"')
script = script.replace('$HOME/kogo-snapshot-backups/$stack', root + '/remote-backups')
script = script.replace('$HOME/kogo-deploy-backups/$stack', root + '/deploy-backups')
pathlib.Path(sys.argv[2]).write_text(script)
PY

make -s -C "$repo" snapshot-local > "$fixture/output"
local_snapshot=$(find "$SNAPSHOT_DIR/local" -name metadata -exec dirname '{}' \;)
cmp "$SNAPSHOT_TEST_DB" "$fixture/original-db"
[[ ! -e "$LOCAL_WP_PATH/.maintenance" && ! -e "$(dirname "$LOCAL_WP_PATH")/.kogo-snapshot.lock" ]]
tar -tzf "$local_snapshot/files.tar.gz" > "$fixture/archive-list"
for name in wp-admin/index.php index.php .htaccess wp-content/plugins/example.php wp-content/uploads/media.txt wp-content/languages/example.mo wp-content/themes/kogo/functions.php; do
	rg -q -F "./$name" "$fixture/archive-list"
done
! rg -q 'wp-config.php|nginx.conf|local-xdebuginfo.php|node_modules|\.env|\.maintenance' "$fixture/archive-list"

# Build a real remote fixture, then run snapshot-remote through SSH/SCP and the shared remote engine.
mkdir -p "$SNAPSHOT_TEST_REMOTE_ROOT"
tar -xpzf "$local_snapshot/files.tar.gz" -C "$SNAPSHOT_TEST_REMOTE_ROOT"
printf 'remote credentials' > "$SNAPSHOT_TEST_REMOTE_ROOT/wp-config.php"
python3 - "$SNAPSHOT_TEST_DB" "$SNAPSHOT_TEST_REMOTE_DB" <<'PY'
import pathlib
import sys
pathlib.Path(sys.argv[2]).write_text(pathlib.Path(sys.argv[1]).read_text().replace('http://kogo.local', 'https://remote.example'))
PY
: > "$SNAPSHOT_TEST_LOG"
make -s -C "$repo" snapshot-remote > "$fixture/output"
remote_snapshot=$(find "$SNAPSHOT_DIR/remote" -name metadata -exec dirname '{}' \;)
rg -q '^https://remote.example$' "$remote_snapshot/metadata"
rg -q 'sandbox mode' "$remote_snapshot/database.sql"
cmp "$SNAPSHOT_TEST_DB" "$fixture/original-db"
[[ ! -e "$SNAPSHOT_TEST_REMOTE_ROOT/.maintenance" ]]

# Remote -> local: choose from remote snapshots, replace URLs, remove stale files, keep configuration/source repo.
printf 'changed source' > "$fixture/source-theme/functions.php"
printf 'changed plugin' > "$LOCAL_WP_PATH/wp-content/plugins/example.php"
printf 'obsolete' > "$LOCAL_WP_PATH/stale.php"
printf '1\n' | make -s -C "$repo" apply-snapshot-local SNAPSHOT=remote > "$fixture/output"
[[ ! -L "$LOCAL_WP_PATH/wp-content/themes/kogo" && ! -e "$LOCAL_WP_PATH/stale.php" ]]
[[ "$(cat "$LOCAL_WP_PATH/wp-content/themes/kogo/functions.php")" == 'original theme' ]]
[[ "$(cat "$fixture/source-theme/functions.php")" == 'changed source' ]]
[[ "$(cat "$LOCAL_WP_PATH/wp-config.php")" == 'keep credentials' && "$(cat "$LOCAL_WP_PATH/.env")" == 'keep environment' ]]
[[ "$(cat "$LOCAL_WP_PATH/nginx.conf")" == 'keep nginx' && "$(cat "$LOCAL_WP_PATH/local-xdebuginfo.php")" == 'keep debugger' ]]
python3 - "$SNAPSHOT_TEST_DB" "$LOCAL_WP_PATH/wp-content/plugins/example.php" <<'PY'
import json
import pathlib
import stat
import sys
db = json.loads(pathlib.Path(sys.argv[1]).read_text())
assert db['home'] == db['siteurl'] == 'http://kogo.local'
assert db['content'] == 'http://kogo.local/media'
assert db['guid'] == 'https://remote.example/original'
assert stat.S_IMODE(pathlib.Path(sys.argv[2]).stat().st_mode) == 0o644
PY
rg -q 'Destination backup saved:' "$fixture/output"
rg -q 'sandbox mode' "$remote_snapshot/database.sql" # The saved SQL was not changed.

# Local -> remote uses the same engine and target credentials, and replaces URLs in the opposite direction.
printf 'obsolete' > "$SNAPSHOT_TEST_REMOTE_ROOT/stale.php"
make -s -C "$repo" apply-snapshot-remote SNAPSHOT="$local_snapshot" > "$fixture/output"
[[ ! -e "$SNAPSHOT_TEST_REMOTE_ROOT/stale.php" && ! -e "$SNAPSHOT_TEST_REMOTE_ROOT/.maintenance" ]]
[[ "$(cat "$SNAPSHOT_TEST_REMOTE_ROOT/wp-config.php")" == 'remote credentials' ]]
python3 - "$SNAPSHOT_TEST_REMOTE_DB" <<'PY'
import json
import pathlib
import sys
db = json.loads(pathlib.Path(sys.argv[1]).read_text())
assert db['home'] == db['siteurl'] == 'https://remote.example'
assert db['content'] == 'https://remote.example/media'
assert db['guid'] == 'http://kogo.local/original'
PY

# Same-environment restore does not run search/replace; exact relative paths work noninteractively.
: > "$SNAPSHOT_TEST_LOG"
make -s -C "$repo" apply-snapshot-local SNAPSHOT="${local_snapshot#"$SNAPSHOT_DIR/"}" > "$fixture/output"
! rg -q 'WP .*search-replace' "$SNAPSHOT_TEST_LOG"

# Failures during import, route rebuild, HTTP check, and remote rebuild recover the original DB/files.
for failure in SNAPSHOT_TEST_EXTRACT_FAIL SNAPSHOT_TEST_IMPORT_FAIL SNAPSHOT_TEST_REWRITE_FAIL SNAPSHOT_TEST_HTTP_FAIL; do
	printf 'destination before failure' > "$LOCAL_WP_PATH/wp-content/plugins/example.php"
	cp "$SNAPSHOT_TEST_DB" "$fixture/before-db"
	rm -f "$fixture/import-failed" "$fixture/extract-failed"
	if env "$failure=1" SNAPSHOT="$remote_snapshot" bash "$repo/scripts/snapshot.sh" apply local > "$fixture/output" 2>&1; then echo "Expected $failure to fail."; exit 1; fi
	cmp "$SNAPSHOT_TEST_DB" "$fixture/before-db"
	[[ "$(cat "$LOCAL_WP_PATH/wp-content/plugins/example.php")" == 'destination before failure' && ! -e "$LOCAL_WP_PATH/.maintenance" ]]
	rg -q 'Recovering destination from:' "$fixture/output"
done
cp "$SNAPSHOT_TEST_REMOTE_DB" "$fixture/before-remote-db"
printf 'remote before failure' > "$SNAPSHOT_TEST_REMOTE_ROOT/wp-content/plugins/example.php"
if SNAPSHOT_TEST_REWRITE_FAIL=1 SNAPSHOT="$local_snapshot" bash "$repo/scripts/snapshot.sh" apply remote > "$fixture/output" 2>&1; then exit 1; fi
cmp "$SNAPSHOT_TEST_REMOTE_DB" "$fixture/before-remote-db"
[[ "$(cat "$SNAPSHOT_TEST_REMOTE_ROOT/wp-content/plugins/example.php")" == 'remote before failure' && ! -e "$SNAPSHOT_TEST_REMOTE_ROOT/.maintenance" ]]

# A failed database recovery retains maintenance and the backup.
if SNAPSHOT_TEST_RECOVERY_FAIL=1 SNAPSHOT="$remote_snapshot" bash "$repo/scripts/snapshot.sh" apply local > "$fixture/output" 2>&1; then exit 1; fi
[[ -f "$LOCAL_WP_PATH/.maintenance" ]]
rg -q 'Recovery failed. Site remains in maintenance mode. Backup:' "$fixture/output"
rm "$LOCAL_WP_PATH/.maintenance"
cp "$fixture/before-db" "$SNAPSHOT_TEST_DB"

# Invalid metadata/archive and occupied locks fail before resetting the database.
mkdir "$fixture/bad"
cp "$local_snapshot/"* "$fixture/bad/"
printf '%s\n' kogo-snapshot-v1 http://kogo.local wrong_ > "$fixture/bad/metadata"
: > "$SNAPSHOT_TEST_LOG"
if SNAPSHOT="$fixture/bad" bash "$repo/scripts/snapshot.sh" apply local > "$fixture/output" 2>&1; then exit 1; fi
! rg -q 'WP .*db (reset|export)' "$SNAPSHOT_TEST_LOG"
cp "$local_snapshot/metadata" "$fixture/bad/metadata"
for entry in traversal absolute configuration symlink hardlink empty; do
python3 - "$fixture/bad/files.tar.gz" "$entry" <<'PY'
import io
import sys
import tarfile
with tarfile.open(sys.argv[1], 'w:gz') as archive:
    names = {'traversal': '../escape', 'absolute': '/escape', 'configuration': 'wp-config.php', 'symlink': 'link', 'hardlink': 'link'}
    if sys.argv[2] == 'empty':
        sys.exit(0)
    member = tarfile.TarInfo(names[sys.argv[2]])
    member.size = 4
    if sys.argv[2] in ('symlink', 'hardlink'):
        member.type = tarfile.SYMTYPE if sys.argv[2] == 'symlink' else tarfile.LNKTYPE
        member.linkname = 'wp-config.php'
        member.size = 0
    archive.addfile(member, io.BytesIO(b'evil'))
PY
: > "$SNAPSHOT_TEST_LOG"
if SNAPSHOT="$fixture/bad" bash "$repo/scripts/snapshot.sh" apply local > "$fixture/output" 2>&1; then exit 1; fi
[[ ! -s "$SNAPSHOT_TEST_LOG" && ! -e "$fixture/escape" ]]
rg -q 'Invalid snapshot archive' "$fixture/output"
done
: > "$SNAPSHOT_TEST_LOG"
if bash "$repo/scripts/snapshot.sh" apply local < /dev/null > "$fixture/output" 2>&1; then exit 1; fi
! rg -q 'WP .*db reset' "$SNAPSHOT_TEST_LOG"
printf 'existing maintenance' > "$LOCAL_WP_PATH/.maintenance"
if bash "$repo/scripts/snapshot.sh" snapshot local > "$fixture/output" 2>&1; then exit 1; fi
[[ "$(cat "$LOCAL_WP_PATH/.maintenance")" == 'existing maintenance' ]]
rm "$LOCAL_WP_PATH/.maintenance"
mkdir "$(dirname "$LOCAL_WP_PATH")/.kogo-snapshot.lock"
if bash "$repo/scripts/snapshot.sh" snapshot local > "$fixture/output" 2>&1; then exit 1; fi
[[ -d "$(dirname "$LOCAL_WP_PATH")/.kogo-snapshot.lock" ]]
rmdir "$(dirname "$LOCAL_WP_PATH")/.kogo-snapshot.lock"
if SNAPSHOT_TEST_LOCK_FAIL=1 bash "$repo/scripts/snapshot.sh" snapshot remote > "$fixture/output" 2>&1; then exit 1; fi

# Empty exports cannot produce a selectable snapshot; Local's PHP socket reaches WP-CLI's DB tools.
count_before=$(find "$SNAPSHOT_DIR/local" -name metadata | wc -l)
if SNAPSHOT_TEST_EMPTY=1 bash "$repo/scripts/snapshot.sh" snapshot local > "$fixture/output" 2>&1; then exit 1; fi
[[ "$(find "$SNAPSHOT_DIR/local" -name metadata | wc -l)" == "$count_before" && ! -e "$LOCAL_WP_PATH/.maintenance" ]]
mkdir "$fixture/php"
printf 'mysqli.default_socket="%s/socket.sock"\n' "$fixture" > "$fixture/php/php.ini"
printf 'export PHPRC="%s/php"\n' "$fixture" > "$(dirname "$LOCAL_WP_PATH")/.envrc"
: > "$SNAPSHOT_TEST_LOG"
bash "$repo/scripts/snapshot.sh" snapshot local > "$fixture/output"
rg -q -F "SOCKET $fixture/socket.sock" "$SNAPSHOT_TEST_LOG"

echo 'Kogo local/remote snapshots, URL migration, chooser, configuration, rollback, and validation checks passed.'
