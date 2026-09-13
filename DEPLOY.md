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

Save and restore complete database + WordPress file snapshots:

```sh
make snapshot-local          # Capture the running Local site.
make snapshot-remote         # Download a snapshot of the Coolify site.
make apply-snapshot-local    # Choose any saved local or remote snapshot; restore it to Local.
make apply-snapshot-remote   # Choose any saved local or remote snapshot; restore it to Coolify.

make apply-snapshot-local SNAPSHOT=remote  # Choose from remote snapshots only.
make apply-snapshot-remote SNAPSHOT=local  # Choose from local snapshots only.
make apply-snapshot-local SNAPSHOT=remote/20260913T120000Z.example
# An absolute snapshot directory also works, including one copied from another machine.
```

Snapshots are saved privately under `snapshots/local/<timestamp>.<random>/` or `snapshots/remote/<timestamp>.<random>/` in this repository and ignored by Git. Set `SNAPSHOT_DIR` to use another storage directory outside WordPress. Each snapshot contains `database.sql`, `files.tar.gz`, and `metadata` recording the original URL and table prefix. Snapshots capture the actual site files without building the theme or changing database URLs. Local must be running for local capture or restore. The existing `DEPLOY_*`, `LOCAL_WP_PATH`, and `WP_CLI_PHAR` overrides also apply; restores additionally need `python3` for archive validation.

A restore replaces the destination database and site files, including WordPress core, themes, plugins, media, users, and settings. The destination's current URL is retained, using [WP-CLI's serialized-data-aware search/replace](https://developer.wordpress.org/cli/commands/search-replace/) and preserving GUIDs. Local imports handle [MariaDB's MySQL-incompatible dump header](https://mariadb.org/mariadb-dump-file-compatibility-change/) without modifying the saved SQL. Both sites must be single WordPress installations with matching `home`/`siteurl` and the same table prefix. The remote URL must match `DEPLOY_URL`.

The destination's `wp-config.php`, `.env*`, `nginx.conf`, and `local-xdebuginfo.php` are preserved and excluded from snapshots, along with maintenance markers, Git metadata, build dependencies, and macOS metadata. Symbolic links are captured as their actual contents and restored as regular files/directories. In particular, restoring to Local replaces the Kogo theme symlink with a directory; the linked source repository is untouched. Re-create the symlink when returning to repository-based theme development.

Every restore first captures a destination backup in the same snapshot format. Local backups are saved under `snapshots/local/`; remote backups remain on the server under `~/kogo-snapshot-backups/<stack>/`. Failed restores automatically recover the original files and database. Failed recovery leaves maintenance mode enabled and reports the backup directory. Visitors see maintenance mode during capture and restore; avoid concurrent CLI/background writes. Remote operations share the existing deployment lock. After a forcibly killed local operation, remove `app/.kogo-snapshot.lock` and `public/.maintenance` only once the operation has stopped and any recovery is complete.

For noninteractive restores, supply an exact `SNAPSHOT` directory. Restore only trusted snapshots: they include executable site code and a complete database. No live restore is needed to run the isolated checks:

```sh
bash tests/kogo-snapshot-test.sh
```
