<?php
/**
 * Plugin Name: BP Notification Purge
 * Description: Automatic scheduled purging of old BuddyBoss notifications to keep database size under control.
 * Version: 1.0.0
 * Author: Evolutionary Herbalism
 * License: GPL-2.0-or-later
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BPNP_VERSION', '1.0.0' );
define( 'BPNP_PLUGIN_FILE', __FILE__ );
define( 'BPNP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Default settings.
define( 'BPNP_DEFAULT_DAYS_UNREAD', 60 );
define( 'BPNP_DEFAULT_DAYS_READ', 30 );
define( 'BPNP_DEFAULT_BATCH_SIZE', 5000 );

// Cron hook name.
define( 'BPNP_CRON_HOOK', 'bpnp_daily_purge' );

// Settings option key.
define( 'BPNP_SETTINGS_KEY', 'bpnp_settings' );

// Log option key.
define( 'BPNP_LOG_KEY', 'bpnp_purge_log' );

/**
 * Get plugin settings merged with defaults.
 */
function bpnp_get_settings() {
	$defaults = array(
		'purge_unread' => true,
		'days_unread'  => BPNP_DEFAULT_DAYS_UNREAD,
		'purge_read'   => true,
		'days_read'    => BPNP_DEFAULT_DAYS_READ,
		'batch_size'   => BPNP_DEFAULT_BATCH_SIZE,
		'enabled'      => true,
	);

	$saved = get_option( BPNP_SETTINGS_KEY, array() );

	return wp_parse_args( $saved, $defaults );
}

// Load includes on plugins_loaded (tables may need cleanup even if BB is disabled).
add_action( 'plugins_loaded', function () {
	require_once BPNP_PLUGIN_DIR . 'includes/class-purge-logger.php';
	require_once BPNP_PLUGIN_DIR . 'includes/class-purge-engine.php';
	require_once BPNP_PLUGIN_DIR . 'includes/class-cron-scheduler.php';

	BPNP_Cron_Scheduler::init();

	// Load WP-CLI command if running in CLI.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once BPNP_PLUGIN_DIR . 'includes/class-cli-command.php';
		WP_CLI::add_command( 'bp-notification-purge', 'BPNP_CLI_Command' );
	}
} );

// Activation: schedule cron.
register_activation_hook( __FILE__, function () {
	if ( ! wp_next_scheduled( BPNP_CRON_HOOK ) ) {
		wp_schedule_event( time(), 'daily', BPNP_CRON_HOOK );
	}
} );

// Deactivation: clear cron.
register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( BPNP_CRON_HOOK );
} );
