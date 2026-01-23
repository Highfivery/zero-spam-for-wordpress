/**
 * Zero Spam API Usage Dashboard Widget JavaScript
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		const widget = $('.zerospam-api-usage-widget');
		
		if (!widget.length) {
			return;
		}

		// Refresh button handler
		widget.on('click', '.refresh-usage', function(e) {
			e.preventDefault();
			
			const button = $(this);
			const isNetwork = $('#zerospam_api_usage_widget').find('.network-note').length > 0;
			
			// Disable button and show loading state
			button.prop('disabled', true).addClass('refreshing');
			
			// AJAX request to refresh data
			$.ajax({
				url: zerospamApiWidget.ajaxUrl,
				type: 'POST',
				data: {
					action: 'zerospam_refresh_api_usage',
					nonce: zerospamApiWidget.nonce,
					is_network: isNetwork ? '1' : '0'
				},
				success: function(response) {
					if (response.success) {
						// Reload the page to show fresh data
						location.reload();
					} else {
						alert(response.data.message || 'Failed to refresh data');
						button.prop('disabled', false).removeClass('refreshing');
					}
				},
				error: function() {
					alert('Error refreshing usage data. Please try again.');
					button.prop('disabled', false).removeClass('refreshing');
				}
			});
		});

		// Add hover effect to chart bars
		widget.find('.chart-bar').hover(
			function() {
				$(this).css('opacity', '0.8');
			},
			function() {
				$(this).css('opacity', '1');
			}
		);

		// Tooltip for chart bars (using title attribute)
		widget.find('.chart-bar').each(function() {
			const title = $(this).attr('title');
			if (title) {
				$(this).attr('data-tooltip', title);
			}
		});
	});

})(jQuery);
