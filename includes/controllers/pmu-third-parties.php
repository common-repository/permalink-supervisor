<?php
class PMU_Third_Parties extends PMU_Class {

	public function __construct() {
		add_action('init', array($this, 'init_hooks'), 99);
		add_action('plugins_loaded', array($this, 'init_early_hooks'), 99);
	}

	function init_hooks() {
		global $puUpdatorOptions;

		// 0. Stop redirect
		add_action('wp', array($this, 'stop_redirect'), 0);

		// 2. AMP
		if(defined('AMP_QUERY_VAR')) {
			// Detect AMP endpoint
			add_filter('permalink_updator_detect_uri', array($this, 'detect_amp'), 10, 2);
			add_filter('request', array($this, 'enable_amp'), 10, 1);
		}

		// 4. WooCommerce
		if(class_exists('WooCommerce')) {
			add_filter('request', array($this, 'woocommerce_detect'), 20, 1);
			add_filter('template_redirect', array($this, 'woocommerce_checkout_fix'), 9);

			if(class_exists('PMU_Pro_Functions')) {
				if(is_admin()){
					add_filter('woocommerce_coupon_data_tabs', 'PMU_Pro_Functions::woocommerce_coupon_tabs');
					add_action('woocommerce_coupon_data_panels', 'PMU_Pro_Functions::woocommerce_coupon_panel');
					add_action('woocommerce_coupon_options_save', 'PMU_Pro_Functions::woocommerce_save_coupon_uri', 9, 2);
				}
				add_filter('request', 'PMU_Pro_Functions::woocommerce_detect_coupon_code', 1, 1);
				add_filter('permalink_updator_disabled_post_types', 'PMU_Pro_Functions::woocommerce_coupon_uris', 9, 1);
			}

			add_action('woocommerce_product_import_inserted_product_object', array($this, 'woocommerce_generate_permalinks_after_import'), 9, 2);
		}

		// 5. Theme My Login
		if(class_exists('Theme_My_Login')) {
			add_filter('permalink_updator_filter_final_post_permalink', array($this, 'tml_keep_query_parameters'), 9, 3);
		}

		// 6. Yoast SEO
		add_filter('wpseo_xml_sitemap_post_url', array($this, 'yoast_fix_sitemap_urls'), 9);
		if(defined('WPSEO_VERSION') && version_compare(WPSEO_VERSION, '14.0', '>=')) {
			add_action('permalink_updator_updated_post_uri', array($this, 'yoast_update_indexable_permalink'), 10, 3);
			add_action('permalink_updator_updated_term_uri', array($this, 'yoast_update_indexable_permalink'), 10, 3);
			add_filter('wpseo_canonical', array($this, 'yoast_fix_canonical'), 10);
			add_filter('wpseo_opengraph_url', array($this, 'yoast_fix_canonical'), 10);
		}

		// 7. Breadcrumbs
		add_filter('wpseo_breadcrumb_links', array($this, 'filter_breadcrumbs'), 9);
		add_filter('rank_math/frontend/breadcrumb/items', array($this, 'filter_breadcrumbs'), 9);
		add_filter('seopress_pro_breadcrumbs_crumbs', array($this, 'filter_breadcrumbs'), 9);
		add_filter('woocommerce_get_breadcrumb', array($this, 'filter_breadcrumbs'), 9);
		add_filter('slim_seo_breadcrumbs_links', array($this, 'filter_breadcrumbs'), 9);

		// 8. WooCommerce Wishlist Plugin
		if(function_exists('tinv_get_option')) {
			add_filter('permalink_updator_detect_uri', array($this, 'ti_woocommerce_wishlist_uris'), 15, 3);
		}

		// 9. Revisionize
		if(defined('REVISIONIZE_ROOT')) {
			add_action('revisionize_after_create_revision', array($this, 'revisionize_keep_post_uri'), 9, 2);
			add_action('revisionize_before_publish', array($this,'revisionize_clone_uri'), 9, 2);
		}

		// 10. WP All Import
		if(class_exists('PMXI_Plugin') && (empty($puUpdatorOptions['general']['pmxi_import_support']))) {
			add_action('pmxi_extend_options_featured', array($this, 'wpaiextra_uri_display'), 9, 2);
			add_filter('pmxi_options_options', array($this, 'wpai_api_options'));
			add_filter('pmxi_addons', array($this, 'wpai_api_register'));
			add_filter('wp_all_import_addon_parse', array($this, 'wpai_api_parse'));
			add_filter('wp_all_import_addon_import', array($this, 'wpai_api_import'));

			add_action('pmxi_saved_post', array($this, 'wpai_save_redirects'));

			add_action('pmxi_after_xml_import', array($this, 'wpai_schedule_regenerate_uris_after_xml_import'), 10, 1);
			add_action('wpai_regenerate_uris_after_import_event', array($this, 'wpai_regenerate_uris_after_import'), 10, 1);
		}

		// 11. Duplicate Post
		if(defined('DUPLICATE_POST_CURRENT_VERSION')) {
			add_action('dp_duplicate_post', array($this, 'duplicate_custom_uri'), 10, 2);
			add_action('dp_duplicate_page', array($this, 'duplicate_custom_uri'), 10, 2);
		}

		// 12. My Listing by 27collective
		if(defined('CASE27_THEME_DIR')) {
			add_filter( 'permalink_updator_filter_default_post_uri', array($this, 'replace_listing_custom_field_tags'), 5, 5 );
		}
	}

	/**
	 * Some of the hooks must be called shortly after all the plugins are loaded
	 */
	public function init_early_hooks() {
		// 13. WP Store Locator
		if(class_exists('WPSL_CSV')) {
			add_action('added_post_meta', array($this, 'wpsl_regenerate_after_import'), 10, 4);
			add_action('updated_post_meta', array($this, 'wpsl_regenerate_after_import'), 10, 4);
		}
		// Woocommerce
		if(class_exists('WooCommerce')) {
			add_filter('woocommerce_get_endpoint_url', array('PMU_Core_Functions', 'control_trailing_slashes'), 9);
		}
	}

	/**
	 * 0. Stop redirect
	 */
	public static function stop_redirect() {
		global $wp_query, $post;

		if(!empty($wp_query->query)) {
			$query_vars = $wp_query->query;

			// WordPress Photo Seller Plugin
			if(!empty($query_vars['image_id']) && !empty($query_vars['gallery_id'])) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			}
			// Ultimate Member
			else if(!empty($query_vars['um_user']) || !empty($query_vars['um_tab']) || (!empty($query_vars['provider']) && !empty($query_vars['state']))) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			}
			// Mailster
			else if(!empty($query_vars['_mailster_page'])) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			}
			// WP Route
			else if(!empty($query_vars['WP_Route'])) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			}
			// WooCommerce Wishlist
			else if(!empty($query_vars['wishlist-action'])) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			}
			// UserPro
			else if(!empty($query_vars['up_username'])) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			}
			// The Events Calendar
			else if(!empty($query_vars['eventDisplay'])) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			}
		}

		// WPForo
		if(class_exists('wpForo')) {
			$forum_page_id = get_option('wpforo_pageid');

			if(!empty($forum_page_id) && !empty($post->ID) && $forum_page_id == $post->ID) {
				$wp_query->query_vars['do_not_redirect'] = 1;
			}
		}
	}

	/**
	 * 2. AMP hooks
	 */
	function detect_amp($uri_parts, $request_url) {
		global $amp_enabled;
		$amp_query_var = AMP_QUERY_VAR;

		// Check if AMP should be triggered
		preg_match("/^(.+?)\/({$amp_query_var})?\/?$/i", $uri_parts['uri'], $regex_parts);
		if(!empty($regex_parts[2])) {
			$uri_parts['uri'] = $regex_parts[1];
			$amp_enabled = true;
		}

		return $uri_parts;
	}

	function enable_amp($query) {
		global $amp_enabled;

		if(!empty($amp_enabled)) {
			$query[AMP_QUERY_VAR] = 1;
		}

		return $query;
	}

	/**
	 * 3. Parse Custom Permalinks import
	 */
	public static function custom_permalinks_uris() {
		global $wpdb;

		$custom_permalinks_uris = array();

	  // 1. List tags/categories
	  $table = get_option('custom_permalink_table');
	  if($table && is_array($table)) {
	    foreach ( $table as $permalink => $info ) {
	      $custom_permalinks_uris[] = array(
					'id' => "tax-" . $info['id'],
					'uri' => trim($permalink, "/")
				);
	    }
	  }

	  // 2. List posts/pages
	  $query = "SELECT p.ID, m.meta_value FROM $wpdb->posts AS p LEFT JOIN $wpdb->postmeta AS m ON (p.ID = m.post_id)  WHERE m.meta_key = 'custom_permalink' AND m.meta_value != '';";
	  $posts = $wpdb->get_results($query);
	  foreach($posts as $post) {
	    $custom_permalinks_uris[] = array(
				'id' => $post->ID,
				'uri' => trim($post->meta_value, "/"),
			);
	  }

		return $custom_permalinks_uris;
	}

	static public function import_custom_permalinks_uris() {
		global $puUris, $puBeforeSectionsHtml;

		$custom_permalinks_plugin = 'custom-permalinks/custom-permalinks.php';

		if(is_plugin_active($custom_permalinks_plugin) && !empty($_POST['disable_custom_permalinks'])) {
			deactivate_plugins($custom_permalinks_plugin);
		}

		// Get a list of imported URIs
		$custom_permalinks_uris = self::custom_permalinks_uris();

		if(!empty($custom_permalinks_uris) && count($custom_permalinks_uris) > 0) {
			foreach($custom_permalinks_uris as $item) {
				$puUris[$item['id']] = $item['uri'];
			}

			$puBeforeSectionsHtml .= PMU_Admin_Functions::get_alert_message(__( '"Custom Permalinks" URIs were imported!', 'permalink-updator' ), 'updated');
			update_option('permalink-updator-uris', $puUris);
		} else {
			$puBeforeSectionsHtml .= PMU_Admin_Functions::get_alert_message(__( 'No "Custom Permalinks" URIs were imported!', 'permalink-updator' ), 'error');
		}
	}

	/**
	 * 4. WooCommerce
	 */
	function woocommerce_detect($query) {
		global $woocommerce, $pm_query;

		$shop_page_id = get_option('woocommerce_shop_page_id');

		// WPML - translate shop page id
		$shop_page_id = apply_filters('wpml_object_id', $shop_page_id, 'page', TRUE);

		// Fix shop page
		if(!empty($pm_query['id']) && is_numeric($pm_query['id']) && $shop_page_id == $pm_query['id']) {
			$query['post_type'] = 'product';
			unset($query['pagename']);
		}

		// Fix WooCommerce pages
		if(!empty($woocommerce->query->query_vars)) {
			$query_vars = $woocommerce->query->query_vars;

			foreach($query_vars as $key => $val) {
				if(isset($query[$key])) {
					$woocommerce_page = true;
					$query['do_not_redirect'] = 1;
					break;
				}
			}
		}

		return $query;
	}

	function woocommerce_checkout_fix() {
		global $wp_query, $pm_query, $puUpdatorOptions;

		// Redirect from Shop archive to selected page
		if(is_shop() && empty($pm_query['id'])) {
			$redirect_mode = (!empty($puUpdatorOptions['general']['redirect'])) ? $puUpdatorOptions['general']['redirect'] : false;
			$redirect_shop = apply_filters('permalink-updator-redirect-shop-archive', false);
			$shop_page = get_option('woocommerce_shop_page_id');

			if($redirect_mode && $redirect_shop && $shop_page && empty($wp_query->query_vars['s'])) {
				$shop_url = get_permalink($shop_page);
				wp_safe_redirect($shop_url, $redirect_mode);
				exit();
			}
		}

		// Do not redirect "thank you" & another WooCommerce pages
		if(is_checkout() || (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url())) {
			$wp_query->query_vars['do_not_redirect'] = 1;
		}
	}

	function woocommerce_generate_permalinks_after_import($object, $data) {
		global $puUris;

		if(!empty($object)) {
			$product_id = $object->get_id();

			// Ignore variations
			if(empty($puUris[$product_id]) && $object->get_type() !== 'variation') {
				$puUris[$product_id] = PMU_URI_Functions_Post::get_default_post_uri($product_id, false, true);

				update_option('permalink-updator-uris', $puUris);
			}
		}
	}

	/**
	 * 5. Theme My Login
	 */
	function tml_keep_query_parameters($permalink, $post, $old_permalink) {
		// Get the query string from old permalink
		$get_parameters = (($pos = strpos($old_permalink, "?")) !== false) ? substr($old_permalink, $pos) : "";

		return $permalink . $get_parameters;
	}

	/**
	 * 6. Yoast SEO hooks
	 */
	function yoast_fix_sitemap_urls($permalink) {
		if(class_exists('WPSEO_Utils')) {
			$home_url = WPSEO_Utils::home_url();
			$home_protocol = parse_url($home_url, PHP_URL_SCHEME);

			$permalink = preg_replace("/^http(s)?/", $home_protocol, $permalink);
		}

		return $permalink;
	}

	function yoast_update_indexable_permalink($element_id, $new_uri, $old_uri) {
		global $wpdb;

		if(!empty($new_uri) && !empty($old_uri) && $new_uri !== $old_uri) {
			if(current_filter() == 'permalink_updator_updated_term_uri') {
				$permalink = get_term_link((int) $element_id);
				$object_type = 'term';
			} else {
				$permalink = get_permalink($element_id);
				$object_type = 'post';
			}

			if(!empty($permalink)) {
				$permalink_hash = strlen($permalink) . ':' . md5($permalink);
				$wpdb->update("{$wpdb->prefix}yoast_indexable", array('permalink' => $permalink, 'permalink_hash' => $permalink_hash), array('object_id' => $element_id, 'object_type' => $object_type), array('%s', '%s'), array('%d', '%s'));
			}
		}
	}

	function yoast_fix_canonical($url) {
		global $pm_query, $wp_rewrite;

		if(!empty($pm_query['id'])) {
			$element = get_queried_object();

			if(!empty($element->ID) && !empty($element->post_type)) {
				$new_url = get_permalink($element->ID);

				$paged = (get_query_var('page')) ? get_query_var('page') : 1;
				if($paged > 1) {
					$new_url = sprintf('%s/%d', trim($new_url, '/'), $paged);
				}
			} else if(!empty($element->taxonomy) && !empty($element->term_id)) {
				$new_url = get_term_link($element, $element->taxonomy);

				$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
				if($paged > 1) {
					$new_url = sprintf('%s/%s/%d', trim($new_url, '/'), $wp_rewrite->pagination_base, $paged);
				}
			}

			$url = (!empty($new_url)) ? PMU_Core_Functions::control_trailing_slashes($new_url) : $url;
		}

		return $url;
	}

	/**
	 * 7. Breadcrumbs
	 */
	 function filter_breadcrumbs($links) {
 		// Get post type permastructure settings
 		global $puUris, $puUpdatorOptions, $post, $wpdb, $wp, $wp_current_filter;

 		// Check if the filter should be activated
 		if(empty($puUpdatorOptions['general']['yoast_breadcrumbs']) || empty($puUris)) { return $links; }

 		// Get current post/page/term (if available)
 		$queried_element = get_queried_object();
 		if(!empty($queried_element->ID)) {
 			$element_id = $queried_element->ID;
 		} else if(!empty($queried_element->term_id)) {
 			$element_id = "tax-{$queried_element->term_id}";
 		}

 		// Get the custom permalink (if available) or the current request URL (if unavailable)
 		if(!empty($element_id) && !empty($puUris[$element_id])) {
 			$custom_uri = preg_replace("/([^\/]+)$/", '', $puUris[$element_id]);
 		} else {
 			$custom_uri = trim(preg_replace("/([^\/]+)$/", '', $wp->request), "/");
 		}

 		$all_uris = array_flip($puUris);
 		$custom_uri_parts = explode('/', trim($custom_uri));
 		$breadcrumbs = array();
 		$snowball = '';
 		$available_taxonomies = PMU_Helper_Functions::get_taxonomies_array();
 		$available_post_types = PMU_Helper_Functions::get_post_types_array();
 		$current_filter = end($wp_current_filter);

 		// Get Yoast Meta (the breadcrumbs titles can be changed in Yoast metabox)
 		$yoast_meta_terms = get_option('wpseo_taxonomy_meta');

 		// Get internal breadcrumb elements
 		foreach($custom_uri_parts as $slug) {
 			if(empty($slug)) { continue; }

 			$snowball = (empty($snowball)) ? $slug : "{$snowball}/{$slug}";

 			// 1A. Try to match any custom URI
 			if($snowball) {
 				$uri = trim($snowball, "/");
 				$element = (!empty($all_uris[$uri])) ? $all_uris[$uri] : false;

 				if(!empty($element) && strpos($element, 'tax-') !== false) {
 					$element_id = intval(preg_replace("/[^0-9]/", "", $element));
 					$element = get_term($element_id);
 				} else if(is_numeric($element)) {
 					$element = get_post($element);
 				}
 			}

 			// 1B. Try to get term
 			if(empty($element) && !empty($available_taxonomies)) {
 				$sql = sprintf("SELECT t.term_id, t.name, tt.taxonomy FROM {$wpdb->terms} AS t LEFT JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id WHERE slug = '%s' AND tt.taxonomy IN ('%s') LIMIT 1", esc_sql($slug), implode("','", array_keys($available_taxonomies)));

 				$element = $wpdb->get_row($sql);
 			}

 			// 1C. Try to get page/post
 			if(empty($element) && !empty($available_post_types)) {
 				$sql = sprintf("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_name = '%s' AND post_status = 'publish' AND post_type IN ('%s') AND post_type != 'attachment' LIMIT 1", esc_sql($slug), implode("','", array_keys($available_post_types)));

 				$element = $wpdb->get_row($sql);
 			}

 			// 2A. When the term is found, we can add it to the breadcrumbs
 			if(!empty($element->term_id)) {
 				$title = (!empty($yoast_meta_terms[$element->taxonomy][$element->term_id]['wpseo_bctitle'])) ? $yoast_meta_terms[$element->taxonomy][$element->term_id]['wpseo_bctitle'] : $element->name;

 				$breadcrumbs[] = array(
 					'text' => $title,
 					'url' => get_term_link((int) $element->term_id, $element->taxonomy),
 				);
 			}
 			// 2B. When the post/page is found, we can add it to the breadcrumbs
 			else if(!empty($element->ID)) {
 				$title = get_post_meta($element->ID, '_yoast_wpseo_bctitle', true);
 				$title = (!empty($title)) ? $title : $element->post_title;

 				$breadcrumbs[] = array(
 					'text' => $title,
 					'url' => get_permalink($element->ID),
 				);
 			}
 		}

 		// Add new links to current breadcrumbs array
 		if(!empty($links) && is_array($links)) {
 			$first_element = reset($links);
 			$last_element = end($links);
 			$breadcrumbs = (!empty($breadcrumbs)) ? $breadcrumbs : array();

 			// Support RankMath/SEOPress/WooCommerce/Slim SEO breadcrumbs
 			if(in_array($current_filter, array('wpseo_breadcrumb_links', 'rank_math/frontend/breadcrumb/items', 'seopress_pro_breadcrumbs_crumbs', 'woocommerce_get_breadcrumb', 'slim_seo_breadcrumbs_links'))) {
 				foreach($breadcrumbs as &$breadcrumb) {
 					if(isset($breadcrumb['text'])) {
 						$breadcrumb[0] = $breadcrumb['text'];
 						$breadcrumb[1] = $breadcrumb['url'];
 					}
 				}
 			}

 			if(in_array($current_filter, array('slim_seo_breadcrumbs_links'))) {
 				$links = array_merge(array($first_element), $breadcrumbs);
 			} else {
 				$links = array_merge(array($first_element), $breadcrumbs, array($last_element));
 			}
 		}

 		return array_filter($links);
 	}

	/**
	 * 8. Support WooCommerce Wishlist Plugin
	 */
	function ti_woocommerce_wishlist_uris($uri_parts, $request_url, $endpoints) {
		global $puUris, $wp;

		$wishlist_pid = tinv_get_option('general', 'page_wishlist');

		// Find the Wishlist page URI
		if(is_numeric($wishlist_pid) && !empty($puUris[$wishlist_pid])) {
			$wishlist_uri = preg_quote($puUris[$wishlist_pid], '/');

			// Extract the Wishlist ID
			preg_match("/^({$wishlist_uri})\/([^\/]+)\/?$/", $uri_parts['uri'], $output_array);

			if(!empty($output_array[2])) {
				$uri_parts['uri'] = $output_array[1];
				$uri_parts['endpoint'] = 'tinvwlID';
				$uri_parts['endpoint_value'] = $output_array[2];
			}
		}

		return $uri_parts;
	}

	/**
	 * 9. Revisionize
	 */
	function revisionize_keep_post_uri($old_id, $new_id) {
		global $puUris;

		// Copy the custom URI from original post and apply it to the new temp. revision post
		if(!empty($puUris[$old_id])) {
			$puUris[$new_id] = $puUris[$old_id];

			update_option('permalink-updator-uris', $puUris);
		}
	}

	function revisionize_clone_uri($old_id, $new_id) {
		global $puUris;

		if(!empty($puUris[$new_id])) {
			// Copy the custom URI from revision post and apply it to the original post
			$puUris[$old_id] = $puUris[$new_id];
			unset($puUris[$new_id]);

			update_option('permalink-updator-uris', $puUris);
		}
	}

	/**
	 * 10. WP All Import
	 */
	function wpaiextra_uri_display($content_type, $current_values) {
		// Check if post type is supported
		if($content_type !== 'taxonomies' && PMU_Helper_Functions::is_disabled($content_type)) {
			return;
		}

		// Get custom URI format
		$custom_uri = (!empty($current_values['custom_uri'])) ? sanitize_text_field($current_values['custom_uri']) : "";

		$html = '<div class="wpallimport-collapsed closed wpallimport-section">';
		$html .= '<div class="wpallimport-content-section">';
		$html .= sprintf('<div class="wpallimport-collapsed-header"><h3>%s</h3></div>', __('Permalink Supervisor', 'permalink-updator'));
		$html .= '<div class="wpallimport-collapsed-content">';

		$html .= '<div class="template_input">';
		$html .= PMU_Admin_Functions::generate_option_field('custom_uri', array('extra_atts' => 'style="width:100%; line-height: 25px;"', 'placeholder' => __('Custom URI', 'permalink-updator'), 'value' => $custom_uri));
		$html .= wpautop(sprintf(__('If empty, a default permalink based on your current <a href="%s" target="_blank">permastructure settings</a> will be used.', 'permalink-updator'), PMU_Admin_Functions::get_admin_url('&section=permastructs')));
		$html .= '</div>';

		// $html .= print_r($current_values, true);

		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		echo $html;
	}

	function wpai_api_options($all_options) {
		return $all_options + array('custom_uri' => null);
	}

	function wpai_api_register($addons) {
		if(empty($addons[PMU_PLPLUGIN_SLUG])) {
			$addons[PMU_PLPLUGIN_SLUG] = 1;
		}
		return $addons;
	}

	function wpai_api_parse($functions) {
		$functions[PMU_PLPLUGIN_SLUG] = array($this, 'wpai_api_parse_function');
		return $functions;
	}

	function wpai_api_import($functions) {
		$functions[PMU_PLPLUGIN_SLUG] = array($this, 'wpai_api_import_function');
		return $functions;
	}

	function wpai_api_parse_function($data) {
		extract($data);

		$data = array(); // parsed data
		$option_name = 'custom_uri';

		if(!empty($import->options[$option_name])) {
			$this->logger = $data['logger'];
			$cxpath = $xpath_prefix . $import->xpath;
			$tmp_files = array();

			if(isset($import->options[$option_name]) && $import->options[$option_name] != '') {
				if($import->options[$option_name] == "xpath") {
					if ($import->options[$this->slug]['xpaths'][$option_name] == "") {
						$count and $this->data[$option_name] = array_fill(0, $count, "");
					} else {
						$data[$option_name] = XmlImportParser::factory($xml, $cxpath, (string) $import->options['xpaths'][$option_name], $file)->parse();
						$tmp_files[] = $file;
					}
				} else {
					$data[$option_name] = XmlImportParser::factory($xml, $cxpath, (string) $import->options[$option_name], $file)->parse();
					$tmp_files[] = $file;
				}
			} else {
				$data[$option_name] = array_fill(0, $count, "");
			}

			foreach ($tmp_files as $file) {
				unlink($file);
			}
		}

		return $data;
	}

	function wpai_api_import_function($importData, $parsedData) {
		global $puUris;

		// Check if the array with $parsedData is not empty
		if(empty($parsedData) || empty($importData['post_type'])) { return; }

		// Check if the imported elements are terms
		if($importData['post_type'] == 'taxonomies') {
			$is_term = true;
		} else if(PMU_Helper_Functions::is_disabled($importData['post_type'], 'post_type')) {
			return;
		}

		// Get the parsed custom URI
		$index = (isset($importData['i'])) ? $importData['i'] : false;
		$pid = (!empty($importData['pid'])) ? $importData['pid'] : false;

		// Prepend "tax-" prefix if needed
		$pid = (!empty($is_term) && !empty($pid)) ? "tax-{$pid}" : $pid;

		if(isset($index) && !empty($pid) && !empty($parsedData['custom_uri'][$index])) {
			$custom_uri = PMU_Helper_Functions::sanitize_title($parsedData['custom_uri'][$index]);

			if(!empty($custom_uri)) {
				$puUris[$pid] = $custom_uri;
				update_option('permalink-updator-uris', $puUris);
			}
		}
	}

	function wpai_save_redirects($pid) {
		global $puExternalRedirects, $puUris;

		$external_url = get_post_meta($pid, '_external_redirect', true);
		$external_url = (empty($external_url)) ? get_post_meta($pid, 'external_redirect', true) : $external_url;

		if($external_url && class_exists('PMU_Pro_Functions')) {
			PMU_Pro_Functions::save_external_redirect($external_url, $pid);
		}
	}

	function wpai_schedule_regenerate_uris_after_xml_import($import_id) {
		global $wpdb;

		$post_ids = $wpdb->get_col("SELECT post_id FROM {$wpdb->prefix}pmxi_posts WHERE import_id = {$import_id}");
		$chunks = array_chunk($post_ids, 200);

		// Schedule URI regenerate and split into bulks
		foreach($chunks as $i => $chunk) {
			wp_schedule_single_event(time() + ($i * 30), 'wpai_regenerate_uris_after_import_event', array($chunk));
		}
	}

	function wpai_regenerate_uris_after_import($post_ids) {
		global $puUris;

		if(!is_array($post_ids)) { return; }

		foreach($post_ids as $id) {
			if(!empty($puUris[$id])) { continue; }
			$puUris[$id] = PMU_URI_Functions_Post::get_default_post_uri($id);
		}

		update_option('permalink-updator-uris', $puUris);
	}

	/**
	 * 11. Duplicate Page
	 */
	function duplicate_custom_uri($new_post_id, $old_post) {
		global $puUris;

		if(!empty($old_post->ID)) {
			$old_post_id = intval($old_post->ID);

			// Clone custom permalink (if set for cloned post/page)
			if(!empty($puUris[$old_post_id])) {
				$old_post_uri = $puUris[$old_post_id];
				$new_post_uri = preg_replace('/(.+?)(\.[^\.]+$|$)/', '$1-2$2', $old_post_uri);

				$puUris[$new_post_id] = $new_post_uri;
				update_option('permalink-updator-uris', $puUris);
			}
		}
	}

	/**
	 * 12. My Listing by 27collective
	 */
	public function replace_listing_custom_field_tags($default_uri, $native_slug, $element, $slug, $native_uri) {
		global $puUris;

		// Use only for "listing" post type & custom permalink
		if(empty($element->post_type) || $element->post_type !== 'job_listing' || $native_uri) { return $default_uri; }

		// A. Listing type
		if(strpos($default_uri, '%listing-type%') !== false || strpos($default_uri, '%listing_type%') !== false) {
			$listing_type = get_post_meta($element->ID, '_case27_listing_type', true);

			if(!empty($listing_type)) {
				$listing_type = PMU_Helper_Functions::sanitize_title($listing_type, true);
				$default_uri = str_replace(array('%listing-type%', '%listing_type%'), $listing_type, $default_uri);
			}
		}

		// B. Listing location
		if(strpos($default_uri, '%listing-location%') !== false || strpos($default_uri, '%listing_location%') !== false) {
			$listing_location = get_post_meta($element->ID, '_job_location', true);

			if(!empty($listing_type)) {
				$listing_location = PMU_Helper_Functions::sanitize_title($listing_location, true);
				$default_uri = str_replace(array('%listing-location%', '%listing_location%'), $listing_location, $default_uri);
			}
		}

		// C. Listing region
		if(strpos($default_uri, '%listing-region%') !== false || strpos($default_uri, '%listing_region%') !== false) {
			$listing_region_terms = wp_get_object_terms($element->ID, 'region');
			$listing_region_term = (!is_wp_error($listing_region_terms) && !empty($listing_region_terms) && is_object($listing_region_terms[0])) ? PMU_Helper_Functions::get_lowest_element($listing_region_terms[0], $listing_region_terms) : "";

			if(!empty($listing_region_term)) {
				$listing_region = PMU_Helper_Functions::get_term_full_slug($listing_region_term, $listing_region_terms, 2, false);
				$listing_region = PMU_Helper_Functions::sanitize_title($listing_region, true);

				$default_uri = str_replace(array('%listing-region%', '%listing_region%'), $listing_region, $default_uri);
			}
		}

		// D. Listing category
		if(strpos($default_uri, '%listing-category%') !== false || strpos($default_uri, '%listing_category%') !== false) {
			$listing_category_terms = wp_get_object_terms($element->ID, 'job_listing_category');
			$listing_category_term = (!is_wp_error($listing_category_terms) && !empty($listing_category_terms) && is_object($listing_category_terms[0])) ? PMU_Helper_Functions::get_lowest_element($listing_category_terms[0], $listing_category_terms) : "";

			if(!empty($listing_category_term)) {
				$listing_category = PMU_Helper_Functions::get_term_full_slug($listing_category_term, $listing_category_terms, 2, false);
				$listing_category = PMU_Helper_Functions::sanitize_title($listing_category, true);

				$default_uri = str_replace(array('%listing-category%', '%listing_category%'), $listing_category, $default_uri);
			}
		}

		return $default_uri;
	}


	public function wpsl_regenerate_after_import($meta_id, $post_id, $meta_key, $meta_value) {
		global $puUris;

		if(strpos($meta_key, 'wpsl_') !== false && isset($_POST['wpsl_csv_import_nonce'])) {
			$default_uri = PMU_URI_Functions_Post::get_default_post_uri($post_id);

			if($default_uri) {
				$puUris[$post_id] = $default_uri;
				update_option('permalink-updator-uris', $puUris);
			}
		}
	}

}
