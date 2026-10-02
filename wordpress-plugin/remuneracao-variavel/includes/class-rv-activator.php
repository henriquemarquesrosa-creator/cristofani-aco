<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rv_Activator {

	public static function activate() {
		Rv_Db::create_tables();

		if ( false === get_option( 'rv_settings' ) ) {
			add_option( 'rv_settings', array(
				'vendedor_nome'       => '',
				'limite_abertura'     => 20000,
				'limite_ativo'        => 40000,
				'premio_abertura'     => 1000,
				'premio_aquisicao'    => 1000,
				'premio_base_ativos'  => 2000,
				'premio_fat_bimestre' => 2000,
				'premio_carteira'     => 3000,
			) );
		}
	}
}
