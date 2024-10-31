<?php
class PMU_Enhenced_Settings extends PMU_Class {

	public function __construct() {
		add_filter( 'permalink_updator_sections', array($this, 'add_admin_section'), 3 );
	}

	public function add_admin_section($admin_sections) {
		$admin_sections['enhenced-settings'] = array(
			'name'				=>	__('Enhanced Redirect', 'permalink-updator'),
			'function'    => array('class' => 'PMU_Enhenced_Settings', 'method' => 'output')
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
			'seo' => array(
				'section_name' => __('Enhanced redirect', 'permalink-updator'),
				'container' => 'row',
				'name' => 'general',
				'fields' => array(
					'redirect' => array(
						'type' => 'select',
						'label' => __('Redirect mode', 'permalink-updator'),
						'input_class' => 'settings-select',
						'choices' => array(0 => __('Disable (Permalink Supervisor redirect functions)', 'permalink-updator'), "301" => __('301 redirect', 'permalink-updator'), "302" => __('302 redirect', 'permalink-updator')),
						'description' => sprintf('%s<br />%s',
							__('<strong>Permalink Supervisor includes a set of hooks that allow to extend the redirect functions used natively by WordPress to avoid 404 errors.</strong>', 'permalink-updator'),
							__('You can disable this feature if you do not want Permalink Supervisor to trigger any additional redirect functions at all.', 'permalink-updator')
						)
					),
					'setup_redirects' => array(
						'type' => 'single_checkbox',
						'label' => __('Old custom permalinks redirect', 'permalink-updator'),
						'input_class' => '',
						'pro' => true,
						'disabled' => true,
						'description' => sprintf('%s<br />%s',
							__('<strong>Permalink Supervisor can automatically set-up extra redirects after the custom permalink is changed.</strong>', 'permalink-updator'),
							__('If enabled, Permalink Manage will add redirect for earlier version of custom permalink after you change it (eg. with URI Editor or Regenerate/reset tool).', 'permalink-updator'),
							__('You can disable this feature if you use another plugin for redirects, eg. Yoast SEO Premium or Redirection.', 'permalink-updator')
						)
					),
					'sslwww_redirect' => array(
						'type' => 'single_checkbox',
						'label' => __('Force HTTPS/WWW', 'permalink-updator'),
						'input_class' => '',
						'description' => sprintf('%s<br />%s',
							__('<strong>You can use Permalink Supervisor to force SSL or "www" prefix in WordPress permalinks.</strong>', 'permalink-updator'),
							__('Please disable it if you encounter any redirect loop issues.', 'permalink-updator')
						)
					),
					'trailing_slashes_redirect' => array(
						'type' => 'single_checkbox',
						'label' => __('Trailing slashes redirect', 'permalink-updator'),
						'input_class' => '',
						'description' => sprintf('%s',
							__('<strong>Permalink Supervisor can force the trailing slashes settings in the custom permalinks with redirect.</strong>', 'permalink-updator')
						)
					)
				)
			)
		));

		$output = PMU_Admin_Functions::get_the_form($sections_and_fields, '', array('text' => __( 'Save settings', 'permalink-updator' ), 'class' => 'primary margin-top'), '', array('action' => 'permalink-updator', 'name' => 'permalink_updator_options'));
		return $output;
	}
}
