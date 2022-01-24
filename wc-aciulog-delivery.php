<?php defined('ABSPATH') || exit('No direct script access allowed');
/**
 * Plugin Name: WooCommerce Aciulog Delivery
 * Description: Integra o sistema de fretes Aciulog ao WooCommerce
 * Author: VVerner
 * Author URI: https://vverner.com
 * Version: 0.1
 * Requires at least: 5.8
 * Tested up to: 5.8
 * Requires PHP: 7.2
 */

define('WAD_FILE', __FILE__);
define('WAD_APP', __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR);
require_once WAD_APP . 'controller' . DIRECTORY_SEPARATOR . 'App.php';

$app = new WAD\App();
$app->init();