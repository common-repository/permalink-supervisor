<?php
class PMU_Permastructs extends PMU_Class {

	public function __construct() {
		add_filter( 'permalink_updator_sections', array($this, 'add_admin_section'), 2 );
	}

	public function add_admin_section($admin_sections) {

		$admin_sections['permastructs'] = array(
			'name'				=>	__('Permastructures', 'permalink-updator'),
			'function'    => array('class' => 'PMU_Permastructs', 'method' => 'output')
		);

		return $admin_sections;
	}

	public function get_fields() {
		global $puPermastructs;

		$all_post_types = PMU_Helper_Functions::get_post_types_array('full');
		$woocommerce_icon = "<i class=\"woocommerce-icon woocommerce-cart\"></i>";

		// 1. Get fields
		$fields = array(
			'post_types' => array(
				'section_name' => __('Post types', 'permalink-updator'),
				'container' => 'row',
				'fields' => array()
			),		);

		// 3. Append fields for all post types
		foreach($all_post_types as $post_type) {

			$fields["post_types"]["fields"][$post_type['name']] = array(
				'label' => $post_type['label'],
				'container' => 'row',
				'input_class' => 'permastruct-field',
				'post_type' => $post_type,
				'type' => 'permastruct'
			);
		}

		return apply_filters('permalink_updator_permastructs_fields', $fields);
	}

	/**
	* Get the array with settings and render the HTML output
	*/
	public function output() {
		global $puPermastructs;

		$sidebar = sprintf('<h3>%s</h3>', __('Instructions', 'permalink-updator'));
		$sidebar .= sprintf(wpautop(__('The current permastructures settings will be applied <strong>only to the new posts & terms</strong>. To apply the <strong>new permastructures to existing posts and terms</strong>, please regenerate the custom permalinks <a href="%s">here</a>.', 'permalink-updator')), admin_url('tools.php?page=permalink-updator&section=tools&subsection=regenerate_slugs'));

		$sidebar .= sprintf('<h4>%s</h4>', __('Permastructure tags', 'permalink-updator'));
		$sidebar .= PMU_Helper_Functions::get_all_structure_tags();

		return PMU_Admin_Functions::get_the_form(self::get_fields(), '', array('text' => __( 'Save permastructures', 'permalink-updator' ), 'class' => 'primary margin-top'), $sidebar, array('action' => 'permalink-updator', 'name' => 'permalink_updator_permastructs'));
	}

}
