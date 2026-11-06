# Entrega Personalizada para WooCommerce

Plugin WordPress/WooCommerce para cálculo de frete por distância, com raio máximo, custo fixo, custo por quilômetro e opção de frete grátis.

> **Status:** versão 2.0.0 em preparação para testes. Não considere esta versão pronta para produção até concluir testes em ambiente WordPress/WooCommerce.

## Recursos

- Painel administrativo com configurações gerais, custos, opções avançadas e simulador.
- Geolocalização opcional no checkout.
- Google Routes API para distância por ruas.
- Haversine como fallback (distância em linha reta).
- Geocodificação com Google Geocoding API ou Nominatim/OpenStreetMap.
- Cache de geocodificação e distâncias.
- Preservação de configurações durante atualizações.

## Requisitos

- WordPress 6.5 ou superior
- WooCommerce ativo
- PHP 8.1 ou superior

## Instalação

1. Baixe o ZIP do projeto ou copie a pasta do plugin para `wp-content/plugins/`.
2. Ative o plugin no painel WordPress.
3. Acesse **WooCommerce → Entrega Personalizada** e configure endereço, coordenadas, raio e custos.
4. Adicione o método às zonas em **WooCommerce → Configurações → Entrega**.

## Google Maps Platform

Para calcular distâncias por ruas, configure uma chave com Routes API e Geocoding API habilitadas. O uso pode gerar cobranças conforme os preços e limites da sua conta Google Cloud. Sem chave Google, o plugin utiliza Haversine, que calcula distância em linha reta. A geocodificação pode recorrer ao Nominatim público, sujeito à política e aos limites do serviço.

- [Google Routes API](https://developers.google.com/maps/documentation/routes)
- [Google Geocoding API](https://developers.google.com/maps/documentation/geocoding)
- [Política de uso do Nominatim](https://operations.osmfoundation.org/policies/nominatim/)
- [Direitos autorais do OpenStreetMap](https://www.openstreetmap.org/copyright)

## Privacidade

Endereços e coordenadas do cliente podem ser enviados aos serviços externos configurados para geocodificação e cálculo de rotas. O plugin também pode armazenar temporariamente resultados em cache e salvar dados relacionados ao cálculo como metadados do pedido. A loja deve revisar sua política de privacidade e informar seus clientes conforme aplicável.

## Testes antes de produção

Esta versão ainda precisa ser validada em ambiente de homologação, incluindo atualização de uma instalação existente, cálculo com e sem chave Google, Checkout clássico, Checkout em blocos e HPOS.

## Licença

GPL-2.0-or-later. Consulte o arquivo `LICENSE`.
