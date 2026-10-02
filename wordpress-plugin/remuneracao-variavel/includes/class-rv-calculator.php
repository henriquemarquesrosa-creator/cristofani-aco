<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All the business rules live here: client ledger analysis (novo cliente, cliente ativo),
 * bimester totals, and the payout formula. Nothing here touches the database schema
 * directly except read-only SELECTs on rv_sales.
 */
class Rv_Calculator {

	/** @var string[] metric_key => tiered? (90%→50% / 100%→proporcional / senão zero) */
	const TIERED_METRICS = array(
		'faturamento_aquisicao'            => true,
		'crescimento_faturamento_bimestre' => true,
		'faturamento_carteira'             => true,
	);

	public static function metric_defs() {
		return array(
			'abertura_novos_clientes'          => array( 'label' => 'Abertura de novos clientes', 'categoria' => 'Negócio Certo', 'vigencia' => 'mensal', 'auto' => true ),
			'faturamento_aquisicao'            => array( 'label' => 'Faturamento na Aquisição', 'categoria' => 'Negócio Certo', 'vigencia' => 'mensal', 'auto' => true ),
			'crescimento_base_ativos'          => array( 'label' => 'Crescimento da Base de Clientes Ativos', 'categoria' => 'Construção do Negócio', 'vigencia' => 'bimestral', 'auto' => true ),
			'crescimento_faturamento_bimestre' => array( 'label' => 'Crescimento do Faturamento no Bimestre', 'categoria' => 'Construção do Negócio', 'vigencia' => 'bimestral', 'auto' => true ),
			'faturamento_carteira'             => array( 'label' => 'Faturamento da Carteira', 'categoria' => 'Resultado', 'vigencia' => 'bimestral', 'auto' => true ),
			'desafio_mes'                      => array( 'label' => 'Desafio do Mês', 'categoria' => 'Desafio', 'vigencia' => 'mensal', 'auto' => false ),
			'desafio_bimestre'                 => array( 'label' => 'Desafio do Bimestre', 'categoria' => 'Desafio', 'vigencia' => 'bimestral', 'auto' => false ),
		);
	}

	/** @return array{month1:array{start:string,end:string,ym:string},month2:array,bimestre:array{start:string,end:string}} */
	public static function cycle_months_from_start( $m1_start ) {
		$m1_end   = date( 'Y-m-t', strtotime( $m1_start ) );
		$m2_start = date( 'Y-m-01', strtotime( $m1_start . ' +1 month' ) );
		$m2_end   = date( 'Y-m-t', strtotime( $m2_start ) );

		return array(
			'month1'   => array( 'start' => $m1_start, 'end' => $m1_end, 'ym' => substr( $m1_start, 0, 7 ) ),
			'month2'   => array( 'start' => $m2_start, 'end' => $m2_end, 'ym' => substr( $m2_start, 0, 7 ) ),
			'bimestre' => array( 'start' => $m1_start, 'end' => $m2_end ),
		);
	}

	public static function cycle_months( $cycle ) {
		return self::cycle_months_from_start( $cycle->month1_start );
	}

	/* ---------------- client ledger analysis ---------------- */

	/**
	 * @return array client_code => ['name'=>, 'first_ym'=>'YYYY-MM', 'months'=>['YYYY-MM'=>total]]
	 */
	public static function client_ledger() {
		global $wpdb;
		$table = Rv_Db::table( 'sales' );
		$rows  = $wpdb->get_results( "SELECT client_code, client_name, sale_date, valor_liq FROM $table ORDER BY client_code ASC, sale_date ASC" );

		$ledger = array();
		foreach ( $rows as $r ) {
			$ym = substr( $r->sale_date, 0, 7 );
			if ( ! isset( $ledger[ $r->client_code ] ) ) {
				$ledger[ $r->client_code ] = array(
					'name'     => $r->client_name,
					'first_ym' => $ym,
					'months'   => array(),
				);
			}
			if ( ! isset( $ledger[ $r->client_code ]['months'][ $ym ] ) ) {
				$ledger[ $r->client_code ]['months'][ $ym ] = 0.0;
			}
			$ledger[ $r->client_code ]['months'][ $ym ] += (float) $r->valor_liq;
		}
		return $ledger;
	}

	/**
	 * @param array $client Entry from client_ledger().
	 * @return string|null 'YYYY-MM' of the month the cumulative revenue since first purchase
	 *                     first reaches the threshold, or null if it never does.
	 */
	public static function crossing_month( $client, $limite ) {
		$cum = 0.0;
		ksort( $client['months'] );
		foreach ( $client['months'] as $ym => $valor ) {
			$cum += $valor;
			if ( $cum >= $limite ) {
				return $ym;
			}
		}
		return null;
	}

	/**
	 * Abertura de novos clientes: quantos clientes cruzaram o limite pela 1ª vez neste mês.
	 */
	public static function abertura_novos_clientes( $ym, $limite ) {
		$ledger = self::client_ledger();
		$count  = 0;
		foreach ( $ledger as $client ) {
			if ( self::crossing_month( $client, $limite ) === $ym ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Faturamento na Aquisição: soma do faturamento, no mês, dos clientes cujo 1º mês de
	 * compra é exatamente este mês (independe de terem atingido o limite).
	 */
	public static function faturamento_aquisicao( $ym ) {
		$ledger = self::client_ledger();
		$total  = 0.0;
		foreach ( $ledger as $client ) {
			if ( $client['first_ym'] === $ym ) {
				$total += $client['months'][ $ym ];
			}
		}
		return $total;
	}

	/* ---------------- bimester totals ---------------- */

	public static function faturamento_periodo( $start, $end ) {
		global $wpdb;
		$table = Rv_Db::table( 'sales' );
		$v     = $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(valor_liq) FROM $table WHERE sale_date BETWEEN %s AND %s",
			$start, $end
		) );
		return (float) $v;
	}

	public static function clientes_ativos_periodo( $start, $end, $limite ) {
		global $wpdb;
		$table = Rv_Db::table( 'sales' );
		$v     = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM (
				SELECT client_code FROM $table
				WHERE sale_date BETWEEN %s AND %s
				GROUP BY client_code
				HAVING SUM(valor_liq) >= %f
			) t",
			$start, $end, $limite
		) );
		return (int) $v;
	}

	/* ---------------- payout rule ---------------- */

	/**
	 * >=100% da meta: proporcional, sem teto (acelerador).
	 * 90–99,99% da meta, só nas 3 métricas "tiered": 50% flat do prêmio-base.
	 * Abaixo disso: zero.
	 */
	public static function payout( $metric_key, $realizado, $meta, $premio_base ) {
		if ( $meta <= 0 ) {
			return 0.0;
		}
		$pct = $realizado / $meta;

		if ( $pct >= 1 ) {
			return $pct * $premio_base;
		}
		if ( ! empty( self::TIERED_METRICS[ $metric_key ] ) && $pct >= 0.9 ) {
			return 0.5 * $premio_base;
		}
		return 0.0;
	}

	/* ---------------- per-cycle computation ---------------- */

	/**
	 * Calcula o Realizado automático (quando aplicável) e o Valor pago de cada linha de
	 * meta do ciclo. Não grava nada — só lê rv_targets + rv_sales e devolve a grade pronta.
	 */
	public static function compute_cycle( $cycle ) {
		$months   = self::cycle_months( $cycle );
		$settings = get_option( 'rv_settings' );
		$limite_abertura = (float) $settings['limite_abertura'];
		$limite_ativo    = (float) $settings['limite_ativo'];

		// Bimestre anterior: calculado sempre a partir da data (2 meses antes), direto dos
		// lançamentos importados — não depende de existir um "ciclo" cadastrado pra ele.
		$prev_m1_start = date( 'Y-m-01', strtotime( $months['month1']['start'] . ' -2 months' ) );
		$prev_months   = self::cycle_months_from_start( $prev_m1_start );

		$faturamento_atual        = self::faturamento_periodo( $months['bimestre']['start'], $months['bimestre']['end'] );
		$faturamento_anterior_calc = self::faturamento_periodo( $prev_months['bimestre']['start'], $prev_months['bimestre']['end'] );

		$ativos_atual         = self::clientes_ativos_periodo( $months['bimestre']['start'], $months['bimestre']['end'], $limite_ativo );
		$ativos_anterior_calc = self::clientes_ativos_periodo( $prev_months['bimestre']['start'], $prev_months['bimestre']['end'], $limite_ativo );

		// Se o histórico anterior não estiver todo importado no sistema, o ciclo pode ter uma
		// base manual (digitada pelo admin a partir dos registros antigos) que prevalece sobre
		// o cálculo automático.
		$faturamento_anterior = null !== $cycle->baseline_faturamento ? (float) $cycle->baseline_faturamento : $faturamento_anterior_calc;
		$ativos_anterior      = null !== $cycle->baseline_ativos ? (int) $cycle->baseline_ativos : $ativos_anterior_calc;

		global $wpdb;
		$table  = Rv_Db::table( 'targets' );
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE cycle_id = %d ORDER BY id ASC", $cycle->id ) );
		$defs   = self::metric_defs();
		$result = array();

		foreach ( $rows as $row ) {
			$def = isset( $defs[ $row->metric_key ] ) ? $defs[ $row->metric_key ] : array( 'label' => $row->metric_key, 'auto' => false );

			$realizado = null;
			if ( $def['auto'] ) {
				switch ( $row->metric_key ) {
					case 'abertura_novos_clientes':
						$ym        = ( 1 === (int) $row->month_index ) ? $months['month1']['ym'] : $months['month2']['ym'];
						$realizado = self::abertura_novos_clientes( $ym, $limite_abertura );
						break;
					case 'faturamento_aquisicao':
						$ym        = ( 1 === (int) $row->month_index ) ? $months['month1']['ym'] : $months['month2']['ym'];
						$realizado = self::faturamento_aquisicao( $ym );
						break;
					case 'crescimento_base_ativos':
						$realizado = $ativos_atual - $ativos_anterior;
						break;
					case 'crescimento_faturamento_bimestre':
						$realizado = $faturamento_atual - $faturamento_anterior;
						break;
					case 'faturamento_carteira':
						$realizado = $faturamento_atual;
						break;
				}
			} else {
				$realizado = null !== $row->realizado_manual ? (float) $row->realizado_manual : 0.0;
			}

			$valor_calculado = self::payout( $row->metric_key, $realizado, (float) $row->target_value, (float) $row->premio_base );
			$valor_final      = null !== $row->valor_override ? (float) $row->valor_override : $valor_calculado;
			$pct              = $row->target_value > 0 ? $realizado / $row->target_value : 0;

			$result[] = array(
				'row'              => $row,
				'label'            => $row->title ? $row->title : $def['label'],
				'categoria'        => $def['categoria'] ?? '',
				'month_index'      => $row->month_index,
				'auto'             => $def['auto'],
				'realizado'        => $realizado,
				'meta'             => (float) $row->target_value,
				'pct'              => $pct,
				'premio_base'      => (float) $row->premio_base,
				'valor_calculado'  => $valor_calculado,
				'valor_final'      => $valor_final,
				'overridden'       => null !== $row->valor_override,
			);
		}

		$total = array_sum( wp_list_pluck( $result, 'valor_final' ) );

		return array(
			'months'                    => $months,
			'faturamento_atual'         => $faturamento_atual,
			'faturamento_anterior'      => $faturamento_anterior,
			'faturamento_anterior_calc' => $faturamento_anterior_calc,
			'ativos_atual'              => $ativos_atual,
			'ativos_anterior'           => $ativos_anterior,
			'ativos_anterior_calc'      => $ativos_anterior_calc,
			'baseline_overridden'       => null !== $cycle->baseline_faturamento || null !== $cycle->baseline_ativos,
			'rows'                      => $result,
			'total'                     => $total,
		);
	}
}
