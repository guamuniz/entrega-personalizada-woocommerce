=== Entrega Personalizada para WooCommerce ===
Contributors: guamuniz
Tags: woocommerce, shipping, delivery, distance, geolocation
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Calcule o frete de entrega com base na distância entre a loja e o endereço do cliente, com custo fixo, valor por quilômetro e raio máximo.

== Description ==

O Entrega Personalizada adiciona um método de entrega ao WooCommerce com cálculo baseado na localização da loja e do cliente.

Principais recursos:
* Endereço e coordenadas configuráveis da loja.
* Raio máximo de atendimento.
* Custo fixo e custo por quilômetro.
* Opção de frete grátis por valor mínimo do carrinho.
* Geolocalização do cliente no checkout (opcional).
* Cálculo de percurso por ruas com Google Routes API.
* Alternativa por distância em linha reta (Haversine).
* Geocodificação com Google Geocoding API ou Nominatim/OpenStreetMap.
* Cache de geocodificação e distâncias.
* Simulador de entrega no painel administrativo.

== External services ==

Este plugin pode enviar endereços e/ou coordenadas para serviços externos para localizar endereços e calcular distâncias. Esses serviços são utilizados conforme configuração e disponibilidade.

= Google Maps Platform =

Quando uma chave de API é configurada, o plugin utiliza Google Geocoding API para geocodificar endereços e Google Routes API para calcular a distância por ruas. O lojista deve habilitar as APIs necessárias, configurar faturamento quando exigido e observar os preços e termos do Google. O plugin envia ao Google os endereços ou coordenadas necessários para executar essas operações.

* Terms: https://cloud.google.com/maps-platform/terms
* Routes API: https://developers.google.com/maps/documentation/routes
* Geocoding API: https://developers.google.com/maps/documentation/geocoding

= OpenStreetMap Nominatim =

Quando o Google não está configurado ou não consegue geocodificar um endereço, o plugin pode consultar o serviço público Nominatim do OpenStreetMap. A utilização está sujeita à política do serviço, incluindo limites de uso, identificação da aplicação e atribuição. O serviço público não é indicado para operações de alto volume; lojas com volume elevado devem contratar ou hospedar um serviço apropriado.

* Usage policy: https://operations.osmfoundation.org/policies/nominatim/
* Copyright and attribution: https://www.openstreetmap.org/copyright

Sem uma chave Google válida, a distância de entrega é calculada em linha reta pela fórmula Haversine. Essa distância não representa o trajeto real pelas ruas.

== Installation ==

1. Envie a pasta `entrega-personalizada` para `/wp-content/plugins/` ou instale o ZIP pelo painel do WordPress.
2. Ative o plugin e confirme que o WooCommerce está instalado e ativo.
3. Acesse WooCommerce > Entrega Personalizada.
4. Informe o endereço e as coordenadas da loja.
5. Configure raio, custos e opções de geolocalização.
6. Acesse WooCommerce > Configurações > Entrega > Zonas de entrega e adicione o método Entrega Personalizada à zona desejada.
7. Para distância por ruas, configure uma chave Google com Routes API e Geocoding API habilitadas.

== Frequently Asked Questions ==

= O plugin exige uma chave Google? =

Não. Sem uma chave, o plugin utiliza Nominatim para geocodificação quando disponível e Haversine para calcular a distância em linha reta.

= A distância Haversine é a distância percorrida pelo entregador? =

Não. É uma estimativa em linha reta. Para calcular percurso por ruas, configure Google Routes API.

= A chave Google pode gerar cobranças? =

Sim. O uso das APIs Google Maps Platform pode gerar cobranças conforme a conta, os serviços habilitados e o volume de solicitações. Consulte os preços oficiais antes de ativar.

= Minhas configurações serão apagadas ao atualizar? =

As atualizações preservam as opções existentes. A desinstalação é uma ação separada e remove os dados do plugin.

== Screenshots ==

1. Configurações gerais da loja, coordenadas e raio de atendimento.
2. Configuração dos custos de entrega.
3. Configurações avançadas, chave de API e cache.
4. Simulador de entrega.

== Changelog ==

= 2.0.0 =
* Removidos valores de endereço e coordenadas específicos de uma instalação.
* Mantidas as configurações existentes durante atualizações.
* Adicionadas validações e sanitização de configurações e coordenadas.
* Corrigida persistência da opção de geolocalização desativada.
* Adicionado cálculo de percurso com Google Routes API.
* Separado cache de distância por modalidade de cálculo.
* Adicionado cache de geocodificação e controle básico de consultas Nominatim.
* Melhorado escape de dados dinâmicos no painel e no frontend.
* Removido arquivo antigo da distribuição.

== Upgrade Notice ==

= 2.0.0 =
Teste a atualização em ambiente de homologação antes de aplicar em lojas de produção. A integração de percurso Google requer Routes API.
