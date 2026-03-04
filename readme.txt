=== BP Cleanup ===
Contributors: gabe462
Tags: buddypress, buddyboss, notifications, cleanup, cron, wp-cli
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.1.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Scheduled housekeeping for BuddyPress and BuddyBoss. Automatically purges old notifications to keep database size under control, with a modular framework for future cleanup tasks.

== Description ==

BuddyBoss has no built-in expiration for notifications, so tables can grow indefinitely. BP Cleanup adds a daily cron job that prunes old notifications in memory-safe batches using direct SQL. It also provides a WP-CLI interface for running and auditing cleanup operations.

Highlights:

* Daily cron purge with configurable thresholds for unread and read notifications.
* Notifications for users who have never logged in are deleted automatically.
* Memory-safe batch deletion using an ID-range cursor.
* WP-CLI commands for stats, dry runs, and logs.
* Modular structure for future cleanup modules.

== Installation ==

1. Upload the `bp-cleanup` folder to your `wp-content/plugins/` directory.
2. Activate the plugin in the WordPress admin.

The daily cron job is scheduled automatically on activation.

== Usage ==

Use WP-CLI to run or preview cleanups:

`wp bp-cleanup notifications run --dry-run`

== Frequently Asked Questions ==

= Does this require BuddyPress or BuddyBoss to be active? =

The notification tables must exist, but the component does not need to be active to run cleanup.

= How do I run it manually? =

Use WP-CLI:

`wp bp-cleanup notifications run --dry-run`

= How does it determine that a user has never logged in? =

Users without a `wpf_last_login` usermeta entry are treated as never logged in.

== Changelog ==

= 1.1.2 =
* Version bump and workflow adjustments.

= 1.1.1 =
* Fix version alignment for the release.
* Purge notifications for users who have never logged in.
* Show never-logged-in counts in WP-CLI stats and logs.

= 1.1.0 =
* Raise minimum PHP to 8.0 and add CI checks.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.2 =
Version bump and workflow adjustments.

= 1.1.1 =
Version alignment update for the release.

= 1.1.0 =
Adds never-logged-in notification cleanup and raises minimum PHP to 8.0.

= 1.0.0 =
Initial release.
