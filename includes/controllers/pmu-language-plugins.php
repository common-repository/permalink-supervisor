<?php
class PMU_Language_Plugins extends PMU_Class {

	public function __construct() {
		add_action('init', array($this, 'init_hooks'), 99);
	}

	function init_hooks() {
		global $sitepress_settings, $puUpdatorOptions, $polylang, $translate_press_settings;
		if($sitepress_settings || !empty($polylang->links_model->options) || class_exists('TRP_Translate_Press')) {
			if(!empty($puUpdatorOptions['general']['fix_language_mismatch'])) {
				add_filter('permalink_updator_detected_post_id', array($this, 'fix_language_mismatch'), 9, 3);
				add_filter('permalink_updator_detected_term_id', array($this, 'fix_language_mismatch'), 9, 3);
			}

			add_filter('permalink_updator_uri_editor_extra_info', array($this, 'language_column_uri_editor'), 9, 3);
			add_filter('permalink_updator_is_front_page', array($this, 'wpml_is_front_page'), 9, 3);
			$mode = 0;

			if(isset($sitepress_settings['language_negotiation_type'])) {
				$url_settings = $sitepress_settings['language_negotiation_type'];

				if(in_array($sitepress_settings['language_negotiation_type'], array(1, 2))) {
					$mode = 'prepend';
				} else if($sitepress_settings['language_negotiation_type'] == 3) {
					$mode = 'append';
				}
			}else if(isset($polylang->links_model->options['force_lang'])) {
				$url_settings = $polylang->links_model->options['force_lang'];

				if(in_array($url_settings, array(1, 2, 3))) {
					$mode = 'prepend';
				}
			}else if(class_exists('TRP_Translate_Press')) {
				$translate_press_settings = get_option('trp_settings');

				$mode = 'prepend';
			}

			if($mode === 'prepend') {
				add_filter('permalink_updator_detect_uri', array($this, 'detect_uri_language'), 9, 3);
				add_filter('permalink_updator_filter_permalink_base', array($this, 'prepend_lang_prefix'), 9, 2);
				add_filter('template_redirect', array($this, 'wpml_redirect'), 0, 998 );
			} else if($mode === 'append') {
				add_filter('permalink_updator_filter_final_post_permalink', array($this, 'append_lang_prefix'), 5, 2);
				add_filter('permalink_updator_filter_final_term_permalink', array($this, 'append_lang_prefix'), 5, 2);
				add_filter('permalink_updator_detect_uri', array($this, 'wpml_ignore_lang_query_parameter'), 9);
			}

			
			add_filter('permalink_updator_filter_permastructure', array($this, 'translate_permastructure'), 9, 2);

			
			if(class_exists('WPML_Slug_Translation')) {
				add_filter('permalink_updator_filter_post_type_slug', array($this, 'wpml_translate_post_type_slug'), 9, 3);
			}

			
			if(class_exists('WCML_Endpoints')) {
				add_filter('request', array($this, 'wpml_translate_wc_endpoints'), 99999);
			}

			
			if(class_exists('WPML_Translation_Editor_UI')) {
				add_filter('wpml_tm_adjust_translation_fields', array($this, 'wpml_translation_edit_uri'), 999, 2);
				add_filter('wpml-translation-editor-fetch-job', array($this, 'wpml_translation_save_uri'), 999, 2);
			}

			add_action('icl_make_duplicate', array($this, 'wpml_duplicate_uri'), 999, 4);
		}
	}

	public static function get_language_code($element) {
		global $TRP_LANGUAGE, $translate_press_settings;

		// Fallback
		if(is_string($element) && strpos($element, 'tax-') !== false) {
			$element_id = intval(preg_replace("/[^0-9]/", "", $element));
			$element = get_term($element_id);
		} else if(is_numeric($element)) {
			$element = get_post($element);
		}

		// A. TranslatePress
		if(!empty($TRP_LANGUAGE)) {
			$lang_code = self::get_translatepress_language_code($TRP_LANGUAGE);
		}
		// B. WPML & Polylang
		else {
			if(isset($element->post_type)) {
				$element_id = $element->ID;
				$element_type = $element->post_type;
			} else if(isset($element->taxonomy)) {
				$element_id = $element->term_taxonomy_id;
				$element_type = $element->taxonomy;
			} else {
				return false;
			}

			$lang_code = apply_filters('wpml_element_language_code', null, array('element_id' => $element_id, 'element_type' => $element_type));
		}

		// Use default language if nothing detected
		return ($lang_code) ? $lang_code : self::get_default_language();
	}

	public static function get_translatepress_language_code($lang) {
		global $translate_press_settings;

		if(!empty($translate_press_settings['url-slugs'])) {
			$lang_code = (!empty($translate_press_settings['url-slugs'][$lang])) ? $translate_press_settings['url-slugs'][$lang] : '';
		}

		return (!empty($lang_code)) ? $lang_code : false;
	}

	public static function get_default_language() {
		global $sitepress, $translate_press_settings;

		if(function_exists('pll_default_language')) {
			$def_lang = pll_default_language('slug');
		} else if(is_object($sitepress)) {
			$def_lang = $sitepress->get_default_language();
		} else if(!empty($translate_press_settings['default-language'])) {
			$def_lang = self::get_translatepress_language_code($translate_press_settings['default-language']);
		} else {
			$def_lang = '';
		}

		return $def_lang;
	}

	public static function get_all_languages($exclude_default_language = false) {
		global $sitepress, $sitepress_settings, $polylang, $translate_press_settings;

		$languages_array = $active_languages = array();
		$default_language = self::get_default_language();

		if(!empty($sitepress_settings['active_languages'])) {
			$languages_array = $sitepress_settings['active_languages'];
		} elseif(function_exists('pll_languages_list')) {
			$languages_array = pll_languages_list(array('fields' => null));
		} if(!empty($translate_press_settings['url-slugs'])) {
			// $languages_array = $translate_press_settings['url-slugs'];
		}

		// Get native language names as value
		if($languages_array) {
			foreach($languages_array as $val) {
				if(!empty($sitepress)) {
					$lang = $val;
					$lang_details = $sitepress->get_language_details($lang);
					$language_name = $lang_details['native_name'];
				} else if(!empty($val->name)) {
					$lang = $val->slug;
					$language_name = $val->name;
				}

				$active_languages[$lang] = (!empty($language_name)) ? sprintf('%s <span>(%s)</span>', $language_name, $lang) : '-';
			}

			// Exclude default language if needed
			if($exclude_default_language && $default_language && !empty($active_languages[$default_language])) {
				unset($active_languages[$default_language]);
			}
		}

		return (array) $active_languages;
	}

	function fix_language_mismatch($item_id, $uri_parts, $is_term = false) {
		global $wp, $language_code;

		if($is_term) {
			$element = get_term($item_id);
			if(!empty($element) && !is_wp_error($element)) {
				$element_id = $element->term_taxonomy_id;
				$element_type = $element->taxonomy;
			} else {
				return false;
			}
		} else {
			$element = get_post($item_id);

			if(!empty($element->post_type)) {
				$element_id = $item_id;
				$element_type = $element->post_type;
			}
		}

		// Stop if no term or post is detected
		if(empty($element)) { return false; }

		$language_code = self::get_language_code($element);

		if(!empty($uri_parts['lang']) && ($uri_parts['lang'] != $language_code)) {
			$wpml_item_id = apply_filters('wpml_object_id', $element_id, $element_type);
			$item_id = (is_numeric($wpml_item_id)) ? $wpml_item_id : $item_id;
		}

		return $item_id;
	}

	function detect_uri_language($uri_parts, $request_url, $endpoints) {
		global $sitepress, $sitepress_settings, $polylang, $translate_press_settings;

		if(!empty($sitepress_settings['active_languages'])) {
			$languages_list = (array) $sitepress_settings['active_languages'];
		} elseif(function_exists('pll_languages_list')) {
			$languages_array = pll_languages_list();
			$languages_list = (is_array($languages_array)) ? (array) $languages_array : "";
		} elseif($translate_press_settings['url-slugs']) {
			$languages_list = $translate_press_settings['url-slugs'];
		}

		if(is_array($languages_list)) {
			$languages_list = implode("|", $languages_list);
		} else {
			return $uri_parts;
		}

		$default_language = self::get_default_language();

		// Fix for multidomain language configuration
		if((isset($sitepress_settings['language_negotiation_type']) && $sitepress_settings['language_negotiation_type'] == 2) || (!empty($polylang->options['force_lang']) && $polylang->options['force_lang'] == 3)) {
			if(!empty($polylang->options['domains'])) {
				$domains = (array) $polylang->options['domains'];
			} else if(!empty($sitepress_settings['language_domains'])) {
				$domains = (array) $sitepress_settings['language_domains'];
			}

			foreach($domains as &$domain) {
				$domain = preg_replace('/((http(s)?:\/\/(www\.)?)|(www\.))?(.+?)\/?$/', 'http://$6', $domain);
			}

			$request_url = trim(str_replace($domains, "", $request_url), "/");
		}

		if(!empty($languages_list)) {
			//preg_match("/^(?:({$languages_list})\/)?(.+?)(?|\/({$endpoints})[\/$]([^\/]*)|\/()([\d+]))?\/?$/i", $request_url, $regex_parts);
			preg_match("/^(?:({$languages_list})\/)?(.+?)(?|\/({$endpoints})(?|\/(.*)|$)|\/()([\d]+)\/?)?$/i", $request_url, $regex_parts);

			$uri_parts['lang'] = (!empty($regex_parts[1])) ? $regex_parts[1] : $default_language;
			$uri_parts['uri'] = (!empty($regex_parts[2])) ? $regex_parts[2] : "";
			$uri_parts['endpoint'] = (!empty($regex_parts[3])) ? $regex_parts[3] : "";
			$uri_parts['endpoint_value'] = (!empty($regex_parts[4])) ? $regex_parts[4] : "";
		}

		return $uri_parts;
	}

	function prepend_lang_prefix($base, $element) {
		global $sitepress_settings, $polylang, $puUris, $translate_press_settings;

		$language_code = self::get_language_code($element);
		$default_language_code = self::get_default_language();
		$home_url = get_home_url();

		// Hide language code if "Use directory for default language" option is enabled
		$hide_prefix_for_default_lang = ((isset($sitepress_settings['urls']['directory_for_default_language']) && $sitepress_settings['urls']['directory_for_default_language'] != 1) || !empty($polylang->links_model->options['hide_default']) || !empty($translate_press_settings['add-subdirectory-to-default-language'])) ? true : false;

		// Last instance - use language paramater from &_GET array
		if(is_admin()) {
			$language_code = (empty($language_code) && !empty($_GET['lang'])) ? sanitize_text_field($_GET['lang']) : $language_code;
		}

		// Adjust URL base
		if(!empty($language_code)) {
			// A. Different domain per language
			if((isset($sitepress_settings['language_negotiation_type']) && $sitepress_settings['language_negotiation_type'] == 2) || (!empty($polylang->options['force_lang']) && $polylang->options['force_lang'] == 3)) {

				if(!empty($polylang->options['domains'])) {
					$domains = $polylang->options['domains'];
				} else if(!empty($sitepress_settings['language_domains'])) {
					$domains = $sitepress_settings['language_domains'];
				}

				$is_term = (!empty($element->term_taxonomy_id)) ? true : false;
				$element_id = ($is_term) ? "tax-{$element->term_taxonomy_id}" : $element->ID;

				// Filter only custom permalinks
				if(empty($puUris[$element_id]) || empty($domains)) { return $base; }

				// Replace the domain name
				if(!empty($domains[$language_code])) {
					$base = trim($domains[$language_code], "/");

					// Append URL scheme
					if(!preg_match("~^(?:f|ht)tps?://~i", $base)) {
						$scehme = parse_url($home_url, PHP_URL_SCHEME);
						$base = "{$scehme}://{$base}";
			    }
				}
			}
			// B. Prepend language code
			else if(!empty($polylang->options['force_lang']) && $polylang->options['force_lang'] == 2) {
				if($hide_prefix_for_default_lang && ($default_language_code == $language_code)) {
					return $base;
				} else {
					$base = preg_replace('/(https?:\/\/)/', "$1{$language_code}.", $home_url);
				}
			}
			// C. Append prefix
			else {
				if($hide_prefix_for_default_lang && ($default_language_code == $language_code)) {
					return $base;
				} else {
					$base .= "/{$language_code}";
				}
			}
		}

		return $base;
	}

	function append_lang_prefix($permalink, $element) {
		global $sitepress_settings, $polylang, $puUris;

		$language_code = self::get_language_code($element);
		$default_language_code = self::get_default_language();

		// Last instance - use language paramater from &_GET array
		if(is_admin()) {
			$language_code = (empty($language_code) && !empty($_GET['lang'])) ? sanitize_text_field($_GET['lang']) : $language_code;
		}

		// B. Append ?lang query parameter
		if(isset($sitepress_settings['language_negotiation_type']) && $sitepress_settings['language_negotiation_type'] == 3) {
			if($default_language_code == $language_code) {
				return $permalink;
			} else {
				$permalink .= "?lang={$language_code}";
			}
		}

		return $permalink;
	}

	function language_column_uri_editor($output, $column, $element) {
		$language_code = self::get_language_code($element);
		$output .= (!empty($language_code)) ? sprintf(" | <span><strong>%s:</strong> %s</span>", __("Language"), $language_code) : "";

		return $output;
	}

	function wpml_is_front_page($bool, $page_id, $front_page_id) {
		$default_language_code = self::get_default_language();
		$page_id = apply_filters('wpml_object_id', $page_id, 'page', true, $default_language_code);

		return (!empty($page_id) && $page_id == $front_page_id) ? true : $bool;
	}

	function wpml_ignore_lang_query_parameter($uri_parts) {
		global $puUris;

		foreach($puUris as &$uri) {
			$uri = trim(strtok($uri, '?'), "/");
		}

		return $uri_parts;
	}

	function wpml_redirect() {
		global $language_code, $wp_query;

		if(!empty($language_code) && defined('ICL_LANGUAGE_CODE') && ICL_LANGUAGE_CODE != $language_code && !empty($wp_query->query['do_not_redirect'])) {
			unset($wp_query->query['do_not_redirect']);
		}
	}

	function translate_permastructure($permastructure, $element) {
		global $puPermastructs, $pagenow;;

		// Get element language code
		if(!empty($_REQUEST['data']) && strpos($_REQUEST['data'], "target_lang")) {
			$language_code = preg_replace('/(.*target_lang=)([^=&]+)(.*)/', '$2', $_REQUEST['data']);
		} else if(in_array($pagenow, array('post.php', 'post-new.php')) && !empty($_GET['lang'])) {
			$language_code = sanitize_text_field($_GET['lang']);
		} else if(!empty($_REQUEST['icl_post_language'])) {
			$language_code = sanitize_text_field($_REQUEST['icl_post_language']);
		} else if(!empty($_POST['action']) && sanitize_text_field($_POST['action']) == 'pu_save_permalink' && defined('ICL_LANGUAGE_CODE')) {
			$language_code = ICL_LANGUAGE_CODE;
		} else {
			$language_code = self::get_language_code($element);
		}

		if(!empty($element->ID)) {
			$translated_permastructure = (!empty($puPermastructs["post_types"]["{$element->post_type}_{$language_code}"])) ? $puPermastructs["post_types"]["{$element->post_type}_{$language_code}"] : '';
		} else if(!empty($element->term_id)) {
			$translated_permastructure = (!empty($puPermastructs["taxonomies"]["{$element->taxonomy}_{$language_code}"])) ? $puPermastructs["taxonomies"]["{$element->taxonomy}_{$language_code}"] : '';
		}

		return (!empty($translated_permastructure)) ? $translated_permastructure : $permastructure;
	}

	function wpml_translate_post_type_slug($post_type_slug, $element, $post_type) {
		$post = (is_integer($element)) ? get_post($element) : $element;
		$language_code = self::get_language_code($post);

		$post_type_slug = apply_filters('wpml_get_translated_slug', $post_type_slug, $post_type, $language_code);

		// Translate %post_type% tag in custom permastructures
		return $post_type_slug;
	}

	function wpml_translate_wc_endpoints($request) {
		global $woocommerce, $wpdb;

		if(!empty($woocommerce->query->query_vars)) {
			// Get WooCommerce original endpoints
			$endpoints = $woocommerce->query->query_vars;

			// Get all endppoint translations
			$endpoint_translations = $wpdb->get_results("SELECT t.value AS translated_endpoint, t.language, s.value AS endpoint FROM {$wpdb->prefix}icl_string_translations AS t LEFT JOIN {$wpdb->prefix}icl_strings AS s ON t.string_id = s.id WHERE context = 'WP Endpoints'");

			// Replace translate endpoint with its original name
			foreach($endpoint_translations as $endpoint) {
				if(isset($request[$endpoint->translated_endpoint])) {
					$request[$endpoint->endpoint] = $request[$endpoint->translated_endpoint];
					unset($request[$endpoint->translated_endpoint]);
				}
			}
		}

		return $request;
	}

	function wpml_translation_edit_uri($fields, $job) {
		global $puUris;

		$element_type = (strpos($job->original_post_type, 'post_') !== false) ? preg_replace('/^(post_)/', '', $job->original_post_type) : '';

		if(!empty($element_type)) {
			$original_id = $job->original_doc_id;
			$translation_id = apply_filters('wpml_object_id', $original_id, $element_type, false, $job->language_code);

			$original_custom_uri = PMU_URI_Functions_Post::get_post_uri($original_id, true);
			$translation_custom_uri = PMU_URI_Functions_Post::get_post_uri($translation_id, true);

			$fields[] = array(
				'field_type' => 'pm-custom_uri',
				//'tid' => 9999,
				'field_data' => $original_custom_uri,
				'field_data_translated' => $translation_custom_uri,
				'field_style' => '0',
				'title' => 'Custom URI',
			);
		}

		return $fields;
	}

	function wpml_translation_save_uri($job, $job_details) {
		global $puUris;

		if(!empty($_POST['data'])) {
			$data = array();
			$post_data = \WPML_TM_Post_Data::strip_slashes_for_single_quote($_POST['data']);
			parse_str($post_data, $data);

			if(isset($data['fields']['pm-custom_uri'])) {
				$original_id = $data['job_post_id'];
				$element_type = (strpos($data['job_post_type'], 'post_') !== false) ? preg_replace('/^(post_)/', '', $data['job_post_type']) : '';

				$translation_id = apply_filters('wpml_object_id', $original_id, $element_type, false, $data['target_lang']);
				$puUris[$translation_id] = (!empty($data['fields']['pm-custom_uri']['data'])) ? PMU_Helper_Functions::sanitize_title($data['fields']['pm-custom_uri']['data'], true) : PMU_URI_Functions_Post::get_default_post_uri($translation_id);

				update_option('permalink-updator-uris', $puUris);
			}
		}

		return $job;
	}

	function wpml_duplicate_uri($master_post_id, $lang, $post_array, $id) {
		global $puUris;

		// Trigger the function only if duplicate is created in the metabox
		if(empty($_POST['action']) || sanitize_text_field($_POST['action']) !== 'make_duplicates') { return; }

		$puUris[$id] = PMU_URI_Functions_Post::get_default_post_uri($id);

		update_option('permalink-updator-uris', $puUris);
	}

}
