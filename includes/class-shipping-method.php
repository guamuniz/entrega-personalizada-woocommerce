<?php
/**
 * Método de Envio Personalizado
 *
 * @package EntregaPersonalizada
 */

defined( 'ABSPATH' ) || exit;

// Garantir que a classe pai existe antes de declarar
if ( ! class_exists( 'WC_Shipping_Method' ) ) {
    return;
}

class EP_Shipping_Method extends WC_Shipping_Method {

    // ✅ Propriedades declaradas (corrige o erro do PHP 8.2+)
    public float $store_lat;
    public float $store_lng;
    public float $max_radius;
    public float $fixed_cost;
    public float $cost_per_km;
    public float $free_shipping_min;
    public string $google_api_key;
    public string $last_distance_source = 'haversine';

    /**
     * Construtor
     */
    public function __construct( $instance_id = 0 ) {
        $this->id                 = 'entrega_personalizada';
        $this->instance_id        = absint( $instance_id );
        $this->method_title       = __( 'Entrega Personalizada', 'entrega-personalizada' );
        $this->method_description = __( 'Entrega baseada em geolocalização com raio configurável.', 'entrega-personalizada' );
        $this->supports           = array( 'shipping-zones', 'instance-settings' );

        $this->init();
    }

    /**
     * Inicializar
     */
    public function init() {
        $this->init_form_fields();
        $this->init_instance_form_fields();
        $this->init_settings();
        $this->init_instance_settings();

        $this->title             = $this->get_instance_option( 'title', __( 'Entrega Personalizada', 'entrega-personalizada' ) );
        $this->store_lat         = (float) get_option( 'ep_store_lat', 0 );
        $this->store_lng         = (float) get_option( 'ep_store_lng', 0 );
        $this->max_radius        = (float) get_option( 'ep_max_radius', 50 );
        $this->fixed_cost        = (float) get_option( 'ep_fixed_cost', 0 );
        $this->cost_per_km       = (float) get_option( 'ep_cost_per_km', 0 );
        $this->free_shipping_min = (float) get_option( 'ep_free_shipping_min', 0 );
        $this->google_api_key    = (string) get_option( 'ep_google_api_key', '' );

        add_action(
            'woocommerce_update_options_shipping_' . $this->id,
            array( $this, 'process_admin_options' )
        );
    }

    /**
     * Campos da instância
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'title' => array(
                'title'   => __( 'Título', 'entrega-personalizada' ),
                'type'    => 'text',
                'default' => __( 'Entrega Personalizada', 'entrega-personalizada' ),
            ),
        );
    }

    /**
     * Campos específicos da instância adicionada à zona de entrega.
     * As regras de preço e origem continuam sendo configuradas no painel do plugin.
     */
    public function init_instance_form_fields() {
        $this->instance_form_fields = array(
            'title' => array(
                'title'       => __( 'Título exibido no checkout', 'entrega-personalizada' ),
                'type'        => 'text',
                'description' => __( 'Nome que o cliente verá para esta opção de entrega.', 'entrega-personalizada' ),
                'default'     => __( 'Entrega Personalizada', 'entrega-personalizada' ),
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Calcular frete
     */
    public function calculate_shipping( $package = array() ) {

        if ( '' === get_option( 'ep_store_lat', '' ) || '' === get_option( 'ep_store_lng', '' )
            || ! $this->valid_coordinates( $this->store_lat, $this->store_lng ) || ! WC()->session ) {
            return;
        }

        $destination = isset( $package['destination'] ) && is_array( $package['destination'] )
            ? $package['destination'] : array();
        $source = WC()->session->get( 'ep_location_source' );

        // Se o CEP do checkout mudar, invalida coordenadas e distância anteriores.
        if ( 'postcode' === $source ) {
            $current_postcode = preg_replace( '/[^0-9]/', '', (string) ( $destination['postcode'] ?? '' ) );
            $saved_postcode   = (string) WC()->session->get( 'ep_destination_postcode' );
            if ( '' !== $saved_postcode && $current_postcode !== $saved_postcode ) {
                WC()->session->set( 'ep_customer_lat', null );
                WC()->session->set( 'ep_customer_lng', null );
                WC()->session->set( 'ep_delivery_distance', null );
                WC()->session->set( 'ep_location_source', null );
                WC()->session->set( 'ep_destination_postcode', null );
                $source = null;
            }
        }

        if ( 'checkout' === $source ) {
            $current_destination = $this->get_destination_string( $destination );
            $current_hash = md5( $current_destination );
            $saved_hash = (string) WC()->session->get( 'ep_destination_hash' );
            if ( '' !== $saved_hash && $current_hash !== $saved_hash ) {
                WC()->session->set( 'ep_customer_lat', null );
                WC()->session->set( 'ep_customer_lng', null );
                WC()->session->set( 'ep_delivery_distance', null );
                WC()->session->set( 'ep_location_source', null );
                WC()->session->set( 'ep_destination_hash', null );
            }
        }

        $distance = WC()->session->get( 'ep_delivery_distance' );

        if ( null === $distance || false === $distance ) {

            $customer_lat = WC()->session->get( 'ep_customer_lat' );
            $customer_lng = WC()->session->get( 'ep_customer_lng' );

            if ( is_numeric( $customer_lat ) && is_numeric( $customer_lng ) ) {

                $distance = $this->calculate_distance(
                    $this->store_lat,
                    $this->store_lng,
                    floatval( $customer_lat ),
                    floatval( $customer_lng )
                );

            } else {

                $destination_string = $this->get_destination_string( $destination );

                if ( ! empty( $destination_string ) ) {
                    $coords = $this->geocode_address( $destination_string );

                    if ( $coords ) {
                        WC()->session->set( 'ep_customer_lat', $coords['lat'] );
                        WC()->session->set( 'ep_customer_lng', $coords['lng'] );
                        WC()->session->set( 'ep_location_source', 'checkout' );
                        WC()->session->set( 'ep_destination_hash', md5( $destination_string ) );

                        $distance = $this->calculate_distance(
                            $this->store_lat,
                            $this->store_lng,
                            $coords['lat'],
                            $coords['lng']
                        );
                    }
                }
            }

            if ( null !== $distance && false !== $distance ) {
                WC()->session->set( 'ep_delivery_distance', $distance );
                WC()->session->set( 'ep_distance_source', $this->last_distance_source );
            }
        }

        if ( null === $distance || false === $distance || $distance > $this->max_radius ) {
            return;
        }

        $cost = $this->calculate_delivery_cost( $distance );

        $this->add_rate( array(
            'id'        => $this->id . '_' . $this->instance_id,
            'label'     => $this->title,
            'cost'      => $cost,
            'meta_data' => array(
                'distancia_km' => round( $distance, 2 ),
            ),
        ) );
    }

    private function calculate_delivery_cost( $distance ) {
        $cart_total = WC()->cart ? WC()->cart->get_subtotal() : 0;

        if ( $this->free_shipping_min > 0 && $cart_total >= $this->free_shipping_min ) {
            return 0;
        }

        return max( 0, $this->fixed_cost + ( $distance * $this->cost_per_km ) );
    }

    public function calculate_distance( $lat1, $lng1, $lat2, $lng2 ) {
        $lat1 = (float) $lat1; $lng1 = (float) $lng1;
        $lat2 = (float) $lat2; $lng2 = (float) $lng2;
        if ( ! $this->valid_coordinates( $lat1, $lng1 ) || ! $this->valid_coordinates( $lat2, $lng2 ) ) {
            return false;
        }

        // Mantém os caches de percurso e linha reta separados. Assim, uma falha
        // temporária do Google não fica armazenada como se fosse distância por ruas.
        if ( ! empty( $this->google_api_key ) ) {
            $cached_route = $this->get_cached_distance( $lat1, $lng1, $lat2, $lng2, 'google-routes' );
            if ( false !== $cached_route ) {
                $this->last_distance_source = 'google-routes';
                return $cached_route;
            }

            $route_distance = $this->calculate_distance_google( $lat1, $lng1, $lat2, $lng2 );
            if ( false !== $route_distance ) {
                $this->last_distance_source = 'google-routes';
                $this->cache_distance( $lat1, $lng1, $lat2, $lng2, $route_distance, 'google-routes' );
                return $route_distance;
            }
        }

        $cached_straight = $this->get_cached_distance( $lat1, $lng1, $lat2, $lng2, 'haversine' );
        if ( false !== $cached_straight ) {
            $this->last_distance_source = 'haversine';
            return $cached_straight;
        }

        $this->last_distance_source = 'haversine';
        $distance = $this->haversine( $lat1, $lng1, $lat2, $lng2 );
        $this->cache_distance( $lat1, $lng1, $lat2, $lng2, $distance, 'haversine' );
        return $distance;
    }

    public function get_distance_source() {
        return $this->last_distance_source;
    }

    private function valid_coordinates( $lat, $lng ) {
        return is_numeric( $lat ) && is_numeric( $lng )
            && $lat >= -90 && $lat <= 90
            && $lng >= -180 && $lng <= 180;
    }

    private function haversine( $lat1, $lng1, $lat2, $lng2 ) {
        $earth_radius = 6371;

        $dlat = deg2rad( $lat2 - $lat1 );
        $dlng = deg2rad( $lng2 - $lng1 );

        $a = sin( $dlat / 2 ) * sin( $dlat / 2 )
           + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) )
           * sin( $dlng / 2 ) * sin( $dlng / 2 );

        $a = min( 1, max( 0, $a ) );
        $c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

        return $earth_radius * $c;
    }

    /**
     * Calcula a distância por ruas usando a Google Routes API.
     * A antiga Distance Matrix API (Legacy) não deve ser usada em novas instalações.
     */
    private function calculate_distance_google( $lat1, $lng1, $lat2, $lng2 ) {
        $url = 'https://routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix';
        $payload = array(
            'origins' => array(
                array(
                    'waypoint' => array(
                        'location' => array(
                            'latLng' => array(
                                'latitude'  => (float) $lat1,
                                'longitude' => (float) $lng1,
                            ),
                        ),
                    ),
                ),
            ),
            'destinations' => array(
                array(
                    'waypoint' => array(
                        'location' => array(
                            'latLng' => array(
                                'latitude'  => (float) $lat2,
                                'longitude' => (float) $lng2,
                            ),
                        ),
                    ),
                ),
            ),
            'travelMode' => 'DRIVE',
            'routingPreference' => 'TRAFFIC_UNAWARE',
            'units' => 'METRIC',
        );

        $response = wp_remote_post( $url, array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type'      => 'application/json',
                'X-Goog-Api-Key'    => $this->google_api_key,
                'X-Goog-FieldMask'  => 'originIndex,destinationIndex,distanceMeters,status,condition',
            ),
            'body' => wp_json_encode( $payload ),
        ) );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) ) {
            return false;
        }

        // A resposta da Compute Route Matrix é uma lista de elementos.
        $element = isset( $body[0] ) ? $body[0] : $body;
        if ( isset( $element['distanceMeters'] ) && 'ROUTE_EXISTS' === ( $element['condition'] ?? '' ) ) {
            return (float) $element['distanceMeters'] / 1000;
        }

        return false;
    }

    public function geocode_address( $address ) {
        $address = trim( sanitize_text_field( $address ) );
        if ( '' === $address || strlen( $address ) > 250 ) {
            return false;
        }
        $cache_key = 'ep_geo_' . md5( strtolower( $address ) );
        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) && isset( $cached['lat'], $cached['lng'] ) ) {
            return $cached;
        }

        if ( ! empty( $this->google_api_key ) ) {
            $result = $this->geocode_google( $address );
            if ( $result && $this->valid_coordinates( $result['lat'], $result['lng'] ) ) {
                set_transient( $cache_key, $result, DAY_IN_SECONDS );
                return $result;
            }
        }
        $result = $this->geocode_nominatim( $address );
        if ( $result && $this->valid_coordinates( $result['lat'], $result['lng'] ) ) {
            set_transient( $cache_key, $result, DAY_IN_SECONDS );
            return $result;
        }
        return false;
    }

    public function geocode_address_public( $address ) {
        return $this->geocode_address( $address );
    }

    private function geocode_google( $address ) {
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query( array(
            'address' => $address,
            'key'     => $this->google_api_key,
        ) );

        $response = wp_remote_get( $url, array( 'timeout' => 15 ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) { return false; }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['status'], $body['results'][0]['geometry']['location'] ) && 'OK' === $body['status'] ) {
            return array(
                'lat' => $body['results'][0]['geometry']['location']['lat'],
                'lng' => $body['results'][0]['geometry']['location']['lng'],
            );
        }

        return false;
    }

    private function geocode_nominatim( $address ) {
        // O serviço público do Nominatim limita o uso a no máximo 1 requisição/segundo.
        // O cache em geocode_address() reduz consultas repetidas; o bloqueio evita rajadas locais.
        if ( get_transient( 'ep_nominatim_request_lock' ) ) {
            return false;
        }
        set_transient( 'ep_nominatim_request_lock', 1, 2 );

        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query( array(
            'q'              => $address . ', Brasil',
            'format'         => 'json',
            'limit'          => 1,
            'addressdetails' => 1,
        ) );

        $response = wp_remote_get( $url, array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'EntregaPersonalizada/2.0; ' . home_url( '/' ),
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) { return false; }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $body[0]['lat'] ) && isset( $body[0]['lon'] ) ) {
            return array(
                'lat' => floatval( $body[0]['lat'] ),
                'lng' => floatval( $body[0]['lon'] ),
            );
        }

        return false;
    }

    private function get_destination_string( $destination ) {
        $parts = array_filter( array(
            ! empty( $destination['address'] )   ? $destination['address']   : '',
            ! empty( $destination['address_2'] ) ? $destination['address_2'] : '',
            ! empty( $destination['city'] )      ? $destination['city']      : '',
            ! empty( $destination['state'] )     ? $destination['state']     : '',
            ! empty( $destination['postcode'] )  ? $destination['postcode']  : '',
        ) );

        return implode( ', ', $parts );
    }

    private function get_cached_distance( $lat1, $lng1, $lat2, $lng2, $provider = 'haversine' ) {
        global $wpdb;

        $table        = $wpdb->prefix . 'ep_distance_cache';
        $origin_hash  = md5( "{$lat1},{$lng1}|{$provider}" );
        $dest_hash    = md5( "{$lat2},{$lng2}" );
        $cache_hours  = intval( get_option( 'ep_cache_duration', 24 ) );

        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
        if ( $table_exists !== $table ) {
            return false;
        }

        $result = $wpdb->get_var( $wpdb->prepare(
            "SELECT distance FROM {$table}
             WHERE origin_hash = %s
               AND destination_hash = %s
               AND created_at > DATE_SUB(NOW(), INTERVAL %d HOUR)
             LIMIT 1",
            $origin_hash,
            $dest_hash,
            $cache_hours
        ) );

        return ( null !== $result ) ? floatval( $result ) : false;
    }

    private function cache_distance( $lat1, $lng1, $lat2, $lng2, $distance, $provider = 'haversine' ) {
        global $wpdb;

        $table = $wpdb->prefix . 'ep_distance_cache';

        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
        if ( $table_exists !== $table ) {
            return;
        }

        $wpdb->replace( $table, array(
            'origin_hash'      => md5( "{$lat1},{$lng1}|{$provider}" ),
            'destination_hash' => md5( "{$lat2},{$lng2}" ),
            'distance'         => $distance,
            'created_at'       => current_time( 'mysql' ),
        ) );
    }
}