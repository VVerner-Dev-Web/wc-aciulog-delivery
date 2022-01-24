<?php

namespace WAD;

use \WC_Shipping_Rate;

defined('ABSPATH') || exit('No direct script access allowed');

class App
{
   public function isWooCommerceInstalled(): bool
   {
      $plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
      return in_array('woocommerce/woocommerce.php', $plugins);
   } 

   public function init(): void
   {
      if (!$this->isWooCommerceInstalled()) :
         require_once ABSPATH . 'wp-admin/includes/plugin.php';
         deactivate_plugins(plugin_basename(WAD_FILE));
         add_action('admin_notices', [$this, 'displayWooCommerceMissingAlert']);

      else :
         $this->enqueueHooks();

      endif;
   }

   public function enqueueHooks(): void
   {
      add_action('plugins_loaded', [$this, 'loadFiles'], 999);
      add_filter('woocommerce_shipping_methods', [$this, 'enqueueShippingMethod']);
      add_filter('woocommerce_after_shipping_rate', [$this, 'printDeliveryForecastTime']);
   }

   public function enqueueShippingMethod(array $methods = []): array
   {
      $methods[] = 'Aciulog_Shipping_Method';
      return $methods;
   }

   public function printDeliveryForecastTime(WC_Shipping_Rate $shipping): void
   {
      $meta_data = $shipping->get_meta_data();
      $total     = isset( $meta_data['delivery_forecast'] ) ? (int) $meta_data['delivery_forecast'] : 0;

      if ( $total ) :
         $message = $total . _n(' dia para entrega', ' dias para entrega', $total);
         echo '<p class="aciulog-delivery-forecast"><small>' . $message . '</small></p>';
      endif;
   }

   public function loadFiles(): void
   {
      $ds = DIRECTORY_SEPARATOR;

      require_once WAD_APP . 'controller'. $ds .'aciulog' . $ds . 'Package.php';
      require_once WAD_APP . 'controller'. $ds .'aciulog' . $ds . 'API.php';

      require_once WAD_APP . 'controller'. $ds .'woocommerce' . $ds . 'AciulogShippingMethod.php';
   }

   public function displayWooCommerceMissingAlert(): void
   {
      echo '
      <div class="notice notice-warning is-dismissible">
         <p><strong>Integração AciuLog:</strong> Por favor, instale o WooCommerce para utilizara integração com o AciuLog</p>
      </div>
      ';
   }
}