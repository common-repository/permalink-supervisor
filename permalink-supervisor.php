<?php
/**
* Plugin Name:Permalink Supervisor
* Description:This plugin allows you to set-up custom permalinks for Posts, Pages, Coupons, Categories, Tags, Products, Product tags, Product categories and WooCommerce.
* Author:serviceprovider1080   
* Version:1.0.0         	
*/

defined( 'ABSPATH' ) || exit;
if(!class_exists('PMU_Class')) {

	// Define the directories used to load plugin files.
	define( 'PMU_PLPLUGIN_NAME', 'Permalink Supervisor' );
	define( 'PMU_PLPLUGIN_SLUG', 'permalink-updator' );
	define( 'PMU_PLVERSION', '1.0.0' );
	define( 'PMU_PLFILE', __FILE__ );
	define( 'PMU_PLDIR', untrailingslashit(dirname(__FILE__)) );
	define( 'PMU_PLBASENAME', plugin_basename(__FILE__));
	define( 'PMU_PLURL', untrailingslashit( plugins_url('', __FILE__) ) );

	//Define a deafault class
	class PMU_Class {
		//Define public variables
		public $puUpdatorOptions, $sections, $functions,  $puBeforeSectionsHtml, $puAfterSectionsHtml;

		//Load feafault classes
		public function __construct() {
			$this->load_classes();
			$this->load_hooks();
		}

		//Define a load class
		function load_classes() {
			// WP_List_Table needed for post types & taxnomies editors
			if( ! class_exists( 'WP_List_Table' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
			}

			//Load classes for controller
			require_once( PMU_PLDIR . "/includes/controllers/pmu-helper-functions.php" );
			new PMU_Helper_Functions();

			require_once( PMU_PLDIR . "/includes/controllers/pmu-uri-functions-post.php" );
			new PMU_URI_Functions_Post();

			require_once( PMU_PLDIR . "/includes/controllers/pmu-uri-functions-tax.php" );
			new PMU_URI_Functions_Tax();

			require_once( PMU_PLDIR . "/includes/controllers/pmu-admin-functions.php" );
			new PMU_Admin_Functions();

			require_once( PMU_PLDIR . "/includes/controllers/pmu-actions.php" );
			new PMU_Actions();

			require_once( PMU_PLDIR . "/includes/controllers/pmu-third-parties.php" );
			new PMU_Third_Parties();

			require_once( PMU_PLDIR . "/includes/controllers/pmu-core-functions.php" );
			new PMU_Core_Functions();

			require_once( PMU_PLDIR . "/includes/controllers/pmu-gutenberg.php" );
			new PMU_Gutenberg();

			require_once( PMU_PLDIR . "/includes/controllers/pmu-main-functions.php" );
			new PMU_Pro_Functions();


			//Load classes for views
			require_once( PMU_PLDIR . "/includes/views/pmu-uri-editor.php" );
			new PMU_Uri_Editor();

			require_once( PMU_PLDIR . "/includes/views/pmu-tools.php" );
			new PMU_Tools();


			require_once( PMU_PLDIR . "/includes/views/pmu-permastructs.php" );
			new PMU_Permastructs();

			require_once( PMU_PLDIR . "/includes/views/pmu-settings.php" );
			new PMU_Settings();

			require_once( PMU_PLDIR . "/includes/views/pmu-advanced-settings.php" );
			new PMU_Advanced_Settings();

			require_once( PMU_PLDIR . "/includes/views/pmu-enhenced-settings.php" );
			new PMU_Enhenced_Settings();

			require_once( PMU_PLDIR . "/includes/views/pmu-thirdparty-settings.php" );
			new PMU_Thirdparty_Settings();

			require_once( PMU_PLDIR . "/includes/views/pmu-main-addons.php" );
			new PMU_Pro_Addons();

			require_once( PMU_PLDIR . "/includes/views/pmu-uri-editor-tax.php" );
			require_once( PMU_PLDIR . "/includes/views/pmu-uri-editor-post.php" );
		}

		//Define hooks
		public function load_hooks() {

			// Support deprecated hooks
			add_action( 'plugins_loaded', array($this, 'deprecated_hooks'), 9 );

			// Deactivate free version if Permalink Supervisor is activated
			add_action( 'plugins_loaded', array($this, 'is_pro_version_activated'), 9 );

			// Load globals & options
			add_action( 'plugins_loaded', array($this, 'get_options_and_globals'), 9 );

			// Legacy support
			add_action( 'init', array($this, 'legacy_support'), 2 );

			// Default settings & alerts
			add_filter( 'permalink_updator_options', array($this, 'default_settings'), 1 );
		}

		
		//Get options values & set global
		public function get_options_and_globals() {
			// Deafault variables
			global $puUpdatorOptions, $puUris, $puPermastructs, $puRedirects, $puExternalRedirects;

			$this->permalink_updator_options = $puUpdatorOptions = apply_filters('permalink_updator_options', get_option('permalink-updator', array()));

			$this->permalink_updator_uris = $puUris = apply_filters('permalink_updator_uris', get_option('permalink-updator-uris', array()));

			$this->permalink_updator_permastructs = $puPermastructs = apply_filters('permalink_updator_permastructs', get_option('permalink-updator-permastructs', array()));

			$this->permalink_updator_redirects = $puRedirects = apply_filters('permalink_updator_redirects', get_option('permalink-updator-redirects', array()));

			$this->permalink_updator_external_redirects = $puExternalRedirects = apply_filters('permalink_updator_external_redirects', get_option('permalink-updator-external-redirects', array()));

			// Deafault variables
			global $permalink_updator_alerts, $puBeforeSectionsHtml, $puAfterSectionsHtml;

			$this->permalink_updator_alerts = $permalink_updator_alerts = apply_filters('permalink_updator_alerts', get_option('permalink-updator-alerts', array()));

			$this->permalink_updator_before_sections_html = $puBeforeSectionsHtml = apply_filters('permalink_updator_before_sections', '');

			$this->permalink_updator_after_sections_html = $puAfterSectionsHtml = apply_filters('permalink_updator_after_sections', '');
		}

		//Default Settings
		public function default_settings($settings) {
			$default_settings = apply_filters('permalink_updator_default_options', array(
				'screen-options' => array(
					'per_page' 		=> 20,
					'post_statuses' => array('publish'),
					'group' 		=> false,
				),
				'general' => array(
					'auto_update_uris' 			=> 0,
					'show_native_slug_field' 	=> 0,
					'pagination_redirect' 		=> 0,
					'sslwww_redirect' 			=> 0,
					'canonical_redirect' 		=> 1,
					'old_slug_redirect' 		=> 0,
					'setup_redirects' 			=> 0,
					'redirect' 					=> '301',
					'trailing_slashes' 			=> 0,
					'trailing_slash_redirect' 	=> 0,
					'auto_remove_duplicates' 	=> 1,
					'fix_language_mismatch' 	=> 1,
					'pmxi_import_support' 		=> 0,
					'yoast_breadcrumbs' 		=> 0,
					'force_custom_slugs' 		=> 0,
					'disable_slug_sanitization' => 0,
					'keep_accents' 				=> 0,
					'partial_disable' => array(
						'post_types' => array('attachment', 'tribe_events')
					),
					'deep_detect' => 1,
					'edit_uris_cap' => 'publish_posts',
				)
			));

			// Apply the default settings (if empty values) in all settings sections
			foreach($default_settings as $group_name => $fields) {
				foreach($fields as $field_name => $field) {
					if(!isset($settings[$group_name][$field_name])) {
						$settings[$group_name][$field_name] = $field;
					}
				}
			}
			return $settings;
		}


		//Legacy Support
		function legacy_support() {
			global $puPermastructs, $puUpdatorOptions;

			if(isset($puUpdatorOptions['base-editor'])) {
				$new_options['post_types'] = $puUpdatorOptions['base-editor'];
				update_option('permalink-updator-permastructs', $new_options);
			}
			else if(empty($puPermastructs['post_types']) && empty($puPermastructs['taxonomies']) && count($puPermastructs) > 0) {
				$new_options['post_types'] = $puPermastructs;
				update_option('permalink-updator-permastructs', $new_options);
			}

			// Adjust options structure
			if(!empty($puUpdatorOptions['miscellaneous'])) {
				// Combine general & miscellaneous options
				$permalink_updator_unfiltered_options['general'] = array_merge($permalink_updator_unfiltered_options['general'], $permalink_updator_unfiltered_options['miscellaneous']);
			}

			// Separate "Trailing slashes" & "Trailing slashes redirect" setting fields
			if(!empty($puUpdatorOptions['general']['trailing_slashes']) && $puUpdatorOptions['general']['trailing_slashes'] >= 10) {
				$permalink_updator_unfiltered_options = $puUpdatorOptions;

				$permalink_updator_unfiltered_options['general']['trailing_slashes_redirect'] = 1;
				$permalink_updator_unfiltered_options['general']['trailing_slashes'] = ($puUpdatorOptions['general']['trailing_slashes'] == 10) ? 1 : 2;
			}

			// Save the settings in database
			if(!empty($permalink_updator_unfiltered_options)) {
				update_option('permalink-updator', $permalink_updator_unfiltered_options);
			}
		}

		//Support deprecated hooks
		function deprecated_hooks_list($filters = true) {
			$deprecated_filters = array(
				'permalink_updator_default_options' 			=> 'permalink-updator-default-options',
				'permalink_updator_options' 					=> 'permalink-updator-options',
				'permalink_updator_uris' 						=> 'permalink-updator-uris',
				'permalink_updator_alerts'	 					=> 'permalink-updator-alerts',
				'permalink_updator_redirects' 					=> 'permalink-updator-redirects',
				'permalink_updator_external_redirects' 			=> 'permalink-updator-external-redirects',
				'permalink_updator_permastructs' 				=> 'permalink-updator-permastructs',
				'permalink_updator_alerts' 						=> 'permalink-updator-alerts',
				'permalink_updator_before_sections' 			=> 'permalink-updator-before-sections',
				'permalink_updator_sections' 					=> 'permalink-updator-sections',
				'permalink_updator_after_sections' 				=> 'permalink-updator-after-sections',
				'permalink_updator_field_args' 					=> 'permalink-updator-field-args',
				'permalink_updator_field_output' 				=> 'permalink-updator-field-output',
				'permalink_updator_deep_uri_detect' 			=> 'permalink-updator-deep-uri-detect',
				'permalink_updator_detect_uri' 					=> 'permalink-updator-detect-uri',
				'permalink_updator_detected_element_id' 		=> 'permalink-updator-detected-initial-id',
				'permalink_updator_detected_term_id' 			=> 'permalink-updator-detected-term-id',
				'permalink_updator_detected_post_id' 			=> 'permalink-updator-detected-post-id',
				'permalink_updator_primary_term' 				=> 'permalink-updator-primary-term',
				'permalink_updator_disabled_post_types' 		=> 'permalink-updator-disabled-post-types',
				'permalink_updator_disabled_taxonomies' 		=> 'permalink-updator-disabled-taxonomies',
				'permalink_updator_endpoints' 					=> 'permalink-updator-endpoints',
				'permalink_updator_filter_permalink_base' 		=> 'permalink_updator-filter-permalink-base',
				'permalink_updator_force_lowercase_uris' 		=> 'permalink-updator-force-lowercase-uris',
				'permalink_updator_uri_editor_extra_info' 		=> 'permalink-updator-uri-editor-extra-info',
				'permalink_updator_debug_fields' 				=> 'permalink-updator-debug-fields',
				'permalink_updator_permastructs_fields' 		=> 'permalink-updator-permastructs-fields',
				'permalink_updator_settings_fields' 			=> 'permalink-updator-settings-fields',
				'permalink_updator_advanced_settings_fields'	=> 'permalink-updator-advanced-settings-fields',
				'permalink_updator_enhenced_settings_fields'  	=> 'permalink-updator-enhenced-settings-fields',
				'permalink_updator_thirdparty_settings_fields'  => 'permalink-updator-thirdparty-settings-fields',
				'permalink_updator_tools_fields' 				=> 'permalink-updator-tools-fields',
				'permalink_updator_uri_editor_columns' 			=> 'permalink-updator-uri-editor-columns',
				'permalink_updator_uri_editor_column_content' 	=> 'permalink-updator-uri-editor-column-content',
			);
			return ($filters) ? $deprecated_filters : array();
		}

		function deprecated_hooks() {
			$deprecated_filters = (array) $this->deprecated_hooks_list(true);
			foreach($deprecated_filters as $new => $old) {
				add_filter($new, array($this, 'deprecated_hooks_mapping'), -1000, 8);
			}
		}

		function deprecated_hooks_mapping($data) {
			$deprecated_filters = $this->deprecated_hooks_list(true);
			$filter = current_filter();
			if(isset($deprecated_filters[$filter])) {
				if(has_filter($deprecated_filters[$filter])) {
					$args = func_get_args();
					$data = apply_filters_ref_array($deprecated_filters[$filter], $args);
				}
			}
			return $data;
		}

		//Check pro version
		function is_pro_version_activated() {
			if(function_exists('is_plugin_active') && is_plugin_active('permalink-updator/permalink-updator.php') && is_plugin_active('permalink-updator-pro/permalink-updator.php')) {
				deactivate_plugins('permalink-updator/permalink-updator.php');
			}
		}

	}

	//Deafault class access
	new PMU_Class();

}
