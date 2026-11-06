(function($) {
    'use strict';

    $(document).ready(function() {

        // Botão de Geolocalização
        $('#ep_use_location').on('click', function() {
            const btn = $(this);
            const message = $('#ep_location_message');

            if (!navigator.geolocation) {
                message.html('<span class="ep-error">Geolocalização não suportada pelo seu navegador.</span>');
                return;
            }

            btn.prop('disabled', true).text(ep_public.strings.locating);
            message.html('<span class="ep-info">⏳ ' + ep_public.strings.locating + '</span>');

            navigator.geolocation.getCurrentPosition(
                // Sucesso
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;

                    message.html('<span class="ep-info">⏳ ' + ep_public.strings.calculating + '</span>');

                    $.ajax({
                        url: ep_public.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'ep_set_location',
                            nonce: ep_public.nonce,
                            lat: lat,
                            lng: lng
                        },
                        success: function(response) {
                            if (response.success) {
                                const data = response.data;
                                let msg = '';

                                if (data.free) {
                                    msg = '✅ ' + ep_public.strings.location_success + ' • Distância: ' + data.distance + ' km • <strong>Frete Grátis!</strong>';
                                } else {
                                    msg = '✅ ' + ep_public.strings.location_success + ' • Distância: ' + data.distance + ' km • Frete: R$ ' + data.cost;
                                }

                                message.empty().append($('<span>', { class: 'ep-success' }).text(msg));
                                // Atualizar cálculo de frete do WooCommerce
                                $('body').trigger('update_checkout');
                            } else {
                                const errorMsg = response.data.message || ep_public.strings.outside_radius;
                                message.empty().append($('<span>', { class: 'ep-error' }).text('❌ ' + errorMsg));
                            }
                        },
                        error: function() {
                            message.empty().append($('<span>', { class: 'ep-error' }).text('❌ ' + ep_public.strings.location_error));
                        },
                        complete: function() {
                            btn.prop('disabled', false).html(
                                '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/></svg> Usar minha localização atual'
                            );
                        }
                    });
                },
                // Erro
                function(error) {
                    let errorMsg = ep_public.strings.location_error;

                    if (error.code === error.PERMISSION_DENIED) {
                        errorMsg = ep_public.strings.permission_denied;
                    }

                    message.html('<span class="ep-error">❌ ' + errorMsg + '</span>');
                    btn.prop('disabled', false);
                },
                // Opções
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 300000
                }
            );
        });

        // Calcular frete pelo CEP do checkout
        $(document).on('change', '#billing_postcode, #shipping_postcode', function() {
            const postcode = $(this).val().replace(/[^0-9]/g, '');

            if (postcode.length !== 8) return;

            $.ajax({
                url: ep_public.ajax_url,
                type: 'POST',
                data: {
                    action: 'ep_calculate_by_postcode',
                    nonce: ep_public.nonce,
                    postcode: postcode
                },
                success: function(response) {
                    if (response.success) {
                        $('body').trigger('update_checkout');
                    }
                }
            });
        });

    });

})(jQuery);