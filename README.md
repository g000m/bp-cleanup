# BP Cleanup

Scheduled housekeeping for BuddyPress and BuddyBoss. Automatically purges old notifications to keep database size under control, with a modular framework for adding future cleanup tasks.

## Background

BuddyBoss has no built-in expiration for notifications — they accumulate indefinitely. On busy sites, `wp_bp_notifications` and `wp_bp_notifications_meta` can easily grow to 500MB+. This plugin adds a daily cron job that prunes old notifications in memory-safe batches using direct SQL (bypassing BP's API, which loads every row into PHP memory before deleting).

## Requirements

- WordPress 6.9+
- PHP 8.0+
- BuddyPress or BuddyBoss Platform (tables must exist; component does not need to be active)

## Installation

Drop the `bp-cleanup` folder into `wp-content/plugins/` and activate:

```bash
wp plugin activate bp-cleanup
```

The daily cron job is scheduled automatically on activation.

### Composer (VCS)

```bash
composer config repositories.bp-cleanup vcs https://github.com/g000m/bp-cleanup
composer require g000m/bp-cleanup:^1.0
```

## Updates (GitHub Releases)

This plugin checks GitHub releases for updates. Release assets include the `vendor/` directory.

### Release process

1. Update `Version:` in `bp-cleanup.php` (authoritative) and match `version` in `composer.json`.
2. Use a strict SemVer version (e.g., `1.2.3`). Pre-release versions are skipped.
3. Push to `main`. The release workflow creates tag `vX.Y.Z`, builds the zip, and publishes the GitHub release.

Releases are skipped when versions mismatch or the new version is not greater than the latest tag.

## WP-CLI Commands

### `wp bp-cleanup notifications stats`

Show current table row counts, sizes, and plugin settings.

```
$ wp bp-cleanup notifications stats

Notification Table Statistics
=============================

Row Counts:
  Total notifications:  246703
  Unread (is_new=1):    243617
  Read (is_new=0):      3086
  Meta rows:            257292

Table Sizes:
  Notifications:  176 MB (data: 28 MB, index: 148 MB)
  Meta:           29 MB (data: 14 MB, index: 15 MB)

Oldest notification:    2025-12-23 14:00:08

Current Settings:
  Enabled:              Yes
  Purge unread:         Yes (>60 days)
  Purge read:           Yes (>30 days)
  Batch size:           5000
  Next scheduled run:   2026-02-22 03:57:21 UTC
```

### `wp bp-cleanup notifications run`

Run the purge. Use `--dry-run` to count qualifying rows without deleting anything.

```bash
# Preview what would be deleted
wp bp-cleanup notifications run --dry-run

# Run with defaults (unread >60 days, read >30 days)
wp bp-cleanup notifications run

# Custom thresholds
wp bp-cleanup notifications run --unread-days=90 --read-days=45

# Skip one category
wp bp-cleanup notifications run --skip-unread
wp bp-cleanup notifications run --skip-read

# Smaller batches for lower DB load
wp bp-cleanup notifications run --batch-size=1000
```

**Options:**

| Flag | Default | Description |
|------|---------|-------------|
| `--dry-run` | — | Count qualifying rows without deleting |
| `--unread-days=N` | 60 | Purge unread notifications older than N days |
| `--read-days=N` | 30 | Purge read notifications older than N days |
| `--batch-size=N` | 5000 | Rows per DELETE batch |
| `--skip-unread` | — | Skip unread notifications |
| `--skip-read` | — | Skip read notifications |

### `wp bp-cleanup notifications logs`

Show the last 50 purge runs.

```
$ wp bp-cleanup notifications logs

Timestamp            Event    Dry Run  Unread   Read    Meta
2026-02-21 04:10:22  purge    No       885103   48882   1011362
2026-02-21 03:58:04  dry_run  Yes      885103   48846   0
```

## How It Works

### Batch deletion with ID-range cursor

Rather than `LIMIT/OFFSET` (which rescans the table on every page), the engine:

1. Captures `MAX(id)` of qualifying rows upfront — new notifications created during the purge are never touched
2. Loops: `SELECT` a batch of IDs → `DELETE` their meta rows → `DELETE` the notification rows → advance cursor
3. Sleeps 50ms between batches when running from cron to reduce lock contention (no sleep for CLI)

### Why direct SQL instead of BP's API

BP's `_delete()` method does `SELECT *` on all matching rows, loads them into PHP memory, iterates meta one-by-one, and fires hooks that also `SELECT *`. On a table with millions of rows this exhausts memory. Direct SQL keeps memory usage flat regardless of table size.

### Cache invalidation

After a live purge, the plugin flushes BP's notification cache groups (`bp_notifications`, `bp_notifications_unread_count`, `bp_notifications_grouped_notifications`, `notification_meta`) via `wp_cache_flush_group()`, falling back to `wp_cache_flush()` if group-level flushing isn't available.

## Settings

Settings are stored in the `bpcu_notifications_settings` option. They can be overridden per-run via CLI flags. Configurable values:

| Key | Default | Description |
|-----|---------|-------------|
| `enabled` | `true` | Enable/disable the daily cron purge |
| `purge_unread` | `true` | Purge unread notifications |
| `days_unread` | `60` | Age threshold for unread |
| `purge_read` | `true` | Purge read notifications |
| `days_read` | `30` | Age threshold for read |
| `batch_size` | `5000` | DELETE batch size |

## Recommended Index

The plugin works without it, but this index significantly speeds up purge queries on large tables:

```sql
ALTER TABLE wp_bp_notifications ADD INDEX idx_purge (is_new, date_notified, id);
```

## Verify Cron Is Scheduled

```bash
wp cron event list | grep bpcu
```

## Uninstall

Deactivating clears the cron hook. Uninstalling (via the WordPress admin) also removes the `bpcu_notifications_settings` and `bpcu_notifications_log` options.

## Future Modules

The plugin is structured to support additional cleanup modules under the `wp bp-cleanup <module>` CLI namespace. Candidates:

- `activity` — prune old activity stream entries
- `messages` — delete old private messages
- `friends` — clean up orphaned friendship records
