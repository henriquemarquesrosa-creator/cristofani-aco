<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cpm_Activator {

	public static function activate() {
		Cpm_Db::create_tables();

		if ( false === get_option( 'cpm_settings' ) ) {
			add_option( 'cpm_settings', array(
				'anthropic_api_key' => '',
				'anthropic_model'   => 'claude-sonnet-5',
				'notify_email'      => get_option( 'admin_email' ),
			) );
		}

		if ( ! wp_next_scheduled( 'cpm_daily_check' ) ) {
			wp_schedule_event( time() + 60, 'daily', 'cpm_daily_check' );
		}
	}
}
