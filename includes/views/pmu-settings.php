<?php
class PMU_Settings extends PMU_Class {

	public function __construct() {
		add_filter( 'permalink_updator_sections', array($this, 'add_admin_section'), 3 );
	}

	public function add_admin_section($admin_sections) {
		$admin_sections['settings'] = array(
			'name'				=>	__('Settings', 'permalink-updator'),
			'function'    => array('class' => 'PMU_Settings', 'method' => 'output')
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
			'general' => array(
				'section_name' => __('General settings', 'permalink-updator'),
				'container' => 'row',
				'name' => 'general',
				'fields' => array(
					'auto_update_uris' => array(
						'type' => 'single_checkbox',
						'label' => __('Auto-update permalinks', 'permalink-updator'),
						'input_class' => '',
						'description' => sprintf('%s<br />%s',
							__('<strong>Permalink Supervisor can automatically update the custom permalink after post or term is saved/updated.</strong>', 'permalink-updator'),
							__('If enabled, Permalink Supervisor will always force the default custom permalink format (based on current <strong>Permastructure</strong> settings).', 'permalink-updator')
						)
					),
					'force_custom_slugs' => array(
						'type' => 'select',
						'label' => __('Slugs mode', 'permalink-updator'),
						'input_class' => 'settings-select',
						'choices' => array(0 => __('Use native slugs', 'permalink-updator'), 1 => __('Use actual titles as slugs', 'permalink-updator'), 2 => __('Inherit parents\' slugs', 'permalink-updator')),
						'description' => sprintf('%s<br />%s<br />%s',
							__('<strong>Permalink Supervisor can use either native slugs or actual titles for custom permalinks.</strong>', 'permalink-updator'),
							__('The native slug is generated from the initial title after the post or term is published.', 'permalink-updator'),
							__('Use this field if you would like Permalink Supervisor to use the actual titles instead of native slugs.', 'permalink-updator')
						)
					),
					'trailing_slashes' => array(
						'type' => 'select',
						'label' => __('Trailing slashes', 'permalink-updator'),
						'input_class' => 'settings-select',
						'choices' => array(0 => __('Use default settings', 'permalink-updator'), 1 => __('Add trailing slashes', 'permalink-updator'), 2 => __('Remove trailing slashes', 'permalink-updator')),
						'description' => __('This option can be used to alter the native settings and control if trailing slash should be added or removed from the end of posts & terms permalinks.', 'permalink-updator'),
						'description' => sprintf('%s<br />%s',
							__('<strong>You can use this feature to either add or remove the slases from end of WordPress permalinks.</strong>', 'permalink-updator'),
							__('Please use "<a href="#sslwww_redirect">Trailing slashes redirect</a>" field if you would like to force the settings with redirect.', 'permalink-updator')
						)
					),
					'canonical_redirect' => array(
						'type' => 'single_checkbox',
						'label' => __('Canonical redirect', 'permalink-updator'),
						'input_class' => '',
						'description' => sprintf('%s<br />%s',
							__('<strong>Canonical redirect allows WordPress to "correct" the requested URL and redirect visitor to the canonical permalink.</strong>', 'permalink-updator'),
							__('This feature will be also used to redirect (old) original permalinks to (new) custom permalinks set with Permalink Supervisor.', 'permalink-updator')
						),
					),
					'old_slug_redirect' => array(
						'type' => 'single_checkbox',
						'label' => __('Old slug redirect', 'permalink-updator'),
						'input_class' => '',
						'description' => sprintf('%s<br />%s',
							__('<strong>Old slug redirect is used by WordPress to provide a fallback for old version of slugs after they are changed.</strong>', 'permalink-updator'),
							__('If enabled, the visitors trying to access the URL with the old slug will be redirected to the canonical permalink.', 'permalink-updator')
						)
					)
				)
			)		
		));

		$output = PMU_Admin_Functions::get_the_form($sections_and_fields, '', array('text' => __( 'Save settings', 'permalink-updator' ), 'class' => 'primary margin-top'), '', array('action' => 'permalink-updator', 'name' => 'permalink_updator_options'));
		return $output;
	}
}
