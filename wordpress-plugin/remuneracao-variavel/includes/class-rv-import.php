<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses the tab-separated text pasted straight from Excel (the "Relatório de Vendas"
 * export) into clean rows ready for rv_sales. Only reads DATA, DOCUMENTO, CLIENTE and
 * VR. LIQ. — every other column from the original report is ignored.
 */
class Rv_Import {

	private static function normalize_header( $text ) {
		$text = remove_accents( $text );
		$text = strtoupper( $text );
		return preg_replace( '/[^A-Z0-9]/', '', $text );
	}

	private static function find_columns( $header_cells ) {
		$map = array();
		foreach ( $header_cells as $i => $cell ) {
			$n = self::normalize_header( $cell );
			if ( 'DATA' === $n && ! isset( $map['date'] ) ) {
				$map['date'] = $i;
			} elseif ( 'DOCUMENTO' === $n && ! isset( $map['doc'] ) ) {
				$map['doc'] = $i;
			} elseif ( 'CLIENTE' === $n && ! isset( $map['client'] ) ) {
				$map['client'] = $i;
			} elseif ( in_array( $n, array( 'VRLIQ', 'VALORLIQUIDO', 'VLIQUIDO', 'VRLIQUIDO' ), true ) && ! isset( $map['valor'] ) ) {
				$map['valor'] = $i;
			}
		}
		return $map;
	}

	private static function parse_date( $raw ) {
		$raw = trim( $raw );
		foreach ( array( 'd/m/Y', 'Y-m-d', 'd-m-Y' ) as $fmt ) {
			$d = DateTime::createFromFormat( $fmt, $raw );
			if ( $d && $d->format( $fmt ) === $raw ) {
				return $d->format( 'Y-m-d' );
			}
		}
		return null;
	}

	private static function parse_valor( $raw ) {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return null;
		}
		$raw = str_replace( array( 'R$', ' ' ), '', $raw );
		// formato brasileiro: milhar com ponto, decimal com vírgula
		$raw = str_replace( '.', '', $raw );
		$raw = str_replace( ',', '.', $raw );
		if ( ! is_numeric( $raw ) ) {
			return null;
		}
		return (float) $raw;
	}

	/**
	 * @param string $raw_text Texto colado (TSV) pelo usuário.
	 * @return array{rows: array, skipped: array, columns_found: array}|array{error: string}
	 */
	public static function parse( $raw_text ) {
		$lines = preg_split( '/\r\n|\r|\n/', trim( $raw_text ) );
		$lines = array_values( array_filter( $lines, function ( $l ) { return '' !== trim( $l ); } ) );

		if ( empty( $lines ) ) {
			return array( 'error' => 'Nada para importar — cole o relatório (com a linha de cabeçalho) e tente de novo.' );
		}

		$header_cells = explode( "\t", $lines[0] );
		$columns      = self::find_columns( $header_cells );

		if ( ! isset( $columns['date'], $columns['client'], $columns['valor'] ) ) {
			return array( 'error' => 'Não encontrei as colunas DATA, CLIENTE e VR. LIQ. na primeira linha. Cole o relatório incluindo a linha de cabeçalho, igual sai do Excel (Ctrl+C na faixa de células, incluindo o título das colunas).' );
		}

		$rows    = array();
		$skipped = array();

		for ( $i = 1; $i < count( $lines ); $i++ ) {
			$cells = explode( "\t", $lines[ $i ] );

			$date_raw   = $cells[ $columns['date'] ] ?? '';
			$client_raw = $cells[ $columns['client'] ] ?? '';
			$valor_raw  = $cells[ $columns['valor'] ] ?? '';
			$doc_raw    = isset( $columns['doc'] ) ? ( $cells[ $columns['doc'] ] ?? '' ) : '';

			if ( self::normalize_header( $date_raw ) === 'DATA' ) {
				continue; // cabeçalho repetido no meio do bloco colado
			}
			if ( '' === trim( $date_raw ) && '' === trim( $client_raw ) ) {
				continue; // linha em branco
			}

			$date  = self::parse_date( $date_raw );
			$valor = self::parse_valor( $valor_raw );

			if ( ! $date || null === $valor || '' === trim( $client_raw ) ) {
				$skipped[] = array( 'line' => $i + 1, 'raw' => $lines[ $i ] );
				continue;
			}

			$client_code = trim( $client_raw );
			$client_name = trim( $client_raw );
			if ( false !== strpos( $client_raw, '-' ) ) {
				list( $code, $name ) = explode( '-', $client_raw, 2 );
				$client_code = trim( $code );
				$client_name = trim( $name );
			}

			$doc_number = trim( $doc_raw );
			if ( '' === $doc_number ) {
				// sem coluna de documento: gera uma chave estável pra evitar duplicar a linha
				$doc_number = 'auto-' . md5( $date . '|' . $client_code . '|' . $valor_raw . '|' . $i );
			}

			$rows[] = array(
				'doc_number'  => $doc_number,
				'sale_date'   => $date,
				'client_code' => $client_code,
				'client_name' => $client_name,
				'valor_liq'   => $valor,
			);
		}

		return array(
			'rows'          => $rows,
			'skipped'       => $skipped,
			'columns_found' => $columns,
		);
	}

	/**
	 * Grava as linhas já parseadas, ignorando (via INSERT IGNORE) documentos já importados antes.
	 * @return array{inserted:int, duplicated:int}
	 */
	public static function commit( $rows ) {
		global $wpdb;
		$table     = Rv_Db::table( 'sales' );
		$inserted  = 0;
		$duplicated = 0;
		$now       = current_time( 'mysql' );

		foreach ( $rows as $r ) {
			$ok = $wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO $table (doc_number, sale_date, client_code, client_name, valor_liq, imported_at) VALUES (%s,%s,%s,%s,%f,%s)",
				$r['doc_number'], $r['sale_date'], $r['client_code'], $r['client_name'], $r['valor_liq'], $now
			) );
			if ( $ok ) {
				$inserted++;
			} else {
				$duplicated++;
			}
		}

		return array( 'inserted' => $inserted, 'duplicated' => $duplicated );
	}
}
