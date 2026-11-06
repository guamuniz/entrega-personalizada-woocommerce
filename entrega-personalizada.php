<?php
/**
 * Plugin Name: Entrega Personalizada
 * Description: Plugin de entrega por geolocalização com raio configurável para WooCommerce
 * Version: 2.0.0
 * Author: Gustavo Muniz
 * Author URI: https://gusmarketing.thebestdigital.xyz/
 * Text Domain: entrega-personalizada
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 *
 * @package EntregaPersonalizada
 */

// Segurança: bloquear acesso direto
defined( 'ABSPATH' ) || exit;

// Constantes
define( 'EP_VERSION',       '2.0.0' );
define( 'EP_PLUGIN_FILE',   __FILE__ );
define( 'EP_PLUGIN_DIR',    plugin_dir_path( __FILE__ ) );
define( 'EP_PLUGIN_URL',    plugin_dir_url( __FILE__ ) );
define( 'EP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Verificar dependência do WooCommerce
 */
function ep_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo '<strong>Entrega Personalizada</strong> requer o WooCommerce ativo.';
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

/**
 * Carregar arquivos do plugin com segurança
 */
function ep_load_files() {
    $files = array(
        EP_PLUGIN_DIR . 'includes/class-shipping-method.php',
        EP_PLUGIN_DIR . 'public/class-public.php',
        EP_PLUGIN_DIR . 'admin/class-admin.php',
    );

    foreach ( $files as $file ) {
        if ( file_exists( $file ) ) {
            require_once $file;
        } else {
            // Log do arquivo não encontrado
            error_log( 'Entrega Personalizada: Arquivo não encontrado: ' . $file );
        }
    }
}

/**
 * Inicializar plugin
 */
function ep_init() {

    // Verificar WooCommerce
    if ( ! ep_check_woocommerce() ) {
        return;
    }

    // Carregar arquivos
    ep_load_files();

    // Inicializar classes se existirem
    if ( class_exists( 'EP_Public' ) ) {
        new EP_Public();
    }

    if ( class_exists( 'EP_Admin' ) ) {
        new EP_Admin();
    }

    // Tradução
    load_plugin_textdomain(
        'entrega-personalizada',
        false,
        dirname( EP_PLUGIN_BASENAME ) . '/languages/'
    );
}
add_action( 'plugins_loaded', 'ep_init', 20 );

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );



/**
 * Registrar método de envio
 */
function ep_register_shipping_method( $methods ) {
    if ( class_exists( 'EP_Shipping_Method' ) ) {
        $methods['entrega_personalizada'] = 'EP_Shipping_Method';
    }
    return $methods;
}
add_filter( 'woocommerce_shipping_methods', 'ep_register_shipping_method' );


/**
 * Adiciona uma sugestão à política de privacidade da loja.
 */
function ep_add_privacy_policy_content() {
    if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
        return;
    }

    $content = '<h2>Entrega Personalizada para WooCommerce</h2>';
    $content .= '<p>Quando o cliente utiliza a geolocalização ou informa o CEP/endereço para calcular a entrega, o plugin processa coordenadas e/ou endereço para estimar a distância até a loja. Dependendo das configurações, esses dados podem ser enviados ao Google Maps Platform (Geocoding API e Routes API) ou ao serviço Nominatim do OpenStreetMap para geocodificação. Consulte os termos e políticas desses fornecedores.</p>';
    $content .= '<p>O plugin pode armazenar temporariamente coordenadas e resultados de distância em cache e salvar distância e coordenadas associadas ao pedido como metadados para apoiar a operação de entrega. A loja deve avaliar os prazos de retenção e informar os titulares conforme sua política de privacidade.</p>';
    wp_add_privacy_policy_content( 'Entrega Personalizada para WooCommerce', wp_kses_post( $content ) );
}
add_action( 'admin_init', 'ep_add_privacy_policy_content' );

/**
 * Ativação do Plugin
 */
function ep_activate() {
    global $wpdb;

    $table_name      = $wpdb->prefix . 'ep_distance_cache';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        origin_hash varchar(32) NOT NULL,
        destination_hash varchar(32) NOT NULL,
        distance decimal(10,4) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY distance_hash (origin_hash, destination_hash)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Opções padrão (só insere se não existir)
    $defaults = array(
        'ep_store_address'    => '',
        'ep_store_lat'        => '',
        'ep_store_lng'        => '',
        'ep_max_radius'       => '50',
        'ep_fixed_cost'       => '0.00',
        'ep_cost_per_km'      => '0.00',
        'ep_free_shipping_min'=> '0.00',
        'ep_use_geolocation'  => 'yes',
        'ep_google_api_key'   => '',
        'ep_cache_duration'   => '24',
    );

    foreach ( $defaults as $key => $value ) {
        if ( get_option( $key ) === false ) {
            add_option( $key, $value );
        }
    }
}
register_activation_hook( __FILE__, 'ep_activate' );

/**
 * Desativação do Plugin
 */
function ep_deactivate() {
    // Mantém configurações e cache para permitir reativação sem perda de dados.
}
register_deactivation_hook( __FILE__, 'ep_deactivate' );

/**
 * Desinstalação do Plugin
 */
function ep_uninstall() {
    global $wpdb;

    // Remover tabela
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ep_distance_cache" );

    // Remover opções
    $options = array(
        'ep_store_address',
        'ep_store_lat',
        'ep_store_lng',
        'ep_max_radius',
        'ep_fixed_cost',
        'ep_cost_per_km',
        'ep_free_shipping_min',
        'ep_use_geolocation',
        'ep_google_api_key',
        'ep_cache_duration',
    );

    foreach ( $options as $option ) {
        delete_option( $option );
    }
}
register_uninstall_hook( __FILE__, 'ep_uninstall' );