<?php
/**
 * Plugin name: Reseller Manager
 * Description: Plugin per Woocomerce per gestire i rivenditori e le scontistiche dedicate
 * Version: 1.0.0
 * Author: Matteo Papparella
 * Author URI: https://www.matcode.dev
 * License: GPLv2 or later
 * Requires plugins: woocommerce
 * Text Domain: mc-reseller-manager
 *
 * @package mc-reseller-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function mpwp_init(){
    $plugin_path = trailingslashit( WP_PLUGIN_DIR ) . 'woocommerce/woocommerce.php';

    if (!in_array( $plugin_path, wp_get_active_and_valid_plugins() )) {
        $message = sprintf(
            /* translators: 1: Plugin name, 2: Required plugin name */
            esc_html__( '"%1$s" necessita che "%2$s" sia installato e attivo per poter funzionare.','mc-reseller-manager'),
            '<strong>' . esc_html__( "Reseller Manager",'mc-reseller-manager') . '</strong>',
            '<strong>' . esc_html__( 'Woocommerce','mc-reseller-manager') . '</strong>'
        );
    
        printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', esc_html($message) );

        if ( is_plugin_active(plugin_basename( __FILE__ ))) {
            if ( isset( $_GET['activate'] ) ) unset( $_GET['activate'] );
            deactivate_plugins(plugin_basename( __FILE__ ));
        }
    }
}


require_once plugin_dir_path(__FILE__) . 'class/class-reseller-manager.php';
$Reseller_Manager = new Reseller_Manager();
$Reseller_Manager::init();

add_action( 'admin_init', 'mpwp_init');
add_action('plugins_loaded', function () {
    load_plugin_textdomain(
        'mc-reseller-manager',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
});
register_activation_hook( __FILE__, ['Reseller_Manager', 'activation'] );
register_deactivation_hook( __FILE__, ['Reseller_Manager', 'deactivation'] );
register_uninstall_hook( __FILE__, ['Reseller_Manager', 'uninstall'] );
