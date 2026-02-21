<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BPCU_Notification_Cron_Scheduler {

	public static function init() {
		add_action( BPCU_NOTIFICATIONS_CRON_HOOK, array( __CLASS__, 'handle_cron' ) );
	}

	public static function handle_cron() {
		BPCU_Notification_Purge_Engine::run( false, array(), true );
	}
}
