<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BPNP_Purge_Logger {

	const MAX_ENTRIES = 50;

	/**
	 * Log a purge run.
	 *
	 * @param string $event       Event type: 'purge', 'dry_run', 'error'.
	 * @param bool   $dry_run     Whether this was a dry run.
	 * @param array  $counts      Associative array of counts.
	 */
	public static function log( $event, $dry_run, $counts = array() ) {
		$log = get_option( BPNP_LOG_KEY, array() );

		$entry = array(
			'timestamp'     => gmdate( 'Y-m-d H:i:s' ),
			'event'         => $event,
			'dry_run'       => $dry_run,
			'unread_deleted' => isset( $counts['unread_deleted'] ) ? $counts['unread_deleted'] : 0,
			'read_deleted'   => isset( $counts['read_deleted'] ) ? $counts['read_deleted'] : 0,
			'meta_deleted'   => isset( $counts['meta_deleted'] ) ? $counts['meta_deleted'] : 0,
		);

		array_unshift( $log, $entry );

		// Keep only the most recent entries.
		$log = array_slice( $log, 0, self::MAX_ENTRIES );

		update_option( BPNP_LOG_KEY, $log, false ); // no autoload
	}

	/**
	 * Get all log entries.
	 *
	 * @return array
	 */
	public static function get_logs() {
		return get_option( BPNP_LOG_KEY, array() );
	}
}
