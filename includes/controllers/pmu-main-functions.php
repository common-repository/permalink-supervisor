<?php
class PMU_Pro_Functions extends PMU_Class {

	public $update_checker, $license_key;

	public function __construct() {
		define( 'PMUPLPRO', true );
		$plugin_name = preg_replace('/(.*)\/([^\/]+\/[^\/]+.php)$/', '$2', PMU_PLFILE);
		add_filter( 'permalink_updator_filter_default_post_slug', array($this, 'remove_stop_words'), 9, 3 );
		add_filter( 'permalink_updator_filter_default_term_slug', array($this, 'remove_stop_words'), 9, 3 );
		add_filter( 'permalink_updator_filter_default_post_uri', array($this, 'replace_custom_field_tags'), 9, 5 );
		add_filter( 'permalink_updator_filter_default_term_uri', array($this, 'replace_custom_field_tags'), 9, 5 );
		add_action( 'permalink_updator_updated_post_uri', array($this, 'save_redirects'), 9, 5 );
		add_action( 'permalink_updator_updated_term_uri', array($this, 'save_redirects'), 9, 5 );
		add_action( 'wp_ajax_pm_get_exp_date', array($this, 'get_expiration_date'), 9 );
		add_action( "after_plugin_row_{$plugin_name}", array($this, 'license_info_bar'), 10, 2);
	}


	public static function get_expiration_date($basic_check = false, $empty_if_valid = false) {
		global $puUpdatorOptions;

		return '';
		die;

	}

	function license_info_bar($plugin_data, $response) {
		$plugin_name = preg_replace('/(.*)\/([^\/]+\/[^\/]+.php)$/', '$2', PMU_PLFILE);
		$exp_info_text = self::get_expiration_date(false, true);

		if($exp_info_text) {
			printf('<tr class="plugin-update-tr active" data-slug="%s" data-plugin="%s"><td colspan="3" class="plugin-update colspanchange plugin_license_info_row">', PMU_PLPLUGIN_SLUG, $plugin_name);
			printf('<div class="update-message notice inline notice-error notice-alt">%s</div>', wpautop($exp_info_text));
			printf('</td></tr>');
		}
	}

	static function load_stop_words_languages() {
		return array (
			'ar' => __('Arabic', 'permalink-updator'),
			'zh' => __('Chinese', 'permalink-updator'),
			'da' => __('Danish', 'permalink-updator'),
			'nl' => __('Dutch', 'permalink-updator'),
			'en' => __('English', 'permalink-updator'),
			'fi' => __('Finnish', 'permalink-updator'),
			'fr' => __('French', 'permalink-updator'),
			'de' => __('German', 'permalink-updator'),
			'he' => __('Hebrew', 'permalink-updator'),
			'hi' => __('Hindi', 'permalink-updator'),
			'it' => __('Italian', 'permalink-updator'),
			'ja' => __('Japanese', 'permalink-updator'),
			'ko' => __('Korean', 'permalink-updator'),
			'no' => __('Norwegian', 'permalink-updator'),
			'fa' => __('Persian', 'permalink-updator'),
			'pl' => __('Polish', 'permalink-updator'),
			'pt' => __('Portuguese', 'permalink-updator'),
			'ru' => __('Russian', 'permalink-updator'),
			'es' => __('Spanish', 'permalink-updator'),
			'sv' => __('Swedish', 'permalink-updator'),
			'tr' => __('Turkish', 'permalink-updator')
		);
	}


	/**
	 * Remove stop words from default URIs
	 */
	public function remove_stop_words($slug, $object, $name) {
		global $puUpdatorOptions;

		if(!empty($puUpdatorOptions['stop-words']['stop-words-enable']) && !empty($puUpdatorOptions['stop-words']['stop-words-list'])) {
			$stop_words = explode(",", strtolower(stripslashes($puUpdatorOptions['stop-words']['stop-words-list'])));

			foreach($stop_words as $stop_word) {
				$stop_word = trim($stop_word);
				$slug = preg_replace("/([\/-]|^)({$stop_word})([\/-]|$)/", '$1$3', $slug);
			}

			// Clear the slug
			$slug = preg_replace("/(-+)/", "-", trim($slug, "-"));
			$slug = preg_replace("/(-\/-)|(\/-)|(-\/)/", "/", $slug);
		}

		return $slug;
	}



	/**
	 * Replace custom field tags in default post URIs
	 */
	function replace_custom_field_tags($default_uri, $native_slug, $element, $slug, $native_uri) {
		// Do not affect native URIs
		if($native_uri == true) { return $default_uri; }

		preg_match_all("/%__(.[^\%]+)%/", $default_uri, $custom_fields);

		if(!empty($custom_fields[1])) {
			foreach($custom_fields[1] as $i => $custom_field) {
				// Reset custom field value
				$custom_field_value = "";

				// 1. Use WooCommerce fields
				if(class_exists('WooCommerce') && in_array($custom_field, array('sku')) && !empty($element->ID)) {
					$product = wc_get_product($element->ID);

					// 1A. SKU
					if($custom_field == 'sku') {
						$custom_field_value = $product->get_sku();
					}
					// 1B ...
				}

				// 2. Try to get value using ACF API
				else if(function_exists('get_field')) {
					$acf_element_id = (!empty($element->ID)) ? $element->ID : "{$element->taxonomy}_{$element->term_id}";
					$field_object = get_field_object($custom_field, $acf_element_id);

					// A. Taxonomy field
					if(!empty($field_object['taxonomy']) && !empty($field_object['value'])) {
						$rel_terms_id = $field_object['value'];

						if(!empty($rel_terms_id) && (is_array($rel_terms_id) || is_numeric($rel_terms_id))) {
							$rel_terms = get_terms(array('taxonomy' => $field_object['taxonomy'], 'include' => $rel_terms_id));

							// Get lowest term
							if(!is_wp_error($rel_terms) && !empty($rel_terms[0]) && is_object($rel_terms[0])) {
								$rel_term = PMU_Helper_Functions::get_lowest_element($rel_terms[0], $rel_terms);
							}

							// Get the replacement slug
							$custom_field_value = (!empty($rel_term->term_id)) ? PMU_Helper_Functions::get_term_full_slug($rel_term, $rel_terms, false, $native_uri) : "";
						}
					}

					// B. Relationship field
					if(!empty($field_object['type']) && (in_array($field_object['type'], array('relationship', 'post_object', 'taxonomy'))) && !empty($field_object['value'])) {
						$rel_elements = $field_object['value'];

						// B1. Terms
						if($field_object['type'] == 'taxonomy') {
							if(!empty($rel_elements) && (is_array($rel_elements))) {
								if(is_numeric($rel_elements[0]) && !empty($field_object['taxonomy'])) {
									$rel_elements = get_terms(array('include' => $rel_elements, 'taxonomy' => $field_object['taxonomy'], 'hide_empty' => false));
								}

								// Get lowest term
								if(!is_wp_error($rel_elements) && !empty($rel_elements) && is_object($rel_elements[0])) {
									$rel_term = PMU_Helper_Functions::get_lowest_element($rel_elements[0], $rel_elements);
								}
							} else if(is_numeric($rel_elements)) {
								$rel_term = get_term($rel_elements, $field_object['taxonomy']);
							}

							if(!empty($rel_term->term_id)) {
								$custom_field_value = $rel_term->slug;
							} else if(!empty($rel_elements->term_id)) {
								$custom_field_value = $rel_elements->slug;
							} else {
								$custom_field_value = "";
							}
						}
						// B2. Posts
						else {
							if(!empty($rel_elements) && (is_array($rel_elements))) {
								if(is_numeric($rel_elements[0])) {
									$rel_elements = get_posts(array('include' => $rel_elements));
								}

								// Get lowest element
								if(!is_wp_error($rel_elements) && !empty($rel_elements) && is_object($rel_elements[0])) {
									$rel_post = PMU_Helper_Functions::get_lowest_element($rel_elements[0], $rel_elements);
								}
							} else if(!empty($rel_elements->ID)) {
								$rel_post = $rel_elements;
							}

							$rel_post_id = (!empty($rel_post->ID)) ? $rel_post->ID : $rel_elements;

							// Get the replacement slug
							$custom_field_value = (is_numeric($rel_post_id)) ? get_page_uri($rel_post_id) : "";
						}
					}
					// C. Text field
					else {
						$custom_field_value = (!empty($field_object['value'])) ? $field_object['value'] : "";
						$custom_field_value = (!empty($custom_field_value['value'])) ? $custom_field_value['value'] : $custom_field_value;
					}
				}

				// 3. Use native method
				if(empty($custom_field_value)) {
					if(!empty($element->ID)) {
						$custom_field_value = get_post_meta($element->ID, $custom_field, true);

						// Toolset
						if(empty($custom_field_value) && (defined('TYPES_VERSION') || defined('WPCF_VERSION'))) {
							$custom_field_value = get_post_meta($element->ID, "wpcf-{$custom_field}", true);
						}
					} else if(!empty($element->term_id)) {
						$custom_field_value = get_term_meta($element->term_id, $custom_field, true);
					} else {
						$custom_field_value = "";
					}
				}

				// Allow to filter the custom field value
				$custom_field_value = apply_filters('permalink_updator_custom_field_value', $custom_field_value, $custom_field, $element);

				// Make sure that custom field is a string
				if(!empty($custom_field_value) && is_string($custom_field_value)) {
					$default_uri = str_replace($custom_fields[0][$i], PMU_Helper_Functions::sanitize_title($custom_field_value), $default_uri);
				}
			}
		}

		return $default_uri;
	}

	/**
	 * Save Redirects
	 */
	public function save_redirects($element_id, $new_uri, $old_uri, $native_uri, $default_uri) {
		global $puUpdatorOptions, $puUris, $puRedirects, $puExternalRedirects;

		// Terms IDs should be prepended with prefix
		$element_id = (current_filter() == 'permalink_updator_updated_term_uri') ? "tax-{$element_id}" : $element_id;

		// Make sure that $puRedirects variable is an array
		$puRedirects = (is_array($puRedirects)) ? $puRedirects : array();

		// 1A. Post/term is saved or updated
		if(isset($_POST['permalink-updator-redirects']) && is_array($_POST['permalink-updator-redirects'])) {
			$puRedirects[$element_id] = array_filter($_POST['permalink-updator-redirects']);
			$redirects_updated = true;
		}
		// 1B. All redirects are removed
		else if(isset($_POST['permalink-updator-redirects'])) {
			$puRedirects[$element_id] = array();
			$redirects_updated = true;
		}

		// 1C. No longer needed
		if(isset($_POST['permalink-updator-redirects'])) {
			unset($_POST['permalink-updator-redirects']);
		}

		// 2. Custom URI is updated
		if(get_option('page_on_front') != $element_id && !empty($puUpdatorOptions['general']['setup_redirects']) && ($new_uri != $old_uri)) {
			// Make sure that the array with redirects exists
			$puRedirects[$element_id] = (!empty($puRedirects[$element_id])) ? $puRedirects[$element_id] : array();

			// Append the old custom URI
			$puRedirects[$element_id][] = $old_uri;
			$redirects_updated = true;
		}

		// 3. Save the custom redirects
		if(!empty($redirects_updated) && is_array($puRedirects[$element_id])) {
			// Remove empty redirects
			$puRedirects[$element_id] = array_filter($puRedirects[$element_id]);

			// Sanitize the array with redirects
			foreach($puRedirects[$element_id] as $i => $redirect) {
				$redirect = rawurldecode($redirect);
				$redirect = PMU_Helper_Functions::sanitize_title($redirect, true);
				$puRedirects[$element_id][$i] = $redirect;
			}

			// Reset the keys
			$puRedirects[$element_id] = array_values($puRedirects[$element_id]);

			// Remove the duplicates
			$puRedirects[$element_id] = array_unique($puRedirects[$element_id]);

			PMU_Actions::clear_single_element_duplicated_redirect($element_id, true, $new_uri);

			update_option('permalink-updator-redirects', $puRedirects);
		}

		// 4. Save the external redirect
		if(isset($_POST['permalink-updator-external-redirect'])) {
			self::save_external_redirect(sanitize_text_field($_POST['permalink-updator-external-redirect']), $element_id);
		}
	}

	/**
	 * Save external redirect
	 */
	public static function save_external_redirect($url, $element_id) {
		global $puExternalRedirects;

		$url = filter_var($url, FILTER_SANITIZE_URL);

		if((empty($url) || filter_var($url, FILTER_VALIDATE_URL) === false) && !empty($puExternalRedirects[$element_id]) && isset($_POST['permalink-updator-external-redirect'])) {
			unset($puExternalRedirects[$element_id]);
		} else {
			$puExternalRedirects[$element_id] = $url;
		}

		update_option('permalink-updator-external-redirects', $puExternalRedirects);
	}

	/**
	 * WooCommerce Coupon URL functions
	 */
	public static function woocommerce_coupon_uris($post_types) {
		$post_types = array_diff($post_types, array('shop_coupon'));
		return $post_types;
	}

	public static function woocommerce_coupon_tabs($tabs = array()) {
		$tabs['coupon-url'] = array(
			'label' => __( 'Coupon Link', 'permalink-updator' ),
			'target' => 'permalink-updator-coupon-url',
			'class' => 'permalink-updator-coupon-url',
		);

		return $tabs;
	}

	public static function woocommerce_coupon_panel() {
		global $puUris, $post;

		$custom_uri = (!empty($puUris[$post->ID])) ? $puUris[$post->ID] : "";

		$html = "<div id=\"permalink-updator-coupon-url\" class=\"panel woocommerce_options_panel custom_uri_container permalink-updator\">";

		// URI field
		ob_start();
			wp_nonce_field('permalink-updator-coupon-uri-box', 'permalink-updator-nonce', true);

			woocommerce_wp_text_input(array(
				'id' => 'custom_uri',
				'label' => __( 'Coupon URI', 'permalink-updator' ),
				'description' => '<span class="duplicated_uri_alert"></span>' . __( 'The URIs are case-insensitive, eg. <strong>BLACKFRIDAY</strong> and <strong>blackfriday</strong> are equivalent.', 'permalink-updator' ),
				'value' => $custom_uri,
				'custom_attributes' => array('data-element-id' => $post->ID),
				//'desc_tip' => true
			));

			$html .= ob_get_contents();
		ob_end_clean();

		// URI preview
		$html .= "<p class=\"form-field coupon-full-url hidden\">";
		$html .= sprintf("<label>%s</label>", __("Coupon Full URL", "permalink-updator"));
 		$html .= sprintf("<code>%s/<span>%s</span></code>", trim(get_option('home'), "/"), $custom_uri);
		$html .= "</p>";

		$html .= "</div>";

		echo $html;
	}

	public static function woocommerce_save_coupon_uri($post_id, $coupon) {
		global $puUris;

		// Verify nonce at first
		if(!isset($_POST['permalink-updator-nonce']) || !wp_verify_nonce($_POST['permalink-updator-nonce'], 'permalink-updator-coupon-uri-box')) { return $post_id; }

		// Do not do anything if post is autosaved
		if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return $post_id; }

		$old_uri = (!empty($puUris[$post_id])) ? $puUris[$post_id] : "";
		$new_uri = (!empty($_POST['custom_uri'])) ? sanitize_text_field($_POST['custom_uri']) : "";

		if($old_uri != $new_uri) {
			$puUris[$post_id] = PMU_Helper_Functions::sanitize_title($new_uri, true);
			update_option('permalink-updator-uris', $puUris);
		}
	}

	public static function woocommerce_detect_coupon_code($query) {
		global $woocommerce, $pm_query;

		// Check if custom URI with coupon URL is requested
		if(!empty($query['shop_coupon']) && !empty($pm_query['id'])) {
			// Check if cart/shop page is set & redirect to it
			$shop_page_id = wc_get_page_id('shop');
			$cart_page_id = wc_get_page_id('cart');


			if(!empty($cart_page_id) && WC()->cart->get_cart_contents_count() > 0) {
				$redirect_page = $cart_page_id;
			} else if(!empty($shop_page_id)) {
				$redirect_page = $shop_page_id;
			}

			$coupon_code = get_the_title($pm_query['id']);

			// Set-up session
			if(!WC()->session->has_session()) {
				WC()->session->set_customer_session_cookie(true);
			}

			// Add the discount code
			if(!WC()->cart->has_discount($coupon_code)) {
				$woocommerce->cart->add_discount(sanitize_text_field($coupon_code));
			}

			// Do redirect
			if(!empty($redirect_page)) {
				wp_safe_redirect(get_permalink($redirect_page));
				exit();
			}

		}

		return $query;
	}

}

?>
