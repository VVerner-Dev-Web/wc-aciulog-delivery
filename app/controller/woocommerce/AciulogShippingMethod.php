<?php defined('ABSPATH') || exit('No direct script access allowed');

class Aciulog_Shipping_Method extends WC_Shipping_Method
{
   public function __construct()
   {
      $this->id                   = 'aciulog-shipping';
      $this->method_title         = 'Aciulog';
      $this->method_description   = 'Entrega pela AciuLog';
      $this->availability         = 'including';
      $this->countries            = ['BR'];

      $this->init();

      $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
      $this->title   = isset($this->settings['title']) ? $this->settings['title'] : 'Aciulog';
      $this->isShowingForecast   = $this->settings['show_estimation'] == 'yes';
      $this->isLogEnabled        = $this->settings['debug'] === 'yes';
      $this->logger              = $this->isLogEnabled ? wc_get_logger() : null;
   }

   public function init()
   {
      $this->init_form_fields();
      $this->init_settings();

      add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
   }

   public function init_form_fields()
   {
      $this->form_fields = [
         'cpf_cnpj'           => [
            'type'   => 'text',
            'class'  => 'regular',
            'title'  => 'CPF / CNPJ do remetente',
         ],
         'origin'             => [
            'type'      => 'select',
            'class'     => 'wc-enhanced-select',
            'title'     => 'Origem do envio',
            'default'   => 'Uberaba',
            'options'   => \Aciulog\API::getAvailableOrigins()
         ],
         'token'              => [
            'type'      => 'text',
            'title'     => 'Token da API',
         ],
         'show_estimation'    => [
            'type'        => 'select',
            'class'       => 'wc-enhanced-select',
            'title'       => 'Estimativa de entrega',
            'desc_tip'    => 'Marque para exibir a estimativa de entrega no carrinho',
            'default'     => '0',
            'options'     => [
               'no'  => 'Não exibir',
               'yes' => 'Exibir'
            ]
         ],
         'additional_time'    => [
            'type'              => 'number',
            'class'             => 'short',
            'title'             => 'Tempo de separação',
            'desc_tip'          => 'Dias adicionais para acrescentar no prazo de entrega',
            'default'           => 0,
            'custom_attributes' => [
               'min'  => 0,
               'step' => 1
            ],
         ],
         'debug'              => [
            'type'         => 'select',
            'class'        => 'wc-enhanced-select',
            'title'        => 'Ativar log de requisições',
            'default'      => '0',
            'options'      => [
               'no'  => 'Não habilitar',
               'yes' => 'Habilitar logs'
            ]
         ]
      ];
   }

   public function log($thing): void
   {
      if ($this->logger) : 
         $message = print_r($thing, true);
         $this->logger->debug( $message, ['source' => 'aciulog-shipping'] );
      endif;
   }

   public function calculate_shipping($_package = [])
   {
      $this->log('==============================================');
      $this->log('Nova requisição de calculo de frete');

      $zip  = isset($_package['destination']['postcode']) ? preg_replace('/\D/', '', $_package['destination']['postcode']) : '';
      if (!$zip) : 
         $this->log('Nenhum CEP enviado. Finalizando requisição.');
         return;
      endif;

      $api        = $this->getApi();
      $package    = $this->getPackage($_package);
      
      $api->setPackage($package);

      $estimates  = $api->fetchEstimates($zip);
      $counter    = $estimates ? count($estimates) : '0';

      $this->log('CEP para simulação: ' . $zip);
      $this->log('Dados do pacote simulado');
      $this->log($package->get_data());
      $this->log($counter . ' fretes válidos recebidos');
      $this->log($estimates);

      $additionalTime   = (int) $this->settings['additional_time'];

      if ($estimates) : 
         $this->log('Inserindo métodos de envio para escolha do cliente');
         foreach ($estimates as $estimate) : 
            $forecast = $estimate->delivery_forecast + $additionalTime;

            $this->add_rate([
               'id'        => $estimate->key,
               'label'     => 'Aciulog ' . $estimate->type,
               'cost'      => $estimate->price,
               'meta_data' => [
                  'delivery_forecast' => $forecast && $this->isShowingForecast ? $forecast : 0
               ]
            ]);
         endforeach;
      endif;

      $this->log('Finalizando requisição');
   }

   public function getSenderID(): string
   {
      return $this->settings['cpf_cnpj'] ? preg_replace('/\D/', '', $this->settings['cpf_cnpj']) : '';
   }

   public function getApiToken(): string
   {
      return $this->settings['token'] ? trim($this->settings['token']) : '';
   }

   public function getOriginCity(): string
   {
      return $this->settings['origin'] ? trim($this->settings['origin']) : '';
   }

   private function getApi(): \Aciulog\API
   {
      return new Aciulog\API($this);
   }

   private function getPackage(array $package): \Aciulog\Package
   {
      return new Aciulog\Package($package);
   }
}
