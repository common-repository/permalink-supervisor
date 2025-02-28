<?php
class PMU_URI_Functions_Post extends PMU_Class {

	public function __construct() {
		add_action( 'admin_init', array($this, 'admin_init'), 99, 3);
		add_filter( '_get_page_link', array($this, 'custom_post_permalinks'), 99, 2);
		add_filter( 'page_link', array($this, 'custom_post_permalinks'), 99, 2);
		add_filter( 'post_link', array($this, 'custom_post_permalinks'), 99, 2);
		add_filter( 'post_type_link', array($this, 'custom_post_permalinks'), 99, 2);
		add_filter( 'attachment_link', array($this, 'custom_post_permalinks'), 99, 2);
		add_filter( 'permalink_updator_uris', array($this, 'exclude_homepage'), 99);
		add_filter( 'url_to_postid', array($this, 'url_to_postid'), 99);
		add_filter( 'get_sample_permalink_html', array($this, 'edit_uri_box'), 99, 5 );
		add_action( 'save_post', array($this, 'update_post_uri'), 99, 1);
		add_action( 'edit_attachment', array($this, 'update_post_uri'), 99, 1 );
		add_action( 'wp_insert_post', array($this, 'new_post_uri'), 99, 1 );
		add_action( 'add_attachment', array($this, 'new_post_uri'), 99, 1 );
		add_action( 'wp_trash_post', array($this, 'remove_post_uri'), 100, 1 );
		add_action( 'quick_edit_custom_box', array($this, 'quick_edit_column_form'), 99, 3);
	}
	function admin_init() {
 		$post_types = PMU_Helper_Functions::get_post_types_array();

 		// Add "URI Editor" to "Quick Edit" for all post_types
 		foreach($post_types as $post_type => $label) {
 			add_filter( "manage_{$post_type}_posts_columns" , array($this, 'quick_edit_column') );
 			add_filter( "manage_{$post_type}_posts_custom_column" , array($this, 'quick_edit_column_content'), 10, 2 );
 		}
	}

	
	static function custom_post_permalinks($permalink, $post) {
		global $wp_rewrite, $puUris, $puUpdatorOptions;

		// Do not filter permalinks in Customizer
		if((function_exists('is_customize_preview') && is_customize_preview()) || !empty($_REQUEST['customize_url'])) { return $permalink; }

		// Do not filter in WPML String Editor
		if(!empty($_REQUEST['icl_ajx_action']) && sanitize_text_field($_REQUEST['icl_ajx_action']) == 'icl_st_save_translation') { return $permalink; }

		// Do not run when metaboxes are loaded with Gutenberg
		if(!empty($_REQUEST['meta-box-loader']) && empty($_POST['custom_uri'])) { return $permalink; }

		$post = (is_integer($post)) ? get_post($post) : $post;

		// Start with homepage URL
		$home_url = PMU_Helper_Functions::get_permalink_base($post);

		// 1. Check if post type is allowed
		if(!empty($post->post_type) && PMU_Helper_Functions::is_disabled($post->post_type, 'post_type')) { return $permalink; }

		// 2A. Do not change permalink of frontpage
		if(PMU_Helper_Functions::is_front_page($post->ID)) {
			return $permalink;
		}
		// 2B. Do not change permalink for drafts and future posts (+ remove trailing slash from them)
		else if(in_array($post->post_status, array('draft', 'pending', 'auto-draft', 'future'))) {
			return $permalink;
		}

		// 3. Save the old permalink to separate variable
		$old_permalink = $permalink;

		// 4. Filter only the posts with custom permalink assigned
		if(isset($puUris[$post->ID])) {
			// Encode URI?
			if(!empty($puUpdatorOptions['general']['decode_uris'])) {
				$permalink = "{$home_url}/" . rawurldecode("/{$puUris[$post->ID]}");
			} else {
				$permalink = "{$home_url}/" . PMU_Helper_Functions::encode_uri("{$puUris[$post->ID]}");
			}
		} else if($post->post_type == 'attachment' && $post->post_parent > 0 && $post->post_parent != $post->ID && !empty($puUris[$post->post_parent])) {
			$permalink = "{$home_url}/{$puUris[$post->post_parent]}/attachment/{$post->post_name}";
		} else if(!empty($puUpdatorOptions['general']['decode_uris'])) {
			$permalink = "{$home_url}/" . rawurldecode("/{$permalink}");
		}

		// 5. Allow to filter (do not filter in Customizer)
		if(!(function_exists('is_customize_preview') && is_customize_preview())) {
			return apply_filters('permalink_updator_filter_final_post_permalink', $permalink, $post, $old_permalink);
		} else {
			return $old_permalink;
		}
	}

	
	static function update_slug_by_id($slug, $id) {
		global $wpdb;

		// Update slug and make it unique
		$slug = (empty($slug)) ? get_the_title($id) : $slug;
		$slug = sanitize_title($slug);

		$new_slug = wp_unique_post_slug($slug, $id, get_post_status($id), get_post_type($id), null);
		$wpdb->query($wpdb->prepare("UPDATE {$wpdb->posts} SET post_name = %s WHERE ID = %d", $new_slug, $id));

		return $new_slug;
	}

	
	public static function get_post_uri($post_id, $native_uri = false, $is_draft = false) {
		global $puUris;

		// Check if input is post object
		$post_id = (isset($post_id->ID)) ? $post_id->ID : $post_id;

		if(!empty($puUris[$post_id])) {
			$final_uri = $puUris[$post_id];
		} else if(!$is_draft) {
			$final_uri = self::get_default_post_uri($post_id, $native_uri);
		} else {
			$final_uri = '';
		}

		return $final_uri;
	}

	
	public static function get_default_post_uri($post, $native_uri = false, $check_if_disabled = false) {
		global $puUpdatorOptions, $puUris, $puPermastructs, $wp_post_types;

		// Load all bases & post
		$post = is_object($post) ? $post : get_post($post);

		// Check if post ID is defined (and front page permalinks should be empty)
		if(empty($post->ID) || PMU_Helper_Functions::is_front_page($post->ID)) { return ''; }

		$post_id = $post->ID;
		$post_type = $post->post_type;
		$post_name = (empty($post->post_name)) ? PMU_Helper_Functions::sanitize_title($post->post_title) : $post->post_name;

		// 1A. Check if post type is allowed
		if($check_if_disabled && PMU_Helper_Functions::is_disabled($post_type, 'post_type')) { return ''; }

		// 1A. Get the native permastructure
		if($post_type == 'attachment') {
			$parent_page = ($post->post_parent > 0 && $post->post_parent != $post->ID) ? get_post($post->post_parent) : false;

			if(!empty($parent_page->ID)) {
				$parent_page_uri = (!empty($puUris[$parent_page->ID])) ? $puUris[$parent_page->ID] : get_page_uri($parent_page->ID);
			} else {
				$parent_page_uri = "";
			}

			$native_permastructure = ($parent_page) ? trim($parent_page_uri, "/") . "/attachment" : "";
		} else {
			$native_permastructure = PMU_Helper_Functions::get_default_permastruct($post_type);
		}

		// 1B. Get the permastructure
		if($native_uri || empty($puPermastructs['post_types'][$post_type])) {
			$permastructure = $native_permastructure;
		} else {
			$permastructure = apply_filters('permalink_updator_filter_permastructure', $puPermastructs['post_types'][$post_type], $post);
		}

		// 1C. Set the permastructure
		$default_base = (!empty($permastructure)) ? trim($permastructure, '/') : "";

		// 2A. Get the date
		$date = explode(" ", date('Y m d H i s', strtotime($post->post_date)));
		$monthname = sanitize_title(date_i18n('F', strtotime($post->post_date)));

		// 2B. Get the author (if needed)
		$author = '';
		if(strpos($default_base, '%author%') !== false) {
			$authordata = get_userdata($post->post_author);
			$author = $authordata->user_nicename;
		}

		// 2C. Get the post type slug
		if(!empty($wp_post_types[$post_type])) {
			if(!empty($wp_post_types[$post_type]->rewrite['slug'])) {
				$post_type_slug = $wp_post_types[$post_type]->rewrite['slug'];
			} else if(is_string($wp_post_types[$post_type]->rewrite)) {
				$post_type_slug = $wp_post_types[$post_type]->rewrite;
			}
		}

		$post_type_slug = (!empty($post_type_slug)) ? $post_type_slug : $post_type;
		$post_type_slug = apply_filters('permalink_updator_filter_post_type_slug', $post_type_slug, $post, $post_type);
		$post_type_slug = preg_replace('/(%([^%]+)%\/?)/', '', $post_type_slug);

		// 3B. Get the full slug
		$post_name = PMU_Helper_Functions::remove_slashes($post_name);
		$custom_slug = $full_custom_slug = PMU_Helper_Functions::force_custom_slugs($post_name, $post);
		$full_native_slug = $post_name;

		// 3A. Fix for hierarchical CPT (start)
		// $full_slug = (is_post_type_hierarchical($post_type)) ? get_page_uri($post) : $post_name;
		if($post->ancestors && is_post_type_hierarchical($post_type)) {
			foreach($post->ancestors as $parent) {
        $parent = get_post($parent);
        if($parent && $parent->post_name) {
					$full_native_slug = $parent->post_name . '/' . $full_native_slug;
					$full_custom_slug = PMU_Helper_Functions::force_custom_slugs($parent->post_name, $parent) . '/' . $full_custom_slug;
        }
    	}
		}

		// 3B. Allow filter the default slug (only custom permalinks)
		if(!$native_uri) {
			$full_slug = apply_filters('permalink_updator_filter_default_post_slug', $full_custom_slug, $post, $post_name);
		} else {
			$full_slug = $full_native_slug;
		}

		$post_type_tag = PMU_Helper_Functions::get_post_tag($post_type);

		// 3C. Get the standard tags and replace them with their values
		$tags = array('%year%', '%monthnum%', '%monthname%', '%day%', '%hour%', '%minute%', '%second%', '%post_id%', '%author%', '%post_type%');
		$tags_replacements = array($date[0], $date[1], $monthname, $date[2], $date[3], $date[4], $date[5], $post->ID, $author, $post_type_slug);
		$default_uri = str_replace($tags, $tags_replacements, $default_base);

		// 3D. Get the slug tags
		$slug_tags = array($post_type_tag, "%postname%", "%postname_flat%", "%{$post_type}_flat%", "%native_slug%");
		$slug_tags_replacement = array($full_slug, $full_slug, $custom_slug, $custom_slug, $full_native_slug);

		// 3E. Check if any post tag is present in custom permastructure
		$do_not_append_slug = (!empty($puUpdatorOptions['permastructure-settings']['do_not_append_slug']['post_types'][$post_type])) ? true : false;
		$do_not_append_slug = apply_filters("permalink_updator_do_not_append_slug", $do_not_append_slug, $post_type, $post);
		if($do_not_append_slug == false) {
			foreach($slug_tags as $tag) {
				if(strpos($default_uri, $tag) !== false) {
					$do_not_append_slug = true;
					break;
				}
			}
		}

		// 3F. Replace the post tags with slugs or rppend the slug if no post tag is defined
		if(!empty($do_not_append_slug)) {
			$default_uri = str_replace($slug_tags, $slug_tags_replacement, $default_uri);
		} else {
			$default_uri .= "/{$full_slug}";
		}

		// 4. Replace taxonomies
		$taxonomies = get_taxonomies();

		if($taxonomies) {
			foreach($taxonomies as $taxonomy) {
				// 1. Reset $replacement
				$replacement = $replacement_term = "";
				$terms = wp_get_object_terms($post->ID, $taxonomy);

				// 2. Try to use Yoast SEO Primary Term
				$replacement_term = $primary_term = PMU_Helper_Functions::get_primary_term($post->ID, $taxonomy, false);

				// 3. Get the first assigned term to this taxonomy
				if(empty($replacement_term)) {
					$replacement_term = (!is_wp_error($terms) && !empty($terms) && is_object($terms[0])) ? PMU_Helper_Functions::get_lowest_element($terms[0], $terms) : "";
					$replacement_term = apply_filters('permalink_updator_filter_post_terms', $replacement_term, $post, $terms, $taxonomy, $native_uri);
				}

				// 4A. Custom URI as term base
				if(!empty($replacement_term->term_id) && strpos($default_uri, "%{$taxonomy}_custom_uri%") !== false && !empty($puUris["tax-{$replacement_term->term_id}"])) {
					$mode = 1;
				}
				// 4B. Hierarhcical term base
				else if(!empty($replacement_term->term_id) && strpos($default_uri, "%{$taxonomy}_flat%") === false && is_taxonomy_hierarchical($taxonomy)) {
					$mode = 2;
				}
				// 4C. Force flat/non-hierarchical term base - get highgest level term (if %taxonomy_flat% tag is used and primary term is not set)
				else if(!$native_uri && strpos($default_uri, "%{$taxonomy}_flat%") !== false && !empty($terms) && empty($primary_term->slug)) {
					$mode = 3;
				}
				// 4D. Flat/non-hierarchical term base - get first term (if primary term not set)
				else if(empty($primary_term->slug)) {
					$mode = 4;
				}
				// 4E. Flat/non-hierarchical term base - get and force primary term (if set)
				else {
					$mode = 5;
					$replacement_term = $primary_term;
				}

				// Get the replacement slug (custom + native)
				$replacement = PMU_Helper_Functions::get_term_full_slug($replacement_term, $terms, $mode, $native_uri);
				$native_replacement = PMU_Helper_Functions::get_term_full_slug($replacement_term, $terms, $mode, true);

				// Trim slashes
				$replacement  = trim($replacement, '/');
				$native_replacement  = trim($native_replacement, '/');

				// Filter final category slug
				$replacement = apply_filters('permalink_updator_filter_term_slug', $replacement, $replacement_term, $post, $terms, $taxonomy, $native_uri);

				// 4. Do the replacement
				$default_uri = (!empty($replacement)) ? str_replace(array("%{$taxonomy}%", "%{$taxonomy}_flat%", "%{$taxonomy}_custom_uri%", "%{$taxonomy}_native_slug%"), array($replacement, $replacement, $replacement, $native_replacement), $default_uri) : $default_uri;
			}
		}

		return apply_filters('permalink_updator_filter_default_post_uri', $default_uri, $post->post_name, $post, $post_name, $native_uri);
	}

	
	function exclude_homepage($uris) {
		// Find the homepage URI
		$homepage_id = get_option('page_on_front');

		if(is_array($uris) && !empty($uris[$homepage_id])) {
			unset($uris[$homepage_id]);
		}

		return $uris;
	}

	
	public function url_to_postid($url) {
		global $pm_query;

		// Filter only defined URLs
		if(empty($url)) { return $url; }

		// Make sure that $pm_query global is not changed
		$old_pm_query = $pm_query;
		$post = PMU_Core_Functions::detect_post(null, $url, true);
		$pm_query = $old_pm_query;

		if(!empty($post->ID)) {
			$native_uri = self::get_default_post_uri($post->ID, true);
			$native_url = sprintf("%s/%s", trim(home_url(), "/"), $native_uri);
		} else {
			$native_uri = '';
		}

		return (!empty($native_uri)) ? $native_uri : $url;
	}

	
	public static function get_items() {
		global $wpdb;

		// Check if post types & statuses are not empty
		if(empty($_POST['post_types']) || empty($_POST['post_statuses'])) { return false; }

		$post_types_array = sanitize_text_field($_POST['post_types']);
		$post_statuses_array = sanitize_text_field($_POST['post_statuses']);
		$post_types = implode("', '", $post_types_array);
		$post_statuses = implode("', '", $post_statuses_array);

		// Filter the posts by IDs
		$where = '';
		if(!empty($_POST['ids'])) {
			// Remove whitespaces and prepare array with IDs and/or ranges
			$ids = esc_sql(preg_replace('/\s*/m', '', $_POST['ids']));
			preg_match_all("/([\d]+(?:-?[\d]+)?)/x", $ids, $groups);

			// Prepare the extra ID filters
			$where .= "AND (";
			foreach($groups[0] as $group) {
				$where .= ($group == reset($groups[0])) ? "" : " OR ";
				// A. Single number
				if(is_numeric($group)) {
					$where .= "(ID = {$group})";
				}
				// B. Range
				else if(substr_count($group, '-')) {
					$range_edges = explode("-", $group);
					$where .= "(ID BETWEEN {$range_edges[0]} AND {$range_edges[1]})";
				}
			}
			$where .= ")";
		}

		// Get excluded items
		$exclude_posts = $wpdb->get_col("SELECT post_ID FROM {$wpdb->postmeta} AS pm LEFT JOIN {$wpdb->posts} AS p ON (pm.post_ID = p.ID) WHERE pm.meta_key = 'auto_update_uri' AND pm.meta_value = '-2' AND post_type IN ('{$post_types}')");
		if(!empty($exclude_posts)) {
			$where .= sprintf(" AND ID NOT IN ('%s') ", implode("', '", $exclude_posts));
		}

		// Support for attachments
		$attachment_support = (in_array('attachment', $post_types_array)) ? " OR (post_type = 'attachment')" : "";

		// Get the rows before they are altered
		return $wpdb->get_results("SELECT post_type, post_title, post_name, ID FROM {$wpdb->posts} WHERE ((post_status IN ('{$post_statuses}') AND post_type IN ('{$post_types}')){$attachment_support}) {$where}", ARRAY_A);
	}

	
	public static function find_and_replace($chunk = null, $mode = '', $old_string = '', $new_string = '') {
		global $wpdb, $puUris;

		// Reset variables
		$updated_slugs_count = 0;
		$updated_array = array();
		$alert_type = $alert_content = $errors = '';

		// Get the rows before they are altered
		$posts_to_update = ($chunk) ? $chunk : self::get_items();

		// Now if the array is not empty use IDs from each subarray as a key
		if($posts_to_update && empty($errors)) {
			foreach ($posts_to_update as $row) {
				// Get default & native URL
				$native_uri = self::get_default_post_uri($row['ID'], true);
				$default_uri = self::get_default_post_uri($row['ID']);

				$old_post_name = $row['post_name'];
				$old_uri = (isset($puUris[$row['ID']])) ? $puUris[$row['ID']] : $native_uri;

				// Do replacement on slugs (non-REGEX)
				if(preg_match("/^\/.+\/[a-z]*$/i", $old_string)) {
					$regex = stripslashes(trim(sanitize_text_field($_POST['old_string']), "/"));
					$regex = preg_quote($regex, '~');
					$pattern = "~{$regex}~";

					$new_post_name = ($mode == 'slugs') ? preg_replace($pattern, $new_string, $old_post_name) : $old_post_name;
					$new_uri = ($mode != 'slugs') ? preg_replace($pattern, $new_string, $old_uri) : $old_uri;
				} else {
					$new_post_name = ($mode == 'slugs') ? str_replace($old_string, $new_string, $old_post_name) : $old_post_name; // Post name is changed only in first mode
					$new_uri = ($mode != 'slugs') ? str_replace($old_string, $new_string, $old_uri) : $old_uri;
				}

				// echo "{$old_uri} - {$new_uri} - {$native_uri} - {$default_uri} \n";

				// Check if native slug should be changed
				if(($mode == 'slugs') && ($old_post_name != $new_post_name)) {
					self::update_slug_by_id($new_post_name, $row['ID']);
				}

				if(($old_uri != $new_uri) || ($old_post_name != $new_post_name) && !(empty($new_uri))) {
					$puUris[$row['ID']] = $new_uri;
					$updated_array[] = array('item_title' => $row['post_title'], 'ID' => $row['ID'], 'old_uri' => $old_uri, 'new_uri' => $new_uri, 'old_slug' => $old_post_name, 'new_slug' => $new_post_name);
					$updated_slugs_count++;
				}

				do_action('permalink_updator_updated_post_uri', $row['ID'], $new_uri, $old_uri, $native_uri, $default_uri);
			}

			// Filter array before saving
			$puUris = array_filter($puUris);
			update_option('permalink-updator-uris', $puUris);

			$output = array('updated' => $updated_array, 'updated_count' => $updated_slugs_count);
			wp_reset_postdata();
		}

		return ($output) ? $output : "";
	}

	
	static function regenerate_all_permalinks($chunk = null, $mode = '') {
		global $wpdb, $puUris;

		// Reset variables
		$updated_slugs_count = 0;
		$updated_array = array();
		$alert_type = $alert_content = $errors = '';

		// Get the rows before they are altered
		$posts_to_update = ($chunk) ? $chunk : self::get_items();

		// Now if the array is not empty use IDs from each subarray as a key
		if($posts_to_update && empty($errors)) {
			foreach ($posts_to_update as $row) {
				// Get default & native URL
				$native_uri = self::get_default_post_uri($row['ID'], true);
				$default_uri = self::get_default_post_uri($row['ID']);
				$old_post_name = $row['post_name'];
				$old_uri = isset($puUris[$row['ID']]) ? trim($puUris[$row['ID']], "/") : '';
				$correct_slug = ($mode == 'slugs') ? sanitize_title($row['post_title']) : PMU_Helper_Functions::sanitize_title($row['post_title']);

				// Process URI & slug
				$new_slug = wp_unique_post_slug($correct_slug, $row['ID'], get_post_status($row['ID']), get_post_type($row['ID']), null);
				$new_post_name = ($mode == 'slugs') ? $new_slug : $old_post_name; // Post name is changed only in first mode

				// Prepare the new URI
				if($mode == 'slugs') {
					$new_uri = ($old_uri) ? $old_uri : $native_uri;
				} else if($mode == 'native') {
					$new_uri = $native_uri;
				} else {
					$new_uri = $default_uri;
				}

				//print_r("{$old_uri} - {$new_uri} - {$native_uri} - {$default_uri} / - {$new_slug} - {$new_post_name} \n");

				// Check if native slug should be changed
				if(($mode == 'slugs') && ($old_post_name != $new_post_name)) {
					self::update_slug_by_id($new_post_name, $row['ID']);
					clean_post_cache($row['ID']);
				}

				if(($old_uri != $new_uri) || ($old_post_name != $new_post_name)) {
					$puUris[$row['ID']] = $new_uri;
					$updated_array[] = array('item_title' => $row['post_title'], 'ID' => $row['ID'], 'old_uri' => $old_uri, 'new_uri' => $new_uri, 'old_slug' => $old_post_name, 'new_slug' => $new_post_name);
					$updated_slugs_count++;
				}

				do_action('permalink_updator_updated_post_uri', $row['ID'], $new_uri, $old_uri, $native_uri, $default_uri);
			}

			// Filter array before saving
			$puUris = array_filter($puUris);
			update_option('permalink-updator-uris', $puUris);

			$output = array('updated' => $updated_array, 'updated_count' => $updated_slugs_count);
			wp_reset_postdata();
		}

		return (!empty($output)) ? $output : "";
	}

	
	static public function update_all_permalinks() {
		global $puUris;

		// Setup needed variables
		$updated_slugs_count = 0;
		$updated_array = array();

		$old_uris = $puUris;
		$new_uris = isset($_POST['uri']) ? sanitize_text_field($_POST['uri']) : array();

		// Double check if the slugs and ids are stored in arrays
		if (!is_array($new_uris)) $new_uris = explode(',', $new_uris);

		if (!empty($new_uris)) {
			foreach($new_uris as $id => $new_uri) {
				// Prepare variables
				$this_post = get_post($id);
				$updated = '';

				// Get default & native URL
				$native_uri = self::get_default_post_uri($id, true);
				$default_uri = self::get_default_post_uri($id);

				$old_uri = isset($old_uris[$id]) ? trim($old_uris[$id], "/") : "";

				// Process new values - empty entries will be treated as default values
				$new_uri = preg_replace('/\s+/', '', $new_uri);
				$new_uri = (!empty($new_uri)) ? trim($new_uri, "/") : $default_uri;
				$new_slug = (strpos($new_uri, '/') !== false) ? substr($new_uri, strrpos($new_uri, '/') + 1) : $new_uri;

				//print_r("{$old_uri} - {$new_uri} - {$native_uri} - {$default_uri}\n");

				if($new_uri != $old_uri) {
					$old_uris[$id] = $new_uri;
					$updated_array[] = array('item_title' => get_the_title($id), 'ID' => $id, 'old_uri' => $old_uri, 'new_uri' => $new_uri);
					$updated_slugs_count++;
				}

				do_action('permalink_updator_updated_post_uri', $id, $new_uri, $old_uri, $native_uri, $default_uri);
			}

			// Filter array before saving & append the global
			$puUris = array_filter($old_uris);
			update_option('permalink-updator-uris', $puUris);

			$output = array('updated' => $updated_array, 'updated_count' => $updated_slugs_count);
		}

		return ($output) ? $output : "";
	}

	
	function edit_uri_box($html, $id, $new_title, $new_slug, $post) {
		global $puUris, $puUpdatorOptions;

		// Detect auto drafts
		$autosave = (!empty($new_title) && empty($new_slug)) ? true : false;

		// Check if post type is disabled
		if(PMU_Helper_Functions::is_disabled($post->post_type, 'post_type')) { return $html; }

		// Stop the hook (if needed)
		$show_uri_editor = apply_filters("permalink_updator_hide_uri_editor_post_{$post->post_type}", true);
		if(!$show_uri_editor) { return $html; }

		$new_html = preg_replace("/^(<strong>(.*)<\/strong>)(.*)/is", "$1 ", $html);
		$default_uri = self::get_default_post_uri($id);
		$native_uri = self::get_default_post_uri($id, true);

		// Make sure that home URL ends with slash
		$home_url = PMU_Helper_Functions::get_permalink_base($post);

		// A. Display original permalink on front-page editor
		if(PMU_Helper_Functions::is_front_page($id)) {
			preg_match('/href="([^"]+)"/mi', $html, $matches);
			$sample_permalink = (!empty($matches[1])) ? $matches[1] : "";
		}
		else {
			// B. Do not change anything if post is not saved yet (display sample permalink instead)
			if($autosave || empty($post->post_status)) {
				$sample_permalink_uri = $default_uri;
			}
			// C. Display custom URI if set
			else {
				$sample_permalink_uri = (!empty($puUris[$id])) ? $puUris[$id] : $native_uri;
			}

			// Decode URI & allow to filter it
			$sample_permalink_uri = apply_filters('permalink_updator_filter_post_sample_uri', rawurldecode($sample_permalink_uri), $post);

			// Prepare the sample & default permalink
			$sample_permalink = sprintf("%s/<span class=\"editable\">%s</span>", $home_url, str_replace("//", "/", $sample_permalink_uri));

			// Allow to filter the sample permalink URL
			// $sample_permalink = apply_filters('permalink_updator_filter_post_sample_permalink', $sample_permalink, $post);
		}

		// Append new HTML output
		$new_html .= sprintf("<span class=\"sample-permalink-span\"><a id=\"sample-permalink\" href=\"%s\">%s</a></span>&nbsp;", strip_tags($sample_permalink), $sample_permalink);
		$new_html .= (!$autosave) ? PMU_Admin_Functions::display_uri_box($post) : "";

		// Append hidden field with native slug
		$new_html .= (!empty($post->post_name)) ? "<span id=\"editable-post-name-full\">{$post->post_name}</span>" : "";

		return $new_html;
	}

	
	function quick_edit_column($columns) {
		global $current_screen;

		// Get post type
		$post_type = (!empty($current_screen->post_type)) ? $current_screen->post_type : false;

		// Check if post type is disabled
		if($post_type && PMU_Helper_Functions::is_disabled($post_type, 'post_type')) { return $columns; }

		return (is_array($columns)) ? array_merge($columns, array('permalink-updator-col' => __( 'Current URI', 'permalink-updator'))) : $columns;
	}

	function quick_edit_column_content($column_name, $post_id) {
		global $puUris, $puUpdatorOptions;

		if($column_name == "permalink-updator-col") {
			// Get auto-update settings
			$auto_update_val = get_post_meta($post_id, "auto_update_uri", true);
			$auto_update_uri = (!empty($auto_update_val)) ? $auto_update_val : $puUpdatorOptions["general"]["auto_update_uris"];

			$uri = (!empty($puUris[$post_id])) ? rawurldecode($puUris[$post_id]) : self::get_post_uri($post_id, true);
			printf('<span class="permalink-updator-col-uri" data-auto_update="%s">%s</span>', intval($auto_update_uri), $uri);
		}
	}

	function quick_edit_column_form($column_name, $post_type, $taxonomy = '') {
		if(!$taxonomy && $column_name == 'permalink-updator-col') {
			echo PMU_Admin_Functions::quick_edit_column_form();
		}
	}

	function new_post_uri($post_id) {
		global $post, $puUris, $puUpdatorOptions, $puBeforeSectionsHtml;

		// Do not trigger if post is a revision or imported via WP All Import (URI should be set after the post meta is added)
		if(wp_is_post_revision($post_id) || (!empty($_REQUEST['page']) && sanitize_text_field($_REQUEST['page']) == 'pmxi-admin-import')) { return $post_id; }

		// Prevent language mismatch in MultilingualPress plugin
		if(is_admin() && !empty($post->ID) && $post->ID != $post_id) { return $post_id; }

		// Stop when products are imported with WooCommerce importer
		if(!empty($_REQUEST['action']) && sanitize_text_field($_REQUEST['action']) == 'woocommerce_do_ajax_product_import') { return $post_id; }

		// Do not do anything if post is autosaved
		if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return $post_id; }

		// Do not do anything on in "Bulk Edit"
		if(!empty($_REQUEST['bulk_edit'])) { return $post_id; }

		// Hotfix
		if(isset($_POST['custom_uri']) || isset($_POST['permalink-updator-quick-edit']) || isset($puUris[$post_id])) { return $post_id; }

		$post_object = get_post($post_id);

		// Check if post type is allowed
		if(PMU_Helper_Functions::is_disabled($post_object->post_type, 'post_type') || empty($post_object->post_type)) { return $post_id; };

		// Stop the hook (if needed)
		$allow_update_post_type = apply_filters("permalink_updator_new_post_uri_{$post_object->post_type}", true);
		if(!$allow_update_post_type) { return $post_id; }

		// Ignore menu items
		if($post_object->post_type == 'nav_menu_item') { return $post_id; }

		// Ignore auto-drafts & removed posts
		if(in_array($post_object->post_status, array('auto-draft', 'trash'))) { return; }

		$native_uri = self::get_default_post_uri($post_id, true);
		$new_uri = self::get_default_post_uri($post_id);
		$puUris[$post_object->ID] = $new_uri;

		update_option('permalink-updator-uris', $puUris);

		do_action('permalink_updator_new_post_uri', $post_id, $new_uri, $native_uri);
	}

	
	static public function update_post_uri($post_id) {
		global $puUris, $puUpdatorOptions, $puBeforeSectionsHtml;

		// Verify nonce at first
		if(!isset($_POST['permalink-updator-nonce']) || !wp_verify_nonce($_POST['permalink-updator-nonce'], 'permalink-updator-edit-uri-box')) { return $post_id; }

		// Do not do anything if post is autosaved
		if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return $post_id; }

		// Do not do anything on in "Bulk Edit" or when the post is imported via WP All Import
		if(!empty($_REQUEST['bulk_edit']) || (!empty($_REQUEST['page']) && sanitize_text_field($_REQUEST['page']) == 'pmxi-admin-import')) { return $post_id; }

		// Do not do anything if the field with URI or element ID are not present
		if(!isset($_POST['custom_uri']) || empty($_POST['permalink-updator-edit-uri-element-id'])) { return $post_id; }

		// Hotfix
		if($_POST['permalink-updator-edit-uri-element-id'] != $post_id) { return $post_id; }

		// Fix for revisions
		$is_revision = wp_is_post_revision($post_id);
		$post_id = ($is_revision) ? $is_revision : $post_id;
		$post = get_post($post_id);

		// Check if post type is allowed
		if(PMU_Helper_Functions::is_disabled($post->post_type, 'post_type') || empty($post->post_type)) { return $post_id; };

		// Stop the hook (if needed)
		$allow_update_post_type = apply_filters("permalink_updator_update_post_uri_{$post->post_type}", true);
		if(!$allow_update_post_type) { return $post_id; }

		// Hotfix for menu items
		if($post->post_type == 'nav_menu_item') { return $post_id; }

		// Ignore auto-drafts & removed posts
		if(in_array($post->post_status, array('auto-draft', 'trash'))) { return $post_id; }

		// Get auto-update URI setting (if empty use global setting)
		if(!empty($_POST["auto_update_uri"])) {
			$auto_update_uri_current = intval($_POST["auto_update_uri"]);
		} else if(!empty($_POST["action"]) && sanitize_text_field($_POST['action']) == 'inline-save') {
			$auto_update_uri_current = get_post_meta($post_id, "auto_update_uri", true);
		}
		$auto_update_uri = (!empty($auto_update_uri_current)) ? $auto_update_uri_current : $puUpdatorOptions["general"]["auto_update_uris"];

		// Update the slug (if changed)
		if(isset($_POST['permalink-updator-edit-uri-element-slug']) && isset($_POST['native_slug']) && ($_POST['native_slug'] !== $_POST['permalink-updator-edit-uri-element-slug'])) {
			self::update_slug_by_id($_POST['native_slug'], $post_id);
			clean_post_cache($post_id);
		}

		$default_uri = self::get_default_post_uri($post_id);
		$native_uri = self::get_default_post_uri($post_id, true);
		$old_uri = (isset($puUris[$post->ID])) ? $puUris[$post->ID] : $native_uri;

		// Use default URI if URI is cleared by user OR URI should be automatically updated
		$new_uri = (($_POST['custom_uri'] == '') || $auto_update_uri == 1) ? $default_uri : PMU_Helper_Functions::sanitize_title($_POST['custom_uri'], true);

		// Save or remove "Auto-update URI" settings
		if(!empty($auto_update_uri_current)) {
			update_post_meta($post_id, "auto_update_uri", $auto_update_uri_current);
		} elseif(isset($_POST['auto_update_uri'])) {
			delete_post_meta($post_id, "auto_update_uri");
		}

		// Save only changed URIs
		$puUris[$post_id] = $new_uri;
		update_option('permalink-updator-uris', $puUris);

		do_action('permalink_updator_updated_post_uri', $post_id, $new_uri, $old_uri, $native_uri, $default_uri, $single_update = true);
	}


	function remove_post_uri($post_id) {
		global $puUris;

		// Check if the custom permalink is assigned to this post
		if(isset($puUris[$post_id])) {
			unset($puUris[$post_id]);
		}

		update_option('permalink-updator-uris', $puUris);
	}

}

?>
