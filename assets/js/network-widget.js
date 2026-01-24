/**
 * Network Overview Widget JavaScript
 *
 * Handles tab switching and data refresh for the dashboard widget
 */

(function ($) {
	'use strict';

	$(document).ready(function () {
		const $widget = $('.zerospam-network-overview-widget');

		if (!$widget.length) {
			return;
		}

		// Tab switching
		$widget.on('click', '.tab-button', function (e) {
			e.preventDefault();

			const $button = $(this);
			const tabName = $button.data('tab');

			// Update active states
			$button.addClass('active').siblings('.tab-button').removeClass('active');

			// Show/hide content
			$widget
				.find(`.tab-content[data-tab="${tabName}"]`)
				.addClass('active')
				.siblings('.tab-content')
				.removeClass('active');
		});

		// Refresh button
		$widget.on('click', '.refresh-overview', function (e) {
			e.preventDefault();

			const $button = $(this);
			const $icon = $button.find('.dashicons');

			// Disable button and animate icon
			$button.prop('disabled', true);
			$icon.addClass('spin');

			// Make AJAX request
			$.ajax({
				url: zerospamNetworkWidget.ajaxUrl,
				type: 'POST',
				data: {
					action: 'zerospam_refresh_overview',
					nonce: zerospamNetworkWidget.nonce,
				},
				success: function (response) {
					if (response.success) {
						// Reload the page to show fresh data
						window.location.reload();
					} else {
						alert(response.data.message || 'Failed to refresh data');
						$button.prop('disabled', false);
						$icon.removeClass('spin');
					}
				},
				error: function () {
					alert('Failed to refresh data. Please try again.');
					$button.prop('disabled', false);
					$icon.removeClass('spin');
				},
			});
		});

		// Add spin animation
		const style = document.createElement('style');
		style.innerHTML = `
			@keyframes spin {
				from { transform: rotate(0deg); }
				to { transform: rotate(360deg); }
			}
			.dashicons.spin {
				animation: spin 1s linear infinite;
			}
		`;
		document.head.appendChild(style);
	});
})(jQuery);
