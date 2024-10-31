<?php
class PMU_Gutenberg extends PMU_Class {

	public function __construct() {
		add_action('enqueue_block_editor_assets', array($this, 'init'));
	}

	public function init() {
		add_meta_box('permalink-updator', __('Permalink Supervisor', 'permalink-updator'), array($this, 'meta_box'), '', 'side', 'high' );
	}

	public function pm_gutenberg_scripts() {
		wp_enqueue_script('permalink-updator-gutenberg', PMU_PLURL . '/vendor/pmu-gutenberg.js', array('wp-blocks', 'wp-element', 'wp-components', 'wp-i18n'), PMU_PLVERSION, true);
	}

	public function meta_box($post) {
		global $puUris;

		if(empty($post->ID)) {
			return '';
		}
		// Display URI Editor
		echo PMU_Admin_Functions::display_uri_box($post, true);
	}

}

?>
