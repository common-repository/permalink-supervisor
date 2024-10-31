<?php
class PMU_Pro_Addons extends PMU_Class {

	public function __construct() {
		add_action('init', array($this, 'init'), 9);
	}

	public function init() {
		add_filter( 'permalink_updator_sections', array($this, 'add_admin_section'), 5 );

		// Stop Words
		add_action( 'admin_init', array($this, 'save_stop_words'), 9 );

		add_filter( 'permalink_updator_tools_fields', array($this, 'filter_tools_fields'), 9, 2 );
		add_filter( 'permalink_updator_permastructs_fields', array($this, 'filter_permastructure_fields'), 9 );

		add_filter( 'permalink_updator_settings_fields', array($this, 'filter_settings_fields'), 9 );
	}

	/**
	 * Permastructures tab
	 */
	public function filter_permastructure_fields($fields) {
		global $puPermastructs;
		$taxonomies = PMU_Helper_Functions::get_taxonomies_array('full');
		foreach($taxonomies as $taxonomy) {
			$taxonomy_name = $taxonomy['name'];

			// Check if taxonomy exists
			if(!taxonomy_exists($taxonomy_name)) { continue; }
		}
		return $fields;
	}

	/**
	 * Tools tab
	 */
	public function filter_tools_fields($fields, $subsection) {
		unset($fields['content_type']['disabled']);
		unset($fields['content_type']['pro']);
		unset($fields['taxonomies']['pro']);
		unset($fields['ids']['disabled']);
		unset($fields['ids']['pro']);

		return $fields;
	}

	/**
	 * Tax Editor & Import support
	 */
	public function add_admin_section($admin_sections) {
		// Add "Stop words" subsectio for "Tools"
		$admin_sections['tools']['subsections']['stop_words']['function'] =	array('class' => 'PMU_Pro_Addons', 'method' => 'stop_words_output');

		// Display Permalinks for all selected taxonomies
		if(!empty($admin_sections['uri_editor']['subsections'])) {
			foreach($admin_sections['uri_editor']['subsections'] as &$subsection) {
				if(isset($subsection['pro'])) {
					$subsection['function'] = array('class' => 'PMU_Tax_Uri_Editor_Table', 'method' => 'display_admin_section');
					unset($subsection['html']);
				}
			}
		}


		// Import support
		$admin_sections['tools']['subsections']['import']['function'] =	array('class' => 'PMU_Pro_Addons', 'method' => 'import_output');

		return $admin_sections;
	}

	/**
	 * Settings tab
	 */
	public function filter_settings_fields($fields) {
		unset($fields['seo']['fields']['setup_redirects']['pro']);
		unset($fields['seo']['fields']['setup_redirects']['disabled']);
		return $fields;
	}

	/**
	 * "Stop words" subsection
	 */
	public function stop_words_output() {
		global $puUpdatorOptions;

		// Fix the escaped quotes
		$words_list = (!empty($puUpdatorOptions['stop-words']['stop-words-list'])) ? stripslashes($puUpdatorOptions['stop-words']['stop-words-list']) : "";


		$buttons = "<table class=\"stop-words-buttons\"><tr>";
		$buttons .= sprintf("<td><a href=\"#\" class=\"clear_all_words button button-small\">%s</a></td>", __("Remove all words", "permalink-updator"));

		$buttons .= sprintf("<td>%s</td>", get_submit_button(__('Add the words from the list', 'permalink-updator'), 'button-small button-primary', 'load_stop_words_button', false));
		$buttons .= "</tr></table>";

		$fields = apply_filters('permalink_updator_tools_fields', array(
			'stop-words' => array(
				'container' => 'row',
				'fields' => array(
					'stop-words-enable' => array(
						'label' => __( 'Enable "stop words"', 'permalink-updator' ),
						'type' => 'single_checkbox',
						'container' => 'row',
						'input_class' => 'enable_stop_words'
					),
					'stop-words-list' => array(
						'label' => __( '"Stop words" list', 'permalink-updator' ),
						'type' => 'textarea',
						'container' => 'row',
						'value' => $words_list,
						'description' => __('Type comma to separate the words.', 'permalink-updator'),
						'input_class' => 'widefat stop_words',
						'after_description' => $buttons
					)
				)
			)
		), 'stop_words');

		$sidebar = '<h3>' . __('Instructions', 'permalink-updator') . '</h3>';
		$sidebar .= wpautop(__('If enabled, all selected "stop words" will be automatically removed from default URIs.', 'permalink-updator'));
		$sidebar .= wpautop(__('Each of the words can be removed and any new words can be added to the list.', 'permalink-updator'));

		return PMU_Admin_Functions::get_the_form($fields, '', array('text' => __('Save', 'permalink-updator'), 'class' => 'primary margin-top'), $sidebar, array('action' => 'permalink-updator', 'name' => 'save_stop_words'), true);
	}

	public function save_stop_words() {
		if(isset($_POST['stop-words']) && wp_verify_nonce($_POST['save_stop_words'], 'permalink-updator')) {
			PMU_Actions::save_settings('stop-words', sanitize_text_field($_POST['stop-words']));
		}
	}

	/**
	 * "Import" subsection
	 */
	public function import_output() {
		global $puUpdatorOptions;

		// Count custom permalinks URIs
		$count_custom_permalinks = count(PMU_Third_Parties::custom_permalinks_uris());

		$fields = apply_filters('permalink_updator_tools_fields', array(
			'disable_custom_permalinks' => array(
				'label' => __( 'Custom Permalinks', 'permalink-updator' ),
				'checkbox_label' => __( 'Deactivate after import', 'permalink-updator' ),
				'type' => 'single_checkbox',
				'container' => 'row',
				'description' => __('If selected, "Custom Permalinks" plugin will be deactivated after its custom URIs are imported.', 'permalink-updator'),
				'input_class' => ''
			)
		), 'regenerate');

		$sidebar = '<h3>' . __('Instructions', 'permalink-updator') . '</h3>';
		$sidebar .= wpautop(__('Please note that "Custom Permalinks" (if activated) may break the behavior of this plugin.', 'permalink-updator'));
		$sidebar .= wpautop(__('Therefore, it is recommended to disable "Custom Permalink" and import old permalinks before using Permalink Supervisor.', 'permalink-updator'));

		// Show some additional info data
		if($count_custom_permalinks > 0) {
			$button = array(
				'text' => sprintf(__('Import %d URIs', 'permalink-updator'), $count_custom_permalinks),
				'class' => 'primary margin-top'
			);
		} else {
			$button = array(
				'text' => __('No custom URIs to import', 'permalink-updator'),
				'class' => 'secondary margin-top',
				'attributes' => array('disabled' => 'disabled')
			);
		}

		return PMU_Admin_Functions::get_the_form($fields, 'columns-3', $button, $sidebar, array('action' => 'permalink-updator', 'name' => 'import'), true);
	}

	/**
	 * Custom Redirects Panel
	 */
	public static function display_redirect_form($element_id) {
		global $puRedirects, $puExternalRedirects;

		// 1. Extra redirects
		$html = "<div class=\"single-section\">";

		$html .= sprintf("<p><label for=\"auto_auri\" class=\"strong\">%s %s</label></p>",
			__("Extra redirects (aliases)", "permalink-updator"),
			PMU_Admin_Functions::help_tooltip(__("All URIs specified below will redirect the visitors to the custom URI defined above in \"Current URI\" field.", "permalink-updator"))
		);

		$html .= "<table>";
		// 1A. Sample row
		$html .= sprintf("<tr class=\"sample-row\"><td>%s</td><td>%s</td></tr>",
			PMU_Admin_Functions::generate_option_field("permalink-updator-redirects", array("input_class" => "widefat", "value" => "", 'extra_atts' => "data-index=\"\"", "placeholder" => __('sample/custom-uri', 'permalink-updator'))),
			"<a href=\"#\" class=\"remove-redirect\"><span class=\"dashicons dashicons-no\"></span></a>"
		);

		// 1B. Rows with redirects
		if(!empty($puRedirects[$element_id]) && is_array($puRedirects[$element_id])) {
			foreach($puRedirects[$element_id] as $index => $redirect) {
				$html .= sprintf("<tr><td>%s</td><td>%s</td></tr>",
					PMU_Admin_Functions::generate_option_field("permalink-updator-redirects[{$index}]", array("input_class" => "widefat", "value" => $redirect, 'extra_atts' => "data-index=\"{$index}\"")),
					"<a href=\"#\" class=\"remove-redirect\"><span class=\"dashicons dashicons-no\"></span></a>"
				);
			}
		}
		$html .= "</table>";

		// 1C. Add new redirect button
		$html .= sprintf("<button type=\"button\" class=\"button button-small hide-if-no-js\" id=\"permalink-updator-new-redirect\">%s</button>",
			__("Add new redirect", "permalink-updator")
		);

		// 1D. Description
		$html .= "<div class=\"redirects-panel-description\">";
		$html .= sprintf(wpautop(__("<strong>Please use URIs only!</strong><br />For instance, to set-up a redirect for <code>%s/old-uri</code> please use <code>old-uri</code>.", "permalink-updator")), home_url());
		$html .= "</div>";

		$html .= "</div>";

		// 2. Extra redirects
		$html .= "<div class=\"single-section\">";

		$html .= sprintf("<p><label for=\"auto_auri\" class=\"strong\">%s %s</label></p>",
			__("Redirect this page to external URL", "permalink-updator"),
			PMU_Admin_Functions::help_tooltip(__("If not empty, the visitors trying to access this page will be redirected to the URL specified below.", "permalink-updator"))
		);

		$external_redirect_url = (!empty($puExternalRedirects[$element_id])) ? $puExternalRedirects[$element_id] : "";
		$html .= PMU_Admin_Functions::generate_option_field("permalink-updator-external-redirect", array("input_class" => "widefat custom_uri", "value" => urldecode($external_redirect_url), "placeholder" => __("http://another-website.com/final-target-url", "permalink-updator")));

		// 2B. Description
		$html .= "<div class=\"redirects-panel-description\">";
		$html .= wpautop(__("<strong>Please use full URLs!</strong><br />For instance, <code>http://another-website.com/final-target-url</code>.", "permalink-updator"));
		$html .= "</div>";

		$html .= "</div>";

		return $html;
	}

}
