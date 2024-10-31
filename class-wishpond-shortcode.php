<?php
  if (!class_exists('WishpondShortcode')) {
    class WishpondShortcode {
      // Here for backwards compatibility
      public static function wpsc($attrs) {
        $attrs = shortcode_atts(array(
          'id' => '',
          'style' => '',
          'class' => '',
          'dom_id' => '',
          'height' => '',
          'width' => '100%',
          'container' => '',
          'frameborder' => '',
          'merchant_id' => ''
        ), $attrs);

        $name = 'wpsc' . $attrs['id'];

        // More backwards compatibility
        if (isset($attrs['mid'])) {
          $attrs['merchant_id'] = $attrs['mid'];
        }

        // Check to see if we have the required attributes
        if (empty($attrs['id']) && empty($attrs['merchant_id'])) {
          return "<h1>" . __('wpsc shortcode needs either id or merchant_id attribute', 'optin-bar') . "</h1>";
        }

        $js = "
          // begin: automated code

          var " . $name . " = " . $name . " || {};" .
          $name . ".id = '" . $attrs['dom_id']      . "';" .
          $name . ".w  = '" . $attrs['width']       . "';" .
          $name . ".h  = '" . $attrs['height']      . "';" .
          $name . ".c  = '" . $attrs['container']   . "';" .
          $name . ".fb = '" . $attrs['frameborder'] . "';" .
          $name . ".cl = '" . $attrs['class']       . "';" .
          $name . ".s  = '" . $attrs['style']       . "';

          // end: automated code
        ";

        if (empty($attrs['id'])) {
          $src = WP_SITE_URL . '/sc/m/' . $attrs['merchant_id'];
        } else {
          $src = WP_SITE_URL . '/sc/' . $attrs['id'];
        }

        return "
          <script type='text/javascript'>" . $js . "</script>
          <script type='text/javascript' src='" . $src . ".js'></script>
        ";
      }

      public static function wp_campaign($attrs) {
        $attrs = shortcode_atts(array(
          'campaign_id' => '',
          'merchant_id' => '',
          'write_key'   => '',
          'id' => ''
        ), $attrs);

        // Only here for backwards compatibility
        if (empty($attrs['campaign_id']) && !empty($attrs['id'])) {
          $attrs['campaign_id'] = $attrs['id'];
        }

        // Check to see if we have the required attributes
        if (empty($attrs['campaign_id']) || empty($attrs['merchant_id']) || empty($attrs['write_key'])) {
          return "<h2 style='color:red;'>" . __('wp_campaign shortcode needs campaign_id, merchant_id, and write_key attributes', 'optin-bar') . "</h2>";
        }

        return "
          <!-- BEGIN Wishpond Embed Code -->
          <script type='text/javascript'>
          (function(d, s, id) {
            window.Wishpond = window.Wishpond || {};
            Wishpond.merchantId = '" . $attrs['merchant_id'] . "';
            Wishpond.writeKey = '" . $attrs['write_key'] . "';
            var js, wpjs = d.getElementsByTagName(s)[0];
            if (d.getElementById(id)) return;
            js = d.createElement(s); js.id = id;
            js.src = '" . WP_SECURE_SITE_URL . "/assets/connect.js';
            wpjs.parentNode.insertBefore(js, wpjs);
          }(document, 'script', 'wishpond-connect'));
          </script>

          <div class='wishpond-campaign'
            data-wishpond-id='" . $attrs['campaign_id'] . "'
            data-wishpond-href='" . WP_SECURE_SITE_URL . '/lp/' . $attrs['campaign_id'] . "/'
          ></div>
          <!-- END Wishpond Embed Code -->
        ";
      }
    }
  }
