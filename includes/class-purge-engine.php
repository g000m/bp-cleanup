<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BPCU_Notification_Purge_Engine {

	/**
	 * Run the purge process.
	 *
	 * @param bool  $dry_run    If true, count but don't delete.
	 * @param array $overrides  Override settings (days_unread, days_read, batch_size, purge_unread, purge_read).
	 * @param bool  $is_cron    Whether running from cron (adds inter-batch sleep).
	 * @return array Results with counts.
	 */
	public static function run( $dry_run = false, $overrides = array(), $is_cron = false ) {
		global $wpdb;

		$settings = wp_parse_args( $overrides, bpcu_get_notification_settings() );

		if ( ! $settings['enabled'] && $is_cron ) {
			return array(
				'skipped' => true,
				'reason'  => 'Purge is disabled in settings.',
			);
		}

		$table_notifications = $wpdb->prefix . 'bp_notifications';
		$table_meta          = $wpdb->prefix . 'bp_notifications_meta';
		$batch_size          = max( 100, (int) $settings['batch_size'] );
		$sleep_us            = $is_cron ? 50000 : 0; // 50ms for cron

		$results = array(
			'unread_deleted' => 0,
			'read_deleted'   => 0,
			'meta_deleted'   => 0,
			'dry_run'        => $dry_run,
		);

		// Phase 1: Purge unread notifications.
		if ( $settings['purge_unread'] ) {
			$counts = self::purge_by_status(
				$table_notifications,
				$table_meta,
				1, // is_new = 1 (unread)
				(int) $settings['days_unread'],
				$batch_size,
				$dry_run,
				$sleep_us
			);
			$results['unread_deleted'] = $counts['notifications'];
			$results['meta_deleted']  += $counts['meta'];
		}

		// Phase 2: Purge read notifications.
		if ( $settings['purge_read'] ) {
			$counts = self::purge_by_status(
				$table_notifications,
				$table_meta,
				0, // is_new = 0 (read)
				(int) $settings['days_read'],
				$batch_size,
				$dry_run,
				$sleep_us
			);
			$results['read_deleted']  = $counts['notifications'];
			$results['meta_deleted'] += $counts['meta'];
		}

		// Phase 3: Cache invalidation (only if something was deleted and BB is active).
		if ( ! $dry_run && ( $results['unread_deleted'] > 0 || $results['read_deleted'] > 0 ) ) {
			self::flush_caches();
		}

		// Log the run.
		$event = $dry_run ? 'dry_run' : 'purge';
		BPCU_Notification_Logger::log( $event, $dry_run, $results );

		return $results;
	}

	/**
	 * Purge notifications by read/unread status using ID-range cursor.
	 *
	 * @param string $table_notifications Table name.
	 * @param string $table_meta          Meta table name.
	 * @param int    $is_new              1 for unread, 0 for read.
	 * @param int    $days                Notifications older than this many days.
	 * @param int    $batch_size          Rows per batch.
	 * @param bool   $dry_run             Count only.
	 * @param int    $sleep_us            Microseconds to sleep between batches.
	 * @return array Counts: notifications, meta.
	 */
	private static function purge_by_status( $table_notifications, $table_meta, $is_new, $days, $batch_size, $dry_run, $sleep_us ) {
		global $wpdb;

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$total_notifications = 0;
		$total_meta          = 0;

		if ( $dry_run ) {
			// Just count.
			$count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_notifications} WHERE is_new = %d AND date_notified < %s",
				$is_new,
				$cutoff_date
			) );
			return array(
				'notifications' => (int) $count,
				'meta'          => 0, // Can't easily count meta in dry run without scanning.
			);
		}

		// Anchor max_id so new notifications created during purge are safe.
		$max_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(id) FROM {$table_notifications} WHERE is_new = %d AND date_notified < %s",
			$is_new,
			$cutoff_date
		) );

		if ( ! $max_id ) {
			return array( 'notifications' => 0, 'meta' => 0 );
		}

		$cursor = 0;

		while ( true ) {
			// Fetch batch of IDs.
			$ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT id FROM {$table_notifications}
				 WHERE is_new = %d AND date_notified < %s AND id > %d AND id <= %d
				 ORDER BY id ASC LIMIT %d",
				$is_new,
				$cutoff_date,
				$cursor,
				$max_id,
				$batch_size
			) );

			if ( empty( $ids ) ) {
				break;
			}

			$id_placeholders = implode( ',', array_map( 'intval', $ids ) );

			// Delete meta rows first.
			$meta_deleted = $wpdb->query(
				"DELETE FROM {$table_meta} WHERE notification_id IN ({$id_placeholders})"
			);
			$total_meta += max( 0, (int) $meta_deleted );

			// Delete notification rows.
			$notif_deleted = $wpdb->query(
				"DELETE FROM {$table_notifications} WHERE id IN ({$id_placeholders})"
			);
			$total_notifications += max( 0, (int) $notif_deleted );

			// Advance cursor to the last ID in this batch.
			$cursor = end( $ids );

			// Sleep between batches if configured (cron).
			if ( $sleep_us > 0 ) {
				usleep( $sleep_us );
			}
		}

		return array(
			'notifications' => $total_notifications,
			'meta'          => $total_meta,
		);
	}

	/**
	 * Flush BuddyBoss notification caches.
	 */
	private static function flush_caches() {
		if ( ! function_exists( 'buddypress' ) ) {
			return;
		}

		$groups = array(
			'bp_notifications',
			'bp_notifications_unread_count',
			'bp_notifications_grouped_notifications',
			'notification_meta',
		);

		foreach ( $groups as $group ) {
			if ( function_exists( 'wp_cache_flush_group' ) ) {
				wp_cache_flush_group( $group );
			}
		}

		// If group flush isn't available, do a full flush as last resort.
		if ( ! function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush();
		}
	}

	/**
	 * Get table statistics for the notifications tables.
	 *
	 * @return array
	 */
	public static function get_stats() {
		global $wpdb;

		$table_notifications = $wpdb->prefix . 'bp_notifications';
		$table_meta          = $wpdb->prefix . 'bp_notifications_meta';

		$stats = array();

		// Row counts.
		$stats['total_notifications'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_notifications}" );
		$stats['unread_count']        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_notifications} WHERE is_new = 1" );
		$stats['read_count']          = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_notifications} WHERE is_new = 0" );
		$stats['meta_count']          = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_meta}" );

		// Table sizes from information_schema.
		$db_name = DB_NAME;
		$sizes = $wpdb->get_results( $wpdb->prepare(
			"SELECT TABLE_NAME, DATA_LENGTH, INDEX_LENGTH
			 FROM information_schema.TABLES
			 WHERE TABLE_SCHEMA = %s AND TABLE_NAME IN (%s, %s)",
			$db_name,
			$table_notifications,
			$table_meta
		), OBJECT_K );

		foreach ( array( $table_notifications => 'notifications', $table_meta => 'meta' ) as $table => $key ) {
			if ( isset( $sizes[ $table ] ) ) {
				$data  = (int) $sizes[ $table ]->DATA_LENGTH;
				$index = (int) $sizes[ $table ]->INDEX_LENGTH;
				$stats[ "{$key}_data_size" ]  = size_format( $data );
				$stats[ "{$key}_index_size" ] = size_format( $index );
				$stats[ "{$key}_total_size" ] = size_format( $data + $index );
			}
		}

		// Age of oldest notification.
		$oldest = $wpdb->get_var( "SELECT MIN(date_notified) FROM {$table_notifications}" );
		$stats['oldest_notification'] = $oldest ?: 'N/A';

		return $stats;
	}
}
