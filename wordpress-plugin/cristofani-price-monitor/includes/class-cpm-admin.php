<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cpm_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );

		$actions = array(
			'cpm_save_competitor', 'cpm_delete_competitor',
			'cpm_save_item', 'cpm_delete_item',
			'cpm_save_persona', 'cpm_delete_persona',
			'cpm_save_settings',
			'cpm_mark_sent', 'cpm_regenerate_draft', 'cpm_delete_round', 'cpm_run_now',
			'cpm_upload_quote', 'cpm_confirm_quote', 'cpm_cancel_review',
		);
		foreach ( $actions as $action ) {
			add_action( 'admin_post_' . $action, array( __CLASS__, $action ) );
		}
	}

	public static function assets( $hook ) {
		if ( strpos( $hook, 'cpm-' ) === false ) {
			return;
		}
		wp_enqueue_style( 'cpm-admin', CPM_PLUGIN_URL . 'admin/css/admin.css', array(), CPM_VERSION );
	}

	public static function register_menu() {
		add_menu_page( 'Monitor de Preços', 'Monitor de Preços', self::CAP, 'cpm-dashboard', array( __CLASS__, 'view_dashboard' ), 'dashicons-chart-line', 58 );
		add_submenu_page( 'cpm-dashboard', 'Dashboard', 'Dashboard', self::CAP, 'cpm-dashboard', array( __CLASS__, 'view_dashboard' ) );
		add_submenu_page( 'cpm-dashboard', 'Concorrentes', 'Concorrentes', self::CAP, 'cpm-competitors', array( __CLASS__, 'view_competitors' ) );
		add_submenu_page( 'cpm-dashboard', 'Itens', 'Itens', self::CAP, 'cpm-items', array( __CLASS__, 'view_items' ) );
		add_submenu_page( 'cpm-dashboard', 'Personas', 'Personas', self::CAP, 'cpm-personas', array( __CLASS__, 'view_personas' ) );
		add_submenu_page( 'cpm-dashboard', 'Rodadas', 'Rodadas', self::CAP, 'cpm-rounds', array( __CLASS__, 'view_rounds' ) );
		add_submenu_page( 'cpm-dashboard', 'Configurações', 'Configurações', self::CAP, 'cpm-settings', array( __CLASS__, 'view_settings' ) );
	}

	private static function redirect( $page, $extra = array() ) {
		$args = array( 'page' => $page );
		foreach ( $extra as $k => $v ) {
			$args[ $k ] = rawurlencode( (string) $v );
		}
		$url = add_query_arg( $args, admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	private static function guard( $nonce_action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( $nonce_action );
	}

	/* ===================== VIEWS ===================== */

	public static function view_dashboard() {
		$data = self::build_dashboard_data();
		include CPM_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	public static function view_competitors() {
		$competitors = Cpm_Db::get_all( 'competitors' );
		include CPM_PLUGIN_DIR . 'admin/views/competitors.php';
	}

	public static function view_items() {
		$items = Cpm_Db::get_all( 'items' );
		include CPM_PLUGIN_DIR . 'admin/views/items.php';
	}

	public static function view_personas() {
		$personas = Cpm_Db::get_all( 'personas' );
		include CPM_PLUGIN_DIR . 'admin/views/personas.php';
	}

	public static function view_rounds() {
		global $wpdb;
		$rounds_table      = Cpm_Db::table( 'rounds' );
		$competitors_table = Cpm_Db::table( 'competitors' );
		$personas_table    = Cpm_Db::table( 'personas' );

		$rounds = $wpdb->get_results(
			"SELECT r.*, c.name AS competitor_name, p.name AS persona_name
			 FROM $rounds_table r
			 LEFT JOIN $competitors_table c ON c.id = r.competitor_id
			 LEFT JOIN $personas_table p ON p.id = r.persona_id
			 ORDER BY r.created_at DESC
			 LIMIT 100"
		);

		$review = null;
		if ( ! empty( $_GET['review'] ) ) {
			$round_id = (int) $_GET['review'];
			$parsed   = get_transient( 'cpm_parsed_' . $round_id );
			if ( $parsed ) {
				$review = array(
					'round_id' => $round_id,
					'parsed'   => $parsed,
					'items'    => Cpm_Db::get_all( 'items', array( 'active_only' => true ) ),
				);
			}
		}

		include CPM_PLUGIN_DIR . 'admin/views/rounds.php';
	}

	public static function view_settings() {
		$settings = Cpm_Anthropic::get_settings();
		include CPM_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/* ===================== COMPETITORS ===================== */

	public static function cpm_save_competitor() {
		self::guard( 'cpm_save_competitor' );

		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$data = array(
			'name'          => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'phone'         => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
			'interval_days' => max( 1, (int) ( $_POST['interval_days'] ?? 30 ) ),
			'active'        => ! empty( $_POST['active'] ) ? 1 : 0,
			'notes'         => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
		);

		if ( '' === $data['name'] ) {
			self::redirect( 'cpm-competitors', array( 'cpm_error' => 'Nome é obrigatório.' ) );
		}

		if ( $id ) {
			Cpm_Db::update( 'competitors', $id, $data );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			Cpm_Db::insert( 'competitors', $data );
		}

		self::redirect( 'cpm-competitors', array( 'cpm_saved' => 1 ) );
	}

	public static function cpm_delete_competitor() {
		self::guard( 'cpm_delete_competitor' );
		Cpm_Db::delete( 'competitors', (int) $_POST['id'] );
		self::redirect( 'cpm-competitors', array( 'cpm_deleted' => 1 ) );
	}

	/* ===================== ITEMS ===================== */

	public static function cpm_save_item() {
		self::guard( 'cpm_save_item' );

		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$data = array(
			'name'   => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'spec'   => sanitize_text_field( wp_unslash( $_POST['spec'] ?? '' ) ),
			'unit'   => sanitize_text_field( wp_unslash( $_POST['unit'] ?? 'un' ) ),
			'active' => ! empty( $_POST['active'] ) ? 1 : 0,
		);

		if ( '' === $data['name'] ) {
			self::redirect( 'cpm-items', array( 'cpm_error' => 'Nome é obrigatório.' ) );
		}

		if ( $id ) {
			Cpm_Db::update( 'items', $id, $data );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			Cpm_Db::insert( 'items', $data );
		}

		self::redirect( 'cpm-items', array( 'cpm_saved' => 1 ) );
	}

	public static function cpm_delete_item() {
		self::guard( 'cpm_delete_item' );
		Cpm_Db::delete( 'items', (int) $_POST['id'] );
		self::redirect( 'cpm-items', array( 'cpm_deleted' => 1 ) );
	}

	/* ===================== PERSONAS ===================== */

	public static function cpm_save_persona() {
		self::guard( 'cpm_save_persona' );

		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$data = array(
			'name'   => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'voice'  => sanitize_textarea_field( wp_unslash( $_POST['voice'] ?? '' ) ),
			'active' => ! empty( $_POST['active'] ) ? 1 : 0,
		);

		if ( '' === $data['name'] || '' === $data['voice'] ) {
			self::redirect( 'cpm-personas', array( 'cpm_error' => 'Nome e descrição são obrigatórios.' ) );
		}

		if ( $id ) {
			Cpm_Db::update( 'personas', $id, $data );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			Cpm_Db::insert( 'personas', $data );
		}

		self::redirect( 'cpm-personas', array( 'cpm_saved' => 1 ) );
	}

	public static function cpm_delete_persona() {
		self::guard( 'cpm_delete_persona' );
		Cpm_Db::delete( 'personas', (int) $_POST['id'] );
		self::redirect( 'cpm-personas', array( 'cpm_deleted' => 1 ) );
	}

	/* ===================== SETTINGS ===================== */

	public static function cpm_save_settings() {
		self::guard( 'cpm_save_settings' );

		$current = Cpm_Anthropic::get_settings();
		$new_key = wp_unslash( $_POST['anthropic_api_key'] ?? '' );

		update_option( 'cpm_settings', array(
			'anthropic_api_key' => '' !== trim( $new_key ) ? trim( $new_key ) : $current['anthropic_api_key'],
			'anthropic_model'   => sanitize_text_field( wp_unslash( $_POST['anthropic_model'] ?? 'claude-sonnet-5' ) ),
			'notify_email'      => sanitize_email( wp_unslash( $_POST['notify_email'] ?? '' ) ),
		) );

		self::redirect( 'cpm-settings', array( 'cpm_saved' => 1 ) );
	}

	/* ===================== ROUNDS ===================== */

	public static function cpm_run_now() {
		self::guard( 'cpm_run_now' );
		Cpm_Cron::run();
		self::redirect( 'cpm-rounds', array( 'cpm_ran' => 1 ) );
	}

	public static function cpm_mark_sent() {
		self::guard( 'cpm_mark_sent' );
		$id = (int) $_POST['id'];
		Cpm_Db::update( 'rounds', $id, array(
			'status'  => 'sent',
			'sent_at' => current_time( 'mysql' ),
		) );
		self::redirect( 'cpm-rounds', array( 'cpm_saved' => 1 ) );
	}

	public static function cpm_regenerate_draft() {
		self::guard( 'cpm_regenerate_draft' );
		$id    = (int) $_POST['id'];
		$round = Cpm_Db::get( 'rounds', $id );
		if ( ! $round ) {
			self::redirect( 'cpm-rounds', array( 'cpm_error' => 'Rodada não encontrada.' ) );
		}

		$persona = Cpm_Db::get( 'personas', $round->persona_id );
		$items   = json_decode( $round->items_json, true );
		if ( ! $persona || ! $items ) {
			self::redirect( 'cpm-rounds', array( 'cpm_error' => 'Faltam dados da persona/itens para regenerar.' ) );
		}

		$draft = Cpm_Anthropic::draft_message( $persona, $items );
		if ( is_wp_error( $draft ) ) {
			self::redirect( 'cpm-rounds', array( 'cpm_error' => $draft->get_error_message() ) );
		}

		Cpm_Db::update( 'rounds', $id, array( 'draft_message' => $draft ) );
		self::redirect( 'cpm-rounds', array( 'cpm_saved' => 1 ) );
	}

	public static function cpm_delete_round() {
		self::guard( 'cpm_delete_round' );
		Cpm_Db::delete( 'rounds', (int) $_POST['id'] );
		self::redirect( 'cpm-rounds', array( 'cpm_deleted' => 1 ) );
	}

	/* ===================== QUOTE UPLOAD / REVIEW ===================== */

	public static function cpm_upload_quote() {
		self::guard( 'cpm_upload_quote' );

		$round_id = (int) $_POST['round_id'];
		$round    = Cpm_Db::get( 'rounds', $round_id );
		if ( ! $round ) {
			self::redirect( 'cpm-rounds', array( 'cpm_error' => 'Rodada não encontrada.' ) );
		}

		if ( empty( $_FILES['quote_pdf'] ) || UPLOAD_ERR_OK !== $_FILES['quote_pdf']['error'] ) {
			self::redirect( 'cpm-rounds', array( 'cpm_error' => 'Envie um arquivo PDF válido.' ) );
		}

		$file = $_FILES['quote_pdf'];
		$type = wp_check_filetype( $file['name'] );
		if ( 'pdf' !== strtolower( $type['ext'] ) ) {
			self::redirect( 'cpm-rounds', array( 'cpm_error' => 'O arquivo precisa ser um PDF.' ) );
		}

		$upload_dir = wp_upload_dir();
		$target_dir = trailingslashit( $upload_dir['basedir'] ) . 'cpm-quotes/';
		if ( ! file_exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}
		$filename = 'quote-' . $round_id . '-' . time() . '.pdf';
		$target   = $target_dir . $filename;

		if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
			self::redirect( 'cpm-rounds', array( 'cpm_error' => 'Falha ao salvar o arquivo enviado.' ) );
		}

		$parsed = Cpm_Anthropic::parse_quote_pdf( $target );
		if ( is_wp_error( $parsed ) ) {
			self::redirect( 'cpm-rounds', array( 'cpm_error' => 'Falha ao ler o PDF: ' . $parsed->get_error_message() ) );
		}

		$parsed['source_file'] = 'cpm-quotes/' . $filename;
		set_transient( 'cpm_parsed_' . $round_id, $parsed, HOUR_IN_SECONDS );

		self::redirect( 'cpm-rounds', array( 'review' => $round_id ) );
	}

	public static function cpm_confirm_quote() {
		self::guard( 'cpm_confirm_quote' );

		$round_id = (int) $_POST['round_id'];
		$round    = Cpm_Db::get( 'rounds', $round_id );
		$parsed   = get_transient( 'cpm_parsed_' . $round_id );
		if ( ! $round || ! $parsed ) {
			self::redirect( 'cpm-rounds', array( 'cpm_error' => 'Sessão de revisão expirada, envie o PDF novamente.' ) );
		}

		$rows       = isset( $_POST['row'] ) && is_array( $_POST['row'] ) ? $_POST['row'] : array();
		$quote_date = ! empty( $parsed['quote_date'] ) ? sanitize_text_field( $parsed['quote_date'] ) : current_time( 'Y-m-d' );
		$saved      = 0;

		foreach ( $rows as $row ) {
			if ( empty( $row['include'] ) ) {
				continue;
			}
			$item_id = ! empty( $row['item_id'] ) ? (int) $row['item_id'] : null;
			Cpm_Db::insert( 'quotes', array(
				'round_id'      => $round_id,
				'competitor_id' => $round->competitor_id,
				'item_id'       => $item_id,
				'item_name_raw' => sanitize_text_field( wp_unslash( $row['name'] ?? '' ) ),
				'quantity'      => is_numeric( $row['quantity'] ?? null ) ? floatval( $row['quantity'] ) : null,
				'unit'          => sanitize_text_field( wp_unslash( $row['unit'] ?? '' ) ),
				'unit_price'    => is_numeric( $row['unit_price'] ?? null ) ? floatval( $row['unit_price'] ) : null,
				'total_price'   => is_numeric( $row['total_price'] ?? null ) ? floatval( $row['total_price'] ) : null,
				'quote_date'    => $quote_date,
				'source_file'   => sanitize_text_field( $parsed['source_file'] ?? '' ),
				'created_at'    => current_time( 'mysql' ),
			) );
			$saved++;
		}

		Cpm_Db::update( 'rounds', $round_id, array(
			'status'      => 'received',
			'received_at' => current_time( 'mysql' ),
		) );

		delete_transient( 'cpm_parsed_' . $round_id );
		self::redirect( 'cpm-rounds', array( 'cpm_saved' => $saved ) );
	}

	public static function cpm_cancel_review() {
		self::guard( 'cpm_cancel_review' );
		delete_transient( 'cpm_parsed_' . (int) $_POST['round_id'] );
		self::redirect( 'cpm-rounds' );
	}

	/* ===================== DASHBOARD DATA ===================== */

	private static function build_dashboard_data() {
		global $wpdb;
		$quotes_table      = Cpm_Db::table( 'quotes' );
		$competitors_table = Cpm_Db::table( 'competitors' );
		$items_table       = Cpm_Db::table( 'items' );

		$rows = $wpdb->get_results(
			"SELECT q.*, c.name AS competitor_name, i.name AS item_name
			 FROM $quotes_table q
			 LEFT JOIN $competitors_table c ON c.id = q.competitor_id
			 LEFT JOIN $items_table i ON i.id = q.item_id
			 WHERE q.item_id IS NOT NULL AND q.unit_price IS NOT NULL
			 ORDER BY q.item_id ASC, q.competitor_id ASC, q.quote_date DESC, q.id DESC"
		);

		$matrix = array(); // item_id => ['name'=>, 'rows'=> [competitor_id => {latest, previous}]]
		$seen   = array();

		foreach ( $rows as $r ) {
			$ik = $r->item_id;
			$ck = $r->competitor_id;
			if ( ! isset( $matrix[ $ik ] ) ) {
				$matrix[ $ik ] = array( 'name' => $r->item_name, 'rows' => array() );
			}
			$seen_key = $ik . ':' . $ck;
			$seen[ $seen_key ] = isset( $seen[ $seen_key ] ) ? $seen[ $seen_key ] + 1 : 1;

			if ( 1 === $seen[ $seen_key ] ) {
				$matrix[ $ik ]['rows'][ $ck ] = array(
					'competitor' => $r->competitor_name,
					'latest'     => (float) $r->unit_price,
					'date'       => $r->quote_date,
					'previous'   => null,
				);
			} elseif ( 2 === $seen[ $seen_key ] ) {
				$matrix[ $ik ]['rows'][ $ck ]['previous'] = (float) $r->unit_price;
			}
		}

		foreach ( $matrix as $ik => &$item ) {
			$cheapest = null;
			foreach ( $item['rows'] as $ck => &$row ) {
				$row['trend'] = 'same';
				if ( null !== $row['previous'] ) {
					if ( $row['latest'] > $row['previous'] ) {
						$row['trend'] = 'up';
					} elseif ( $row['latest'] < $row['previous'] ) {
						$row['trend'] = 'down';
					}
				} else {
					$row['trend'] = 'new';
				}
				if ( null === $cheapest || $row['latest'] < $cheapest ) {
					$cheapest = $row['latest'];
				}
			}
			unset( $row );
			foreach ( $item['rows'] as $ck => &$row ) {
				$row['is_cheapest'] = ( $row['latest'] === $cheapest );
			}
			unset( $row );
		}
		unset( $item );

		return $matrix;
	}
}
