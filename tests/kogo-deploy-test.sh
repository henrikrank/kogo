#!/usr/bin/env bash
# Exercise the actual local driver with fake network/build/WordPress commands.
set -euo pipefail
repo=$(cd "$(dirname "$0")/.." && pwd)
fixture=$(mktemp -d "${TMPDIR:-/tmp}/kogo-deploy-test.XXXXXXXX")
trap 'rm -rf "$fixture"' EXIT
mkdir -p "$fixture/bin" "$fixture/site/public/wp-content/"{plugins,uploads}
touch "$fixture/key" "$fixture/wp-cli.phar" "$fixture/site/public/wp-config.php"
printf 'plugin fixture' > "$fixture/site/public/wp-content/plugins/example.php"
printf 'media fixture' > "$fixture/site/public/wp-content/uploads/example.txt"
export DEPLOY_TEST_LOG="$fixture/log"
export LOCAL_WP_PATH="$fixture/site/public" DEPLOY_SSH_KEY="$fixture/key" WP_CLI_PHAR="$fixture/wp-cli.phar"

cat > "$fixture/bin/ssh" <<'SH'
#!/usr/bin/env bash
printf 'SSH %s\n' "${*: -1}" >> "$DEPLOY_TEST_LOG"
case "${*: -1}" in
	*'docker inspect'*) printf 'true\ntrue\n' ;;
	*'mktemp -d'*) printf '/tmp/kogo-deploy.test\n' ;;
esac
SH
cat > "$fixture/bin/scp" <<'SH'
#!/usr/bin/env bash
for argument in "$@"; do
	if [[ -f "$argument" ]]; then
		printf 'UPLOAD %s\n' "$(basename "$argument")" >> "$DEPLOY_TEST_LOG"
		if [[ "$argument" == */content.tar.gz ]]; then tar -tzf "$argument" >> "$DEPLOY_TEST_LOG"; fi
	fi
done
SH
cat > "$fixture/bin/wp" <<'SH'
#!/usr/bin/env bash
printf 'WP %s\n' "$*" >> "$DEPLOY_TEST_LOG"
case "$*" in
	*'option get'*) printf 'http://kogo.local\n' ;;
	*'config get table_prefix'*) printf 'wp_\n' ;;
	*'search-replace'*) for argument in "$@"; do
		if [[ "$argument" == --export=* ]]; then printf 'DROP TABLE IF EXISTS wp_options;\n' > "${argument#--export=}"; fi
	done ;;
esac
SH
cat > "$fixture/bin/pnpm" <<'SH'
#!/usr/bin/env bash
printf 'BUILD\n' >> "$DEPLOY_TEST_LOG"
[[ "${DEPLOY_TEST_BUILD_FAIL:-0}" != 1 ]]
SH
cat > "$fixture/bin/curl" <<'SH'
#!/usr/bin/env bash
printf 'HTTP CHECK\n' >> "$DEPLOY_TEST_LOG"
SH
chmod +x "$fixture/bin/"*
export PATH="$fixture/bin:$PATH"

bash "$repo/scripts/deploy.sh" theme >/dev/null
! grep -q '^WP ' "$DEPLOY_TEST_LOG" || { echo 'Theme deployment accessed the local database.'; exit 1; }
! grep -q '^UPLOAD database.sql' "$DEPLOY_TEST_LOG" || exit 1
! grep -q '^plugins/' "$DEPLOY_TEST_LOG" || exit 1
! grep -q '/node_modules/' "$DEPLOY_TEST_LOG" || { echo 'Build dependencies were packaged.'; exit 1; }
grep -q '^themes/kogo/functions.php' "$DEPLOY_TEST_LOG"
grep -q "deploy-remote.sh' 'theme'" "$DEPLOY_TEST_LOG"

: > "$DEPLOY_TEST_LOG"
bash "$repo/scripts/deploy.sh" full >/dev/null
grep -q '^UPLOAD database.sql' "$DEPLOY_TEST_LOG"
grep -q '^UPLOAD wp-cli.phar' "$DEPLOY_TEST_LOG"
grep -q '^plugins/example.php' "$DEPLOY_TEST_LOG"
grep -q '^uploads/example.txt' "$DEPLOY_TEST_LOG"
grep -q 'search-replace http://kogo.local https://kogo.henrikrank.ee .*--skip-columns=guid .*--export=' "$DEPLOY_TEST_LOG"
grep -q "deploy-remote.sh' 'full'" "$DEPLOY_TEST_LOG"

: > "$DEPLOY_TEST_LOG"
if DEPLOY_TEST_BUILD_FAIL=1 bash "$repo/scripts/deploy.sh" full >/dev/null 2>&1; then echo 'Failed builds must stop deployment.'; exit 1; fi
! grep -q '^UPLOAD\|^WP ' "$DEPLOY_TEST_LOG" || { echo 'A failed build still exported or uploaded data.'; exit 1; }
echo 'Kogo deployment mode and build-failure checks passed.'
