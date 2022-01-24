<?php

namespace Aciulog;

defined('ABSPATH') || exit('No direct script access allowed');

class Package
{
   protected $package = [];

   public function __construct(array $package = [])
   {
      $this->package = $package;
   }

   public function get_data()
   {
      $this->_package_data = $this->get_package_data();
      $data = $this->_package_data;

      if (!empty($data['height']) && !empty($data['width']) && !empty($data['length'])) {
         $cubage = $this->get_cubage($data['height'], $data['width'], $data['length']);
      } else {
         $cubage = [
            'height' => 0,
            'width'  => 0,
            'length' => 0,
            'weight' => 0,
            'price'  => 0
         ];
      }

      return $cubage;
   }

   protected function get_package_data()
   {
      $count  = 0;
      $height = [];
      $width  = [];
      $length = [];
      $weight = [];

      foreach ($this->package['contents'] as $item_id => $values) {
         $product = $values['data'];
         $qty     = $values['quantity'];

         if ($qty > 0 && $product->needs_shipping()) {

            $_height = wc_get_dimension((float) $product->get_height(), 'cm');
            $_width  = wc_get_dimension((float) $product->get_width(), 'cm');
            $_length = wc_get_dimension((float) $product->get_length(), 'cm');
            $_weight = wc_get_weight((float) $product->get_weight(), 'kg');

            $height[$count] = $_height;
            $width[$count]  = $_width;
            $length[$count] = $_length;
            $weight[$count] = $_weight;

            if ($qty > 1) {
               $n = $count;
               for ($i = 0; $i < $qty; $i++) {
                  $height[$n] = $_height;
                  $width[$n]  = $_width;
                  $length[$n] = $_length;
                  $weight[$n] = $_weight;
                  $n++;
               }
               $count = $n;
            }

            $count++;
         }
      }

      return [
         'height' => array_values($height),
         'length' => array_values($length),
         'width'  => array_values($width),
         'weight' => array_sum($weight)
      ];
   }

   protected function cubage_total($height, $width, $length)
   {
      $total       = 0;
      $total_items = count($height);

      for ($i = 0; $i < $total_items; $i++) {
         $total += $height[$i] * $width[$i] * $length[$i];
      }

      return $total;
   }

   protected function get_max_values($height, $width, $length)
   {
      $find = [
         'height' => max($height),
         'width'  => max($width),
         'length' => max($length),
      ];

      return $find;
   }

   protected function calculate_root($height, $width, $length, $max_values)
   {
      $cubage_total = $this->cubage_total($height, $width, $length);
      $root         = 0;
      $biggest      = max($max_values);

      if (0 !== $cubage_total && 0 < $biggest) {
         $division = $cubage_total / $biggest;
         $root = round(sqrt($division), 1);
      }

      return $root;
   }

   protected function get_cubage($height, $width, $length)
   {
      $cubage     = [];
      $max_values = $this->get_max_values($height, $width, $length);
      $root       = $this->calculate_root($height, $width, $length, $max_values);
      $greatest   = array_search(max($max_values), $max_values, true);

      switch ($greatest) {
         case 'height':
            $cubage = [
               'height' => round(max($height)),
               'width'  => round($root),
               'length' => round($root),
               'weight' => $this->_package_data['weight'],
               'price'  => $this->package['cart_subtotal']
            ];
            break;
         case 'width':
            $cubage = [
               'height' => round($root),
               'width'  => round(max($width)),
               'length' => round($root),
               'weight' => $this->_package_data['weight'],
               'price'  => $this->package['cart_subtotal']
            ];
            break;
         case 'length':
            $cubage = [
               'height' => round($root),
               'width'  => round($root),
               'length' => round(max($length)),
               'weight' => $this->_package_data['weight'],
               'price'  => $this->package['cart_subtotal']
            ];
            break;

         default:
            $cubage = [
               'height' => 0,
               'width'  => 0,
               'length' => 0,
               'weight' => 0,
               'price'  => 0
            ];
      }

      return $cubage;
   }
}
