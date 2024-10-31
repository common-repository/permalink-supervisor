<?php
class PMU_Advanced_Settings extends PMU_Class {

	public function __construct() {
		add_filter( 'permalink_updator_sections', array($this, 'add_admin_section'), 3 );
	}

	public function add_admin_section($admin_sections) {
		$admin_sections['advanced-settings'] = array(
			'name'				=>	__('Advanced Settings', 'permalink-updator'),
			'function'    => array('class' => 'PMU_Advanced_Settings', 'method' => 'output')
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
				'advanced' => array(
				'section_name' => __('Advanced settings', 'permalink-updator'),
				'container' => 'row',
				'name' => 'general',
				'fields' => array(
					'show_native_slug_field' => array(
						'type' => 'single_checkbox',
						'label' => __('Show "Native slug" field', 'permalink-updator'),
						'input_class' => '',
						'description' => __('If enabled, it would be possible to edit the native slug via URI Editor on single post/term edit page.', 'permalink-updator')
					),
					'pagination_redirect' => array(
						'type' => 'single_checkbox',
						'label' => __('Force 404 on non-existing pagination pages', 'permalink-updator'),
						'input_class' => '',
						'description' => __('If enabled, the non-existing pagination pages (for single posts) will return 404 ("Not Found") error.<br /><strong>Please disable it, if you encounter any problems with pagination pages or use custom pagination system.</strong>', 'permalink-updator')
					),
					'disable_slug_sanitization' => array(
						'type' => 'select',
						'label' => __('Strip special characters', 'permalink-updator'),
						'input_class' => 'settings-select',
						'choices' => array(0 => __('Yes, use native settings', 'permalink-updator'), 1 => __('No, keep special characters (.,|_+) in the slugs', 'permalink-updator')),
						'description' => __('If enabled only alphanumeric characters, underscores and dashes will be allowed for post/term slugs.', 'permalink-updator')
					),
					'keep_accents' => array(
						'type' => 'select',
						'label' => __('Convert accented letters', 'permalink-updator'),
						'input_class' => 'settings-select',
						'choices' => array(0 => __('Yes, use native settings', 'permalink-updator'), 1 => __('No, keep accented letters in the slugs', 'permalink-updator')),
						'description' => __('If enabled, all the accented letters will be replaced with their non-accented equivalent (eg. Å => A, Æ => AE, Ø => O, Ć => C).', 'permalink-updator')
					),
					'edit_uris_cap' => array(
						'type' => 'select',
						'label' => __('URI Editor role capability', 'permalink-updator'),
						'choices' => array('edit_theme_options' => __('Administrator (edit_theme_options)', 'permalink-updator'), 'publish_pages' => __('Editor (publish_pages)', 'permalink-updator'), 'publish_posts' => __('Author (publish_posts)', 'permalink-updator'), 'edit_posts' => __('Contributor (edit_posts)', 'permalink-updator')),
						'description' => sprintf(__('Only the users who have selected capability will be able to access URI Editor.<br />The list of capabilities <a href="%s" target="_blank">can be found here</a>.', 'permalink-updator'), 'https://wordpress.org/support/article/roles-and-capabilities/#capability-vs-role-table')
					),
					'auto_remove_duplicates' => array(
						'type' => 'select',
						'label' => __('Automatically fix broken URIs', 'permalink-updator'),
						'input_class' => 'settings-select',
						'choices' => array(0 => __('Disable', 'permalink-updator'), 1 => __('Fix URIs individually (during page load)', 'permalink-updator'), 2 => __('Bulk fix all URIs (once a day, in the background)', 'permalink-updator')),
						'description' => sprintf('%s',
							__('Enable this option if you would like to automatically remove redundant permalinks & duplicated redirects.', 'permalink-updator')
						)
					),
				)
			)
		));

		$output = PMU_Admin_Functions::get_the_form($sections_and_fields, '', array('text' => __( 'Save settings', 'permalink-updator' ), 'class' => 'primary margin-top'), '', array('action' => 'permalink-updator', 'name' => 'permalink_updator_options'));
		return $output;
	}
}
