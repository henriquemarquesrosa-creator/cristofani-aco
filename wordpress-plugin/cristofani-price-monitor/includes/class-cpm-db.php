<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Table creation and small CRUD helpers shared by the admin screens and the cron job.
 * Kept as plain array-based helpers (no ORM) since every entity here is a flat row.
 */
class Cpm_Db {

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'cpm_' . $name;
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();

		$competitors = self::table( 'competitors' );
		$items       = self::table( 'items' );
		$personas    = self::table( 'personas' );
		$rounds      = self::table( 'rounds' );
		$quotes      = self::table( 'quotes' );

		$sql = "
		CREATE TABLE $competitors (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			phone VARCHAR(40) NOT NULL,
			interval_days INT UNSIGNED NOT NULL DEFAULT 30,
			last_round_at DATETIME NULL,
			active TINYINT(1) NOT NULL DEFAULT 1,
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;

		CREATE TABLE $items (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			spec VARCHAR(255) NULL,
			unit VARCHAR(30) NOT NULL DEFAULT 'un',
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;

		CREATE TABLE $personas (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			voice TEXT NOT NULL,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;

		CREATE TABLE $rounds (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			competitor_id BIGINT UNSIGNED NOT NULL,
			persona_id BIGINT UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			items_json LONGTEXT NULL,
			draft_message LONGTEXT NULL,
			scheduled_at DATETIME NOT NULL,
			sent_at DATETIME NULL,
			received_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY competitor_id (competitor_id),
			KEY status (status)
		) $charset_collate;

		CREATE TABLE $quotes (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			round_id BIGINT UNSIGNED NULL,
			competitor_id BIGINT UNSIGNED NOT NULL,
			item_id BIGINT UNSIGNED NULL,
			item_name_raw VARCHAR(255) NOT NULL,
			quantity DECIMAL(12,2) NULL,
			unit VARCHAR(30) NULL,
			unit_price DECIMAL(12,2) NULL,
			total_price DECIMAL(12,2) NULL,
			quote_date DATE NULL,
			source_file VARCHAR(255) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY competitor_id (competitor_id),
			KEY item_id (item_id)
		) $charset_collate;
		";

		dbDelta( $sql );
	}

	/* ---------- generic helpers ---------- */

	public static function get_all( $entity, $args = array() ) {
		global $wpdb;
		$table   = self::table( $entity );
		$where   = ! empty( $args['active_only'] ) ? 'WHERE active = 1' : '';
		$orderby = ! empty( $args['orderby'] ) ? 'ORDER BY ' . esc_sql( $args['orderby'] ) : 'ORDER BY id DESC';
		return $wpdb->get_results( "SELECT * FROM $table $where $orderby" );
	}

	public static function get( $entity, $id ) {
		global $wpdb;
		$table = self::table( $entity );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
	}

	public static function insert( $entity, $data, $formats = null ) {
		global $wpdb;
		$wpdb->insert( self::table( $entity ), $data, $formats );
		return $wpdb->insert_id;
	}

	public static function update( $entity, $id, $data, $formats = null ) {
		global $wpdb;
		return $wpdb->update( self::table( $entity ), $data, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	public static function delete( $entity, $id ) {
		global $wpdb;
		return $wpdb->delete( self::table( $entity ), array( 'id' => $id ), array( '%d' ) );
	}
}
