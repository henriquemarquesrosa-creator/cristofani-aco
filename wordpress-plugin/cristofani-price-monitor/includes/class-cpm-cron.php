<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Daily check: for each active competitor whose interval has elapsed and that has no
 * round in flight, creates a new "pending" round with a random persona + a random
 * subset of items/quantities, and drafts the WhatsApp message via Cpm_Anthropic.
 * Nothing here ever sends anything — a human always sends the draft manually.
 */
class Cpm_Cron {

	public static function init() {
		add_action( 'cpm_daily_check', array( __CLASS__, 'run' ) );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'cpm_daily_check' );
	}

	public static function run() {
		global $wpdb;

		$competitors = Cpm_Db::get_all( 'competitors', array( 'active_only' => true ) );
		if ( empty( $competitors ) ) {
			return;
		}

		$created = array();

		foreach ( $competitors as $competitor ) {
			if ( ! self::is_due( $competitor ) ) {
				continue;
			}
			$round = self::generate_round( $competitor );
			if ( $round ) {
				$created[] = $round;
			}
		}

		if ( ! empty( $created ) ) {
			self::notify_admin( $created );
		}
	}

	private static function is_due( $competitor ) {
		global $wpdb;

		$rounds_table = Cpm_Db::table( 'rounds' );
		$in_flight    = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $rounds_table WHERE competitor_id = %d AND status IN ('pending','sent')",
			$competitor->id
		) );
		if ( $in_flight > 0 ) {
			return false;
		}

		if ( empty( $competitor->last_round_at ) ) {
			return true;
		}

		$days_since = ( time() - strtotime( $competitor->last_round_at ) ) / DAY_IN_SECONDS;
		return $days_since >= (int) $competitor->interval_days;
	}

	private static function generate_round( $competitor ) {
		$personas = Cpm_Db::get_all( 'personas', array( 'active_only' => true ) );
		$items    = Cpm_Db::get_all( 'items', array( 'active_only' => true ) );

		if ( empty( $personas ) || empty( $items ) ) {
			return null; // nada cadastrado ainda para gerar uma rodada
		}

		$persona = $personas[ array_rand( $personas ) ];

		$count       = min( count( $items ), wp_rand( 4, 8 ) );
		$keys        = array_rand( $items, $count );
		$keys        = is_array( $keys ) ? $keys : array( $keys );
		$chosen_rows = array_map( function ( $k ) use ( $items ) { return $items[ $k ]; }, $keys );

		$chosen = array();
		foreach ( $chosen_rows as $item ) {
			$chosen[] = array(
				'item_id'  => (int) $item->id,
				'name'     => $item->name,
				'spec'     => $item->spec,
				'unit'     => $item->unit,
				'quantity' => wp_rand( 1, 12 ) * 5, // múltiplo de 5 entre 5 e 60
			);
		}

		$draft = Cpm_Anthropic::draft_message( $persona, $chosen );
		$draft_text  = is_wp_error( $draft ) ? '' : $draft;
		$draft_error = is_wp_error( $draft ) ? $draft->get_error_message() : '';

		$round_id = Cpm_Db::insert( 'rounds', array(
			'competitor_id' => $competitor->id,
			'persona_id'    => $persona->id,
			'status'        => 'pending',
			'items_json'    => wp_json_encode( $chosen ),
			'draft_message' => $draft_text,
			'scheduled_at'  => current_time( 'mysql' ),
			'created_at'    => current_time( 'mysql' ),
		) );

		Cpm_Db::update( 'competitors', $competitor->id, array(
			'last_round_at' => current_time( 'mysql' ),
		) );

		return array(
			'round_id'     => $round_id,
			'competitor'   => $competitor->name,
			'persona'      => $persona->name,
			'draft_error'  => $draft_error,
		);
	}

	private static function notify_admin( $created ) {
		$settings = Cpm_Anthropic::get_settings();
		$to       = $settings['notify_email'] ? $settings['notify_email'] : get_option( 'admin_email' );

		$lines = array( 'Novas rodadas de cotação prontas para revisão:', '' );
		foreach ( $created as $c ) {
			$line = "- {$c['competitor']} (persona: {$c['persona']})";
			if ( $c['draft_error'] ) {
				$line .= " — falha ao gerar rascunho: {$c['draft_error']} (gere manualmente na tela Rodadas)";
			}
			$lines[] = $line;
		}
		$lines[] = '';
		$lines[] = 'Acesse: ' . admin_url( 'admin.php?page=cpm-rounds' );

		wp_mail( $to, 'Monitor de Preços — novas rodadas prontas', implode( "\n", $lines ) );
	}
}
