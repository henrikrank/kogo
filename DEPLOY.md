Run from this repository:

```sh
make deploy          # Build and upload the Kogo theme only; preserve the database, plugins, and media.
make deploy-with-db  # Build and upload the theme, local plugins, uploads, and local database state.
```

The destination is [kogo.henrikrank.ee](https://kogo.henrikrank.ee), using the existing WordPress/MariaDB Coolify stack `zlsdoihenjgxmq6jkg1wrbj9`.

You need `pnpm`, `ssh`, `scp`, `tar`, and `curl`, plus the existing SSH key at `~/.ssh/id_kr6psik_ed25519`. For `deploy-with-db`, start the Kogo site in Local first. The script loads Local's PHP environment from `~/Local Sites/kogo/app/.envrc` and uses its existing WP-CLI utility.

The full command replaces the destination database, plugins, and uploads with the local state, including WordPress users and settings. It exports an adjusted database copy using [WP-CLI's serialized-data-aware search/replace](https://developer.wordpress.org/cli/commands/search-replace/), preserving post GUIDs. The local database remains unchanged. WordPress core and the destination `wp-config.php` stay in place, preserving Coolify's database credentials.

Each run saves a private backup on the server under `~/kogo-deploy-backups/<stack>/<timestamp>.<random>/`. This contains `wp-content.tar.gz`, the original `.htaccess`, and, for a full deploy, `database.sql`. Failed remote deployments automatically restore this backup. If recovery fails, the script reports the backup path and leaves the site in maintenance mode for recovery. Keep or remove older backups as needed.

Defaults can be overridden with environment variables: `DEPLOY_SSH_TARGET`, `DEPLOY_SSH_KEY`, `DEPLOY_STACK`, `DEPLOY_URL`, `LOCAL_WP_PATH`, and `WP_CLI_PHAR`. For example:

```sh
DEPLOY_SSH_KEY="$HOME/.ssh/another-key" make deploy
```
