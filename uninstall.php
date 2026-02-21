<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove stored options.
delete_option( 'bpcu_notifications_settings' );
delete_option( 'bpcu_notifications_log' );

// Clear scheduled cron hooks.
wp_clear_scheduled_hook( 'bpcu_notifications_daily_purge' );
