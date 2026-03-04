<?php
/**
 * Plugin Name: BP Cleanup
 * Description: Scheduled housekeeping for BuddyPress and BuddyBoss - purges old notifications and provides a framework for future cleanup modules.
 * Version: 1.1.1
 * Author: Gabe Herbert
 * License: GPL-2.0-or-later
 * Requires at least: 6.9
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin_data = get_file_data( __FILE__, array( 'Version' => 'Version' ) );

define( 'BPCU_VERSION', isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '1.1.1' );
define( 'BPCU_PLUGIN_FILE', __FILE__ );
define( 'BPCU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

$autoload = BPCU_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

// Notifications module defaults.
define( 'BPCU_DEFAULT_DAYS_UNREAD', 60 );
define( 'BPCU_DEFAULT_DAYS_READ', 30 );
define( 'BPCU_DEFAULT_BATCH_SIZE', 5000 );

// Notifications module cron hook and option keys.
define( 'BPCU_NOTIFICATIONS_CRON_HOOK', 'bpcu_notifications_daily_purge' );
define( 'BPCU_NOTIFICATIONS_SETTINGS_KEY', 'bpcu_notifications_settings' );
define( 'BPCU_NOTIFICATIONS_LOG_KEY', 'bpcu_notifications_log' );

/**
 * Get notification purge settings merged with defaults.
 */
function bpcu_get_notification_settings() {
	$defaults = array(
		'purge_unread' => true,
		'days_unread'  => BPCU_DEFAULT_DAYS_UNREAD,
		'purge_read'   => true,
		'days_read'    => BPCU_DEFAULT_DAYS_READ,
		'batch_size'   => BPCU_DEFAULT_BATCH_SIZE,
		'enabled'      => true,
	);

	$saved = get_option( BPCU_NOTIFICATIONS_SETTINGS_KEY, array() );

	return wp_parse_args( $saved, $defaults );
}

// Load modules on plugins_loaded (tables may need cleanup even if BB is disabled).
add_action( 'plugins_loaded', function () {
	if ( class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
		$update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/g000m/bp-cleanup/',
			BPCU_PLUGIN_FILE,
			'bp-cleanup'
		);
		$update_checker->setBranch( 'main' );

		$vcs_api = $update_checker->getVcsApi();
		if ( $vcs_api && method_exists( $vcs_api, 'enableReleaseAssets' ) ) {
			$vcs_api->enableReleaseAssets();
		}
	}

	require_once BPCU_PLUGIN_DIR . 'includes/class-purge-logger.php';
	require_once BPCU_PLUGIN_DIR . 'includes/class-purge-engine.php';
	require_once BPCU_PLUGIN_DIR . 'includes/class-cron-scheduler.php';

	BPCU_Notification_Cron_Scheduler::init();

	// Load WP-CLI commands if running in CLI.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once BPCU_PLUGIN_DIR . 'includes/class-cli-command.php';
		WP_CLI::add_command( 'bp-cleanup notifications', 'BPCU_Notifications_CLI_Command' );
	}
} );

// Activation: schedule cron.
register_activation_hook( __FILE__, function () {
	if ( ! wp_next_scheduled( BPCU_NOTIFICATIONS_CRON_HOOK ) ) {
		wp_schedule_event( time(), 'daily', BPCU_NOTIFICATIONS_CRON_HOOK );
	}
} );

// Deactivation: clear cron.
register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( BPCU_NOTIFICATIONS_CRON_HOOK );
} );
