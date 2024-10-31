<?php
class PMU_Thirdparty_Settings extends PMU_Class {

	public function __construct() {
		add_filter( 'permalink_updator_sections', array($this, 'add_admin_section'), 3 );
	}

	public function add_admin_section($admin_sections) {
		$admin_sections['thirdparty-settings'] = array(
			'name'				=>	__('Thirdparty Settings', 'permalink-updator'),
			'function'    => array('class' => 'PMU_Thirdparty_Settings', 'method' => 'output')
		);

		return $admin_sections;
	}

	/**
	* Get the array with settings and render the HTML output
	*/
	public function output() {
		// Get all registered post types array & statuses
		$all_post_statuses_array = get_post_statuses();
		$all_post_types = PMU_Helper_Functions::get_post_types_array(null, null, true);
		$all_taxonomies = PMU_Helper_Functions::get_taxonomies_array(false, false, false, true);
		$content_types  = (defined('PMUPLPRO')) ? array('post_types' => $all_post_types, 'taxonomies' => $all_taxonomies) : array('post_types' => $all_post_types);

		$sections_and_fields = apply_filters('permalink_updator_settings_fields', array(
			'third_parties' => array(
					'section_name' => __('Third party plugins', 'permalink-updator'),
					'container' => 'row',
					'name' => 'general',
					'fields' => array(
						'fix_language_mismatch' => array(
							'type' => 'single_checkbox',
							'label' => __('WPML/Polylang language mismatch', 'permalink-updator'),
							'input_class' => '',
							'description' => __('If enabled, the plugin will load the adjacent translation of post when the custom permalink is detected, but the language code in the URL does not match the language code assigned to the post/term.', 'permalink-updator')
						),
						'pmxi_import_support' => array(
							'type' => 'single_checkbox',
							'label' => __('WP All Import support', 'permalink-updator'),
							'input_class' => '',
							'description' => __('If checked, the custom permalinks will not be saved for the posts imported with Wp All Import plugin.', 'permalink-updator')
						),
						'yoast_breadcrumbs' => array(
							'type' => 'single_checkbox',
							'label' => __('Breadcrumbs support', 'permalink-updator'),
							'input_class' => '',
							'description' => __('If checked, the HTML breadcrumbs will be filtered by Permalink Supervisor to mimic the current URL structure.<br />Works with: <strong>WooCommerce, Yoast SEO, RankMath and SEOPress</strong> breadcrumbs.', 'permalink-updator')
						),
						'partial_disable' => array(
							'type' => 'checkbox',
							'label' => __('Excluded content types', 'permalink-updator'),
							'choices' => $content_types,
							'description' => __('Permalink Supervisor will ignore and not filter the custom permalinks of all selected above post types & taxonomies.', 'permalink-updator')
						),
					)
				)		
			));

		$output = PMU_Admin_Functions::get_the_form($sections_and_fields, '', array('text' => __( 'Save settings', 'permalink-updator' ), 'class' => 'primary margin-top'), '', array('action' => 'permalink-updator', 'name' => 'permalink_updator_options'));
		return $output;
	}
}
