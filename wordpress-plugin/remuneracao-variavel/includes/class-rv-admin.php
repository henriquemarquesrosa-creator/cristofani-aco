<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rv_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );

		$actions = array(
			'rv_import_commit',
			'rv_create_cycle', 'rv_save_targets', 'rv_close_cycle', 'rv_reopen_cycle', 'rv_delete_cycle',
			'rv_save_settings',
		);
		foreach ( $actions as $action ) {
			add_action( 'admin_post_' . $action, array( __CLASS__, $action ) );
		}
	}

	public static function assets( $hook ) {
		if ( strpos( $hook, 'rv-' ) === false ) {
			return;
		}
		wp_enqueue_style( 'rv-admin', RV_PLUGIN_URL . 'admin/css/admin.css', array(), RV_VERSION );
	}

	public static function register_menu() {
		add_menu_page( 'Remuneração Variável', 'Remuneração Variável', self::CAP, 'rv-dashboard', array( __CLASS__, 'view_dashboard' ), 'dashicons-money-alt', 59 );
		add_submenu_page( 'rv-dashboard', 'Dashboard', 'Dashboard', self::CAP, 'rv-dashboard', array( __CLASS__, 'view_dashboard' ) );
		add_submenu_page( 'rv-dashboard', 'Importar Relatório', 'Importar Relatório', self::CAP, 'rv-import', array( __CLASS__, 'view_import' ) );
		add_submenu_page( 'rv-dashboard', 'Ciclos', 'Ciclos', self::CAP, 'rv-cycles', array( __CLASS__, 'view_cycles' ) );
		add_submenu_page( 'rv-dashboard', 'Configurações', 'Configurações', self::CAP, 'rv-settings', array( __CLASS__, 'view_settings' ) );
	}

	private static function redirect( $page, $extra = array() ) {
		$args = array( 'page' => $page );
		foreach ( $extra as $k => $v ) {
			$args[ $k ] = rawurlencode( (string) $v );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function guard( $nonce_action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( $nonce_action );
	}

	private static function get_open_cycle() {
		global $wpdb;
		$table = Rv_Db::table( 'cycles' );
		return $wpdb->get_row( "SELECT * FROM $table WHERE status = 'aberto' ORDER BY month1_start DESC LIMIT 1" );
	}

	/* ===================== VIEWS ===================== */

	public static function view_dashboard() {
		$cycle   = self::get_open_cycle();
		$compute = $cycle ? Rv_Calculator::compute_cycle( $cycle ) : null;

		$closed = Rv_Db::get_all( 'cycles', array( 'where' => "status = 'fechado'", 'orderby' => 'month1_start DESC' ) );
		$closed_totals = array();
		foreach ( $closed as $c ) {
			$closed_totals[ $c->id ] = Rv_Calculator::compute_cycle( $c )['total'];
		}

		include RV_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	public static function view_import() {
		global $wpdb;
		$total_rows = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Rv_Db::table( 'sales' ) );
		include RV_PLUGIN_DIR . 'admin/views/import.php';
	}

	public static function view_cycles() {
		$cycles = Rv_Db::get_all( 'cycles', array( 'orderby' => 'month1_start DESC' ) );

		$detail = null;
		if ( ! empty( $_GET['id'] ) ) {
			$cycle = Rv_Db::get( 'cycles', (int) $_GET['id'] );
			if ( $cycle ) {
				$detail = array(
					'cycle'   => $cycle,
					'compute' => Rv_Calculator::compute_cycle( $cycle ),
				);
			}
		}

		include RV_PLUGIN_DIR . 'admin/views/cycles.php';
	}

	public static function view_settings() {
		$settings = get_option( 'rv_settings' );
		include RV_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/* ===================== IMPORT ===================== */

	public static function rv_import_commit() {
		self::guard( 'rv_import' );

		$raw = wp_unslash( $_POST['raw_report'] ?? '' );
		$parsed = Rv_Import::parse( $raw );

		if ( isset( $parsed['error'] ) ) {
			self::redirect( 'rv-import', array( 'rv_error' => $parsed['error'] ) );
		}

		$result = Rv_Import::commit( $parsed['rows'] );

		self::redirect( 'rv-import', array(
			'rv_inserted'   => $result['inserted'],
			'rv_duplicated' => $result['duplicated'],
			'rv_skipped'    => count( $parsed['skipped'] ),
		) );
	}

	/* ===================== CYCLES ===================== */

	public static function rv_create_cycle() {
		self::guard( 'rv_create_cycle' );

		$month1_start = sanitize_text_field( wp_unslash( $_POST['month1_start'] ?? '' ) );
		$d = DateTime::createFromFormat( 'Y-m-d', $month1_start );
		if ( ! $d ) {
			self::redirect( 'rv-cycles', array( 'rv_error' => 'Data inválida.' ) );
		}
		$month1_start = $d->format( 'Y-m-01' );

		$label = sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) );
		if ( '' === $label ) {
			$m1 = date_i18n( 'M', strtotime( $month1_start ) );
			$m2 = date_i18n( 'M/Y', strtotime( $month1_start . ' +1 month' ) );
			$label = ucfirst( $m1 ) . '/' . ucfirst( $m2 );
		}

		$settings = get_option( 'rv_settings' );
		$now      = current_time( 'mysql' );

		$cycle_id = Rv_Db::insert( 'cycles', array(
			'label'        => $label,
			'month1_start' => $month1_start,
			'status'       => 'aberto',
			'created_at'   => $now,
		) );

		$fixed = array(
			array( 'metric_key' => 'abertura_novos_clientes', 'month_index' => 1, 'premio' => $settings['premio_abertura'] ),
			array( 'metric_key' => 'abertura_novos_clientes', 'month_index' => 2, 'premio' => $settings['premio_abertura'] ),
			array( 'metric_key' => 'faturamento_aquisicao', 'month_index' => 1, 'premio' => $settings['premio_aquisicao'] ),
			array( 'metric_key' => 'faturamento_aquisicao', 'month_index' => 2, 'premio' => $settings['premio_aquisicao'] ),
			array( 'metric_key' => 'crescimento_base_ativos', 'month_index' => null, 'premio' => $settings['premio_base_ativos'] ),
			array( 'metric_key' => 'crescimento_faturamento_bimestre', 'month_index' => null, 'premio' => $settings['premio_fat_bimestre'] ),
			array( 'metric_key' => 'faturamento_carteira', 'month_index' => null, 'premio' => $settings['premio_carteira'] ),
			array( 'metric_key' => 'desafio_mes', 'month_index' => null, 'premio' => 0 ),
			array( 'metric_key' => 'desafio_bimestre', 'month_index' => null, 'premio' => 0 ),
		);

		foreach ( $fixed as $f ) {
			Rv_Db::insert( 'targets', array(
				'cycle_id'     => $cycle_id,
				'metric_key'   => $f['metric_key'],
				'month_index'  => $f['month_index'],
				'target_value' => 0,
				'premio_base'  => $f['premio'],
				'created_at'   => $now,
			) );
		}

		self::redirect( 'rv-cycles', array( 'id' => $cycle_id, 'rv_saved' => 1 ) );
	}

	public static function rv_save_targets() {
		self::guard( 'rv_save_targets' );

		$cycle_id = (int) $_POST['cycle_id'];
		$targets  = isset( $_POST['target'] ) && is_array( $_POST['target'] ) ? $_POST['target'] : array();

		foreach ( $targets as $id => $t ) {
			$id   = (int) $id;
			$data = array(
				'target_value' => isset( $t['target_value'] ) ? (float) str_replace( ',', '.', $t['target_value'] ) : 0,
				'premio_base'  => isset( $t['premio_base'] ) ? (float) str_replace( ',', '.', $t['premio_base'] ) : 0,
			);
			if ( isset( $t['title'] ) ) {
				$data['title'] = sanitize_text_field( wp_unslash( $t['title'] ) );
			}
			if ( isset( $t['realizado_manual'] ) && '' !== trim( $t['realizado_manual'] ) ) {
				$data['realizado_manual'] = (float) str_replace( ',', '.', $t['realizado_manual'] );
			} else {
				$data['realizado_manual'] = null;
			}
			if ( isset( $t['valor_override'] ) && '' !== trim( $t['valor_override'] ) ) {
				$data['valor_override'] = (float) str_replace( ',', '.', $t['valor_override'] );
			} else {
				$data['valor_override'] = null;
			}
			Rv_Db::update( 'targets', $id, $data );
		}

		self::redirect( 'rv-cycles', array( 'id' => $cycle_id, 'rv_saved' => 1 ) );
	}

	public static function rv_close_cycle() {
		self::guard( 'rv_close_cycle' );
		$id = (int) $_POST['id'];
		Rv_Db::update( 'cycles', $id, array( 'status' => 'fechado' ) );
		self::redirect( 'rv-cycles', array( 'id' => $id, 'rv_saved' => 1 ) );
	}

	public static function rv_reopen_cycle() {
		self::guard( 'rv_reopen_cycle' );
		$id = (int) $_POST['id'];
		Rv_Db::update( 'cycles', $id, array( 'status' => 'aberto' ) );
		self::redirect( 'rv-cycles', array( 'id' => $id, 'rv_saved' => 1 ) );
	}

	public static function rv_delete_cycle() {
		self::guard( 'rv_delete_cycle' );
		global $wpdb;
		$id = (int) $_POST['id'];
		$wpdb->delete( Rv_Db::table( 'targets' ), array( 'cycle_id' => $id ), array( '%d' ) );
		Rv_Db::delete( 'cycles', $id );
		self::redirect( 'rv-cycles', array( 'rv_deleted' => 1 ) );
	}

	/* ===================== SETTINGS ===================== */

	public static function rv_save_settings() {
		self::guard( 'rv_save_settings' );

		update_option( 'rv_settings', array(
			'vendedor_nome'       => sanitize_text_field( wp_unslash( $_POST['vendedor_nome'] ?? '' ) ),
			'limite_abertura'     => (float) str_replace( ',', '.', $_POST['limite_abertura'] ?? 20000 ),
			'limite_ativo'        => (float) str_replace( ',', '.', $_POST['limite_ativo'] ?? 40000 ),
			'premio_abertura'     => (float) str_replace( ',', '.', $_POST['premio_abertura'] ?? 1000 ),
			'premio_aquisicao'    => (float) str_replace( ',', '.', $_POST['premio_aquisicao'] ?? 1000 ),
			'premio_base_ativos'  => (float) str_replace( ',', '.', $_POST['premio_base_ativos'] ?? 2000 ),
			'premio_fat_bimestre' => (float) str_replace( ',', '.', $_POST['premio_fat_bimestre'] ?? 2000 ),
			'premio_carteira'     => (float) str_replace( ',', '.', $_POST['premio_carteira'] ?? 3000 ),
		) );

		self::redirect( 'rv-settings', array( 'rv_saved' => 1 ) );
	}
}
