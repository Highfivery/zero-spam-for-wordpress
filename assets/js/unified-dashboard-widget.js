/**
 * Unified Dashboard Widget JavaScript
 *
 * Handles chart initialization, collapsible sections, and AJAX data refresh.
 *
 * @package ZeroSpam
 */

( function( $ ) {
	'use strict';

	/**
	 * Chart instance references for live updates.
	 */
	var trendChart = null;
	var typesChart = null;

	/**
	 * Wait for Chart.js to be available before initializing charts.
	 *
	 * @param {Function} callback Function to call when Chart.js is ready.
	 * @param {number}   maxAttempts Maximum number of polling attempts.
	 */
	function waitForChart( callback, maxAttempts ) {
		maxAttempts = maxAttempts || 50;
		var attempts = 0;
		var interval = setInterval( function() {
			attempts++;
			if ( typeof Chart !== 'undefined' ) {
				clearInterval( interval );
				callback();
			} else if ( attempts >= maxAttempts ) {
				clearInterval( interval );
			}
		}, 100 );
	}

	/**
	 * Initialize the dashboard widget.
	 */
	function initDashboard() {
		initCollapsible();
		initRefreshButton();

		waitForChart( function() {
			initCharts();
		} );
	}

	/**
	 * Initialize collapsible section toggles.
	 */
	function initCollapsible() {
		$( '.zerospam-toggle-trigger' ).on( 'click', function() {
			$( this ).closest( '.zerospam-collapsible' ).toggleClass( 'is-open' );
		} );
	}

	/**
	 * Initialize the refresh button with AJAX handler.
	 */
	function initRefreshButton() {
		if ( typeof zerospamDashboard === 'undefined' ) {
			return;
		}

		$( '.zerospam-refresh-btn' ).on( 'click', function( e ) {
			e.preventDefault();

			var $btn = $( this );
			var $widget = $btn.closest( '.zerospam-dashboard-widget' );

			if ( $btn.prop( 'disabled' ) ) {
				return;
			}

			$btn.prop( 'disabled', true ).addClass( 'is-loading' );

			$.post( zerospamDashboard.ajaxUrl, {
				action: 'zerospam_refresh_dashboard',
				nonce: zerospamDashboard.nonce,
				is_network: zerospamDashboard.isNetwork || '0'
			} )
			.done( function( response ) {
				if ( response.success && response.data && response.data.data ) {
					updateWidgetData( $widget, response.data.data );
					showRefreshFeedback( $btn, 'success' );
				} else {
					showRefreshFeedback( $btn, 'error' );
				}
			} )
			.fail( function() {
				showRefreshFeedback( $btn, 'error' );
			} )
			.always( function() {
				$btn.prop( 'disabled', false ).removeClass( 'is-loading' );
			} );
		} );
	}

	/**
	 * Show brief visual feedback on the refresh button.
	 *
	 * @param {jQuery} $btn    The refresh button element.
	 * @param {string} status  Either 'success' or 'error'.
	 */
	function showRefreshFeedback( $btn, status ) {
		var feedbackClass = 'success' === status ? 'is-refreshed' : 'is-error';
		var icon = $btn.find( '.dashicons' );
		var originalClass = 'dashicons-update';
		var feedbackIcon = 'success' === status ? 'dashicons-yes' : 'dashicons-no';

		icon.removeClass( originalClass ).addClass( feedbackIcon );
		$btn.addClass( feedbackClass );

		setTimeout( function() {
			icon.removeClass( feedbackIcon ).addClass( originalClass );
			$btn.removeClass( feedbackClass );
		}, 2000 );
	}

	/**
	 * Update widget stat values and charts with fresh data.
	 *
	 * @param {jQuery} $widget The widget container element.
	 * @param {Object} data    Fresh dashboard data from AJAX response.
	 */
	function updateWidgetData( $widget, data ) {
		// Update stat card values.
		var statMap = {
			total_blocked: data.total_blocked || 0,
			unique_ips: data.unique_ips || 0,
			active_days: data.active_days || 0,
			total_sites: data.total_sites || 0
		};

		$.each( statMap, function( key, value ) {
			var $el = $widget.find( '[data-stat="' + key + '"]' );
			if ( $el.length ) {
				$el.text( numberFormat( value ) );
			}
		} );

		// Update API remaining if present.
		if ( data.api_usage ) {
			var $apiEl = $widget.find( '[data-stat="api_remaining"]' );
			if ( $apiEl.length ) {
				$apiEl.text( numberFormat( data.api_usage.remaining ) );
			}
		}

		// Update trend chart.
		if ( trendChart && data.trend_data ) {
			updateTrendChart( data.trend_data );
		}

		// Update types chart.
		if ( typesChart && data.spam_types && data.spam_types.length > 0 ) {
			updateTypesChart( data.spam_types );
		}
	}

	/**
	 * Format a number with locale-appropriate thousands separators.
	 *
	 * @param {number} num The number to format.
	 * @return {string} Formatted number string.
	 */
	function numberFormat( num ) {
		return parseInt( num, 10 ).toLocaleString();
	}

	/**
	 * Initialize all charts.
	 */
	function initCharts() {
		if ( typeof zerospamChartData === 'undefined' ) {
			return;
		}

		var trendCanvas = document.getElementById( 'zerospam-trend-chart' );
		if ( trendCanvas && zerospamChartData.trendData && zerospamChartData.trendData.length > 0 ) {
			trendChart = initTrendChart( trendCanvas, zerospamChartData.trendData );
		}

		var typesCanvas = document.getElementById( 'zerospam-types-chart' );
		if ( typesCanvas && zerospamChartData.spamTypes && zerospamChartData.spamTypes.length > 0 ) {
			typesChart = initTypesChart( typesCanvas, zerospamChartData.spamTypes );
		}
	}

	/**
	 * Build labels and values arrays for the 30-day trend chart.
	 *
	 * Ensures all 30 days are represented, filling gaps with zero.
	 *
	 * @param {Array} data Array of { date, count } objects from the server.
	 * @return {Object} Object with labels and values arrays.
	 */
	function buildTrendData( data ) {
		var labels = [];
		var values = [];
		var today = new Date();
		var dataMap = {};

		data.forEach( function( item ) {
			dataMap[ item.date ] = parseInt( item.count, 10 );
		} );

		for ( var i = 29; i >= 0; i-- ) {
			var date = new Date( today );
			date.setDate( date.getDate() - i );
			var dateKey = date.toISOString().split( 'T' )[ 0 ];
			var label = date.toLocaleDateString( 'en-US', { month: 'short', day: 'numeric' } );

			labels.push( label );
			values.push( dataMap[ dateKey ] || 0 );
		}

		return { labels: labels, values: values };
	}

	/**
	 * Initialize the 30-day trend line chart.
	 *
	 * @param {HTMLCanvasElement} canvas The canvas element.
	 * @param {Array}             data   Trend data from the server.
	 * @return {Chart} The Chart.js instance.
	 */
	function initTrendChart( canvas, data ) {
		var trendData = buildTrendData( data );

		return new Chart( canvas, {
			type: 'line',
			data: {
				labels: trendData.labels,
				datasets: [ {
					label: 'Blocked',
					data: trendData.values,
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
				} ]
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
						titleFont: { size: 13, weight: '600' },
						bodyFont: { size: 12 },
						borderColor: 'rgba(255, 255, 255, 0.1)',
						borderWidth: 1,
						displayColors: false,
						callbacks: {
							title: function( context ) {
								return context[ 0 ].label;
							},
							label: function( context ) {
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
							font: { size: 11 },
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
							font: { size: 10 },
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
		} );
	}

	/**
	 * Update the trend chart with new data.
	 *
	 * @param {Array} data Fresh trend data from the server.
	 */
	function updateTrendChart( data ) {
		if ( ! trendChart ) {
			return;
		}

		var trendData = buildTrendData( data );
		trendChart.data.labels = trendData.labels;
		trendChart.data.datasets[ 0 ].data = trendData.values;
		trendChart.update();
	}

	/**
	 * Generate a color palette for the spam types doughnut chart.
	 *
	 * @param {number} count Number of colors to generate.
	 * @return {Array} Array of HSL color strings.
	 */
	function generateTypeColors( count ) {
		var colors = [];
		for ( var i = 0; i < count; i++ ) {
			var lightness = 25 + ( i * 5 );
			colors.push( 'hsl(0, 100%, ' + Math.min( lightness, 60 ) + '%)' );
		}
		return colors;
	}

	/**
	 * Initialize the spam types doughnut chart.
	 *
	 * @param {HTMLCanvasElement} canvas The canvas element.
	 * @param {Array}             data   Spam types data from the server.
	 * @return {Chart} The Chart.js instance.
	 */
	function initTypesChart( canvas, data ) {
		var labels = [];
		var values = [];

		data.forEach( function( item ) {
			labels.push( item.log_type || 'Unknown' );
			values.push( parseInt( item.count, 10 ) );
		} );

		var colors = generateTypeColors( labels.length );

		return new Chart( canvas, {
			type: 'doughnut',
			data: {
				labels: labels,
				datasets: [ {
					data: values,
					backgroundColor: colors,
					borderWidth: 2,
					borderColor: '#fff',
					hoverOffset: 6,
					hoverBorderColor: '#fff'
				} ]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						position: 'right',
						labels: {
							padding: 12,
							font: { size: 11 },
							color: '#1d2327',
							usePointStyle: true,
							pointStyle: 'circle',
							generateLabels: function( chart ) {
								var chartData = chart.data;
								var total = chartData.datasets[ 0 ].data.reduce( function( a, b ) {
									return a + b;
								}, 0 );

								return chartData.labels.map( function( label, i ) {
									var value = chartData.datasets[ 0 ].data[ i ];
									var percentage = ( ( value / total ) * 100 ).toFixed( 1 );
									return {
										text: label + ': ' + value + ' (' + percentage + '%)',
										fillStyle: chartData.datasets[ 0 ].backgroundColor[ i ],
										hidden: false,
										index: i
									};
								} );
							}
						}
					},
					tooltip: {
						backgroundColor: 'rgba(0, 0, 0, 0.8)',
						padding: 10,
						titleFont: { size: 13, weight: '600' },
						bodyFont: { size: 12 },
						callbacks: {
							label: function( context ) {
								var total = context.dataset.data.reduce( function( a, b ) {
									return a + b;
								}, 0 );
								var percentage = ( ( context.parsed / total ) * 100 ).toFixed( 1 );
								return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
							}
						}
					}
				}
			}
		} );
	}

	/**
	 * Update the spam types chart with new data.
	 *
	 * @param {Array} data Fresh spam types data from the server.
	 */
	function updateTypesChart( data ) {
		if ( ! typesChart ) {
			return;
		}

		var labels = [];
		var values = [];

		data.forEach( function( item ) {
			labels.push( item.log_type || 'Unknown' );
			values.push( parseInt( item.count, 10 ) );
		} );

		typesChart.data.labels = labels;
		typesChart.data.datasets[ 0 ].data = values;
		typesChart.data.datasets[ 0 ].backgroundColor = generateTypeColors( labels.length );
		typesChart.update();
	}

	// Initialize on document ready.
	$( document ).ready( function() {
		initDashboard();
	} );

} )( jQuery );
