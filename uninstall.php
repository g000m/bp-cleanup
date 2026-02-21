<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove stored options.
delete_option( 'bpnp_settings' );
delete_option( 'bpnp_purge_log' );

// Clear scheduled cron hook.
wp_clear_scheduled_hook( 'bpnp_daily_purge' );
