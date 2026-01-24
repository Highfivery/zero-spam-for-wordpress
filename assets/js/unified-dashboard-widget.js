/**
 * Unified Dashboard Widget JavaScript
 * Handles chart initialization and collapsible sections
 */

(function($) {
	'use strict';

	// Wait for Chart.js to load
	function waitForChart(callback, maxAttempts = 50) {
		let attempts = 0;
		const interval = setInterval(function() {
			attempts++;
			if (typeof Chart !== 'undefined') {
				clearInterval(interval);
				callback();
			} else if (attempts >= maxAttempts) {
				clearInterval(interval);
				console.error('Zero Spam: Chart.js failed to load');
			}
		}, 100);
	}

	// Initialize dashboard widget
	function initDashboard() {
		// Collapsible sections
		$('.zerospam-toggle-trigger').on('click', function() {
			$(this).closest('.zerospam-collapsible').toggleClass('is-open');
		});

		// Initialize charts when Chart.js is ready
		waitForChart(function() {
			initCharts();
		});
	}

	// Initialize all charts
	function initCharts() {
		if (typeof zerospamChartData === 'undefined') {
			return;
		}

		// Trend Chart (30-day line chart)
		const trendCanvas = document.getElementById('zerospam-trend-chart');
		if (trendCanvas && zerospamChartData.trendData) {
			initTrendChart(trendCanvas, zerospamChartData.trendData);
		}

		// Spam Types Chart (doughnut chart)
		const typesCanvas = document.getElementById('zerospam-types-chart');
		if (typesCanvas && zerospamChartData.spamTypes && zerospamChartData.spamTypes.length > 0) {
			initTypesChart(typesCanvas, zerospamChartData.spamTypes);
		}
	}

	// Initialize 30-day trend chart
	function initTrendChart(canvas, data) {
		// Prepare data for last 30 days
		const labels = [];
		const values = [];
		const today = new Date();
		const dataMap = {};

		// Create map of date -> count
		data.forEach(function(item) {
			dataMap[item.date] = parseInt(item.count, 10);
		});

		// Generate all 30 days
		for (let i = 29; i >= 0; i--) {
			const date = new Date(today);
			date.setDate(date.getDate() - i);
			const dateKey = date.toISOString().split('T')[0];
			const label = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
			
			labels.push(label);
			values.push(dataMap[dateKey] || 0);
		}

		// Create chart
		new Chart(canvas, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [{
					label: 'Blocked',
					data: values,
					borderColor: '#3F0008',
					backgroundColor: 'rgba(63, 0, 8, 0.1)',
					fill: true,
					tension: 0.4,
					pointRadius: 2,
					pointHoverRadius: 4,
					pointBackgroundColor: '#3F0008',
					pointBorderColor: '#fff',
					pointBorderWidth: 2,
					pointHoverBackgroundColor: '#3F0008',
					pointHoverBorderColor: '#fff',
					pointHoverBorderWidth: 2
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: {
					intersect: false,
					mode: 'index'
				},
				plugins: {
					legend: {
						display: false
					},
					tooltip: {
						backgroundColor: 'rgba(0, 0, 0, 0.8)',
						padding: 10,
						titleFont: {
							size: 13,
							weight: '600'
						},
						bodyFont: {
							size: 12
						},
						borderColor: 'rgba(255, 255, 255, 0.1)',
						borderWidth: 1,
						displayColors: false,
						callbacks: {
							title: function(context) {
								return context[0].label;
							},
							label: function(context) {
								return 'Blocked: ' + context.parsed.y;
							}
						}
					}
				},
				scales: {
					y: {
						beginAtZero: true,
						ticks: {
							precision: 0,
							font: {
								size: 11
							},
							color: '#646970'
						},
						grid: {
							color: 'rgba(0, 0, 0, 0.05)',
							drawBorder: false
						}
					},
					x: {
						ticks: {
							maxRotation: 45,
							minRotation: 0,
							font: {
								size: 10
							},
							color: '#646970',
							autoSkip: true,
							maxTicksLimit: 10
						},
						grid: {
							display: false
						}
					}
				}
			}
		});
	}

	// Initialize spam types doughnut chart
	function initTypesChart(canvas, data) {
		const labels = [];
		const values = [];

		data.forEach(function(item) {
			labels.push(item.log_type || 'Unknown');
			values.push(parseInt(item.count, 10));
		});

		// Generate color palette (shades of red for Zero Spam brand)
		const colors = labels.map(function(_, i) {
			const hue = 0; // Red
			const saturation = 100;
			const lightness = 25 + (i * 5); // Vary lightness
			return 'hsl(' + hue + ', ' + saturation + '%, ' + Math.min(lightness, 60) + '%)';
		});

		// Create chart
		new Chart(canvas, {
			type: 'doughnut',
			data: {
				labels: labels,
				datasets: [{
					data: values,
					backgroundColor: colors,
					borderWidth: 2,
					borderColor: '#fff',
					hoverOffset: 6,
					hoverBorderColor: '#fff'
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						position: 'right',
						labels: {
							padding: 12,
							font: {
								size: 11
							},
							color: '#1d2327',
							usePointStyle: true,
							pointStyle: 'circle',
							generateLabels: function(chart) {
								const data = chart.data;
								const total = data.datasets[0].data.reduce(function(a, b) {
									return a + b;
								}, 0);
								
								return data.labels.map(function(label, i) {
									const value = data.datasets[0].data[i];
									const percentage = ((value / total) * 100).toFixed(1);
									return {
										text: label + ': ' + value + ' (' + percentage + '%)',
										fillStyle: data.datasets[0].backgroundColor[i],
										hidden: false,
										index: i
									};
								});
							}
						}
					},
					tooltip: {
						backgroundColor: 'rgba(0, 0, 0, 0.8)',
						padding: 10,
						titleFont: {
							size: 13,
							weight: '600'
						},
						bodyFont: {
							size: 12
						},
						callbacks: {
							label: function(context) {
								const total = context.dataset.data.reduce(function(a, b) {
									return a + b;
								}, 0);
								const percentage = ((context.parsed / total) * 100).toFixed(1);
								return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
							}
						}
					}
				}
			}
		});
	}

	// Initialize on document ready
	$(document).ready(function() {
		initDashboard();
	});

})(jQuery);
