<?php
  if (!class_exists('WishpondCampaign')) {
    class WishpondCampaign {
      public function add_or_update_meta($key) {
        if (get_post_meta($this->post_id, $key, true)) {
          update_post_meta($this->post_id, $key, $this->{$key});
        } else {
          add_post_meta($this->post_id, $key, $this->{$key});
        }
      }

      public function campaign_shortcode_regex($campaign_id) {
        return '/\[\s*wp_campaign.*' . strval($campaign_id) . '.*\]((\s+?)?\[\/wp_campaign\])?/';
      }

      // Check if content has wp_campaign shortcode
      public function shortcode_found($campaign_id, $content) {
        return preg_match($this->campaign_shortcode_regex($campaign_id), $content);
      }

      public function extract_shortcode($campaign_id, $content) {
        return preg_replace($this->campaign_shortcode_regex($campaign_id), '', $content);
      }

      public function campaign_shortcode() {
        $shortcode  = "\n[wp_campaign ";
        $shortcode .= "campaign_id='" . $this->campaign_id . "' ";
        $shortcode .= "merchant_id='" . $this->merchant_id . "' ";
        $shortcode .= "write_key='"   . $this->write_key   . "']\n";

        return $shortcode;
      }

      public function __construct($args) {
        if (!array_key_exists('post_id', $args) || $args['post_id'] == '') {
          $args['post_id'] = -1;
        }

        $this->path        = $args['path'];
        $this->post_id     = $args['post_id'];
        $this->write_key   = $args['write_key'];
        $this->merchant_id = $args['merchant_id'];
        $this->campaign_id = $args['campaign_id'];
      }

      public function save() {
        $fields = array(
          'post_name'      => $this->path,
          'post_author'    => get_current_user_id(),
          'post_content'   => $this->campaign_shortcode(),
          'post_title'     => htmlspecialchars($this->path),
          'post_type'      => 'page',
          'comment_status' => 'closed',
          'ping_status'    => 'closed',
          'post_status'    => 'publish'
        );

        $success = false;

        // Don't have a wordpress post associated with the campaign
        if ($this->post_id == -1) {
          $post = get_page_by_path($this->path);

          // Found a post with the same path, The shortcode will be appended to the post
          if ($post) {
            if ($this->shortcode_found($this->campaign_id, $post->post_content)) {
              // Shortcode already on the page. Nothing to do
              $success = true;
            } else {
              // Append the shortcode to the post content
              $fields = array(
                'ID' => $post->ID,
                'post_content' => $post->post_content . $this->campaign_shortcode()
              );

              // Update the post with the new content
              $success = $this->post_id = wp_update_post($fields);
            }
          } else {
            // Create a new post with the shortcode
            $success = $this->post_id = wp_insert_post($fields);
          }
        } else {
          // Retrieve the associated post
          $post = get_post($this->post_id);

          // If post found with the shortcode, do nothing
          if ($post) {
            // Do nothing if the post already has the shortcode
            if ($this->shortcode_found($this->campaign_id, $post->post_content)) {
              $success = true;
            } else {
              $fields = array(
                'ID' => $post->ID,
                'post_content' => $post->post_content . $this->campaign_shortcode()
              );

              // Update the post with the new content
              $success = $this->post_id = wp_update_post($fields);
            }
          } else {
            // Post was deleted by the user. Create a new post
            $this->post_id = -1;

            return $this->save();
          }
        }

        // Update post meta
        if (!!$success) {
          add_post_meta($this->post_id, 'campaign_id', $this->campaign_id);

          $this->add_or_update_meta('write_key');
          $this->add_or_update_meta('merchant_id');

          return true;
        } else {
          return false;
        }
      }

      // public function set_as_homepage() {
      //   update_option('page_on_front', $this->post_id);
      //   update_option('show_on_front', 'page');
      // }

      public function update($path) {
        // Remove campaign from current page if the path is changing
        if ($this->path !== $path) {
          if ($this->remove()) {
            $this->path = $path;
          } else {
            return false;
          }
        }

        return $this->save();
      }

      public function remove() {
        if (empty($this->post_id) || $this->post_id == -1) {
          return true;
        }

        $success = false;
        $post = get_post($this->post_id);

        if (empty($post)) {
          // The post was removed by the user
          $success = true;
        } elseif ($this->shortcode_found($this->campaign_id, $post->post_content)) {
          // Remove the shortcode from the content
          $content = $this->extract_shortcode($this->campaign_id, $post->post_content);

          if (empty($content)) {
            // Post has no more content so remove it
            $success = wp_delete_post($post->ID);
          } else {
            // Trim the content
            $content = trim($content);

            // Check again
            if (empty($content)) {
              $success = wp_delete_post($post->ID);
            } else {
              // We still have some content so the post needs to be updated
              $success = wp_update_post(array('ID' => $post->ID, 'post_content' => $content));
            }
          }
        } else {
          // The shortcode was removed by the user
          $success = true;
        }

        $this->post_id = -1;

        return !!$success;
      }

      public static function find_or_create($path, $args) {
        $campaign = WishpondCampaign::find($args['campaign_id']);

        if (empty($campaign)) {
          $campaign = new WishpondCampaign(array(
            'path'        => $path,
            'campaign_id' => $args['campaign_id'],
            'write_key'   => $args['write_key'],
            'merchant_id' => $args['merchant_id']
          ));
        }

        return $campaign;
      }

      public static function find($campaign_id) {
        $query = new WP_Query(
          array(
            'post_type'     => 'page',
            'meta_key'      => 'campaign_id',
            'meta_value'    => $campaign_id,
            'meta_compare' => 'LIKE'
          )
        );

        $posts = $query->get_posts();

        if (empty($posts)) {
          return;
        }

        $post = $posts[0];

        return new WishpondCampaign(array(
          'post_id'     => $post->ID,
          'path'        => $post->post_name,
          'title'       => $post->post_title,
          'campaign_id' => $campaign_id,
          'write_key'   => get_post_meta($post->ID, 'write_key', true),
          'merchant_id' => get_post_meta($post->ID, 'merchant_id', true)
        ));
      }
    }
  }
