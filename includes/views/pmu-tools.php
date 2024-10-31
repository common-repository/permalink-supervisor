<?php
class PMU_Tools extends PMU_Class {

	public function __construct() {
		add_filter( 'permalink_updator_sections', array($this, 'add_admin_section'), 1 );
	}

	public function add_admin_section($admin_sections) {

		$admin_sections['tools'] = array(
			'name'				=>	__('Duplicate Permalinks', 'permalink-updator'),
			'subsections' => array(
				'duplicates' => array(
					'name'				=>	__('Permalink Duplicates', 'permalink-updator'),
					'function'		=>	array('class' => 'PMU_Tools', 'method' => 'duplicates_output')
				),
				'find_and_replace' => array(
					'name'				=>	__('Find & Replace', 'permalink-updator'),
					'function'		=>	array('class' => 'PMU_Tools', 'method' => 'find_and_replace_output')
				),
				'regenerate_slugs' => array(
					'name'				=>	__('Regenerate/Reset', 'permalink-updator'),
					'function'		=>	array('class' => 'PMU_Tools', 'method' => 'regenerate_slugs_output')
				),
				'stop_words' => array(
					'name'				=>	__('Stop Words', 'permalink-updator'),
					'function'		=>	array('class' => 'PMU_Admin_Functions', 'method' => 'pro_text')
				),
				'import' => array(
					'name'				=>	__('Custom Permalinks', 'permalink-updator'),
					'function'		=>	array('class' => 'PMU_Admin_Functions', 'method' => 'pro_text')
				)
			)
		);

		return $admin_sections;
	}

	public function display_instructions() {
		return wpautop(__('<strong>A MySQL backup is highly recommended before using "<em>Native slugs</em>" mode!</strong>', 'permalink-updator'));
	}

	public function duplicates_output() {
		global $puUris, $puRedirects;

		// Get the duplicates & another variables
		$all_duplicates = PMU_Helper_Functions::get_all_duplicates();
		$home_url = trim(get_option('home'), "/");

		$html = sprintf("<h3>%s</h3>", __("List of duplicated permalinks", "permalink-updator"));
		$html .= wpautop(sprintf("<a class=\"button button-primary\" href=\"%s\">%s</a>", admin_url('tools.php?page=permalink-updator&section=tools&subsection=duplicates&clear-permalink-updator-uris=1'), __('Fix custom permalinks & redirects', 'permalink-updator')));

		if(!empty($all_duplicates)) {
			foreach($all_duplicates as $uri => $duplicates) {
				$html .= "<div class=\"permalink-updator postbox permalink-updator-duplicate-box\">";
				$html .= "<h4 class=\"heading\"><a href=\"{$home_url}/{$uri}\" target=\"_blank\">{$home_url}/{$uri} <span class=\"dashicons dashicons-external\"></span></a></h4>";
				$html .= "<table>";

				foreach($duplicates as $item_id) {
					$html .= "<tr>";

					// Detect duplicate type
					preg_match("/(redirect-([\d]+)_)?(?:(tax-)?([\d]*))/", $item_id, $parts);

					$is_extra_redirect = (!empty($parts[1])) ? true : false;
					$duplicate_type = ($is_extra_redirect) ? __('Extra Redirect', 'permalink-updator') : __('Custom URI', 'permalink-updator');
					$detected_id = $parts[4];
					$detected_index = $parts[2];
					$detected_term = (!empty($parts[3])) ? true : false;
					$remove_link = ($is_extra_redirect) ? sprintf(" <a href=\"%s\"><span class=\"dashicons dashicons-trash\"></span> %s</a>", admin_url("tools.php?page=permalink-updator&section=tools&subsection=duplicates&remove-redirect={$item_id}"), __("Remove Redirect")) : "";

					// Get term
					if($detected_term && !empty($detected_id)) {
						$term = get_term($detected_id);
						if(!empty($term->name)) {
							$title = $term->name;
							$edit_label = "<span class=\"dashicons dashicons-edit\"></span>" . __("Edit term", "permalink-updator");
							$edit_link = get_edit_tag_link($term->term_id, $term->taxonomy);
						} else {
							$title = __("(Removed term)", "permalink-updator");
							$edit_label = "<span class=\"dashicons dashicons-trash\"></span>" . __("Remove broken URI", "permalink-updator");
							$edit_link = admin_url("tools.php?page=permalink-updator&section=tools&subsection=duplicates&remove-uri=tax-{$detected_id}");
						}
					}
					// Get post
					else if(!empty($detected_id)) {
						$post = get_post($detected_id);
						if(!empty($post->post_title) && post_type_exists($post->post_type)) {
							$title = $post->post_title;
							$edit_label = "<span class=\"dashicons dashicons-edit\"></span>" . __("Edit post", "permalink-updator");
							$edit_link = get_edit_post_link($post->ID);
						} else {
							$title = __("(Removed post)", "permalink-updator");
							$edit_label = "<span class=\"dashicons dashicons-trash\"></span>" . __("Remove broken URI", "permalink-updator");
							$edit_link = admin_url("tools.php?page=permalink-updator&section=tools&subsection=duplicates&remove-uri={$detected_id}");
						}
					} else {
						continue;
					}

					$html .= sprintf(
						'<td><a href="%1$s">%2$s</a>%3$s</td><td>%4$s</td><td class="actions"><a href="%1$s">%5$s</a>%6$s</td>',
						$edit_link,
						$title,
						" <small>#{$detected_id}</small>",
						$duplicate_type,
						$edit_label,
						$remove_link
					);
					$html .= "</tr>";
				}
				$html .= "</table>";
				$html .= "</div>";
			}
		} else {
			$html .= sprintf("<p class=\"alert notice-success notice\">%s</p>", __('Congratulations! No duplicated URIs or Redirects found!', 'permalink-updator'));
		}

		return $html;
	}

	public function find_and_replace_output() {
		// Get all registered post types array & statuses
		$all_post_statuses_array = PMU_Helper_Functions::get_post_statuses();
		$all_post_types = PMU_Helper_Functions::get_post_types_array();
		$all_taxonomies = PMU_Helper_Functions::get_taxonomies_array();

		$fields = apply_filters('permalink_updator_tools_fields', array(
			'old_string' => array(
				'label' => __( 'Find ...', 'permalink-updator' ),
				'type' => 'text',
				'container' => 'row',
				'input_class' => 'widefat'
			),
			'new_string' => array(
				'label' => __( 'Replace with ...', 'permalink-updator' ),
				'type' => 'text',
				'container' => 'row',
				'input_class' => 'widefat'
			),
			'mode' => array(
				'label' => __( 'Mode', 'permalink-updator' ),
				'type' => 'select',
				'container' => 'row',
				'choices' => array(
					'custom_uris' => __('Custom URIs', 'permalink-updator'),
					'slugs' => __('Native slugs', 'permalink-updator')
				),
			),
			'content_type' => array(
				'label' => __( 'Select content type', 'permalink-updator' ),
				'type' => 'select',
				'disabled' => true,
				'pro' => true,
				'container' => 'row',
				'default' => 'post_types',
				'choices' => array(
					'post_types' => __('Post types', 'permalink-updator'),
					'taxonomies' => __('Taxonomies', 'permalink-updator')
				),
			),
			'post_types' => array(
				'label' => __( 'Select post types', 'permalink-updator' ),
				'type' => 'checkbox',
				'container' => 'row',
				'default' => array('post', 'page'),
				'choices' => $all_post_types,
				'select_all' => '',
				'unselect_all' => '',
			),
			'taxonomies' => array(
				'label' => __( 'Select taxonomies', 'permalink-updator' ),
				'type' => 'checkbox',
				'container' => 'row',
				'container_class' => 'hidden',
				'default' => array('category', 'post_tag'),
				'choices' => $all_taxonomies,
				'pro' => true,
				'select_all' => '',
				'unselect_all' => '',
			),
			'post_statuses' => array(
				'label' => __( 'Select post statuses', 'permalink-updator' ),
				'type' => 'checkbox',
				'container' => 'row',
				'default' => array('publish'),
				'choices' => $all_post_statuses_array,
				'select_all' => '',
				'unselect_all' => '',
			),
			'ids' => array(
				'label' => __( 'Select IDs', 'permalink-updator' ),
				'type' => 'text',
				'container' => 'row',
				//'disabled' => true,
				'description' => __('To narrow the above filters you can type the post IDs (or ranges) here. Eg. <strong>1-8, 10, 25</strong>.', 'permalink-updator'),
				//'pro' => true,
				'input_class' => 'widefat'
			)
		), 'find_and_replace');

		$sidebar = '<h3>' . __('Important notices', 'permalink-updator') . '</h3>';
		$sidebar .= self::display_instructions();

		$output = PMU_Admin_Functions::get_the_form($fields, 'columns', array('text' => __('Find and replace', 'permalink-updator'), 'class' => 'primary margin-top'), $sidebar, array('action' => 'permalink-updator', 'name' => 'find_and_replace'), true, 'form-ajax');

		return $output;
	}

	public function regenerate_slugs_output() {
		// Get all registered post types array & statuses
		$all_post_statuses_array = PMU_Helper_Functions::get_post_statuses();
		$all_post_types = PMU_Helper_Functions::get_post_types_array();
		$all_taxonomies = PMU_Helper_Functions::get_taxonomies_array();

		$fields = apply_filters('permalink_updator_tools_fields', array(
			'mode' => array(
				'label' => __( 'Mode', 'permalink-updator' ),
				'type' => 'select',
				'container' => 'row',
				'choices' => array(
					'custom_uris' => __('Regenerate custom permalinks', 'permalink-updator'),
					'slugs' => __('Regenerate native slugs', 'permalink-updator'),
					'native' => __('Use original URLs as custom permalinks', 'permalink-updator')
				),
			),
			'content_type' => array(
				'label' => __( 'Select content type', 'permalink-updator' ),
				'type' => 'select',
				'disabled' => true,
				'pro' => true,
				'container' => 'row',
				'default' => 'post_types',
				'choices' => array(
					'post_types' => __('Post types', 'permalink-updator'),
					'taxonomies' => __('Taxonomies', 'permalink-updator')
				),
			),
			'post_types' => array(
				'label' => __( 'Select post types', 'permalink-updator' ),
				'type' => 'checkbox',
				'container' => 'row',
				'default' => array('post', 'page'),
				'choices' => $all_post_types,
				'select_all' => '',
				'unselect_all' => '',
			),
			'taxonomies' => array(
				'label' => __( 'Select taxonomies', 'permalink-updator' ),
				'type' => 'checkbox',
				'container' => 'row',
				'container_class' => 'hidden',
				'default' => array('category', 'post_tag'),
				'choices' => $all_taxonomies,
				'pro' => true,
				'select_all' => '',
				'unselect_all' => '',
			),
			'post_statuses' => array(
				'label' => __( 'Select post statuses', 'permalink-updator' ),
				'type' => 'checkbox',
				'container' => 'row',
				'default' => array('publish'),
				'choices' => $all_post_statuses_array,
				'select_all' => '',
				'unselect_all' => '',
			),
			'ids' => array(
				'label' => __( 'Select IDs', 'permalink-updator' ),
				'type' => 'text',
				'container' => 'row',
				//'disabled' => true,
				'description' => __('To narrow the above filters you can type the post IDs (or ranges) here. Eg. <strong>1-8, 10, 25</strong>.', 'permalink-updator'),
				//'pro' => true,
				'input_class' => 'widefat'
			)
		), 'regenerate');

		$sidebar = '<h3>' . __('Important notices', 'permalink-updator') . '</h3>';
		$sidebar .= self::display_instructions();

		$output = PMU_Admin_Functions::get_the_form($fields, 'columns', array('text' => __( 'Regenerate', 'permalink-updator' ), 'class' => 'primary margin-top'), $sidebar, array('action' => 'permalink-updator', 'name' => 'regenerate'), true, 'form-ajax');

		return $output;
	}
}
