<?php
class PMU_Actions extends PMU_Class {

	public function __construct() {
		add_action('admin_init', array($this, 'trigger_action'), 9);
		add_action('admin_init', array($this, 'extra_actions'));
		if(is_admin()) {
			add_action('wp_ajax_pm_bulk_tools', array($this, 'pm_bulk_tools'));
			add_action('wp_ajax_pu_save_permalink', array($this, 'pu_save_permalink'));
			add_action('wp_ajax_detect_duplicates',  array($this, 'ajax_detect_duplicates') );
		}

		add_action('clean_permalinks_event', array($this, 'clean_permalinks_hook'));
		add_action('init', array($this, 'clean_permalinks_cronjob'));
	}
	public function trigger_action() {
		global $puAfterSectionsHtml;

		// 1. Check if the form was submitted
		if(empty($_POST)) {
			return;
		}

		// 2. Do nothing if search query is not empty
		if(isset($_REQUEST['search-submit']) || isset($_REQUEST['months-filter-button'])) {
			return;
		}

		$actions_map = array(
			'uri_editor' => array('function' => 'update_all_permalinks', 'display_uri_table' => true),
			'permalink_updator_options' => array('function' => 'save_settings'),
			'permalink_updator_permastructs' => array('function' => 'save_permastructures'),
			'flush_sitemaps' => array('function' => 'flush_sitemaps'),
			'import' => array('function' => 'import_custom_permalinks_uris'),
		);

		// 3. Find the action
		foreach($actions_map as $action => $map) {
			if(isset($_POST[$action]) && wp_verify_nonce($_POST[$action], 'permalink-updator')) {
				// Execute the function
				$output = call_user_func(array($this, $map['function']));

				// Get list of updated URIs
				if(!empty($map['display_uri_table'])) {
					$updated_slugs_count = (isset($output['updated_count']) && $output['updated_count'] > 0) ? $output['updated_count'] : false;
					$updated_slugs_array = ($updated_slugs_count) ? $output['updated'] : '';
				}

				// Trigger only one function
				break;
			}
		}

		// 4. Display the slugs table (and append the globals)
		if(isset($updated_slugs_count)) {
			$puAfterSectionsHtml .= PMU_Admin_Functions::display_updated_slugs($updated_slugs_array);
		}
	}

	/**
	* Save settings
	*/
	public static function save_settings($field = false, $value = false, $display_alert = true) {
		global $puUpdatorOptions, $puBeforeSectionsHtml;

		// Info: The settings array is used also by "Screen Options"
		$new_options = $puUpdatorOptions;
		//$new_options = array();

		// Save only selected field/sections
		if($field && $value) {
			$new_options[$field] = $value;
		} else {
			$post_fields = sanitize_text_field($_POST);
			foreach($post_fields as $option_name => $option_value) {
				$new_options[$option_name] = $option_value;
			}
		}


		// Sanitize & override the global with new settings
		$new_options = PMU_Helper_Functions::sanitize_array($new_options);
		$puUpdatorOptions = $new_options = array_filter($new_options);

		// Save the settings in database
		update_option('permalink-updator', $new_options);

		// Display the message
		$puBeforeSectionsHtml .= ($display_alert) ? PMU_Admin_Functions::get_alert_message(__( 'The settings has been update successfully.', 'permalink-updator' ), 'updated') : "";
	}

	/**
	 * Trigger bulk tools via AJAX
	 */
	function pm_bulk_tools() {
		global $sitepress, $wp_filter;

		// Define variables
		$return = array('alert' => PMU_Admin_Functions::get_alert_message(__( '<strong>No slugs</strong> were updated!', 'permalink-updator' ), 'error updated_slugs'));

		// Get the name of the function
		if(isset($_POST['regenerate']) && wp_verify_nonce($_POST['regenerate'], 'permalink-updator')) {
			$function_name = 'regenerate_all_permalinks';
			$uniq_id = sanitize_text_field($_POST['pm_session_id']);
		} else if(isset($_POST['find_and_replace']) && wp_verify_nonce($_POST['find_and_replace'], 'permalink-updator') && !empty($_POST['old_string']) && !empty($_POST['new_string'])) {
			$function_name = 'find_and_replace';
			$uniq_id = sanitize_text_field($_POST['pm_session_id']);
		}

		// Check if both strings are set for "Find and replace" tool
		if(!empty($function_name)) {
			// Hotfix for WPML (start)
			if($sitepress) {
				remove_filter('get_terms_args', array($sitepress, 'get_terms_args_filter'), 10);
	    	remove_filter('get_term', array($sitepress, 'get_term_adjust_id'), 1);
	    	remove_filter('terms_clauses', array($sitepress, 'terms_clauses'), 10);
				remove_filter('get_pages', array($sitepress, 'get_pages_adjust_ids'), 1);
			}

			// Get the mode
			$mode = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'custom_uris';

			// Get the content type
			if(!empty($_POST['content_type']) && sanitize_text_field($_POST['content_type']) == 'taxonomies') {
				$class_name = 'PMU_URI_Functions_Tax';
			} else {
				$class_name = 'PMU_URI_Functions_Post';
			}

			// Get items (try to get them from transient)
			$items = get_transient("pm_{$uniq_id}");
			$progress = get_transient("pm_{$uniq_id}_progress");

			$first_chunk = true;
			$chunk_size = apply_filters('permalink_updator_chunk_size', 50);

			if(empty($items)) {
				$items = $class_name::get_items();

				// Set stats (to display the progress)
				$total = count($items);

				// Split items array into chunks and save them to transient
				$items = array_chunk($items, $chunk_size);

				set_transient("pm_{$uniq_id}", $items, 300);
				set_transient("pm_{$uniq_id}_progress", 0, 300);
			}

			// Get homepage URL and ensure that it ends with slash
			$home_url = PMU_Helper_Functions::get_permalink_base() . "/";

			// Process the variables from $_POST object
			$old_string = (!empty($_POST['old_string'])) ? str_replace($home_url, '', esc_sql(sanitize_text_field($_POST['old_string']))) : '';
			$new_string = (!empty($_POST['old_string'])) ? str_replace($home_url, '', esc_sql(sanitize_text_field($_POST['new_string']))) : '';

			// Process only one subarray
			if(!empty($items[0])) {
				$chunk = $items[0];

				// Remove from array & update the transient
				unset($items[0]);
				$items = array_values($items);
				set_transient("pm_{$uniq_id}", $items, 300);

				// Check if posts or terms should be updated
				if($function_name == 'find_and_replace') {
					$output = $class_name::find_and_replace($chunk, $mode, $old_string, $new_string);
				} else {
					$output = $class_name::regenerate_all_permalinks($chunk, $mode);
				}

				if(!empty($output['updated_count'])) {
					$return = array_merge($return, (array) PMU_Admin_Functions::display_updated_slugs($output['updated'], true, $first_chunk));
					$return['updated_count'] = $output['updated_count'];
				}

				// Send total number of processed items with a first chunk
				if(!empty($total)) {
					$return['total'] = $total;
				}

				// Check if there are any chunks left
				if(count($items) > 0) {
					// Update progress
					$progress += $chunk_size;
					set_transient("pm_{$uniq_id}_progress", $progress, 300);

					$return['left_chunks'] = true;
					$return['progress'] = $progress;
				} else {
					delete_transient("pm_{$uniq_id}");
					delete_transient("pm_{$uniq_id}_progress");
				}
			}

			// Hotfix for WPML (end)
			if($sitepress) {
				add_filter('terms_clauses', array($sitepress, 'terms_clauses'), 10, 4);
	    		add_filter('get_term', array($sitepress, 'get_term_adjust_id'), 1, 1);
	    		add_filter('get_terms_args', array($sitepress, 'get_terms_args_filter'), 10, 2);
				add_filter('get_pages', array($sitepress, 'get_pages_adjust_ids'), 1, 2);
			}
		}

		wp_send_json($return);
		die();
	}

	/**
	 * Save permalink via AJAX
	 */
	public function pu_save_permalink() {
		$element_id = (!empty($_POST['permalink-updator-edit-uri-element-id'])) ? sanitize_text_field($_POST['permalink-updator-edit-uri-element-id']) : '';

		if(!empty($element_id) && is_numeric($element_id)) {
			PMU_URI_Functions_Post::update_post_uri($element_id);

			// Reload URI Editor & clean post cache
			clean_post_cache($element_id);
			$element = get_post($element_id);
			$html = PMU_Admin_Functions::display_uri_box($element, true);

			echo $html;
			die();
		}
	}

	/**
	* Update all permalinks in "Permalink Editor"
	*/
	function update_all_permalinks() {
		// Check if posts or terms should be updated
		if(!empty($_POST['content_type']) && sanitize_text_field($_POST['content_type']) == 'taxonomies') {
			return PMU_URI_Functions_Tax::update_all_permalinks();
		} else {
			return PMU_URI_Functions_Post::update_all_permalinks();
		}
	}

	/**
	 * Additional actions
	 */
	public static function extra_actions() {
		if(isset($_GET['flush_sitemaps'])) {
			self::flush_sitemaps();
		} else if(isset($_GET['clear-permalink-updator-uris'])) {
			self::clear_all_uris();
		} else if(isset($_GET['remove-permalink-updator-settings'])) {
			$option_name = sanitize_text_field($_GET['remove-permalink-updator-settings']);
			self::remove_plugin_data($option_name);
		} else if(!empty($_REQUEST['remove-uri'])) {
			$uri_key = sanitize_text_field($_REQUEST['remove-uri']);
			self::force_clear_single_element_uris_and_redirects($uri_key);
		} else if(!empty($_REQUEST['remove-redirect'])) {
			$redirect_key = sanitize_text_field($_REQUEST['remove-redirect']);
			self::force_clear_single_redirect($redirect_key);
		} else if(!empty($_POST['screen-options-apply'])) {
			self::save_screen_options();
		}
	}

	/**
	 * Save "Screen Options"
	 */
	public static function save_screen_options() {
		check_admin_referer( 'screen-options-nonce', 'screenoptionnonce' );

		// The values will be sanitized inside the function
		self::save_settings('screen-options', sanitize_text_field($_POST['screen-options']));
	}

	/**
	* Save permastructures
	*/
	public static function save_permastructures() {
		global $puPermastructs;

		$post_fields = sanitize_text_field($_POST);
		$permastructure_options = $permastructures = array();
		$permastructure_types = array('post_types', 'taxonomies');

		// Split permastructures & sanitize them
		foreach($permastructure_types as $type) {
			if(empty($_POST[$type]) || !is_array($_POST[$type])) { continue; }

			$permastructures[$type] = sanitize_text_field($_POST[$type]);

			foreach($permastructures[$type] as &$single_permastructure) {
				$single_permastructure = PMU_Helper_Functions::sanitize_title($single_permastructure, true, false, false);
				$single_permastructure = trim($single_permastructure, '\/ ');
			}
		}

		if(!empty($_POST['permastructure-settings'])) {
			$permastructure_options = sanitize_text_field($_POST['permastructure-settings']);
		}

		// A. Permastructures
		if(!empty($permastructures['post_types']) || !empty($permastructures['taxonomies'])) {
			// Override the global with settings
			$puPermastructs = $permastructures;

			// Save the settings in database
			update_option('permalink-updator-permastructs', $permastructures);
		}

		// B. Permastructure settings
		if(!empty($permastructure_options)) {
			self::save_settings('permastructure-settings', $permastructure_options);
		}
	}

	/**
	 * Clear URIs
	 */
	public static function clear_all_uris() {
		global $puUris, $puRedirects, $wpdb, $puBeforeSectionsHtml;

		// Check if array with custom URIs exists
		if(empty($puUris)) { return; }

		// Count removed URIs & redirects
		$removed_uris = 0;
		$removed_redirects = 0;

		// Get all element IDs
		$element_ids = array_merge(array_keys((array) $puUris), array_keys((array) $puRedirects));

		// 1. Remove unused custom URI & redirects for deleted post or term
		foreach($element_ids as $element_id) {
			$count = self::clear_single_element_uris_and_redirects($element_id, true);

			$removed_uris = (!empty($count[0])) ? $count[0] + $removed_uris : $removed_uris;
			$removed_redirects = (!empty($count[1])) ? $count[1] + $removed_redirects : $removed_redirects;
		}

		// 2. Keep only a single redirect (make it unique)
		$removed_redirects += self::clear_redirects_array(true);

		// 3. Optional method to keep the permalinks unique
		if(apply_filters('permalink_updator_fix_uri_duplicates', false) == true) {
			self::fix_uri_duplicates();
		}

		// Save cleared URIs & Redirects
		if($removed_uris > 0 || $removed_redirects > 0) {
			update_option('permalink-updator-uris', array_filter($puUris));
			update_option('permalink-updator-redirects', array_filter($puRedirects));

			$puBeforeSectionsHtml .= PMU_Admin_Functions::get_alert_message(sprintf(__( '%d Custom URIs and %d Custom Redirects were removed!', 'permalink-updator' ), $removed_uris, $removed_redirects), 'updated updated_slugs');
		} else {
			$puBeforeSectionsHtml .= PMU_Admin_Functions::get_alert_message(__( 'No Custom URIs or Custom Redirects were removed!', 'permalink-updator' ), 'error updated_slugs');
		}
	}

	/**
	 * Remove plugin data
	 */
	public static function remove_plugin_data($field_name) {
		global $permalink_updator, $puBeforeSectionsHtml;

		// Make sure that the user is allowed to remove the plugin data
		if(!current_user_can('manage_options')) {
			$puBeforeSectionsHtml .= PMU_Admin_Functions::get_alert_message(__( 'You are not allowed to remove Permalink Supervisor data!', 'permalink-updator' ), 'error updated_slugs');
		}

		switch($field_name) {
			case 'uris' :
				$option_name = 'permalink-updator-uris';
				$alert = __('Custom permalinks', 'permalink-updator');
				break;
			case 'redirects' :
				$option_name = 'permalink-updator-redirects';
				$alert = __('Custom redirects', 'permalink-updator');
				break;
			case 'external-redirects' :
				$option_name = 'permalink-updator-external-redirects';
				$alert = __('External redirects', 'permalink-updator');
				break;
			case 'permastructs' :
				$option_name = 'permalink-updator-permastructs';
				$alert = __('Permastructure settings', 'permalink-updator');
				break;
			case 'settings' :
				$option_name = 'permalink-updator';
				$alert = __('Permastructure settings', 'permalink-updator');
				break;
		}

		if(!empty($option_name)) {
			// Remove the option from DB
			delete_option($option_name);

			// Reload globals
			$permalink_updator->get_options_and_globals();

			$alert_message = sprintf(__('%s were removed!', 'permalink-updator'), $alert);
			$puBeforeSectionsHtml .= PMU_Admin_Functions::get_alert_message($alert_message, 'updated updated_slugs');
		}
	}

	/**
	 * Check if the post/term uses the same URI for both permalink & custom redirects
	 */
	public static function clear_single_element_duplicated_redirect($element_id, $count_removed = false, $uri = null) {
		global $puUris, $puRedirects;

		$custom_uri = (empty($uri) && !empty($puUris[$element_id])) ? $puUris[$element_id] : $uri;

		if($custom_uri && !empty($puRedirects[$element_id]) && in_array($custom_uri, $puRedirects[$element_id])) {
			$duplicated_redirect_id = array_search($custom_uri, $puRedirects[$element_id]);
			unset($puRedirects[$element_id][$duplicated_redirect_id]);
		}

		// Check if function should only return the counts or update
		if($count_removed) {
			return (isset($duplicated_redirect_id)) ? 1 : 0;
		} else if(isset($duplicated_redirect_id)) {
			update_option('permalink-updator-redirects', array_filter($puRedirects));
			return true;
		}
	}

	/**
	 * Remove unused custom URI & redirects for deleted post or term
	 */
 	public static function clear_single_element_uris_and_redirects($element_id, $count_removed = false) {
		global $wpdb, $puUris, $puRedirects;

		// Count removed URIs & redirects
		$removed_uris = 0;
		$removed_redirects = 0;

		// Only admin users can remove the broken URIs for removed post types & taxonomies
		$check_if_exists = (is_admin()) ? true : false;

		// 1. Check if element exists
		if(strpos($element_id, 'tax-') !== false) {
			$term_id = preg_replace("/[^0-9]/", "", $element_id);
			$taxonomy = $wpdb->get_var($wpdb->prepare("SELECT t.taxonomy FROM $wpdb->term_taxonomy AS t WHERE t.term_id = %s LIMIT 1", $term_id));

			// Remove custom URIs for removed terms or disabled taxonomies
			$remove = (!empty($taxonomy)) ? PMU_Helper_Functions::is_disabled($taxonomy, 'taxonomy', $check_if_exists) : true;
		} else if(is_numeric($element_id)) {
			$post_type = $wpdb->get_var("SELECT post_type FROM {$wpdb->prefix}posts WHERE ID = {$element_id} AND post_status NOT IN ('auto-draft', 'trash') AND post_type != 'nav_menu_item'");

			// Remove custom URIs for removed, auto-draft posts or disabled post types
			$remove = (!empty($post_type)) ? PMU_Helper_Functions::is_disabled($post_type, 'post_type', $check_if_exists) : true;

			// Remove custom URIs for attachments redirected with Yoast's SEO Premium
			$yoast_permalink_options = (class_exists('WPSEO_Premium')) ? get_option('wpseo_permalinks') : array();

			if(!empty($yoast_permalink_options['redirectattachment']) && $post_type == 'attachment') {
				$attachment_parent = $wpdb->get_var("SELECT post_parent FROM {$wpdb->prefix}posts WHERE ID = {$element_id} AND post_type = 'attachment'");
				if(!empty($attachment_parent)) {
					$remove = true;
				}
			}
		}

		// 2A. Remove ALL unused custom permalinks & redirects
		if(!empty($remove)) {
			// Remove URI
			if(!empty($puUris[$element_id])) {
				$removed_uris = 1;
				unset($puUris[$element_id]);
			}

			// Remove all custom redirects
			if(!empty($puRedirects[$element_id]) && is_array($puRedirects[$element_id])) {
				$removed_redirects = count($puRedirects[$element_id]);
				unset($puRedirects[$element_id]);;
			}
		}
		// 2B. Check if the post/term uses the same URI for both permalink & custom redirects
		else {
			$removed_redirect = self::clear_single_element_duplicated_redirect($element_id, true);
			$removed_redirects = (!empty($removed_redirect)) ? 1 : 0;
		}

		// Check if function should only return the counts or update
		if($count_removed) {
			return array($removed_uris, $removed_redirects);
		} else if(!empty($removed_uris) || !empty($removed_redirects)) {
			update_option('permalink-updator-uris', array_filter($puUris));
			update_option('permalink-updator-redirects', array_filter($puRedirects));
			return true;
		}
 	}

	/**
	 * Make the redirects unique
	 */
	public static function clear_redirects_array($count_removed = false) {
		global $puRedirects;

		$removed_redirects = 0;

		$all_redirect_duplicates = PMU_Helper_Functions::get_all_duplicates(true);

		foreach($all_redirect_duplicates as $single_redirect_duplicate) {
			$last_element = reset($single_redirect_duplicate);

			foreach($single_redirect_duplicate as $redirect_key) {
				// Keep a single redirect
				if($last_element == $redirect_key) { continue; }
				preg_match("/redirect-([\d]+)_((?:tax-)?(?:[\d]+))/", $redirect_key, $ids);

				if(!empty($ids[2]) && !empty($puRedirects[$ids[2]][$ids[1]])) {
					$removed_redirects++;
					unset($puRedirects[$ids[2]][$ids[1]]);
				}
			}
		}

		// Check if function should only return the counts or update
		if($count_removed) {
			return $removed_redirects;
		} else if(isset($duplicated_redirect_id)) {
			update_option('permalink-updator-redirects', array_filter($puRedirects));
			return true;
		}
	}

	/**
	 * Remove custom URI & redirects for any requested post or term
	 */
	public static function force_clear_single_element_uris_and_redirects($uri_key) {
		global $puUris, $puRedirects, $puBeforeSectionsHtml;

		// Check if custom URI is set
		if(isset($puUris[$uri_key])) {
			$uri = $puUris[$uri_key];

			unset($puUris[$uri_key]);
			update_option('permalink-updator-uris', $puUris);

			$updated = PMU_Admin_Functions::get_alert_message(sprintf(__( 'URI "%s" was removed successfully!', 'permalink-updator' ), $uri), 'updated');
		}

		// Check if custom redirects are set
		if(isset($puRedirects[$uri_key])) {
			unset($puRedirects[$uri_key]);
			update_option('permalink-updator-redirects', $puRedirects);

			$updated = PMU_Admin_Functions::get_alert_message(__( 'Broken redirects were removed successfully!', 'permalink-updator' ), 'updated');
		}

		if(empty($updated)) {
			$puBeforeSectionsHtml .= PMU_Admin_Functions::get_alert_message(__( 'URI and/or custom redirects does not exist or were already removed!', 'permalink-updator' ), 'error');
		} else {
			// Display the alert in admin panel
			if(!empty($puBeforeSectionsHtml) && is_admin()) {
				$puBeforeSectionsHtml .= $updated;
			}
			return true;
		}
	}

	public static function force_clear_single_redirect($redirect_key) {
		global $puRedirects, $puBeforeSectionsHtml;

		preg_match("/redirect-([\d]+)_((?:tax-)?(?:[\d]+))/", $redirect_key, $ids);

		if(!empty($puRedirects[$ids[2]][$ids[1]])) {
			unset($puRedirects[$ids[2]][$ids[1]]);

			update_option('permalink-updator-redirects', array_filter($puRedirects));

			$puBeforeSectionsHtml = PMU_Admin_Functions::get_alert_message(__( 'The redirect was removed successfully!', 'permalink-updator' ), 'updated');
		}
	}

	/**
	 * Keep the permalinks unique
	 */
	public static function fix_uri_duplicates() {
		global $puUris;

		$duplicates = array_count_values($puUris);

		foreach($duplicates as $uri => $count) {
			if($count == 1) { continue; }

			$ids = array_keys($puUris, $uri);
			foreach($ids as $index => $id) {
				if($index > 0) {
					$puUris[$id] = preg_replace('/(.+?)(\.[^\.]+$|$)/', '$1-' . $index . '$2', $uri);
				}
			}
		}

		update_option('permalink-updator-uris', $puUris);
	}

	/**
	 * Check if URI was used before
	 */
	function ajax_detect_duplicates($uri = null, $element_id = null) {
		$duplicate_alert = __("URI is already in use, please select another one!", "permalink-updator");

		if(!empty($_REQUEST['custom_uris'])) {
			// Sanitize the array
			$custom_uris = PMU_Helper_Functions::sanitize_array($_REQUEST['custom_uris']);
			$duplicates_array = array();

			// Check each URI
			foreach($custom_uris as $element_id => $uri) {
				$duplicates_array[$element_id] = PMU_Helper_Functions::is_uri_duplicated($uri, $element_id) ? $duplicate_alert : 0;
			}

			// Convert the output to JSON and stop the function
			echo json_encode($duplicates_array);
		} else if(!empty($_REQUEST['custom_uri']) && !empty($_REQUEST['element_id'])) {
			$is_duplicated = PMU_Helper_Functions::is_uri_duplicated($uri, $element_id);

			echo ($is_duplicated) ? $duplicate_alert : 0;;
		}

		die();
	}

	/**
	 * Clear sitemaps cache
	 */
	function flush_sitemaps($types = array()) {
		global $puBeforeSectionsHtml;

		if(class_exists('WPSEO_Sitemaps_Cache')) {
			$sitemaps = WPSEO_Sitemaps_Cache::clear($types);

			$puBeforeSectionsHtml .= PMU_Admin_Functions::get_alert_message(__( 'Sitemaps were updated!', 'permalink-updator' ), 'updated');
		}
	}

	/**
	 * Import old URIs from "Custom Permalinks" (Pro)
	 */
	function import_custom_permalinks_uris() {
		PMU_Third_Parties::import_custom_permalinks_uris();
	}

	/**
	 * "Automatically remove duplicates" (if enabled) in background
	 */
	function clean_permalinks_hook() {
		global $puUris, $puRedirects;

		// Backup the custom URIs
		if(is_array($puUris)) {
			update_option('permalink-updator-uris_backup', $puUris, false);
		}
		// Backup the custom redirects
		if(is_array($puRedirects)) {
			update_option('permalink-updator-redirects_backup', $puRedirects, false);
		}

		self::clear_all_uris();
	}

	function clean_permalinks_cronjob() {
		global $puUpdatorOptions;

		$event_name = 'clean_permalinks_event';

		// Set-up the "Automatically remove duplicates" function that runs in background once a day
		if(!empty($puUpdatorOptions['general']['auto_remove_duplicates']) && $puUpdatorOptions['general']['auto_remove_duplicates'] == 2) {
			if(!wp_next_scheduled($event_name)) {
				wp_schedule_event(time(), 'daily', $event_name);
			}
		} else if(wp_next_scheduled($event_name)) {
			$event_timestamp = wp_next_scheduled($event_name);
			wp_unschedule_event($event_timestamp, $event_name);
		}
	}

}
