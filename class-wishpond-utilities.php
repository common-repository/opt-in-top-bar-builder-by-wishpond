<?php
  if (!class_exists('WishpondUtilities')) {
    class WishpondUtilities {
      private static $token = null;

      public static function get_auth_token() {
        // Try to fetch the auth token from DB
        if (empty(self::$token)) {
          self::$token = get_option('wishpond_auth_token');
        }

        // Couldn't find an auth token in the DB. Ask Wishpond for one
        if (empty(self::$token)) {
          $url = self::build_url('/api/wordpress/token.json');

          $ch = curl_init(); // cURL Handler

          // Set cURL options
          curl_setopt($ch, CURLOPT_URL, $url);
          curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

          $resp = curl_exec($ch);
          $json = json_decode($resp, true);

          curl_close($ch); // Close the cURL connection

          // Check for errors
          if (!empty($json['token'])) {
            self::$token = $json['token'];
            // Save the auth token
            add_option('wishpond_auth_token', self::$token, '', 'no');
          } else {
            // Set global error message for the user
            global $error_message;

            if (empty($json['error'])) {
              $error_message = 'An unknown error occurred.';
            } else {
              $error_message = $json['error'];
            }

            // Log the error message
            error_log($error_message);
          }
        }

        return self::$token;
      }

      public static function json_message($type, $message) {
        return json_encode(array('message' => array('type' => $type, 'text' => $message)));
      }

      public static function permalink_structure_valid() {
        return strpos(get_option('permalink_structure'), 'postname') !== false;
      }

      public static function build_url($path, $params = array()) {
        // Wishpond V1 requires the following params:
        //   wordpress_host
        //   wordpress_plugin_name
        //   wordpress_plugin_version
        $params = array_merge($params, array(
          'email'                    => get_option('admin_email'),
          'wordpress_host'           => site_url(),
          'wordpress_plugin_name'    => $GLOBALS['WP_PLUGIN_NAME'],
          'wordpress_plugin_version' => $GLOBALS['WP_PLUGIN_VERSION']
        ));

        return WISHPOND_SECURE_SITE_URL . $path . '?' . http_build_query($params);
      }

      public static function build_action_url($action, $token) {
        return self::build_url('/api/wordpress/authenticate.html',
          array('wordpress_plugin_action' => $action, 'wordpress_token' => $token)
        );
      }

      public static function extract_wordpress_path($data) {
        return substr($data['wordpress_path'], strrpos($data['wordpress_path'], '/'));
      }

      public static function get_campaign_url($campaign, $text = 'View') {
        return '<a class="btn" target="_blank" href="' . get_bloginfo('url') . '/' . $campaign->path . '">' . $text . '</a>';
      }
    }
  }
