<?php
  if (!class_exists('WishpondPlugin')) {
    class WishpondPlugin {
      public function __construct($map) {
        $this->map = $map;

        // Current Shortcode(s)
        add_shortcode('wp_campaign', array('WishpondShortcode', 'wp_campaign'));

        // Deprecated Shortcode(s)
        add_shortcode('wpsc_landing_page', array('WishpondShortcode', 'wp_campaign'));
        add_shortcode('wpsc',              array('WishpondShortcode', 'wpsc'));
        add_shortcode('wpoffer',           array('WishpondShortcode', 'wpsc'));
        add_shortcode('wpsweepstakes',     array('WishpondShortcode', 'wpsc'));
        add_shortcode('wpphotocontest',    array('WishpondShortcode', 'wpsc'));

        // Menu Links
        add_action('admin_menu', array($this, 'add_menu_pages'));

        // CORS
        add_action('init', array($this, 'add_cors_headers'));

        // AJAX
        add_action('wp_ajax_wishpond_ajax', array($this, 'wishpond_ajax_handler'));

        // Load Text Domain
        add_action( 'plugins_loaded', array($this, 'wishpond_load_plugin_textdomain'));

      }

      //
      // Menu Links
      //
      public function add_menu_pages() {
        // https://developer.wordpress.org/reference/functions/add_menu_page/
        $menu =
          add_menu_page(
            $this->map['menu']['main'],
            $this->map['menu']['main'],
            'administrator',
            $this->map['slug'],
            null,
            plugins_url('assets/images/icon.png', __FILE__)
          );

        self::init_globals($menu);

        // https://developer.wordpress.org/reference/functions/add_submenu_page/
        $menu =
          add_submenu_page(
            $this->map['slug'],
            $this->map['menu']['dashboard'],
            $this->map['menu']['dashboard'],
            'administrator',
            $this->map['slug'],
            array($this, 'display_dashboard')
          );

        self::init_globals($menu);

        // https://developer.wordpress.org/reference/functions/add_submenu_page/
        $menu =
          add_submenu_page(
            $this->map['slug'],
            $this->map['menu']['editor'],
            $this->map['menu']['editor'],
            'administrator',
            $this->map['slug'] . '-editor',
            array($this, 'display_editor')
          );

        self::init_globals($menu);

        // https://developer.wordpress.org/reference/functions/add_submenu_page/
        $menu =
          add_submenu_page(
            $this->map['slug'],
            $this->map['menu']['settings'],
            $this->map['menu']['settings'],
            'administrator',
            $this->map['slug'] . '-settings',
            array($this, 'display_settings')
          );

        self::init_globals($menu);
      }

      public function display_editor() {
        self::enqueue_scripts();
        include_once(plugin_dir_path(__FILE__) . 'views/editor.php');
      }

      public function display_dashboard() {
        self::enqueue_scripts();
        include_once(plugin_dir_path(__FILE__) . 'views/dashboard.php');
      }

      public function set_globals() {
        $GLOBALS['WP_PLUGIN_NAME']    = $this->map['name'];
        $GLOBALS['WP_PLUGIN_VERSION'] = $this->map['version'];
      }

      public function display_settings() {
        self::enqueue_scripts();

        global $error_message;
        global $notice_message;

        // Update the Wishpond Auth Token
        if (isset($_POST['submit']) && $_POST['submit']) {
          if (isset($_POST['token'])) {
            $token = $_POST['token'];

            if (empty($token) || trim($token) == false) {
              $token = '';
            }

            if ($token === get_option('wishpond_auth_token')) {
              $error_message = __('Token value is the same', 'optin-bar') . ': ' . $token;
            } else {
              $saved = update_option('wishpond_auth_token', $token, '', 'no');

              if ($saved) {
                $notice_message = __('Successfully updated token', 'optin-bar') . ': ' . $token;
              } else {
                $error_message = __('Unable to update token', 'optin-bar') . ': ' . $token;
              }
            }
          }
        }

        include_once(plugin_dir_path(__FILE__) . 'views/settings.php');
      }

      public function init_globals($menu) {
        add_action('load-' . $menu, array($this, 'set_globals'));
      }

      public function enqueue_scripts() {
        wp_register_style('wishpond-style', plugins_url('assets/css/style.css', __FILE__));
        wp_enqueue_style('wishpond-style');

        wp_enqueue_script('json2');

        wp_register_script('wishpond-xd', plugins_url('assets/js/xd.js', __FILE__), array(), '', true);
        wp_enqueue_script('wishpond-xd');

        wp_register_script('wishpond-js-api', plugins_url('assets/js/api.js', __FILE__), array(), '', true);
        wp_enqueue_script('wishpond-js-api');

        wp_localize_script('wishpond-js-api', 'JS',
          array(
            'wishpondUrl' => WP_SITE_URL,
            'wishpondSecureUrl' => WP_SECURE_SITE_URL,
            'wordpress' => admin_url('admin-ajax.php')
          )
        );
      }

      //
      // CORS
      //
      public function add_cors_headers() {
        header('Origin: ' . WP_SITE_URL . ', ' . WP_SECURE_SITE_URL);
        header('Access-Control-Allow-Origin: ' . WP_SITE_URL . ', ' . WP_SECURE_SITE_URL);
        header('Access-Control-Allow-Headers: origin, x-requested-with, content-type');
        header('Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS');
      }

      //
      // AJAX
      //
      public function wishpond_ajax_handler() {
        $data = $_POST['data'];

        switch($data['action']) {
          case 'publish_campaign': {
            self::publish_campaign($data);
            break;
          }
          case 'delete_campaign': {
            self::delete_campaign($data);
            break;
          }
        }
      }

      public function publish_campaign($data) {
        if (!is_user_logged_in()) {
          wp_die(__('Not logged in', 'optin-bar'));
        }

        if (!current_user_can('activate_plugins')) {
          wp_die(__('Not enough permissions', 'optin-bar'));
        }

        if (!WishpondUtilities::permalink_structure_valid()) {
          $message = __('Invalid permalink structure. Please go to "Settings"->"Permalinks" and make sure your permalinks use the "postname"', 'optin-bar');
          $message = WishpondUtilities::json_message('error', $message);
        } else {
          $path     = WishpondUtilities::extract_wordpress_path($data);
          $campaign = WishpondCampaign::find_or_create($path, $data);
          $action   = __('saved', 'optin-bar');

          if ($campaign->post_id !== -1) {
            $saved  = $campaign->update($path);
            $action = __('updated', 'optin-bar');
          } else {
            $saved = $campaign->save();
          }

          if ($saved) {
            $campaign_url = WishpondUtilities::get_campaign_url($campaign);
            $message = WishpondUtilities::json_message('updated', __('Campaign ', 'optin-bar') . $action . __(' successfully. ', 'optin-bar') . $campaign_url);
          } else {
            $message = WishpondUtilities::json_message('error', __('An unknown error occurred.', 'optin-bar'));
          }
        }

        wp_die($message);
      }

      public function delete_campaign($data) {
        if (!is_user_logged_in()) {
          wp_die(__('Not logged in', 'optin-bar'));
        }

        if (!current_user_can('activate_plugins')) {
          wp_die(__('Not enough permissions', 'optin-bar'));
        }

        $campaign = WishpondCampaign::find($data['campaign_id']);

        if ($campaign) {
          if ($campaign->remove()) {
            $message = WishpondUtilities::json_message('updated', __('Campaign removed successfully.', 'optin-bar'));
          } else {
            $message = WishpondUtilities::json_message('error', __('An unknown error occurred.', 'optin-bar'));
          }
        } else {
          $message = WishpondUtilities::json_message('error', __('Campaign not found.', 'optin-bar'));
        }

        wp_die($message);
      }

      function wishpond_load_plugin_textdomain() {
        load_plugin_textdomain( 'optin-bar', FALSE, basename( dirname( __FILE__ ) ) . '/lang/' );
      }
    }
  }
