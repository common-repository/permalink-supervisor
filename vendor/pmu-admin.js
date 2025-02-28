jQuery(document).ready(function() {

	var checkbox_actions = ['select_all', 'unselect_all'];
 	checkbox_actions.forEach(function(element) {
		jQuery('#permalink-updator .' + element).on('click', function() {
			jQuery(this).parents('.field-container').find('.checkboxes input[type="checkbox"]').each(function() {
				var action = (element == 'select_all') ? true : false;
				jQuery(this).prop('checked', action);
			});
			return false;
		});
	});

	jQuery('#permalink-updator .checkboxes label, #permalink-updator .single_checkbox label').not('input').on('click', function(ev) {
		var input = jQuery(this).find("input");
		if(!jQuery(ev.target).is("input")) {
			input.prop('checked', !(input.prop("checked")));
		}
	});

	jQuery('.pm-confirm-action').on('click', function () {
		return confirm(permalink_updator.confirm);
	});

	jQuery('#permalink-updator #months-filter-button, #permalink-updator #search-submit').on('click', function(e) {
		var search_value = jQuery('#permalink-updator input[name="s"]').val();
		var filter_value = jQuery("#months-filter-select").val();
		var filter_url 	 = window.location.href;

		// Date filter
		if(filter_url.indexOf('month=') > 1) {
			filter_url = filter_url.replace(/month=([^&]+)/gm, 'month=' + filter_value);
		} else if(filter_value != '') {
			filter_url = filter_url + '&month=' + filter_value;
		}

		// Search query
		if(filter_url.indexOf('s=') > 1) {
			filter_url = filter_url.replace(/s=([^&]+)/gm, 's=' + search_value);
		} else if(search_value != '') {
			filter_url = filter_url + '&s=' + search_value;
		}

		window.location.href = filter_url;
		e.preventDefault();
		return false;
	});

	jQuery('#permalink-updator #uri_editor form input[name="s"]').on('keydown keypress keyup', function(e){
		if(e.keyCode == 13) {
			jQuery('#permalink-updator #search-submit').trigger('click');
			e.preventDefault();
			return false;
		}
	});

	jQuery('#permalink-updator *[data-field="content_type"] select').on('change', function() {
		var content_type = jQuery(this).val();
		if(content_type == 'post_types') {
			jQuery(this).parents('.form-table').find('*[data-field="post_types"],*[data-field="post_statuses"]').removeClass('hidden');
			jQuery(this).parents('.form-table').find('*[data-field="taxonomies"]').addClass('hidden');
		} else {
			jQuery(this).parents('.form-table').find('*[data-field="post_types"],*[data-field="post_statuses"]').addClass('hidden');
			jQuery(this).parents('.form-table').find('*[data-field="taxonomies"]').removeClass('hidden');
		}
	}).trigger("change");


	jQuery('#permalink-updator-toggle, .permalink-updator-edit-uri-box .close-button').on('click', function() {
		jQuery('.permalink-updator-edit-uri-box').slideToggle();
		return false;
	});

	jQuery('#permalink-updator').on('click', '#toggle-redirect-panel', function() {
		jQuery('#redirect-panel-inside').slideToggle();
		return false;
	});

	jQuery('#permalink-updator').on('click', '.permalink-updator.redirects-panel #permalink-updator-new-redirect', function() {
		// Find the table
		var table = jQuery(this).parents('.redirects-panel').find('table');

		// Copy the row from the sample
		var new_row = jQuery(this).parents('.redirects-panel').find('.sample-row').clone().removeClass('sample-row');

		// Adjust the array key
		var last_key = jQuery(table).find("tr:last-of-type input[data-index]").data("index") + 1;
		jQuery("input[data-index]", new_row).attr("data-index", last_key).attr("name", function(){ return jQuery(this).attr("name") + "[" + last_key + "]" });

		// Append the new row
		jQuery(table).append(new_row);
		return false;
	});

	jQuery('#permalink-updator').on('click', '.remove-redirect', function() {
		var table = jQuery(this).closest('tr').remove();
		return false;
	});

	var custom_uri_input = jQuery('.permalink-updator-edit-uri-box input[name="custom_uri"]');
	jQuery(custom_uri_input).on('keyup change', function() {
		jQuery('.sample-permalink-span .editable').text(jQuery(this).val());
	});

	jQuery('#permalink-updator-coupon-url input[name="custom_uri"]').on('keyup change', function() {
		var uri = jQuery(this).val();
		jQuery('#permalink-updator-coupon-url code span').text(uri);
		if(!uri) {
			jQuery('#permalink-updator-coupon-url .coupon-full-url').addClass("hidden");
		} else {
			jQuery('#permalink-updator-coupon-url .coupon-full-url').removeClass("hidden");
		}
	});

	function permalink_updator_duplicate_check(custom_uri_input, multi) {
		// Set default values
		custom_uri_input = typeof custom_uri_input !== 'undefined' ? custom_uri_input : false;
  		multi = typeof multi !== 'undefined' ? multi : false;

		var all_custom_uris_values = {};
		if(custom_uri_input) {
			var custom_uri = jQuery(custom_uri_input).val();
			var element_id = jQuery(custom_uri_input).attr("data-element-id");

			all_custom_uris_values[element_id] = custom_uri;
		} else {
			jQuery('.custom_uri').each(function(i, obj) {
				var field_name = jQuery(obj).attr('data-element-id');
			  all_custom_uris_values[field_name] = jQuery(obj).val();
			});
		}

		if(all_custom_uris_values) {
			jQuery.ajax(permalink_updator.ajax_url, {
				type: 'POST',
				async: true,
				data: {
					action: 'detect_duplicates',
					custom_uris: all_custom_uris_values
				},
				success: function(data) {
					if(data.length > 5) {
						try {
							var results = JSON.parse(data);
						} catch (e) {
							return;
						}

						// Loop through results
						jQuery.each(results, function(key, is_duplicate) {
							var alert_container = jQuery('.custom_uri[data-element-id="' + key + '"]').parents('.custom_uri_container').find('.duplicated_uri_alert');

							if(is_duplicate) {
								jQuery(alert_container).text(is_duplicate);
							} else {
								jQuery(alert_container).empty();
							}
						});
					}
				}
			});
		}
	}


	var custom_uri_check_timeout = null;
	jQuery('.custom_uri_container input[name="custom_uri"], .custom_uri_container input.custom_uri').each(function() {
		var input = this;

		jQuery(this).on('keyup change', function() {
			clearTimeout(custom_uri_check_timeout);

			// Wait until user finishes typing
	    custom_uri_check_timeout = setTimeout(function() {
				permalink_updator_duplicate_check(input);
	    	}, 500);
		});

	});

	
	if(jQuery('#uri_editor .custom_uri').length > 0) {
		permalink_updator_duplicate_check(false, true);
	}

	
	jQuery('#permalink-updator').on('change', 'select[name="auto_update_uri"]', function() {
		var selected = jQuery(this).find('option:selected');
		var auto_update_status = jQuery(selected).data('auto-update');
		var container = jQuery(this).parents('#permalink-updator');

		if(auto_update_status == 1) {
			jQuery(container).find('input[name="custom_uri"]').attr("readonly", true);
		} else {
			jQuery(container).find('input[name="custom_uri"]').removeAttr("readonly", true);
		}
	});
	jQuery('select[name="auto_update_uri"]').trigger("change");

	
	jQuery('#permalink-updator').on('click', '.restore-default', function() {
		var input = jQuery(this).parents('.field-container, .permalink-updator-edit-uri-box, #permalink-updator .inside').find('input.custom_uri, input.permastruct-field');
		var default_uri = jQuery(input).attr('data-default');

		jQuery(input).val(default_uri).trigger('keyup');

		return false;
	});

	
	jQuery('#permalink-updator').on('click', '.permastruct-toggle-button a', function() {
		jQuery(this).parents('.field-container').find('.permastruct-toggle').slideToggle();

		return false;
	});

	
	jQuery(document).on('click', '.permalink-updator-notice.is-dismissible .notice-dismiss', function() {
		var alert_id = jQuery(this).closest('.permalink-updator-notice').data('alert_id');

		jQuery.ajax(permalink_updator.ajax_url, {
			type: 'POST',
			data: {
				action: 'dismissed_notice_handler',
				alert_id: alert_id,
			}
		});
	});


	jQuery('#permalink-updator .save-row.hidden').removeClass('hidden');
	jQuery('#permalink-updator').on('click', '#permalink-updator-save-button', function() {
		pm_reload_gutenberg_uri_editor();
		return false;
	});

	var pm_reload_pending = false;
	function pm_reload_gutenberg_uri_editor() {
		var pm_container = jQuery('#permalink-updator.postbox');
		var pm_fields 	= jQuery(pm_container).find("input, select");
		var pm_data 	= jQuery(pm_fields).serialize() + '&action=' + 'pu_save_permalink';

		// Do not duplicate AJAX requests
		if(!pm_reload_pending) {
			// console.log(pm_data);

			jQuery.ajax({
				type: 'POST',
				url: permalink_updator.ajax_url,
				data: pm_data,
				beforeSend: function() {
					pm_reload_pending = true;

					jQuery(pm_container).LoadingOverlay("show", {
						background  : "rgba(0, 0, 0, 0.1)",
					});
				},
				success: function(html) {
					jQuery(pm_container).find('.permalink-updator-gutenberg').replaceWith(html);
					jQuery(pm_container).LoadingOverlay("hide");

					jQuery(pm_container).find('select[name="auto_update_uri"]').trigger("change");
					pm_help_tooltips();

					if(wp && wp.data !== 'undefined') {
						wp.data.dispatch('controllers/editor').refreshPost();
					}

					pm_reload_pending = false;
	      }
			});
		}
	}


	try {
		if(typeof wp !== 'undefined' && typeof wp.data !== 'undefined' && typeof wp.data.select !== 'undefined' && typeof wp.blocks !== 'undefined' && typeof wp.data.subscribe !== 'undefined') {
			if(wp.data.select('controllers/editor') == undefined || wp.data.select('controllers/editor') == null) {
				throw "Gutenberg was not loaded correctly!";
			}

			wp.data.subscribe(function () {
				var isSavingPost = wp.data.select('controllers/editor').isSavingPost();
				var isAutosavingPost = wp.data.select('controllers/editor').isAutosavingPost();

				if(isSavingPost && !isAutosavingPost) {
					old_status = wp.data.select('controllers/editor').getCurrentPostAttribute('status');
					new_status = wp.data.select('controllers/editor').getEditedPostAttribute('status');

					old_title = wp.data.select('controllers/editor').getCurrentPostAttribute('title');
					new_title = wp.data.select('controllers/editor').getEditedPostAttribute('title');

					old_slug = wp.data.select('controllers/editor').getCurrentPostAttribute('slug');
					new_slug = wp.data.select('controllers/editor').getEditedPostAttribute('slug');

					if((old_status !== new_status && new_status == 'publish') || (old_title !== new_title) || (old_slug !== new_slug)) {
						setTimeout(function() {
							pm_reload_gutenberg_uri_editor();
						}, 1500);
					}
				}
			})
		};
	} catch (e) {
		console.log(e);
	}

	
	function pm_help_tooltips() {
		if(jQuery('#permalink-updator .help_tooltip').length > 0) {
			new Tippy('#permalink-updator .help_tooltip', {
				position: 'top-start',
				arrow: true,
				theme: 'tippy-pm',
				distance: 20,
			});
		}
	}
	pm_help_tooltips();


	
	jQuery(document).on('click', '#pm_get_exp_date', function() {
		jQuery.ajax(permalink_updator.ajax_url, {
			type: 'POST',
			data: {
				action: 'pm_get_exp_date',
			},
			beforeSend: function() {
				var spinner = '<img src="' + permalink_updator.spinners + '/wpspin_light-2x.gif" width="16" height="16">';
				jQuery('#permalink-updator .licence-info').html(spinner);
			},
			success: function(data) {
				jQuery('#permalink-updator .licence-info').html(data);
			}
		});

		return false;
	});


	function pm_show_progress(elem, progress) {
		if(progress) {
			jQuery(elem).LoadingOverlay("text", progress + "%");
		} else {
			jQuery(elem).LoadingOverlay("show", {
				background  : "rgba(0, 0, 0, 0.1)",
				text: '0%'
			});
		}
	}

	jQuery('#permalink-updator #tools form.form-ajax').on('submit', function() {
		var data = jQuery(this).serialize() + '&action=' + 'pm_bulk_tools';
		var form = jQuery(this);
		var updated_count = total = progress = 0;

		// Hide alert & results table
		jQuery('#permalink-updator .updated-slugs-table, .permalink-updator-notice.updated_slugs, #permalink-updator #updated-list').remove();

		jQuery.ajax({
			type: 'POST',
			url: permalink_updator.ajax_url,
			data: data,
			beforeSend: function() {
				// Show progress overlay
				pm_show_progress("#permalink-updator #tools", progress);
			},
			success: function(data) {
				var table_dom = jQuery('#permalink-updator .updated-slugs-table');
				// console.log(data);

				// Display the table
				if(data.hasOwnProperty('html')) {
					var table = jQuery(data.html);

					if(table_dom.length == 0) {
						jQuery('#permalink-updator #tools').after(data.html);
					} else {
						jQuery(table_dom).append(jQuery(table).find('tbody').html());
					}
				}

				// Hide error message
				jQuery('.permalink-updator-notice.updated_slugs.error').remove();

				// Display the alert (should be hidden at first)
				if(data.hasOwnProperty('alert') && jQuery('.permalink-updator-notice.updated_slugs .updated_count').length == 0) {
					var alert = jQuery(data.alert).hide();
					jQuery('#plugin-name-heading').after(alert);
				}

				// Increase updated count
				if(data.hasOwnProperty('updated_count')) {
					if(jQuery(form).attr("data-updated_count")) {
						updated_count = parseInt(jQuery(form).attr("data-updated_count")) + parseInt(data.updated_count);
					} else {
						updated_count = parseInt(data.updated_count);
					}

					jQuery(form).attr("data-updated_count", updated_count);
					jQuery('.permalink-updator-notice.updated_slugs .updated_count').text(updated_count);
				}

				// Show total
				if(data.hasOwnProperty('total')) {
					total = parseInt(data.total);

					jQuery(form).attr("data-total", total);
				}

				// Trigger again
				if(data.hasOwnProperty('left_chunks')) {
					jQuery.ajax(this);

					// Update progress
					if(data.hasOwnProperty('progress')) {
						progress = Math.floor((data.progress / total) * 100)
						console.log(data.progress + "/" + total + " = " + progress + "%");
					}
				} else {
					// Display results
					jQuery('.permalink-updator-notice.updated_slugs').fadeIn();
					jQuery('#permalink-updator #tools').LoadingOverlay("hide", true);

					if(table_dom.length > 0) {
						jQuery('html, body').animate({
							scrollTop: table_dom.offset().top - 100
	          }, 2000);
					}

					// Reset progress & updated count
					progress = updated_count = 0;
					jQuery(form).attr("data-updated_count", 0);
				}
      },
			error: function(xhr, status, error_data) {
				alert('Tthere was a problem running this tool and the process could not be completed.')
			}
		});

		return false;
	});

	
	var stop_words_input = '#permalink-updator .field-container textarea.stop_words';

	if(jQuery(stop_words_input).length > 0) {
		var stop_words = new TIB(document.querySelector(stop_words_input), {
			alert: false,
			//escape: null,
			escape: [','],
			classes: ['tags words-editor', 'tag', 'tags-input', 'tags-output', 'tags-view'],
		});
		jQuery('.tags-output').hide();

		// Force lowercase
		stop_words.filter = function(text) {
			return text.toLowerCase();
		};

		// Remove all words
		jQuery('#permalink-updator .field-container .clear_all_words').on('click', function() {
			stop_words.reset();
		});


	}

	/**
	 * Quick Edit
	 */
	if(typeof inlineEditPost !== "undefined") {
		var inline_post_editor = inlineEditPost.edit;
		inlineEditPost.edit = function(id) {
			inline_post_editor.apply(this, arguments);

			// Get the Post ID
			var post_id = 0;
			if(typeof(id) == 'object') {
				post_id = parseInt(this.getId(id));
			}

			if(post_id != 0) {
				// Get the row & "Custom URI" field
				custom_uri_field = jQuery('#edit-' + post_id).find('.custom_uri');

				// Prepare the Custom URI
				custom_uri = jQuery("#post-" + post_id).find(".column-permalink-updator-col").text();

				// Fill with the Custom URI
				custom_uri_field.val(custom_uri);

				// Get auto-update settings
				auto_update = jQuery("#post-" + post_id).find(".permalink-updator-col-uri").attr('data-auto_update');
				if(typeof auto_update !== "undefined" && auto_update == 1) {
					custom_uri_field.attr('readonly', 'readonly');
				}

				console.log(auto_update);

				// Set the element ID
				jQuery('#edit-' + post_id).find('.permalink-updator-edit-uri-element-id').val(post_id);
			}
		}
	}

	if(typeof inlineEditTax !== "undefined") {
		var inline_tax_editor = inlineEditTax.edit;
		inlineEditTax.edit = function(id) {
			inline_tax_editor.apply(this, arguments);

			// Get the Post ID
			var term_id = 0;
			if(typeof(id) == 'object') {
				term_id = parseInt(this.getId(id));
			}

			if(term_id != 0) {
				// Get the row & "Custom URI" field
				custom_uri_field = jQuery('#edit-' + term_id).find('.custom_uri');

				// Prepare the Custom URI
				custom_uri = jQuery("#tag-" + term_id).find(".column-permalink-updator-col").text();

				// Fill with the Custom URI
				custom_uri_field.val(custom_uri);

				// Set the element ID
				jQuery('#edit-' + term_id).find('.permalink-updator-edit-uri-element-id').val("tax-" + term_id);
			}
		}
	}

});
