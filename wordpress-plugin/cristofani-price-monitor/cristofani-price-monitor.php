<?php
/**
 * Plugin Name: Cristófani - Monitor de Preços
 * Description: Cadastro de concorrentes e itens, geração de rascunhos de mensagem por persona, leitura automática de orçamentos em PDF e painel de comparação de preços. O envio das mensagens é sempre manual, feito por uma pessoa via WhatsApp.
 * Version: 1.0.0
 * Author: Cristófani Aço
 * Text Domain: cpm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CPM_VERSION', '1.0.0' );
define( 'CPM_PLUGIN_FILE', __FILE__ );
define( 'CPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once CPM_PLUGIN_DIR . 'includes/class-cpm-db.php';
require_once CPM_PLUGIN_DIR . 'includes/class-cpm-activator.php';
require_once CPM_PLUGIN_DIR . 'includes/class-cpm-anthropic.php';
require_once CPM_PLUGIN_DIR . 'includes/class-cpm-cron.php';
require_once CPM_PLUGIN_DIR . 'includes/class-cpm-admin.php';

register_activation_hook( __FILE__, array( 'Cpm_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Cpm_Cron', 'deactivate' ) );

add_action( 'plugins_loaded', function () {
	Cpm_Cron::init();
	Cpm_Admin::init();
} );
