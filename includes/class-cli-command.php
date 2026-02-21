<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage BuddyBoss notification purging.
 *
 * ## EXAMPLES
 *
 *     # Dry run with defaults
 *     $ wp bp-notification-purge run --dry-run
 *
 *     # Purge with custom thresholds
 *     $ wp bp-notification-purge run --unread-days=90 --read-days=45
 *
 *     # Show table statistics
 *     $ wp bp-notification-purge stats
 *
 *     # Show recent purge logs
 *     $ wp bp-notification-purge logs
 */
class BPNP_CLI_Command extends WP_CLI_Command {

	/**
	 * Run the notification purge.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Count qualifying rows without deleting.
	 *
	 * [--unread-days=<days>]
	 * : Purge unread notifications older than this many days.
	 *
	 * [--read-days=<days>]
	 * : Purge read notifications older than this many days.
	 *
	 * [--batch-size=<size>]
	 * : Number of rows per batch.
	 *
	 * [--skip-unread]
	 * : Skip purging unread notifications.
	 *
	 * [--skip-read]
	 * : Skip purging read notifications.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Named args.
	 */
	public function run( $args, $assoc_args ) {
		$dry_run   = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$overrides = array();

		if ( isset( $assoc_args['unread-days'] ) ) {
			$overrides['days_unread'] = (int) $assoc_args['unread-days'];
		}
		if ( isset( $assoc_args['read-days'] ) ) {
			$overrides['days_read'] = (int) $assoc_args['read-days'];
		}
		if ( isset( $assoc_args['batch-size'] ) ) {
			$overrides['batch_size'] = (int) $assoc_args['batch-size'];
		}
		if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-unread', false ) ) {
			$overrides['purge_unread'] = false;
		}
		if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-read', false ) ) {
			$overrides['purge_read'] = false;
		}

		// Always treat as enabled when running via CLI.
		$overrides['enabled'] = true;

		$mode = $dry_run ? 'DRY RUN' : 'LIVE';
		WP_CLI::log( "Starting notification purge ({$mode})..." );

		$results = BPNP_Purge_Engine::run( $dry_run, $overrides, false );

		if ( isset( $results['skipped'] ) ) {
			WP_CLI::warning( $results['reason'] );
			return;
		}

		$verb = $dry_run ? 'Would delete' : 'Deleted';

		WP_CLI::log( '' );
		WP_CLI::log( "Results:" );
		WP_CLI::log( "  Unread notifications: {$verb} {$results['unread_deleted']}" );
		WP_CLI::log( "  Read notifications:   {$verb} {$results['read_deleted']}" );

		if ( ! $dry_run ) {
			WP_CLI::log( "  Meta rows deleted:    {$results['meta_deleted']}" );
		}

		$total = $results['unread_deleted'] + $results['read_deleted'];
		WP_CLI::success( "{$verb} {$total} total notifications." );
	}

	/**
	 * Show notification table statistics.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Named args.
	 */
	public function stats( $args, $assoc_args ) {
		$stats = BPNP_Purge_Engine::get_stats();

		WP_CLI::log( 'Notification Table Statistics' );
		WP_CLI::log( '=============================' );
		WP_CLI::log( '' );
		WP_CLI::log( "Row Counts:" );
		WP_CLI::log( "  Total notifications:  {$stats['total_notifications']}" );
		WP_CLI::log( "  Unread (is_new=1):    {$stats['unread_count']}" );
		WP_CLI::log( "  Read (is_new=0):      {$stats['read_count']}" );
		WP_CLI::log( "  Meta rows:            {$stats['meta_count']}" );
		WP_CLI::log( '' );

		if ( isset( $stats['notifications_total_size'] ) ) {
			WP_CLI::log( "Table Sizes:" );
			WP_CLI::log( "  Notifications:  {$stats['notifications_total_size']} (data: {$stats['notifications_data_size']}, index: {$stats['notifications_index_size']})" );
			WP_CLI::log( "  Meta:           {$stats['meta_total_size']} (data: {$stats['meta_data_size']}, index: {$stats['meta_index_size']})" );
			WP_CLI::log( '' );
		}

		WP_CLI::log( "Oldest notification:    {$stats['oldest_notification']}" );

		// Show current settings.
		$settings = bpnp_get_settings();
		WP_CLI::log( '' );
		WP_CLI::log( "Current Settings:" );
		WP_CLI::log( "  Enabled:              " . ( $settings['enabled'] ? 'Yes' : 'No' ) );
		WP_CLI::log( "  Purge unread:         " . ( $settings['purge_unread'] ? "Yes (>{$settings['days_unread']} days)" : 'No' ) );
		WP_CLI::log( "  Purge read:           " . ( $settings['purge_read'] ? "Yes (>{$settings['days_read']} days)" : 'No' ) );
		WP_CLI::log( "  Batch size:           {$settings['batch_size']}" );

		// Show next scheduled cron.
		$next = wp_next_scheduled( BPNP_CRON_HOOK );
		if ( $next ) {
			$next_str = gmdate( 'Y-m-d H:i:s', $next ) . ' UTC';
			WP_CLI::log( "  Next scheduled run:   {$next_str}" );
		} else {
			WP_CLI::log( "  Next scheduled run:   Not scheduled" );
		}
	}

	/**
	 * Show recent purge log entries.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Named args.
	 */
	public function logs( $args, $assoc_args ) {
		$logs = BPNP_Purge_Logger::get_logs();

		if ( empty( $logs ) ) {
			WP_CLI::log( 'No purge runs recorded yet.' );
			return;
		}

		$table_data = array();
		foreach ( $logs as $entry ) {
			$table_data[] = array(
				'Timestamp' => $entry['timestamp'],
				'Event'     => $entry['event'],
				'Dry Run'   => $entry['dry_run'] ? 'Yes' : 'No',
				'Unread'    => $entry['unread_deleted'],
				'Read'      => $entry['read_deleted'],
				'Meta'      => $entry['meta_deleted'],
			);
		}

		WP_CLI\Utils\format_items( 'table', $table_data, array( 'Timestamp', 'Event', 'Dry Run', 'Unread', 'Read', 'Meta' ) );
	}
}
