<?php
/**
 * Classe de Administração
 *
 * @package EntregaPersonalizada
 */

defined( 'ABSPATH' ) || exit;

class EP_Admin {

    public function __construct() {
        add_action( 'admin_menu',              array( $this, 'add_menu' ) );
        add_action( 'admin_init',              array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts',   array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_ep_test_address', array( $this, 'ajax_test_address' ) );
        add_action( 'wp_ajax_ep_clear_cache',  array( $this, 'ajax_clear_cache' ) );
        add_action( 'wp_ajax_ep_get_coordinates', array( $this, 'ajax_get_coordinates' ) );
    }

    /**
     * Menu no painel
     */
    public function add_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Entrega Personalizada', 'entrega-personalizada' ),
            __( 'Entrega Personalizada', 'entrega-personalizada' ),
            'manage_woocommerce',
            'entrega-personalizada',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Registrar settings
     */
    public function register_settings() {
        $sanitizers = array(
            'ep_store_address'     => 'sanitize_text_field',
            'ep_store_lat'         => array( $this, 'sanitize_latitude' ),
            'ep_store_lng'         => array( $this, 'sanitize_longitude' ),
            'ep_max_radius'        => array( $this, 'sanitize_radius' ),
            'ep_fixed_cost'        => array( $this, 'sanitize_amount' ),
            'ep_cost_per_km'       => array( $this, 'sanitize_amount' ),
            'ep_free_shipping_min' => array( $this, 'sanitize_amount' ),
            'ep_use_geolocation'   => array( $this, 'sanitize_checkbox' ),
            'ep_google_api_key'    => 'sanitize_text_field',
            'ep_cache_duration'    => array( $this, 'sanitize_cache_duration' ),
        );

        foreach ( $sanitizers as $option => $callback ) {
            register_setting( 'ep_settings_group', $option, array( 'sanitize_callback' => $callback ) );
        }
    }

    /** Sanitiza latitude dentro dos limites geográficos. */
    public function sanitize_latitude( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) { return ''; }
        $value = str_replace( ',', '.', $value );
        if ( ! is_numeric( $value ) ) { return ''; }
        $value = (float) $value;
        return ( $value >= -90 && $value <= 90 ) ? (string) $value : '';
    }

    /** Sanitiza longitude dentro dos limites geográficos. */
    public function sanitize_longitude( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) { return ''; }
        $value = str_replace( ',', '.', $value );
        if ( ! is_numeric( $value ) ) { return ''; }
        $value = (float) $value;
        return ( $value >= -180 && $value <= 180 ) ? (string) $value : '';
    }

    public function sanitize_radius( $value ) {
        return (string) min( 1000, max( 1, absint( $value ) ) );
    }

    public function sanitize_amount( $value ) {
        $value = str_replace( ',', '.', (string) $value );
        return (string) max( 0, (float) $value );
    }

    public function sanitize_checkbox( $value ) {
        return ( 'yes' === $value ) ? 'yes' : 'no';
    }

    public function sanitize_cache_duration( $value ) {
        return (string) min( 720, max( 1, absint( $value ) ) );
    }

    /**
     * Scripts do admin
     */
    public function enqueue_scripts( $hook ) {
        if ( 'woocommerce_page_entrega-personalizada' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'ep-admin',
            EP_PLUGIN_URL . 'admin/admin.js',
            array( 'jquery' ),
            EP_VERSION,
            true
        );

        wp_localize_script( 'ep-admin', 'ep_admin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ep_admin_nonce' ),
            'strings'  => array(
                'testing'        => __( 'Testando...', 'entrega-personalizada' ),
                'clearing'       => __( 'Limpando...', 'entrega-personalizada' ),
                'cache_cleared'  => __( 'Cache limpo com sucesso!', 'entrega-personalizada' ),
                'error'          => __( 'Erro ao processar requisição.', 'entrega-personalizada' ),
                'within_radius'  => __( '✅ Endereço dentro do raio de entrega!', 'entrega-personalizada' ),
                'outside_radius' => __( '❌ Endereço fora do raio de entrega!', 'entrega-personalizada' ),
            ),
        ) );

        wp_enqueue_style(
            'ep-admin',
            EP_PLUGIN_URL . 'assets/css/entrega.css',
            array(),
            EP_VERSION
        );
    }

    /**
     * Página de configurações
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Acesso negado.', 'entrega-personalizada' ) );
        }

        global $wpdb;
        $count = 0;
        $table = $wpdb->prefix . 'ep_distance_cache';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
            $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        }

        $fixed_cost  = get_option( 'ep_fixed_cost', '10.00' );
        $cost_per_km = get_option( 'ep_cost_per_km', '0.50' );
        $exemplo     = floatval( $fixed_cost ) + ( 10 * floatval( $cost_per_km ) );
        ?>
        <div class="wrap ep-admin-wrap">

            <h1>
                <span class="dashicons dashicons-location"></span>
                <?php esc_html_e( 'Entrega Personalizada', 'entrega-personalizada' ); ?>
            </h1>

            <?php settings_errors( 'ep_settings' ); ?>

            <nav class="nav-tab-wrapper ep-tabs">
                <a href="#tab-geral"     class="nav-tab nav-tab-active"><?php esc_html_e( 'Configurações Gerais', 'entrega-personalizada' ); ?></a>
                <a href="#tab-custos"    class="nav-tab"><?php esc_html_e( 'Custos de Entrega', 'entrega-personalizada' ); ?></a>
                <a href="#tab-avancado"  class="nav-tab"><?php esc_html_e( 'Avançado', 'entrega-personalizada' ); ?></a>
                <a href="#tab-simulador" class="nav-tab"><?php esc_html_e( 'Simulador', 'entrega-personalizada' ); ?></a>
            </nav>

            <form method="post" action="options.php">
                <?php settings_fields( 'ep_settings_group' ); ?>

                <!-- GERAL -->
                <div id="tab-geral" class="ep-tab-content active">
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Endereço da Loja', 'entrega-personalizada' ); ?></th>
                            <td>
                                <input type="text"
                                    name="ep_store_address"
                                    id="ep_store_address"
                                    value="<?php echo esc_attr( get_option( 'ep_store_address' ) ); ?>"
                                    class="regular-text"
                                />
                                <button type="button" id="ep_get_coords" class="button">
                                    <?php esc_html_e( 'Obter Coordenadas', 'entrega-personalizada' ); ?>
                                </button>
                                <p class="description">
                                    <?php esc_html_e( 'Informe o endereço completo da sua loja, incluindo cidade e estado.', 'entrega-personalizada' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Latitude', 'entrega-personalizada' ); ?></th>
                            <td>
                                <input type="text"
                                    name="ep_store_lat"
                                    id="ep_store_lat"
                                    value="<?php echo esc_attr( get_option( 'ep_store_lat' ) ); ?>"
                                    class="small-text"
                                />
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Longitude', 'entrega-personalizada' ); ?></th>
                            <td>
                                <input type="text"
                                    name="ep_store_lng"
                                    id="ep_store_lng"
                                    value="<?php echo esc_attr( get_option( 'ep_store_lng' ) ); ?>"
                                    class="small-text"
                                />
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Raio Máximo (km)', 'entrega-personalizada' ); ?></th>
                            <td>
                                <input type="number"
                                    name="ep_max_radius"
                                    value="<?php echo esc_attr( get_option( 'ep_max_radius', 200 ) ); ?>"
                                    min="1" max="1000" step="1"
                                    class="small-text"
                                /> km
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Usar Geolocalização', 'entrega-personalizada' ); ?></th>
                            <td>
                                <label>
                                    <input type="hidden" name="ep_use_geolocation" value="no" />
                                    <input type="checkbox"
                                        name="ep_use_geolocation"
                                        value="yes"
                                        <?php checked( get_option( 'ep_use_geolocation' ), 'yes' ); ?>
                                    />
                                    <?php esc_html_e( 'Permitir que o cliente use sua localização atual', 'entrega-personalizada' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- CUSTOS -->
                <div id="tab-custos" class="ep-tab-content">
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Custo Fixo (R$)', 'entrega-personalizada' ); ?></th>
                            <td>
                                <input type="number"
                                    name="ep_fixed_cost"
                                    id="ep_fixed_cost"
                                    value="<?php echo esc_attr( $fixed_cost ); ?>"
                                    min="0" step="0.01"
                                    class="small-text"
                                />
                                <p class="description"><?php esc_html_e( 'Valor base cobrado em toda entrega.', 'entrega-personalizada' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Custo por Km (R$)', 'entrega-personalizada' ); ?></th>
                            <td>
                                <input type="number"
                                    name="ep_cost_per_km"
                                    id="ep_cost_per_km"
                                    value="<?php echo esc_attr( $cost_per_km ); ?>"
                                    min="0" step="0.01"
                                    class="small-text"
                                />
                                <p class="description"><?php esc_html_e( 'Valor cobrado por quilômetro.', 'entrega-personalizada' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Frete Grátis a partir de (R$)', 'entrega-personalizada' ); ?></th>
                            <td>
                                <input type="number"
                                    name="ep_free_shipping_min"
                                    value="<?php echo esc_attr( get_option( 'ep_free_shipping_min', '300.00' ) ); ?>"
                                    min="0" step="0.01"
                                    class="small-text"
                                />
                                <p class="description"><?php esc_html_e( 'Use 0 para desativar.', 'entrega-personalizada' ); ?></p>
                            </td>
                        </tr>
                    </table>

                    <div class="ep-card">
                        <h3><?php esc_html_e( 'Pré-visualização do Cálculo', 'entrega-personalizada' ); ?></h3>
                        <p><?php esc_html_e( 'Fórmula: Custo Fixo + (Distância × Custo/km)', 'entrega-personalizada' ); ?></p>
                        <code id="ep_formula_preview">
                            R$ <?php echo esc_html( number_format( $fixed_cost, 2, ',', '.' ) ); ?> +
                            (10 km × R$ <?php echo esc_html( number_format( $cost_per_km, 2, ',', '.' ) ); ?>) =
                            R$ <?php echo esc_html( number_format( $exemplo, 2, ',', '.' ) ); ?>
                        </code>
                    </div>
                </div>

                <!-- AVANÇADO -->
                <div id="tab-avancado" class="ep-tab-content">
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Google Maps API Key', 'entrega-personalizada' ); ?></th>
                            <td>
                                <input type="password"
                                    name="ep_google_api_key"
                                    value="<?php echo esc_attr( get_option( 'ep_google_api_key' ) ); ?>"
                                    class="regular-text"
                                    autocomplete="new-password"
                                />
                                <p class="description">
                                    <?php esc_html_e( 'Opcional. Com ela, calcula o percurso por ruas usando Google Routes API. Sem ela, utiliza distância em linha reta (Haversine).', 'entrega-personalizada' ); ?>
                                    <a href="https://developers.google.com/maps/documentation/routes/get-api-key" target="_blank">
                                        <?php esc_html_e( 'Obter chave', 'entrega-personalizada' ); ?>
                                    </a>
                                </p>
                                <p class="description"><?php esc_html_e( 'A geocodificação alternativa utiliza o Nominatim do OpenStreetMap, sujeito à política de uso e disponibilidade. Não é indicado para alto volume. Dados © OpenStreetMap contributors.', 'entrega-personalizada' ); ?> <a href="https://operations.osmfoundation.org/policies/nominatim/" target="_blank" rel="noopener noreferrer">Nominatim Usage Policy</a>.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Cache (horas)', 'entrega-personalizada' ); ?></th>
                            <td>
                                <input type="number"
                                    name="ep_cache_duration"
                                    value="<?php echo esc_attr( get_option( 'ep_cache_duration', 24 ) ); ?>"
                                    min="1" max="720"
                                    class="small-text"
                                />
                            </td>
                        </tr>
                    </table>

                    <div class="ep-card">
                        <h3><?php esc_html_e( 'Cache de Distâncias', 'entrega-personalizada' ); ?></h3>
                        <p>
                            <?php printf(
                                esc_html__( 'Registros em cache: %d', 'entrega-personalizada' ),
                                $count
                            ); ?>
                        </p>
                        <button type="button" id="ep_clear_cache" class="button button-secondary">
                            <?php esc_html_e( 'Limpar Cache', 'entrega-personalizada' ); ?>
                        </button>
                        <span id="ep_cache_message"></span>
                    </div>
                </div>

                <?php submit_button( __( 'Salvar Configurações', 'entrega-personalizada' ) ); ?>
            </form>

            <!-- SIMULADOR (fora do form) -->
            <div id="tab-simulador" class="ep-tab-content">
                <div class="ep-card">
                    <h3><?php esc_html_e( 'Simular Entrega', 'entrega-personalizada' ); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Endereço de Destino', 'entrega-personalizada' ); ?></th>
                            <td>
                                <input type="text"
                                    id="ep_test_address"
                                    class="regular-text"
                                    placeholder="<?php esc_attr_e( 'Ex: Av. Paulista, 1000 - São Paulo / SP', 'entrega-personalizada' ); ?>"
                                />
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Valor dos Produtos (R$)', 'entrega-personalizada' ); ?></th>
                            <td>
                                <input type="number" id="ep_test_cart_total" class="small-text" min="0" step="0.01" value="0" />
                                <p class="description"><?php esc_html_e( 'Informe o valor do pedido para testar a regra de frete grátis.', 'entrega-personalizada' ); ?></p>
                                <button type="button" id="ep_test_delivery" class="button button-primary">
                                    <?php esc_html_e( 'Simular', 'entrega-personalizada' ); ?>
                                </button>
                            </td>
                        </tr>
                    </table>
                    <div id="ep_test_result" class="ep-test-result" style="display:none;"></div>
                </div>
            </div>

        </div>
        <?php
    }

    /**
     * AJAX: Testar endereço
     */
    public function ajax_test_address() {
        check_ajax_referer( 'ep_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Acesso negado.' );
        }

        $address = sanitize_text_field( wp_unslash( $_POST['address'] ?? '' ) );

        if ( empty( $address ) ) {
            wp_send_json_error( 'Endereço não informado.' );
        }

        $method = new EP_Shipping_Method();
        $coords = $method->geocode_address_public( $address );

        if ( ! $coords ) {
            wp_send_json_error( 'Não foi possível localizar o endereço.' );
        }

        $store_lat = get_option( 'ep_store_lat', '' );
        $store_lng = get_option( 'ep_store_lng', '' );
        if ( '' === $store_lat || '' === $store_lng || ! is_numeric( $store_lat ) || ! is_numeric( $store_lng ) ) {
            wp_send_json_error( __( 'Configure as coordenadas da loja antes de simular uma entrega.', 'entrega-personalizada' ) );
        }
        $store_lat   = (float) $store_lat;
        $store_lng   = (float) $store_lng;
        $max_radius  = floatval( get_option( 'ep_max_radius', 50 ) );
        $fixed_cost  = (float) get_option( 'ep_fixed_cost', 0 );
        $cost_per_km = (float) get_option( 'ep_cost_per_km', 0 );
        $free_min    = (float) get_option( 'ep_free_shipping_min', 0 );
        $cart_total_raw = isset( $_POST['cart_total'] ) ? str_replace( ',', '.', (string) wp_unslash( $_POST['cart_total'] ) ) : '0';
        $cart_total = is_numeric( $cart_total_raw ) ? max( 0, (float) $cart_total_raw ) : 0;

        $distance = $method->calculate_distance( $store_lat, $store_lng, $coords['lat'], $coords['lng'] );
        if ( false === $distance ) {
            wp_send_json_error( __( 'Não foi possível calcular a distância.', 'entrega-personalizada' ) );
        }
        $within_radius = $distance <= $max_radius;
        $free = $within_radius && $free_min > 0 && $cart_total >= $free_min;
        $cost = $within_radius ? ( $free ? 0 : max( 0, $fixed_cost + ( $distance * $cost_per_km ) ) ) : 0;

        wp_send_json_success( array(
            'distance'      => round( $distance, 2 ),
            'within_radius' => $within_radius,
            'cost'          => number_format( $cost, 2, ',', '.' ),
            'free'          => $free,
            'mode'          => $method->get_distance_source(),
            'coords'        => $coords,
        ) );
    }

    /**
     * AJAX: Limpar cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer( 'ep_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Acesso negado.' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ep_distance_cache';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
        if ( $exists === $table ) {
            $wpdb->query( "TRUNCATE TABLE `{$table}`" );
        }

        wp_send_json_success( array( 'message' => 'Cache limpo.' ) );
    }

    /**
     * AJAX: Obter coordenadas
     */
    public function ajax_get_coordinates() {
        check_ajax_referer( 'ep_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Acesso negado.' );
        }

        $address = sanitize_text_field( wp_unslash( $_POST['address'] ?? '' ) );

        if ( empty( $address ) ) {
            wp_send_json_error( 'Endereço não informado.' );
        }

        $method = new EP_Shipping_Method();
        $coords = $method->geocode_address_public( $address );

        if ( ! $coords ) {
            wp_send_json_error( 'Não foi possível localizar o endereço.' );
        }

        wp_send_json_success( $coords );
    }
}