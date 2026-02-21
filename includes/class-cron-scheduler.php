<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BPNP_Cron_Scheduler {

	public static function init() {
		add_action( BPNP_CRON_HOOK, array( __CLASS__, 'handle_cron' ) );
	}

	public static function handle_cron() {
		BPNP_Purge_Engine::run( false, array(), true );
	}
}
