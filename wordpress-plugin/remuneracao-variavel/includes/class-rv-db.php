<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rv_Db {

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'rv_' . $name;
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();

		$sales   = self::table( 'sales' );
		$cycles  = self::table( 'cycles' );
		$targets = self::table( 'targets' );

		$sql = "
		CREATE TABLE $sales (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			doc_number VARCHAR(60) NOT NULL,
			sale_date DATE NOT NULL,
			client_code VARCHAR(40) NOT NULL,
			client_name VARCHAR(255) NOT NULL,
			valor_liq DECIMAL(12,2) NOT NULL,
			imported_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY doc_number (doc_number),
			KEY client_code (client_code),
			KEY sale_date (sale_date)
		) $charset_collate;

		CREATE TABLE $cycles (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			label VARCHAR(60) NOT NULL,
			month1_start DATE NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'aberto',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY month1_start (month1_start)
		) $charset_collate;

		CREATE TABLE $targets (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			cycle_id BIGINT UNSIGNED NOT NULL,
			metric_key VARCHAR(40) NOT NULL,
			month_index TINYINT UNSIGNED NULL,
			title VARCHAR(255) NULL,
			target_value DECIMAL(12,2) NOT NULL DEFAULT 0,
			premio_base DECIMAL(12,2) NOT NULL DEFAULT 0,
			realizado_manual DECIMAL(12,2) NULL,
			valor_override DECIMAL(12,2) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY cycle_id (cycle_id),
			KEY metric_key (metric_key)
		) $charset_collate;
		";

		dbDelta( $sql );
	}

	/* ---------- generic helpers ---------- */

	public static function get_all( $entity, $args = array() ) {
		global $wpdb;
		$table   = self::table( $entity );
		$where   = '';
		if ( ! empty( $args['where'] ) ) {
			$where = 'WHERE ' . $args['where'];
		}
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
