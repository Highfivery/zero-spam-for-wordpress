/**
 * Network Statistics Page JavaScript
 */

(function ($) {
	'use strict';

	$(document).ready(function () {
		// Period change
		$('#period-select').on('change', function () {
			const period = $(this).val();
			window.location.href = window.location.pathname + '?page=wordpress-zero-spam-network-stats&period=' + period;
		});

		// Export CSV
		$('.export-stats').on('click', function (e) {
			e.preventDefault();

			const period = $('#period-select').val();

			$.ajax({
				url: zerospamNetworkStats.ajaxUrl,
				type: 'POST',
				data: {
					action: 'zerospam_export_network_stats',
					nonce: zerospamNetworkStats.nonce,
					period: period,
				},
				success: function (response) {
					if (response.success && response.data.csv) {
						// Convert to CSV string
						let csvContent = '';
						response.data.csv.forEach(function (row) {
							csvContent += row.map(val => `"${val}"`).join(',') + '\n';
						});

						// Download
						const blob = new Blob([csvContent], { type: 'text/csv' });
						const url = window.URL.createObjectURL(blob);
						const a = document.createElement('a');
						a.href = url;
						a.download = response.data.filename;
						document.body.appendChild(a);
						a.click();
						document.body.removeChild(a);
						window.URL.revokeObjectURL(url);
					} else {
						alert('Failed to export data');
					}
				},
				error: function () {
					alert('Failed to export data');
				},
			});
		});
	});
})(jQuery);
