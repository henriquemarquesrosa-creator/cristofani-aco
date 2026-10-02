<?php
/**
 * Plugin Name: Cristófani - Remuneração Variável
 * Description: Calculadora de meta e remuneração variável do vendedor externo, a partir do relatório de vendas colado a cada ciclo.
 * Version: 1.1.0
 * Author: Cristófani Aço
 * Text Domain: rv
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RV_VERSION', '1.1.0' );
define( 'RV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once RV_PLUGIN_DIR . 'includes/class-rv-db.php';
require_once RV_PLUGIN_DIR . 'includes/class-rv-activator.php';
require_once RV_PLUGIN_DIR . 'includes/class-rv-calculator.php';
require_once RV_PLUGIN_DIR . 'includes/class-rv-import.php';
require_once RV_PLUGIN_DIR . 'includes/class-rv-admin.php';

register_activation_hook( __FILE__, array( 'Rv_Activator', 'activate' ) );

add_action( 'plugins_loaded', function () {
	Rv_Activator::maybe_upgrade();
	Rv_Admin::init();
} );
