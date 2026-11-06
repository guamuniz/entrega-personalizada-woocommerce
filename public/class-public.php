<?php
/**
 * Classe Pública - Frontend
 *
 * @package EntregaPersonalizada
 */

defined( 'ABSPATH' ) || exit;

class EP_Public {

    public function __construct() {
        add_action( 'wp_enqueue_scripts',                    array( $this, 'enqueue_scripts' ) );
        add_action( 'woocommerce_before_checkout_form',      array( $this, 'render_location_button' ) );
        add_action( 'woocommerce_checkout_process',          array( $this, 'validate_delivery' ) );
        add_action( 'woocommerce_checkout_create_order', array( $this, 'save_delivery_meta' ), 20, 2 );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'save_delivery_meta' ), 20, 1 );
        add_action( 'wp_ajax_ep_set_location',               array( $this, 'ajax_set_location' ) );
        add_action( 'wp_ajax_nopriv_ep_set_location',        array( $this, 'ajax_set_location' ) );
        add_action( 'wp_ajax_ep_calculate_by_postcode',      array( $this, 'ajax_calculate_by_postcode' ) );
        add_action( 'wp_ajax_nopriv_ep_calculate_by_postcode', array( $this, 'ajax_calculate_by_postcode' ) );
        add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'shipping_label' ), 10, 2 );
    }

    /**
     * Scripts públicos
     */
    public function enqueue_scripts() {
        if ( ! is_checkout() && ! is_cart() ) {
            return;
        }

        wp_enqueue_script(
            'ep-public',
            EP_PLUGIN_URL . 'public/public.js',
            array( 'jquery' ),
            EP_VERSION,
            true
        );

        wp_localize_script( 'ep-public', 'ep_public', array(
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'ep_public_nonce' ),
            'use_geolocation' => get_option( 'ep_use_geolocation', 'yes' ),
            'strings'         => array(
                'locating'          => __( 'Obtendo sua localização...', 'entrega-personalizada' ),
                'calculating'       => __( 'Calculando frete...', 'entrega-personalizada' ),
                'location_success'  => __( 'Localização obtida!', 'entrega-personalizada' ),
                'location_error'    => __( 'Erro ao obter localização.', 'entrega-personalizada' ),
                'outside_radius'    => __( 'Fora da área de entrega.', 'entrega-personalizada' ),
                'permission_denied' => __( 'Permissão negada. Informe seu CEP.', 'entrega-personalizada' ),
            ),
        ) );

        wp_enqueue_style(
            'ep-public',
            EP_PLUGIN_URL . 'assets/css/entrega.css',
            array(),
            EP_VERSION
        );
    }

    /**
     * Botão de localização no checkout
     */
    public function render_location_button() {
        if ( get_option( 'ep_use_geolocation' ) !== 'yes' ) {
            return;
        }
        ?>
        <div class="ep-location-wrapper">
            <button type="button" id="ep_use_location" class="ep-btn-location">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/>
                </svg>
                <?php esc_html_e( 'Usar minha localização atual', 'entrega-personalizada' ); ?>
            </button>
            <div id="ep_location_message" class="ep-location-message"></div>
        </div>
        <?php
    }

    /**
     * Validar entrega
     */
    public function validate_delivery() {
        $chosen = WC()->session->get( 'chosen_shipping_methods' );

        if ( ! empty( $chosen ) && strpos( $chosen[0], 'entrega_personalizada' ) !== false ) {
            if ( null === WC()->session->get( 'ep_delivery_distance' ) ) {
                wc_add_notice(
                    __( 'Informe seu CEP para calcular o frete de entrega personalizada.', 'entrega-personalizada' ),
                    'error'
                );
            }
        }
    }

    /**
     * Salvar dados no pedido
     */
    public function save_delivery_meta( $order, $data = array() ) {
        if ( ! is_a( $order, 'WC_Order' ) ) { return; }
        $distance = WC()->session ? WC()->session->get( 'ep_delivery_distance' ) : null;
        $lat      = WC()->session ? WC()->session->get( 'ep_customer_lat' ) : null;
        $lng      = WC()->session ? WC()->session->get( 'ep_customer_lng' ) : null;
        $source   = WC()->session ? WC()->session->get( 'ep_distance_source' ) : null;

        if ( is_numeric( $distance ) && $distance >= 0 ) {
            $order->update_meta_data( '_ep_delivery_distance', round( (float) $distance, 2 ) );
            if ( in_array( $source, array( 'google-routes', 'haversine' ), true ) ) {
                $order->update_meta_data( '_ep_distance_source', $source );
            }
        }
        if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
            $order->update_meta_data( '_ep_customer_lat', (float) $lat );
            $order->update_meta_data( '_ep_customer_lng', (float) $lng );
        }
    }

    /**
     * Label do método com distância
     */
    public function shipping_label( $label, $method ) {
        if ( strpos( $method->id, 'entrega_personalizada' ) !== false ) {
            $distance = WC()->session->get( 'ep_delivery_distance' );
            if ( is_numeric( $distance ) ) {
                $source = WC()->session->get( 'ep_distance_source' );
                $method_label = 'haversine' === $source ? 'linha reta' : 'percurso por ruas';
                $label .= ' <small>(' . number_format( (float) $distance, 1, ',', '.' ) . ' km - ' . esc_html( $method_label ) . ')</small>';
            }
        }
        return $label;
    }

    /**
     * AJAX: Definir localização
     */
    public function ajax_set_location() {
        check_ajax_referer( 'ep_public_nonce', 'nonce' );

        $lat_raw = isset( $_POST['lat'] ) ? wp_unslash( $_POST['lat'] ) : null;
        $lng_raw = isset( $_POST['lng'] ) ? wp_unslash( $_POST['lng'] ) : null;
        if ( ! is_numeric( $lat_raw ) || ! is_numeric( $lng_raw ) ) {
            wp_send_json_error( __( 'Coordenadas inválidas.', 'entrega-personalizada' ) );
        }
        $lat = (float) $lat_raw;
        $lng = (float) $lng_raw;

        if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
            wp_send_json_error( __( 'Coordenadas inválidas.', 'entrega-personalizada' ) );
        }

        WC()->session->set( 'ep_customer_lat',       $lat );
        WC()->session->set( 'ep_customer_lng',       $lng );
        WC()->session->set( 'ep_location_source',   'geolocation' );
        WC()->session->set( 'ep_destination_postcode', null );
        WC()->session->set( 'ep_destination_hash', null );
        WC()->session->set( 'ep_delivery_distance',  null );

        $method      = new EP_Shipping_Method();
        $store_lat = get_option( 'ep_store_lat', '' );
        $store_lng = get_option( 'ep_store_lng', '' );
        if ( '' === $store_lat || '' === $store_lng || ! is_numeric( $store_lat ) || ! is_numeric( $store_lng ) ) {
            wp_send_json_error( array( 'message' => __( 'A loja ainda não configurou suas coordenadas.', 'entrega-personalizada' ) ) );
        }
        $store_lat   = (float) $store_lat;
        $store_lng   = (float) $store_lng;
        $max_radius  = floatval( get_option( 'ep_max_radius', 200 ) );
        $fixed_cost  = floatval( get_option( 'ep_fixed_cost', 10 ) );
        $cost_per_km = floatval( get_option( 'ep_cost_per_km', 0.50 ) );
        $free        = floatval( get_option( 'ep_free_shipping_min', 300 ) );

        $distance = $method->calculate_distance( $store_lat, $store_lng, $lat, $lng );
        WC()->session->set( 'ep_delivery_distance', $distance );
        WC()->session->set( 'ep_distance_source', $method->get_distance_source() );

        if ( false === $distance ) {
            WC()->session->set( 'ep_delivery_distance', null );
            wp_send_json_error( array( 'message' => __( 'Não foi possível calcular a distância. Verifique as coordenadas da loja e tente novamente.', 'entrega-personalizada' ) ) );
        }

        if ( $distance > $max_radius ) {
            WC()->session->set( 'ep_delivery_distance', null );
            wp_send_json_error( array(
                'message'  => sprintf(
                    __( 'Localização a %s km, fora do raio de %s km.', 'entrega-personalizada' ),
                    number_format( $distance, 1, ',', '.' ),
                    number_format( $max_radius, 0, ',', '.' )
                ),
                'distance' => round( $distance, 2 ),
            ) );
        }

        $cart_total = WC()->cart ? WC()->cart->get_subtotal() : 0;
        $cost       = ( $free > 0 && $cart_total >= $free ) ? 0 : ( $fixed_cost + ( $distance * $cost_per_km ) );

        wp_send_json_success( array(
            'distance' => round( $distance, 2 ),
            'cost'     => number_format( $cost, 2, ',', '.' ),
            'free'     => ( 0 === $cost ),
            'mode'     => $method->get_distance_source(),
        ) );
    }

    /**
     * AJAX: Calcular pelo CEP
     */
    public function ajax_calculate_by_postcode() {
        check_ajax_referer( 'ep_public_nonce', 'nonce' );

        $postcode = preg_replace( '/[^0-9]/', '', sanitize_text_field( wp_unslash( $_POST['postcode'] ?? '' ) ) );

        if ( strlen( $postcode ) !== 8 ) {
            WC()->session->set( 'ep_delivery_distance', null );
            wp_send_json_error( __( 'CEP inválido.', 'entrega-personalizada' ) );
        }

        WC()->session->set( 'ep_customer_lat', null );
        WC()->session->set( 'ep_customer_lng', null );
        WC()->session->set( 'ep_delivery_distance', null );
        WC()->session->set( 'ep_location_source', null );
        WC()->session->set( 'ep_destination_postcode', null );

        $method = new EP_Shipping_Method();
        $coords = $method->geocode_address_public( $postcode . ', Brasil' );

        if ( ! $coords ) {
            wp_send_json_error( __( 'CEP não encontrado.', 'entrega-personalizada' ) );
        }

        WC()->session->set( 'ep_customer_lat', $coords['lat'] );
        WC()->session->set( 'ep_customer_lng', $coords['lng'] );
        WC()->session->set( 'ep_location_source', 'postcode' );
        WC()->session->set( 'ep_destination_postcode', $postcode );
        WC()->session->set( 'ep_destination_hash', null );

        $store_lat = get_option( 'ep_store_lat', '' );
        $store_lng = get_option( 'ep_store_lng', '' );
        if ( '' === $store_lat || '' === $store_lng || ! is_numeric( $store_lat ) || ! is_numeric( $store_lng ) ) {
            wp_send_json_error( array( 'message' => __( 'A loja ainda não configurou suas coordenadas.', 'entrega-personalizada' ) ) );
        }
        $store_lat   = (float) $store_lat;
        $store_lng   = (float) $store_lng;
        $max_radius  = floatval( get_option( 'ep_max_radius', 200 ) );
        $fixed_cost  = floatval( get_option( 'ep_fixed_cost', 10 ) );
        $cost_per_km = floatval( get_option( 'ep_cost_per_km', 0.50 ) );
        $free        = floatval( get_option( 'ep_free_shipping_min', 300 ) );

        $distance = $method->calculate_distance( $store_lat, $store_lng, $coords['lat'], $coords['lng'] );
        WC()->session->set( 'ep_delivery_distance', $distance );
        WC()->session->set( 'ep_distance_source', $method->get_distance_source() );

        if ( false === $distance ) {
            WC()->session->set( 'ep_delivery_distance', null );
            wp_send_json_error( array( 'message' => __( 'Não foi possível calcular a distância. Verifique as coordenadas da loja e tente novamente.', 'entrega-personalizada' ) ) );
        }

        if ( $distance > $max_radius ) {
            WC()->session->set( 'ep_delivery_distance', null );
            wp_send_json_error( array(
                'message'  => sprintf(
                    __( 'CEP fora da área. Distância: %s km (máx: %s km).', 'entrega-personalizada' ),
                    number_format( $distance, 1, ',', '.' ),
                    number_format( $max_radius, 0, ',', '.' )
                ),
                'distance' => round( $distance, 2 ),
            ) );
        }

        $cart_total = WC()->cart ? WC()->cart->get_subtotal() : 0;
        $cost       = ( $free > 0 && $cart_total >= $free ) ? 0 : ( $fixed_cost + ( $distance * $cost_per_km ) );

        wp_send_json_success( array(
            'distance' => round( $distance, 2 ),
            'cost'     => number_format( $cost, 2, ',', '.' ),
            'free'     => ( 0 === $cost ),
            'mode'     => $method->get_distance_source(),
        ) );
    }
}