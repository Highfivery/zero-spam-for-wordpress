/**
 * Network Settings JavaScript
 *
 * Handles interactive functionality for the Network Settings page.
 */

(function($) {
	'use strict';

	const ZeroSpamNetworkSettings = {
		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			// Lock/Unlock toggle
			$(document).on('click', '.toggle-lock', this.toggleLock);

			// Save individual setting
			$(document).on('click', '.save-setting', this.saveSetting);

			// Save all settings
			$('#save-all-settings').on('click', this.saveAllSettings);

			// Apply to all sites
			$('#apply-to-all-sites, #apply-to-all-sites-settings').on('click', this.applyToAllSites);

			// Load comparison
			$('#load-comparison').on('click', this.loadComparison);

			// Export settings
			$('#export-settings').on('click', this.exportSettings);

			// Import settings
			$('#import-settings').on('click', this.importSettings);

			// Template actions
			$(document).on('click', '.apply-template-network', this.applyTemplateNetwork);
			$(document).on('click', '.apply-template-sites', this.applyTemplateSites);
			$(document).on('click', '.delete-template', this.deleteTemplate);
			$('#save-current-as-template').on('click', this.saveTemplate);
		},

		/**
		 * Toggle lock status
		 */
		toggleLock: function(e) {
			e.preventDefault();

			const $button = $(this);
			const $row = $button.closest('tr');
			const key = $row.data('setting-key');
			const isLocked = $button.data('locked') === 1;
			const action = isLocked ? 'zerospam_network_unlock_setting' : 'zerospam_network_lock_setting';

			$button.prop('disabled', true);

			$.ajax({
				url: zeroSpamNetwork.ajaxUrl,
				type: 'POST',
				data: {
					action: action,
					nonce: zeroSpamNetwork.nonce,
					key: key
				},
				success: function(response) {
					if (response.success) {
						// Toggle button state
						const newLocked = !isLocked;
						$button.data('locked', newLocked ? 1 : 0);
						$button.html(newLocked 
							? '🔓 Unlock'
							: '🔒 Lock'
						);

						// Update badge
						const $status = $row.find('.setting-status');
						if (newLocked) {
							if ($status.find('.locked-badge').length === 0) {
								$status.prepend('<span class="locked-badge">🔒 Locked</span>');
							}
						} else {
							$status.find('.locked-badge').remove();
						}

						ZeroSpamNetworkSettings.showNotice('success', response.data.message);
					} else {
						ZeroSpamNetworkSettings.showNotice('error', response.data.message);
					}
				},
				error: function() {
					ZeroSpamNetworkSettings.showNotice('error', zeroSpamNetwork.strings.error);
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Save individual setting
		 */
		saveSetting: function(e) {
			e.preventDefault();

			const $button = $(this);
			const $row = $button.closest('tr');
			const key = $row.data('setting-key');
			const $input = $row.find('input, select').first();
			let value;

			if ($input.is(':checkbox')) {
				value = $input.is(':checked') ? 'enabled' : 'disabled';
			} else {
				value = $input.val();
			}

			const locked = $button.siblings('.toggle-lock').data('locked') === 1;

			$button.prop('disabled', true);

			$.ajax({
				url: zeroSpamNetwork.ajaxUrl,
				type: 'POST',
				data: {
					action: 'zerospam_network_set_setting',
					nonce: zeroSpamNetwork.nonce,
					key: key,
					value: value,
					locked: locked ? '1' : '0'
				},
				success: function(response) {
					if (response.success) {
						ZeroSpamNetworkSettings.showNotice('success', response.data.message);
						// Could reload to update status counts
					} else {
						ZeroSpamNetworkSettings.showNotice('error', response.data.message);
					}
				},
				error: function() {
					ZeroSpamNetworkSettings.showNotice('error', zeroSpamNetwork.strings.error);
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Save all settings
		 */
		saveAllSettings: function(e) {
			e.preventDefault();

			const $button = $(this);
			$button.prop('disabled', true).text('Saving...');

			let savedCount = 0;
			let errorCount = 0;
			const $rows = $('.zerospam-settings-table tbody tr');

			$rows.each(function() {
				const $row = $(this);
				const key = $row.data('setting-key');
				const $input = $row.find('input, select').first();
				let value;

				if ($input.is(':checkbox')) {
					value = $input.is(':checked') ? 'enabled' : 'disabled';
				} else {
					value = $input.val();
				}

				const locked = $row.find('.toggle-lock').data('locked') === 1;

				$.ajax({
					url: zeroSpamNetwork.ajaxUrl,
					type: 'POST',
					data: {
						action: 'zerospam_network_set_setting',
						nonce: zeroSpamNetwork.nonce,
						key: key,
						value: value,
						locked: locked ? '1' : '0'
					},
					success: function(response) {
						if (response.success) {
							savedCount++;
						} else {
							errorCount++;
						}
					},
					error: function() {
						errorCount++;
					}
				});
			});

			// Wait for all requests (simplified - in production use Promise.all)
			setTimeout(function() {
				$button.prop('disabled', false).text('Save All Settings');
				if (errorCount === 0) {
					ZeroSpamNetworkSettings.showNotice('success', savedCount + ' settings saved successfully!');
					location.reload();
				} else {
					ZeroSpamNetworkSettings.showNotice('error', errorCount + ' settings failed to save.');
				}
			}, 1000);
		},

		/**
		 * Apply to all sites
		 */
		applyToAllSites: function(e) {
			e.preventDefault();

			if (!confirm(zeroSpamNetwork.strings.confirm_apply)) {
				return;
			}

			const $button = $(this);
			$button.prop('disabled', true).text('Applying...');

			$.ajax({
				url: zeroSpamNetwork.ajaxUrl,
				type: 'POST',
				data: {
					action: 'zerospam_network_apply_all',
					nonce: zeroSpamNetwork.nonce,
					force: '0',
					mode: 'all'
				},
				success: function(response) {
					if (response.success) {
						const msg = response.data.updated_count + ' sites updated, ' + 
									response.data.skipped_count + ' skipped.';
						ZeroSpamNetworkSettings.showNotice('success', msg);
						location.reload();
					} else {
						ZeroSpamNetworkSettings.showNotice('error', response.data.message);
					}
				},
				error: function() {
					ZeroSpamNetworkSettings.showNotice('error', zeroSpamNetwork.strings.error);
				},
				complete: function() {
					$button.prop('disabled', false).text($button.text().replace('Applying...', 'Apply to All Sites'));
				}
			});
		},

		/**
		 * Load comparison
		 */
		loadComparison: function(e) {
			e.preventDefault();

			const $button = $(this);
			const $results = $('#comparison-results');

			$button.prop('disabled', true).text('Loading...');
			$results.html('<p>Loading comparison data...</p>').show();

			$.ajax({
				url: zeroSpamNetwork.ajaxUrl,
				type: 'POST',
				data: {
					action: 'zerospam_network_get_comparison',
					nonce: zeroSpamNetwork.nonce
				},
				success: function(response) {
					if (response.success) {
						ZeroSpamNetworkSettings.renderComparison(response.data.comparison, response.data.sites);
					} else {
						$results.html('<p class="error">Failed to load comparison.</p>');
					}
				},
				error: function() {
					$results.html('<p class="error">Failed to load comparison.</p>');
				},
				complete: function() {
					$button.prop('disabled', false).text('Load Comparison');
				}
			});
		},

		/**
		 * Render comparison table
		 */
		renderComparison: function(comparison, sites) {
			let html = '<table class="wp-list-table widefat striped"><thead><tr>';
			html += '<th>Setting</th>';

			sites.forEach(function(site) {
				html += '<th>' + site.blogname + ' (' + site.blog_id + ')</th>';
			});

			html += '</tr></thead><tbody>';

			$.each(comparison, function(key, data) {
				html += '<tr><td><strong>' + key + '</strong><br>';
				html += '<span class="description">Network: ' + data.network_value + '</span>';
				if (data.locked) {
					html += ' 🔒';
				}
				html += '</td>';

				sites.forEach(function(site) {
					const siteData = data.sites[site.blog_id];
					let cellClass = '';

					if (siteData.source === 'locked') {
						cellClass = 'locked';
					} else if (siteData.source === 'override') {
						cellClass = 'override';
					}

					html += '<td class="' + cellClass + '">';
					html += siteData.value;
					html += '<br><span class="source-badge">' + siteData.source + '</span>';
					html += '</td>';
				});

				html += '</tr>';
			});

			html += '</tbody></table>';

			$('#comparison-results').html(html);
		},

		/**
		 * Export settings
		 */
		exportSettings: function(e) {
			e.preventDefault();

			const $button = $(this);
			$button.prop('disabled', true).text('Exporting...');

			$.ajax({
				url: zeroSpamNetwork.ajaxUrl,
				type: 'POST',
				data: {
					action: 'zerospam_network_export',
					nonce: zeroSpamNetwork.nonce
				},
				success: function(response) {
					if (response.success) {
						// Create download
						const blob = new Blob([response.data.json], {type: 'application/json'});
						const url = window.URL.createObjectURL(blob);
						const a = document.createElement('a');
						a.href = url;
						a.download = 'zerospam-network-settings-' + Date.now() + '.json';
						document.body.appendChild(a);
						a.click();
						window.URL.revokeObjectURL(url);
						document.body.removeChild(a);

						ZeroSpamNetworkSettings.showNotice('success', 'Settings exported successfully!');
					} else {
						ZeroSpamNetworkSettings.showNotice('error', response.data.message);
					}
				},
				error: function() {
					ZeroSpamNetworkSettings.showNotice('error', zeroSpamNetwork.strings.error);
				},
				complete: function() {
					$button.prop('disabled', false).text('Export as JSON');
				}
			});
		},

		/**
		 * Import settings
		 */
		importSettings: function(e) {
			e.preventDefault();

			if (!confirm(zeroSpamNetwork.strings.confirm_import)) {
				return;
			}

			const $button = $(this);
			const file = $('#import-file')[0].files[0];
			const mode = $('input[name="import_mode"]:checked').val();

			if (!file) {
				alert('Please select a file to import.');
				return;
			}

			const reader = new FileReader();

			reader.onload = function(e) {
				const json = e.target.result;

				$button.prop('disabled', true).text('Importing...');

				$.ajax({
					url: zeroSpamNetwork.ajaxUrl,
					type: 'POST',
					data: {
						action: 'zerospam_network_import',
						nonce: zeroSpamNetwork.nonce,
						json: json,
						mode: mode
					},
					success: function(response) {
						if (response.success) {
							const msg = response.data.imported_count + ' settings imported!';
							ZeroSpamNetworkSettings.showNotice('success', msg);
							location.reload();
						} else {
							ZeroSpamNetworkSettings.showNotice('error', response.data.message);
						}
					},
					error: function() {
						ZeroSpamNetworkSettings.showNotice('error', zeroSpamNetwork.strings.error);
					},
					complete: function() {
						$button.prop('disabled', false).text('Import Settings');
					}
				});
			};

			reader.readAsText(file);
		},

		/**
		 * Show notice
		 */
		showNotice: function(type, message) {
			const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
			$('.wrap h1').after($notice);

			setTimeout(function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);
		},

		/**
		 * Apply template to network
		 */
		applyTemplateNetwork: function(e) {
			e.preventDefault();

			const slug = $(this).data('slug');

			if (!confirm('Apply this template to network settings?')) {
				return;
			}

			const $button = $(this);
			$button.prop('disabled', true).text('Applying...');

			$.ajax({
				url: zeroSpamNetwork.ajaxUrl,
				type: 'POST',
				data: {
					action: 'zerospam_network_apply_template',
					nonce: zeroSpamNetwork.nonce,
					slug: slug,
					scope: 'network',
					lock: '0'
				},
				success: function(response) {
					if (response.success) {
						ZeroSpamNetworkSettings.showNotice('success', response.data.message);
						setTimeout(function() {
							location.reload();
						}, 1000);
					} else {
						ZeroSpamNetworkSettings.showNotice('error', response.data.message);
					}
				},
				error: function() {
					ZeroSpamNetworkSettings.showNotice('error', zeroSpamNetwork.strings.error);
				},
				complete: function() {
					$button.prop('disabled', false).text('Apply to Network');
				}
			});
		},

		/**
		 * Apply template to sites
		 */
		applyTemplateSites: function(e) {
			e.preventDefault();

			const slug = $(this).data('slug');

			if (!confirm('Apply this template to all sites?')) {
				return;
			}

			const $button = $(this);
			$button.prop('disabled', true).text('Applying...');

			$.ajax({
				url: zeroSpamNetwork.ajaxUrl,
				type: 'POST',
				data: {
					action: 'zerospam_network_apply_template',
					nonce: zeroSpamNetwork.nonce,
					slug: slug,
					scope: 'sites',
					lock: '0'
				},
				success: function(response) {
					if (response.success) {
						const msg = response.data.updated_count + ' sites updated!';
						ZeroSpamNetworkSettings.showNotice('success', msg);
						setTimeout(function() {
							location.reload();
						}, 1000);
					} else {
						ZeroSpamNetworkSettings.showNotice('error', response.data.message);
					}
				},
				error: function() {
					ZeroSpamNetworkSettings.showNotice('error', zeroSpamNetwork.strings.error);
				},
				complete: function() {
					$button.prop('disabled', false).text('Apply to Sites');
				}
			});
		},

		/**
		 * Save template
		 */
		saveTemplate: function(e) {
			e.preventDefault();

			const name = $('#template-name').val();
			const slug = $('#template-slug').val();
			const description = $('#template-description').val();

			if (!name || !slug) {
				alert('Template name and slug are required.');
				return;
			}

			const $button = $(this);
			$button.prop('disabled', true).text('Saving...');

			$.ajax({
				url: zeroSpamNetwork.ajaxUrl,
				type: 'POST',
				data: {
					action: 'zerospam_network_save_template',
					nonce: zeroSpamNetwork.nonce,
					name: name,
					slug: slug,
					description: description
				},
				success: function(response) {
					if (response.success) {
						ZeroSpamNetworkSettings.showNotice('success', response.data.message);
						// Clear form
						$('#template-name').val('');
						$('#template-slug').val('');
						$('#template-description').val('');
						setTimeout(function() {
							location.reload();
						}, 1000);
					} else {
						ZeroSpamNetworkSettings.showNotice('error', response.data.message);
					}
				},
				error: function() {
					ZeroSpamNetworkSettings.showNotice('error', zeroSpamNetwork.strings.error);
				},
				complete: function() {
					$button.prop('disabled', false).text('Save Current Settings as Template');
				}
			});
		},

		/**
		 * Delete template
		 */
		deleteTemplate: function(e) {
			e.preventDefault();

			const slug = $(this).data('slug');

			if (!confirm('Are you sure you want to delete this template?')) {
				return;
			}

			const $button = $(this);
			const $card = $button.closest('.template-card');

			$button.prop('disabled', true).text('Deleting...');

			$.ajax({
				url: zeroSpamNetwork.ajaxUrl,
				type: 'POST',
				data: {
					action: 'zerospam_network_delete_template',
					nonce: zeroSpamNetwork.nonce,
					slug: slug
				},
				success: function(response) {
					if (response.success) {
						ZeroSpamNetworkSettings.showNotice('success', response.data.message);
						$card.fadeOut(function() {
							$(this).remove();
						});
					} else {
						ZeroSpamNetworkSettings.showNotice('error', response.data.message);
						$button.prop('disabled', false).text('Delete');
					}
				},
				error: function() {
					ZeroSpamNetworkSettings.showNotice('error', zeroSpamNetwork.strings.error);
					$button.prop('disabled', false).text('Delete');
				}
			});
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		ZeroSpamNetworkSettings.init();
	});

})(jQuery);
