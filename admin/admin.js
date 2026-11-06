(function($) {
    'use strict';

    $(document).ready(function() {

        function escapeHtml(value) {
            return $('<div>').text(value == null ? '' : String(value)).html();
        }

        // =============================================
        // SISTEMA DE TABS
        // =============================================
        $('.ep-tabs .nav-tab').on('click', function(e) {
            e.preventDefault();
            const target = $(this).attr('href');

            $('.ep-tabs .nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $('.ep-tab-content').removeClass('active');
            $(target).addClass('active');
        });

        // =============================================
        // OBTER COORDENADAS DO ENDEREÇO DA LOJA
        // =============================================
        $('#ep_get_coords').on('click', function() {
            const address = $('#ep_store_address').val().trim();
            const btn     = $(this);

            if (!address) {
                alert('Por favor, insira o endereço da loja.');
                return;
            }

            btn.prop('disabled', true).text('Buscando...');

            $.ajax({
                url:  ep_admin.ajax_url,
                type: 'POST',
                data: {
                    action:  'ep_get_coordinates',
                    nonce:   ep_admin.nonce,
                    address: address
                },
                success: function(response) {
                    if (response.success) {
                        $('#ep_store_lat').val(response.data.lat);
                        $('#ep_store_lng').val(response.data.lng);
                        alert(
                            '✅ Coordenadas obtidas com sucesso!\n' +
                            'Latitude: '  + response.data.lat + '\n' +
                            'Longitude: ' + response.data.lng
                        );
                    } else {
                        alert('❌ Erro: ' + response.data);
                    }
                },
                error: function() {
                    alert('❌ ' + ep_admin.strings.error);
                },
                complete: function() {
                    btn.prop('disabled', false).text('Obter Coordenadas');
                }
            });
        });

        // =============================================
        // SIMULADOR DE ENTREGA
        // =============================================
        $('#ep_test_delivery').on('click', function() {
            const address = $('#ep_test_address').val().trim();
            const btn     = $(this);
            const result  = $('#ep_test_result');

            if (!address) {
                alert('Por favor, insira um endereço para testar.');
                return;
            }

            btn.prop('disabled', true).text(ep_admin.strings.testing);
            result.hide().html('');

            $.ajax({
                url:  ep_admin.ajax_url,
                type: 'POST',
                data: {
                    action:  'ep_test_address',
                    nonce:   ep_admin.nonce,
                    address: address,
                    cart_total: ($('#ep_test_cart_total').val() || '0').replace(',', '.')
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        let html   = '';

                        if (data.within_radius) {
                            html += '<div class="ep-result-success">';
                            html += '<p><strong>' + ep_admin.strings.within_radius + '</strong></p>';
                            html += '<ul>';
                            html += '<li><strong>Distância:</strong> '          + escapeHtml(data.distance) + ' km</li>';
                            html += '<li><strong>Custo estimado:</strong> ' + (data.free ? '<strong>Frete grátis</strong>' : 'R$ ' + escapeHtml(data.cost)) + '</li>';
                            html += '<li><strong>Tipo de cálculo:</strong> ' + (data.mode === 'google-routes' ? 'Percurso por ruas (Google Routes)' : 'Linha reta (Haversine)') + '</li>';
                            html += '<li><strong>Latitude:</strong> '           + escapeHtml(data.coords.lat) + '</li>';
                            html += '<li><strong>Longitude:</strong> '          + escapeHtml(data.coords.lng) + '</li>';
                            html += '</ul>';
                            html += '</div>';
                        } else {
                            html += '<div class="ep-result-error">';
                            html += '<p><strong>' + ep_admin.strings.outside_radius + '</strong></p>';
                            html += '<ul>';
                            html += '<li><strong>Distância:</strong> '    + escapeHtml(data.distance) + ' km</li>';
                            html += '<li><strong>Raio máximo:</strong> '  + escapeHtml($('[name="ep_max_radius"]').val()) + ' km</li>';
                            html += '</ul>';
                            html += '</div>';
                        }

                        result.html(html).show();

                    } else {
                        const msg = (typeof response.data === 'object')
                            ? response.data.message
                            : response.data;

                        result.html(
                            '<div class="ep-result-error"><p>❌ ' + escapeHtml(msg) + '</p></div>'
                        ).show();
                    }
                },
                error: function() {
                    result.html(
                        '<div class="ep-result-error"><p>❌ ' + ep_admin.strings.error + '</p></div>'
                    ).show();
                },
                complete: function() {
                    btn.prop('disabled', false).text('Simular Entrega');
                }
            });
        });

        // =============================================
        // LIMPAR CACHE
        // =============================================
        $('#ep_clear_cache').on('click', function() {
            if (!confirm('Tem certeza que deseja limpar o cache de distâncias?')) {
                return;
            }

            const btn = $(this);
            const msg = $('#ep_cache_message');

            btn.prop('disabled', true).text(ep_admin.strings.clearing);

            $.ajax({
                url:  ep_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ep_clear_cache',
                    nonce:  ep_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        msg.html(
                            '<span style="color:#28a745; margin-left:10px;">✅ ' +
                            ep_admin.strings.cache_cleared +
                            '</span>'
                        );
                        setTimeout(function() {
                            msg.html('');
                        }, 3000);
                    } else {
                        msg.html(
                            '<span style="color:#dc3545; margin-left:10px;">❌ ' +
                            ep_admin.strings.error +
                            '</span>'
                        );
                    }
                },
                error: function() {
                    msg.html(
                        '<span style="color:#dc3545; margin-left:10px;">❌ ' +
                        ep_admin.strings.error +
                        '</span>'
                    );
                },
                complete: function() {
                    btn.prop('disabled', false).text('Limpar Cache de Distâncias');
                }
            });
        });

        // =============================================
        // PREVIEW DO CÁLCULO EM TEMPO REAL
        // =============================================
        function updateFormulaPreview() {
            const fixed  = parseFloat(
                $('[name="ep_fixed_cost"]').val().replace(',', '.')
            ) || 0;

            const perKm  = parseFloat(
                $('[name="ep_cost_per_km"]').val().replace(',', '.')
            ) || 0;

            const dist   = 10; // exemplo fixo de 10 km
            const total  = fixed + (dist * perKm);

            $('#ep_formula_preview').text(
                'R$ ' + fixed.toFixed(2).replace('.', ',') +
                ' + (10 km × R$ ' + perKm.toFixed(2).replace('.', ',') +
                ') = R$ ' + total.toFixed(2).replace('.', ',')
            );
        }

        $('[name="ep_fixed_cost"], [name="ep_cost_per_km"]').on('input change', function() {
            updateFormulaPreview();
        });

        // Inicializar preview ao carregar a página
        updateFormulaPreview();

        // =============================================
        // MÁSCARA DE CEP NO SIMULADOR
        // =============================================
        $('#ep_test_address').on('input', function() {
            // Limpar resultado ao digitar novo endereço
            $('#ep_test_result').hide().html('');
        });

        // =============================================
        // VALIDAÇÃO DO RAIO MÁXIMO
        // =============================================
        $('[name="ep_max_radius"]').on('change', function() {
            const val = parseInt($(this).val());
            if (val < 1) {
                $(this).val(1);
            }
            if (val > 1000) {
                $(this).val(1000);
                alert('O raio máximo permitido é de 1000 km.');
            }
        });

        // =============================================
        // CONFIRMAÇÃO ANTES DE SALVAR
        // =============================================
        $('form').on('submit', function(e) {
            const lat = $('#ep_store_lat').val();
            const lng = $('#ep_store_lng').val();

            if (!lat || !lng) {
                if (!confirm(
                    '⚠️ As coordenadas da loja não foram definidas.\n' +
                    'O cálculo de distância pode não funcionar corretamente.\n\n' +
                    'Deseja salvar mesmo assim?'
                )) {
                    e.preventDefault();
                    return false;
                }
            }
        });

    }); // fim document.ready

})(jQuery);