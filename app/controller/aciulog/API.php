<?php

namespace Aciulog;

use stdClass;
use SimpleXMLElement;

defined('ABSPATH') || exit('No direct script access allowed');

class API
{
   private $package;
   private $shippingMethod;

   private const URL = ' https://www.aciulog.com.br/ws/api/Frete/CalcularFrete';

   public function __construct(\Aciulog_Shipping_Method $method)
   {
      $this->shippingMethod = $method;
   }

   public static function getAvailableOrigins(): array
   {
      return [
         'Uberaba' => 'Uberaba'
      ];
   }

   public function setPackage(Package $package): void
   {
      $this->package = $package;
   }

   public function fetchEstimates(string $destinationZip)
   {
      if ($this->package) : 
         $volume  = $this->package->get_data();
         $url     = self::URL . '?' . $this->buildQuery($destinationZip, $volume);

         $this->shippingMethod->log('URL consultada');
         $this->shippingMethod->log($url);
         
      else : 
         $this->shippingMethod->log('Requisição não realizada devido a falta de um pacote');
         $url = null;

      endif;

      return $url ? $this->fetch($url) : null;
   }

   private function buildQuery(string $destinationZip, array $volume): string
   {
      return http_build_query([
         'cpf_cnpj'           => $this->shippingMethod->getSenderID(),
         'token'              => $this->shippingMethod->getApiToken(),
         'origem'             => $this->shippingMethod->getOriginCity(),
         'cep_destino'        => preg_replace('/\D/', '', $destinationZip),
         'altura'             => $volume['height'],
         'largura'            => $volume['length'],
         'comprimento'        => $volume['width'],
         'peso'               => $volume['weight'],
         'valor_nota_fiscal'  => $volume['price'],
      ]);
   }

   private function fetch(string $url)
   {
      $response = wp_remote_get($url, [
         'timeout'   => 10,
         'sslverify' => false
      ]);

      if (is_wp_error($response)) : 
         $this->shippingMethod->log('Houve um erro ao buscar os dados da API.');
         $this->shippingMethod->log($response->get_error_message());
         return null;
      endif;

      if (wp_remote_retrieve_response_code($response) !== 200) : 
         $this->shippingMethod->log('A API retornou com um cabeçalho inválido');
         return null;
      endif;

      $body = wp_remote_retrieve_body($response);

      $this->shippingMethod->log('Retorno bruto da requisição');
      $this->shippingMethod->log($body);

      return $this->parseBody($body);
   }

   private function parseBody(string $body): array
   {
      $data       = json_decode($body);
      $collection = [];

      if ($data) : 
         $collection = array_map([$this, 'normalizeEstimate'], $data);
         $collection = array_filter($collection, function($item){
            return $item->price > 0;
         });
      endif;

      return $collection;
   }

   private function normalizeEstimate($row): stdClass
   {
      return (object) [
         'key'       => sanitize_key('aciulog-' . $row->Tipo),
         'type'      => sanitize_text_field($row->Tipo),
         'price'     => (float) $row->Valor,
         'alert'     => sanitize_textarea_field($row->Mensagem),
         'delivery_forecast'  => $row->Prazo
      ];
   }
}