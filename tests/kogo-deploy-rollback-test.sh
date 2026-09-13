#!/usr/bin/env bash
# Exercise the real recovery function without touching WordPress or a server.
set -euo pipefail
repo=$(cd "$(dirname "$0")/.." && pwd)
fixture=$(mktemp -d "${TMPDIR:-/tmp}/kogo-rollback-test.XXXXXXXX")
trap 'rm -rf "$fixture"' EXIT
mkdir -p "$fixture/bin"
printf 'backup fixture' > "$fixture/wp-content.tar.gz"
printf 'database fixture' > "$fixture/database.sql"
printf 'htaccess fixture' > "$fixture/htaccess"
export DEPLOY_TEST_LOG="$fixture/log" DEPLOY_TEST_BACKUP="$fixture"
cat > "$fixture/bin/docker" <<'SH'
#!/usr/bin/env bash
printf 'DOCKER %s\n' "$*" >> "$DEPLOY_TEST_LOG"
if [[ "${DEPLOY_TEST_RECOVERY_FAIL:-0}" == 1 && "$*" == *'tar --no-same-owner -xzf'* ]]; then exit 1; fi
SH
chmod +x "$fixture/bin/docker"
export PATH="$fixture/bin:$PATH"
cat > "$fixture/run" <<'SH'
files_changed=true
database_changed=true
maintenance=false
wordpress=wordpress-test
wp_root=/var/www/html
container_staging=/tmp/kogo-deploy.test
backup=$DEPLOY_TEST_BACKUP
reset_database() { printf 'RESTORE DATABASE\n' >> "$DEPLOY_TEST_LOG"; }
mysql() { cat >/dev/null; }
SH
awk '/^cleanup\(\) \{/ {copy=1} copy {print} copy && /^}/ {exit}' "$repo/scripts/deploy-remote.sh" >> "$fixture/run"
printf '\nfalse\ncleanup\n' >> "$fixture/run"

if bash "$fixture/run" >/dev/null 2>&1; then echo 'Recovery must retain the deployment failure exit status.'; exit 1; fi
grep -q 'RESTORE DATABASE' "$DEPLOY_TEST_LOG"
grep -q 'rm -f /var/www/html/.maintenance' "$DEPLOY_TEST_LOG"
grep -q 'chmod 644 /var/www/html/.htaccess' "$DEPLOY_TEST_LOG"

: > "$DEPLOY_TEST_LOG"
if DEPLOY_TEST_RECOVERY_FAIL=1 bash "$fixture/run" >/dev/null 2>&1; then exit 1; fi
! grep -q 'rm -f /var/www/html/.maintenance' "$DEPLOY_TEST_LOG" || {
	echo 'A failed recovery must leave the site in maintenance mode.'; exit 1;
}
echo 'Kogo deployment recovery checks passed.'
