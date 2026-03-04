/**
 * WP Plugin Health Monitor — Admin Script
 *
 * Handles AJAX scan triggers, gauge animation, and UI interactions.
 *
 * @package WP_Plugin_Health_Monitor
 */

( function () {
	'use strict';

	/**
	 * Initialize when DOM is ready.
	 */
	document.addEventListener( 'DOMContentLoaded', function () {
		wphmInitGauge();
		wphmBindScanButton();
		wphmBindModuleScanButtons();
		wphmBindDownloadButtons();
	} );

	/**
	 * Animate the SVG ring gauge on load.
	 *
	 * Uses stroke-dasharray animation on the progress circle.
	 */
	function wphmInitGauge() {
		var wrap = document.getElementById( 'wphm-ring-wrap' );
		if ( ! wrap ) {
			return;
		}

		var score    = parseInt( wrap.getAttribute( 'data-score' ), 10 ) || 0;
		var circle   = wrap.querySelector( '.wphm-dash__ring-progress' );
		var numEl    = document.getElementById( 'wphm-ring-num' );
		var CIRCUM   = 2 * Math.PI * 88; // r=88 → ~553.

		// Animate the ring fill.
		if ( circle ) {
			var target = ( score / 100 ) * CIRCUM;
			// Small delay so the browser paints the 0 state first.
			setTimeout( function () {
				circle.style.transition = 'stroke-dasharray 1.2s cubic-bezier(0.4, 0, 0.2, 1)';
				circle.setAttribute( 'stroke-dasharray', target + ' ' + CIRCUM );
			}, 80 );
		}

		// Animate the number counting up.
		if ( numEl ) {
			wphmAnimateCounter( numEl, 0, score, 1000 );
		}
	}

	/**
	 * Bind click handler to the "Run Full Scan" button on dashboard.
	 */
	function wphmBindScanButton() {
		var btn = document.getElementById( 'wphm-run-scan' );
		if ( ! btn ) {
			return;
		}

		btn.addEventListener( 'click', function () {
			wphmRunFullScan( btn );
		} );
	}

	/**
	 * Run the full scan via AJAX and update the dashboard.
	 *
	 * @param {HTMLElement} btn The scan button element.
	 */
	function wphmRunFullScan( btn ) {
		var status = document.getElementById( 'wphm-scan-status' );

		btn.disabled = true;
		btn.innerHTML = '<span class="wphm-spinner"></span> ' + wphmData.i18n.scanning;

		if ( status ) {
			status.textContent = '';
		}

		var formData = new FormData();
		formData.append( 'action', 'wphm_run_scan' );
		formData.append( 'nonce', wphmData.nonce );

		fetch( wphmData.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( result.success && result.data && result.data.score ) {
					wphmUpdateGauge( result.data.score.total );
					wphmUpdateBreakdown( result.data.score );

					if ( status ) {
						status.textContent = wphmData.i18n.completed;
					}
				} else {
					if ( status ) {
						status.textContent = wphmData.i18n.error;
					}
				}
			} )
			.catch( function () {
				if ( status ) {
					status.textContent = wphmData.i18n.error;
				}
			} )
			.finally( function () {
				btn.disabled = false;
				btn.innerHTML = wphmGetScanLabel();
			} );
	}

	/**
	 * Get the default "Run Full Scan" button label (HTML with icon).
	 *
	 * @return {string}
	 */
	function wphmGetScanLabel() {
		return '<span class="dashicons dashicons-search"></span> Run Full Scan';
	}

	/**
	 * Update the SVG ring gauge with a new score.
	 *
	 * @param {number} newScore The new overall score (0–100).
	 */
	function wphmUpdateGauge( newScore ) {
		var wrap = document.getElementById( 'wphm-ring-wrap' );
		if ( ! wrap ) {
			return;
		}

		var CIRCUM   = 2 * Math.PI * 88;
		var target   = ( newScore / 100 ) * CIRCUM;
		var circle   = wrap.querySelector( '.wphm-dash__ring-progress' );
		var numEl    = document.getElementById( 'wphm-ring-num' );
		var labelEl  = document.getElementById( 'wphm-ring-label' );
		var oldScore = parseInt( wrap.getAttribute( 'data-score' ), 10 ) || 0;
		var newClass = wphmGetScoreClass( newScore );

		wrap.setAttribute( 'data-score', newScore );
		wrap.setAttribute( 'data-class', newClass );

		// Update ring stroke.
		if ( circle ) {
			circle.style.transition = 'stroke-dasharray 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
			circle.setAttribute( 'stroke-dasharray', target + ' ' + CIRCUM );
			circle.setAttribute( 'stroke', 'url(#wphm-ring-grad-' + newClass + ')' );
		}

		// Animate number.
		if ( numEl ) {
			wphmAnimateCounter( numEl, oldScore, newScore, 600 );
		}

		// Update label.
		if ( labelEl ) {
			labelEl.textContent = wphmGetScoreLabel( newScore );
			labelEl.className   = 'wphm-dash__ring-label wphm-dash__ring-label--' + newClass;
		}
	}

	/**
	 * Animate a number counter in an element.
	 *
	 * @param {HTMLElement} el       Target element.
	 * @param {number}      from     Starting value.
	 * @param {number}      to       Ending value.
	 * @param {number}      duration Duration in ms.
	 */
	function wphmAnimateCounter( el, from, to, duration ) {
		var start = null;
		function step( ts ) {
			if ( ! start ) {
				start = ts;
			}
			var progress = Math.min( ( ts - start ) / duration, 1 );
			// Ease-out quad.
			var eased = 1 - Math.pow( 1 - progress, 2 );
			el.textContent = Math.round( from + ( to - from ) * eased );
			if ( progress < 1 ) {
				requestAnimationFrame( step );
			}
		}
		requestAnimationFrame( step );
	}

	/**
	 * Update the dimension bar values after a scan.
	 *
	 * @param {Object} scoreData The score data from AJAX response.
	 */
	function wphmUpdateBreakdown( scoreData ) {
		var dims = document.querySelectorAll( '.wphm-dash__dim' );
		if ( ! dims.length ) {
			return;
		}

		var mapping = {
			plugins:    { raw: 'plugin_count', max: 30, suffix: ' active',    isBytes: false },
			assets:     { raw: 'asset_count',  max: 30, suffix: ' enqueued',  isBytes: false },
			db_queries: { raw: 'db_query_count', max: 20, suffix: ' queries', isBytes: false },
			autoload:   { raw: 'autoload_size',  max: 20, suffix: ' loaded',  isBytes: true },
		};

		dims.forEach( function ( dim ) {
			var key = dim.getAttribute( 'data-key' );
			var m   = mapping[ key ];
			if ( ! m ) {
				return;
			}

			var val = scoreData[ key ] || 0;
			var pct = Math.round( ( val / m.max ) * 100 );

			// Update score text.
			var valEl = dim.querySelector( '.wphm-dash__dim-val strong' );
			if ( valEl ) {
				valEl.textContent = val;
			}

			// Update bar width.
			var fill = dim.querySelector( '.wphm-dash__dim-fill' );
			if ( fill ) {
				fill.style.width = pct + '%';
			}

			// Update detail text.
			var detail = dim.querySelector( '.wphm-dash__dim-detail' );
			if ( detail && scoreData.raw && typeof scoreData.raw[ m.raw ] !== 'undefined' ) {
				if ( m.isBytes ) {
					detail.textContent = wphmFormatBytes( scoreData.raw[ m.raw ] ) + m.suffix;
				} else {
					detail.textContent = scoreData.raw[ m.raw ] + m.suffix;
				}
			}
		} );
	}

	/**
	 * Get a human-readable label for a score.
	 *
	 * @param {number} score Score 0–100.
	 * @return {string}
	 */
	function wphmGetScoreLabel( score ) {
		if ( score >= 80 ) {
			return 'Excellent';
		}
		if ( score >= 60 ) {
			return 'Good';
		}
		if ( score >= 40 ) {
			return 'Fair';
		}
		if ( score >= 20 ) {
			return 'Poor';
		}
		return 'Critical';
	}

	/**
	 * Get CSS class suffix for a score.
	 *
	 * @param {number} score Score 0–100.
	 * @return {string}
	 */
	function wphmGetScoreClass( score ) {
		if ( score >= 80 ) {
			return 'excellent';
		}
		if ( score >= 60 ) {
			return 'good';
		}
		if ( score >= 40 ) {
			return 'fair';
		}
		if ( score >= 20 ) {
			return 'poor';
		}
		return 'critical';
	}

	/**
	 * Format bytes to a human-readable string.
	 *
	 * @param {number} bytes Number of bytes.
	 * @return {string}
	 */
	function wphmFormatBytes( bytes ) {
		if ( bytes === 0 ) {
			return '0 B';
		}
		var units = [ 'B', 'KB', 'MB', 'GB' ];
		var i     = Math.floor( Math.log( bytes ) / Math.log( 1024 ) );
		if ( i >= units.length ) {
			i = units.length - 1;
		}
		return ( bytes / Math.pow( 1024, i ) ).toFixed( 1 ) + ' ' + units[ i ];
	}

	/**
	 * Bind scan buttons on individual module pages.
	 *
	 * Looks for buttons with data-wphm-action attribute and sends the
	 * corresponding AJAX request.
	 */
	function wphmBindModuleScanButtons() {
		var buttons = document.querySelectorAll( '[data-wphm-action]' );
		buttons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var action    = btn.getAttribute( 'data-wphm-action' );
				var targetId  = btn.getAttribute( 'data-wphm-target' );
				var targetEl  = targetId ? document.getElementById( targetId ) : null;

				btn.disabled = true;
				btn.textContent = wphmData.i18n.scanning;

				var formData = new FormData();
				formData.append( 'action', action );
				formData.append( 'nonce', wphmData.nonce );

				fetch( wphmData.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: formData,
				} )
					.then( function ( response ) {
						return response.json();
					} )
					.then( function ( result ) {
						if ( result.success && targetEl ) {
							wphmRenderModuleResults( targetEl, result.data, action );
						} else if ( targetEl ) {
							targetEl.innerHTML = '<p>' + wphmEscHtml( wphmData.i18n.error ) + '</p>';
						}
					} )
					.catch( function () {
						if ( targetEl ) {
							targetEl.innerHTML = '<p>' + wphmEscHtml( wphmData.i18n.error ) + '</p>';
						}
					} )
					.finally( function () {
						btn.disabled = false;
						btn.textContent = btn.getAttribute( 'data-wphm-label' ) || 'Scan';
					} );
			} );
		} );
	}

	/**
	 * Render module-specific results into a target container.
	 *
	 * @param {HTMLElement} target The target container element.
	 * @param {Object}      data   The AJAX response data.
	 * @param {string}      action The AJAX action name.
	 */
	function wphmRenderModuleResults( target, data, action ) {
		switch ( action ) {
			case 'wphm_get_conflicts':
				wphmRenderConflicts( target, data );
				break;
			case 'wphm_get_php_compat':
				wphmRenderPhpCompat( target, data );
				break;
			case 'wphm_get_debug_log':
				wphmRenderDebugLog( target, data );
				break;
			case 'wphm_get_performance':
				wphmRenderPerformance( target, data );
				break;
			case 'wphm_get_report':
				wphmRenderReport( target, data );
				break;
			default:
				target.innerHTML = '<pre>' + wphmEscHtml( JSON.stringify( data, null, 2 ) ) + '</pre>';
		}
	}

	/**
	 * Render conflict scan results as a table.
	 *
	 * @param {HTMLElement} target Container element.
	 * @param {Object}      data   Conflict data.
	 */
	function wphmRenderConflicts( target, data ) {
		var items = [];
		if ( data.duplicate_assets ) {
			items = items.concat( data.duplicate_assets );
		}
		if ( data.hook_conflicts ) {
			items = items.concat( data.hook_conflicts );
		}

		if ( items.length === 0 ) {
			target.innerHTML = '<div class="wphm-notice-inline wphm-notice-inline--success">' +
				'<p>No plugin conflicts detected.</p></div>';
			return;
		}

		var html = '<div class="wphm-table-wrap"><table class="wphm-table">';
		html += '<thead><tr><th>Handle / Hook</th><th>Plugins</th><th>Type</th><th>Severity</th></tr></thead><tbody>';

		items.forEach( function ( item ) {
			var severityClass = item.severity === 'error' ? 'error' : 'warning';
			html += '<tr>';
			html += '<td>' + wphmEscHtml( item.handle || item.hook || '' ) + '</td>';
			html += '<td>' + wphmEscHtml( ( item.plugins || [] ).join( ', ' ) ) + '</td>';
			html += '<td>' + wphmEscHtml( item.type || '' ) + '</td>';
			html += '<td><span class="wphm-badge wphm-badge--' + severityClass + '">' + wphmEscHtml( item.severity || '' ) + '</span></td>';
			html += '</tr>';
		} );

		html += '</tbody></table></div>';
		target.innerHTML = html;
	}

	/**
	 * Render PHP compatibility results as a table.
	 *
	 * @param {HTMLElement} target Container element.
	 * @param {Object}      data   PHP compat data.
	 */
	function wphmRenderPhpCompat( target, data ) {
		var items = data.plugins || [];

		if ( items.length === 0 ) {
			target.innerHTML = '<div class="wphm-notice-inline wphm-notice-inline--info">' +
				'<p>No plugin compatibility data available.</p></div>';
			return;
		}

		var html = '<div class="wphm-table-wrap"><table class="wphm-table">';
		html += '<thead><tr><th>Plugin</th><th>Required PHP</th><th>Current PHP</th><th>Status</th></tr></thead><tbody>';

		items.forEach( function ( item ) {
			var statusClass = item.status === 'compatible' ? 'success' : 'error';
			html += '<tr>';
			html += '<td>' + wphmEscHtml( item.name || '' ) + '</td>';
			html += '<td>' + wphmEscHtml( item.required_php || 'N/A' ) + '</td>';
			html += '<td>' + wphmEscHtml( item.current_php || '' ) + '</td>';
			html += '<td><span class="wphm-badge wphm-badge--' + statusClass + '">' + wphmEscHtml( item.status || '' ) + '</span></td>';
			html += '</tr>';
		} );

		html += '</tbody></table></div>';

		if ( data.deprecated_usage && data.deprecated_usage.length > 0 ) {
			html += '<h3>Deprecated Function Usage</h3>';
			html += '<div class="wphm-table-wrap"><table class="wphm-table">';
			html += '<thead><tr><th>Plugin</th><th>Function</th><th>File</th></tr></thead><tbody>';

			data.deprecated_usage.forEach( function ( item ) {
				html += '<tr>';
				html += '<td>' + wphmEscHtml( item.plugin || '' ) + '</td>';
				html += '<td><code>' + wphmEscHtml( item.function || '' ) + '</code></td>';
				html += '<td>' + wphmEscHtml( item.file || '' ) + '</td>';
				html += '</tr>';
			} );

			html += '</tbody></table></div>';
		}

		target.innerHTML = html;
	}

	/**
	 * Render debug log results.
	 *
	 * @param {HTMLElement} target Container element.
	 * @param {Object}      data   Debug log data.
	 */
	function wphmRenderDebugLog( target, data ) {
		if ( ! data.exists ) {
			target.innerHTML = '<div class="wphm-debug-nolog">' +
				'<div class="wphm-debug-nolog__icon"><span class="dashicons dashicons-info-outline"></span></div>' +
				'<h3>No debug.log File Found</h3>' +
				'<p>WordPress debug logging is not active or the log file does not exist yet. ' +
				'Enable <code>WP_DEBUG</code> and <code>WP_DEBUG_LOG</code> in your <code>wp-config.php</code> to start capturing errors.</p>' +
				'</div>';
			return;
		}

		var html = '';

		// Error summary cards with visual indicators.
		if ( data.summary ) {
			var fatalCount   = wphmSafeInt( data.summary.fatal );
			var warningCount = wphmSafeInt( data.summary.warning );
			var noticeCount  = wphmSafeInt( data.summary.notice );
			var total        = fatalCount + warningCount + noticeCount;

			html += '<div class="wphm-debug-summary">';
			html += '<h3><span class="dashicons dashicons-chart-pie"></span> Error Summary</h3>';
			html += '<div class="wphm-debug-summary__grid">';

			// Fatal card.
			html += '<div class="wphm-debug-card wphm-debug-card--fatal">';
			html += '<div class="wphm-debug-card__icon"><span class="dashicons dashicons-dismiss"></span></div>';
			html += '<div class="wphm-debug-card__info">';
			html += '<span class="wphm-debug-card__count">' + fatalCount + '</span>';
			html += '<span class="wphm-debug-card__label">Fatal Errors</span>';
			html += '</div>';
			if ( total > 0 ) {
				html += '<div class="wphm-debug-card__bar"><div class="wphm-debug-card__bar-fill wphm-debug-card__bar-fill--fatal" style="width:' + Math.round( ( fatalCount / total ) * 100 ) + '%"></div></div>';
			}
			html += '</div>';

			// Warning card.
			html += '<div class="wphm-debug-card wphm-debug-card--warning">';
			html += '<div class="wphm-debug-card__icon"><span class="dashicons dashicons-warning"></span></div>';
			html += '<div class="wphm-debug-card__info">';
			html += '<span class="wphm-debug-card__count">' + warningCount + '</span>';
			html += '<span class="wphm-debug-card__label">Warnings</span>';
			html += '</div>';
			if ( total > 0 ) {
				html += '<div class="wphm-debug-card__bar"><div class="wphm-debug-card__bar-fill wphm-debug-card__bar-fill--warning" style="width:' + Math.round( ( warningCount / total ) * 100 ) + '%"></div></div>';
			}
			html += '</div>';

			// Notice card.
			html += '<div class="wphm-debug-card wphm-debug-card--notice">';
			html += '<div class="wphm-debug-card__icon"><span class="dashicons dashicons-info-outline"></span></div>';
			html += '<div class="wphm-debug-card__info">';
			html += '<span class="wphm-debug-card__count">' + noticeCount + '</span>';
			html += '<span class="wphm-debug-card__label">Notices</span>';
			html += '</div>';
			if ( total > 0 ) {
				html += '<div class="wphm-debug-card__bar"><div class="wphm-debug-card__bar-fill wphm-debug-card__bar-fill--notice" style="width:' + Math.round( ( noticeCount / total ) * 100 ) + '%"></div></div>';
			}
			html += '</div>';

			html += '</div>'; // grid.

			// File size info.
			if ( data.file_size ) {
				html += '<p class="wphm-debug-fileinfo">Log file size: <strong>' + wphmFormatBytes( data.file_size ) + '</strong></p>';
			}

			html += '</div>'; // summary.
		}

		// Top offending plugins with horizontal bar chart.
		if ( data.top_plugins && data.top_plugins.length > 0 ) {
			var maxCount = Math.max( 1, wphmSafeInt( data.top_plugins[0].count, 1 ) );

			html += '<div class="wphm-debug-offenders">';
			html += '<h3><span class="dashicons dashicons-admin-plugins"></span> Top Offending Plugins</h3>';
			html += '<div class="wphm-hbar-chart">';

			data.top_plugins.forEach( function ( item, index ) {
				var itemCount = wphmSafeInt( item.count );
				var pct = Math.round( ( itemCount / maxCount ) * 100 );
				var colors = [ '#d63638', '#e65100', '#dba617', '#2271b1', '#50575e' ];
				var color = colors[ index ] || '#50575e';

				html += '<div class="wphm-hbar">';
				html += '<div class="wphm-hbar__label">' + wphmEscHtml( item.plugin || '' ) + '</div>';
				html += '<div class="wphm-hbar__track">';
				html += '<div class="wphm-hbar__fill" style="width:' + pct + '%;background:' + color + '"></div>';
				html += '</div>';
				html += '<div class="wphm-hbar__value">' + itemCount + '</div>';
				html += '</div>';
			} );

			html += '</div></div>';
		}

		// Last entries in a styled log viewer.
		if ( data.entries && data.entries.length > 0 ) {
			html += '<div class="wphm-debug-entries">';
			html += '<h3><span class="dashicons dashicons-editor-code"></span> Last ' + data.entries.length + ' Log Entries</h3>';
			html += '<div class="wphm-log-viewer">';

			data.entries.forEach( function ( entry ) {
				var lineClass = 'wphm-log-line';
				if ( /PHP Fatal error/i.test( entry ) ) {
					lineClass += ' wphm-log-line--fatal';
				} else if ( /PHP Warning/i.test( entry ) ) {
					lineClass += ' wphm-log-line--warning';
				} else if ( /PHP Notice/i.test( entry ) ) {
					lineClass += ' wphm-log-line--notice';
				}
				html += '<div class="' + lineClass + '">' + wphmEscHtml( entry ) + '</div>';
			} );

			html += '</div></div>';
		}

		target.innerHTML = html;
	}

	/**
	 * Render performance scan results.
	 *
	 * @param {HTMLElement} target Container element.
	 * @param {Object}      data   Performance/score data.
	 */
	function wphmRenderPerformance( target, data ) {
		var html = '<div class="wphm-breakdown-grid">';

		html += '<div class="wphm-breakdown-card postbox">';
		html += '<h3 class="wphm-breakdown-card__title">Active Plugins</h3>';
		html += '<div class="wphm-breakdown-card__score"><strong>' + ( data.raw ? data.raw.plugin_count : 0 ) + '</strong></div></div>';

		html += '<div class="wphm-breakdown-card postbox">';
		html += '<h3 class="wphm-breakdown-card__title">Enqueued Assets</h3>';
		html += '<div class="wphm-breakdown-card__score"><strong>' + ( data.raw ? data.raw.asset_count : 0 ) + '</strong></div></div>';

		html += '<div class="wphm-breakdown-card postbox">';
		html += '<h3 class="wphm-breakdown-card__title">DB Queries</h3>';
		html += '<div class="wphm-breakdown-card__score"><strong>' + ( data.raw ? data.raw.db_query_count : 0 ) + '</strong></div></div>';

		html += '<div class="wphm-breakdown-card postbox">';
		html += '<h3 class="wphm-breakdown-card__title">Autoload Size</h3>';
		html += '<div class="wphm-breakdown-card__score"><strong>' + wphmFormatBytes( data.raw ? data.raw.autoload_size : 0 ) + '</strong></div></div>';

		html += '</div>';
		target.innerHTML = html;
	}

	/**
	 * Render full report results — professional, human-readable layout.
	 *
	 * @param {HTMLElement} target Container element.
	 * @param {Object}      data   Full report data.
	 */
	function wphmRenderReport( target, data ) {
		var html = '';

		// Store data globally for download handlers.
		wphmLastReportData = data;

		// Show the download bar.
		var dlBar = document.getElementById( 'wphm-download-bar' );
		if ( dlBar ) {
			dlBar.style.display = '';
		}

		// ── Report Header ──
		html += '<div class="wphm-rpt">';
		html += '<div class="wphm-rpt__header">';
		html += '<div class="wphm-rpt__header-top">';
		html += '<div class="wphm-rpt__brand">';
		html += '<span class="dashicons dashicons-heart wphm-rpt__logo"></span>';
		html += '<div>';
		html += '<h2 class="wphm-rpt__title">WordPress Site Health Report</h2>';
		html += '<p class="wphm-rpt__subtitle">' + wphmEscHtml( data.site_url || '' ) + '</p>';
		html += '</div>';
		html += '</div>';
		html += '<div class="wphm-rpt__meta">';
		html += '<div class="wphm-rpt__meta-item"><strong>Generated:</strong> ' + wphmEscHtml( data.generated_at || '' ) + '</div>';
		html += '<div class="wphm-rpt__meta-item"><strong>WordPress:</strong> ' + wphmEscHtml( data.wordpress || '' ) + '</div>';
		html += '<div class="wphm-rpt__meta-item"><strong>PHP:</strong> ' + wphmEscHtml( data.php_version || '' ) + '</div>';
		html += '</div>';
		html += '</div>'; // header-top.

		// ── Overall Score Section ──
		if ( data.health_score ) {
			var score      = Math.max( 0, Math.min( 100, wphmSafeInt( data.health_score.total ) ) );
			var scoreClass = wphmGetScoreClass( score );
			var scoreLabel = wphmGetScoreLabel( score );

			html += '<div class="wphm-rpt__score-hero wphm-rpt__score-hero--' + scoreClass + '">';
			html += '<div class="wphm-rpt__score-ring">';
			html += '<svg viewBox="0 0 120 120" class="wphm-rpt__score-svg">';
			html += '<circle class="wphm-rpt__score-bg" cx="60" cy="60" r="52" />';
			html += '<circle class="wphm-rpt__score-fg wphm-rpt__score-fg--' + scoreClass + '" cx="60" cy="60" r="52" ' +
				'stroke-dasharray="' + Math.round( ( score / 100 ) * 327 ) + ' 327" />';
			html += '</svg>';
			html += '<div class="wphm-rpt__score-value">';
			html += '<span class="wphm-rpt__score-num">' + score + '</span>';
			html += '<span class="wphm-rpt__score-of">/100</span>';
			html += '</div>';
			html += '</div>';
			html += '<div class="wphm-rpt__score-info">';
			html += '<span class="wphm-rpt__score-label wphm-rpt__score-label--' + scoreClass + '">' + scoreLabel + '</span>';
			html += '<p class="wphm-rpt__score-desc">' + wphmGetScoreDescription( score ) + '</p>';
			html += '</div>';
			html += '</div>';

			// ── Score Dimension Bars ──
			html += '<div class="wphm-rpt__section">';
			html += '<h3 class="wphm-rpt__section-title"><span class="dashicons dashicons-chart-bar"></span> Score Breakdown</h3>';
			html += '<div class="wphm-rpt__dim-grid">';

			var dims = [
				{ label: 'Plugins', val: data.health_score.plugins || 0, max: 30, icon: 'admin-plugins', raw: data.health_score.raw ? data.health_score.raw.plugin_count : 0, unit: ' active' },
				{ label: 'Assets', val: data.health_score.assets || 0, max: 30, icon: 'media-code', raw: data.health_score.raw ? data.health_score.raw.asset_count : 0, unit: ' enqueued' },
				{ label: 'DB Queries', val: data.health_score.db_queries || 0, max: 20, icon: 'database', raw: data.health_score.raw ? data.health_score.raw.db_query_count : 0, unit: '' },
				{ label: 'Autoload Size', val: data.health_score.autoload || 0, max: 20, icon: 'editor-table', raw: data.health_score.raw ? data.health_score.raw.autoload_size : 0, unit: '' },
			];

			dims.forEach( function ( d ) {
				d.val = wphmSafeInt( d.val );
				d.max = Math.max( 1, wphmSafeInt( d.max, 1 ) );
				var pct      = Math.round( ( d.val / d.max ) * 100 );
				var barClass = pct >= 80 ? 'excellent' : pct >= 60 ? 'good' : pct >= 40 ? 'fair' : pct >= 20 ? 'poor' : 'critical';
				d.raw = wphmSafeInt( d.raw );
				var rawText  = d.label === 'Autoload Size' ? wphmFormatBytes( d.raw ) : d.raw + d.unit;

				html += '<div class="wphm-rpt__dim">';
				html += '<div class="wphm-rpt__dim-head">';
				html += '<span class="wphm-rpt__dim-icon"><span class="dashicons dashicons-' + d.icon + '"></span></span>';
				html += '<span class="wphm-rpt__dim-label">' + d.label + '</span>';
				html += '<span class="wphm-rpt__dim-score">' + d.val + '<span class="wphm-rpt__dim-max">/' + d.max + '</span></span>';
				html += '</div>';
				html += '<div class="wphm-rpt__bar-track">';
				html += '<div class="wphm-rpt__bar-fill wphm-rpt__bar-fill--' + barClass + '" style="width:' + pct + '%"></div>';
				html += '</div>';
				html += '<div class="wphm-rpt__dim-detail">' + rawText + '</div>';
				html += '</div>';
			} );

			html += '</div></div>';
		}

		html += '</div>'; // header.

		// ── Conflicts Section ──
		html += '<div class="wphm-rpt__section">';
		html += '<h3 class="wphm-rpt__section-title"><span class="dashicons dashicons-warning"></span> Plugin Conflicts</h3>';

		if ( data.conflicts ) {
			var conflictItems = [];
			if ( data.conflicts.duplicate_assets ) {
				conflictItems = conflictItems.concat( data.conflicts.duplicate_assets );
			}
			if ( data.conflicts.hook_conflicts ) {
				conflictItems = conflictItems.concat( data.conflicts.hook_conflicts );
			}

			if ( conflictItems.length === 0 ) {
				html += '<div class="wphm-rpt__status wphm-rpt__status--pass">';
				html += '<span class="dashicons dashicons-yes-alt"></span>';
				html += '<div><strong>No Conflicts Detected</strong><p>All active plugins are operating without detected conflicts.</p></div>';
				html += '</div>';
			} else {
				html += '<div class="wphm-rpt__status wphm-rpt__status--warn">';
				html += '<span class="dashicons dashicons-warning"></span>';
				html += '<div><strong>' + conflictItems.length + ' Conflict' + ( conflictItems.length > 1 ? 's' : '' ) + ' Found</strong></div>';
				html += '</div>';
				html += '<div class="wphm-table-wrap"><table class="wphm-table wphm-rpt__table">';
				html += '<thead><tr><th>Handle / Hook</th><th>Involved Plugins</th><th>Type</th><th>Severity</th></tr></thead><tbody>';
				conflictItems.forEach( function ( item ) {
					var sevClass = item.severity === 'error' ? 'error' : 'warning';
					html += '<tr>';
					html += '<td><code>' + wphmEscHtml( item.handle || item.hook || '' ) + '</code></td>';
					html += '<td>' + wphmEscHtml( ( item.plugins || [] ).join( ', ' ) ) + '</td>';
					html += '<td><span class="wphm-badge wphm-badge--info">' + wphmEscHtml( item.type || '' ) + '</span></td>';
					html += '<td><span class="wphm-badge wphm-badge--' + sevClass + '">' + wphmEscHtml( item.severity || '' ) + '</span></td>';
					html += '</tr>';
				} );
				html += '</tbody></table></div>';
			}
		}
		html += '</div>';

		// ── Duplicate Assets Section ──
		html += '<div class="wphm-rpt__section">';
		html += '<h3 class="wphm-rpt__section-title"><span class="dashicons dashicons-media-code"></span> Duplicate Assets</h3>';

		if ( data.duplicate_assets ) {
			var dupAll = [];
			if ( data.duplicate_assets.hash_duplicates ) {
				dupAll = dupAll.concat( data.duplicate_assets.hash_duplicates );
			}
			if ( data.duplicate_assets.url_duplicates ) {
				dupAll = dupAll.concat( data.duplicate_assets.url_duplicates );
			}
			if ( data.duplicate_assets.library_duplicates ) {
				dupAll = dupAll.concat( data.duplicate_assets.library_duplicates );
			}

			if ( dupAll.length === 0 ) {
				html += '<div class="wphm-rpt__status wphm-rpt__status--pass">';
				html += '<span class="dashicons dashicons-yes-alt"></span>';
				html += '<div><strong>No Duplicate Assets</strong><p>Each script and style is loaded only once.</p></div>';
				html += '</div>';
			} else {
				html += '<div class="wphm-rpt__status wphm-rpt__status--warn">';
				html += '<span class="dashicons dashicons-warning"></span>';
				html += '<div><strong>' + dupAll.length + ' Duplicate' + ( dupAll.length > 1 ? 's' : '' ) + ' Found</strong></div>';
				html += '</div>';
				html += '<div class="wphm-table-wrap"><table class="wphm-table wphm-rpt__table">';
				html += '<thead><tr><th>Handles</th><th>Plugins</th><th>Type</th></tr></thead><tbody>';
				dupAll.forEach( function ( item ) {
					html += '<tr>';
					html += '<td><code>' + wphmEscHtml( item.handle || '' ) + '</code></td>';
					html += '<td>' + wphmEscHtml( ( item.plugins || [] ).join( ', ' ) ) + '</td>';
					html += '<td><span class="wphm-badge wphm-badge--info">' + wphmEscHtml( item.type || '' ) + '</span></td>';
					html += '</tr>';
				} );
				html += '</tbody></table></div>';
			}

			// Asset inventory summary.
			if ( data.duplicate_assets.asset_inventory && data.duplicate_assets.asset_inventory.length > 0 ) {
				var inv      = data.duplicate_assets.asset_inventory;
				var scripts  = inv.filter( function ( a ) { return a.type === 'script'; } );
				var styles   = inv.filter( function ( a ) { return a.type === 'style'; } );
				var totalSize = 0;
				inv.forEach( function ( a ) { totalSize += a.file_size || 0; } );

				html += '<div class="wphm-rpt__inventory-summary">';
				html += '<div class="wphm-rpt__inv-card"><span class="wphm-rpt__inv-num">' + scripts.length + '</span><span>Scripts</span></div>';
				html += '<div class="wphm-rpt__inv-card"><span class="wphm-rpt__inv-num">' + styles.length + '</span><span>Styles</span></div>';
				html += '<div class="wphm-rpt__inv-card"><span class="wphm-rpt__inv-num">' + wphmFormatBytes( totalSize ) + '</span><span>Total Size</span></div>';
				html += '</div>';
			}
		}
		html += '</div>';

		// ── PHP Compatibility Section ──
		html += '<div class="wphm-rpt__section">';
		html += '<h3 class="wphm-rpt__section-title"><span class="dashicons dashicons-editor-code"></span> PHP Compatibility</h3>';

		if ( data.php_compat && data.php_compat.plugins ) {
			var phpPlugins   = data.php_compat.plugins;
			var compatible   = phpPlugins.filter( function ( p ) { return p.status === 'compatible'; } );
			var incompatible = phpPlugins.filter( function ( p ) { return p.status === 'incompatible'; } );
			var unknown      = phpPlugins.filter( function ( p ) { return p.status === 'unknown'; } );

			// Mini chart.
			var phpTotal = phpPlugins.length || 1;
			html += '<div class="wphm-rpt__php-overview">';
			html += '<div class="wphm-rpt__php-chart">';
			html += '<div class="wphm-rpt__stacked-bar">';
			if ( compatible.length > 0 ) {
				html += '<div class="wphm-rpt__stacked-seg wphm-rpt__stacked-seg--pass" style="width:' + Math.round( ( compatible.length / phpTotal ) * 100 ) + '%">' + compatible.length + '</div>';
			}
			if ( incompatible.length > 0 ) {
				html += '<div class="wphm-rpt__stacked-seg wphm-rpt__stacked-seg--fail" style="width:' + Math.round( ( incompatible.length / phpTotal ) * 100 ) + '%">' + incompatible.length + '</div>';
			}
			if ( unknown.length > 0 ) {
				html += '<div class="wphm-rpt__stacked-seg wphm-rpt__stacked-seg--unknown" style="width:' + Math.round( ( unknown.length / phpTotal ) * 100 ) + '%">' + unknown.length + '</div>';
			}
			html += '</div>';
			html += '<div class="wphm-rpt__php-legend">';
			html += '<span class="wphm-rpt__legend-item"><span class="wphm-rpt__legend-dot wphm-rpt__legend-dot--pass"></span> Compatible (' + compatible.length + ')</span>';
			html += '<span class="wphm-rpt__legend-item"><span class="wphm-rpt__legend-dot wphm-rpt__legend-dot--fail"></span> Incompatible (' + incompatible.length + ')</span>';
			html += '<span class="wphm-rpt__legend-item"><span class="wphm-rpt__legend-dot wphm-rpt__legend-dot--unknown"></span> Unknown (' + unknown.length + ')</span>';
			html += '</div>';
			html += '</div></div>';

			if ( incompatible.length > 0 ) {
				html += '<h4>Incompatible Plugins</h4>';
				html += '<div class="wphm-table-wrap"><table class="wphm-table wphm-rpt__table">';
				html += '<thead><tr><th>Plugin</th><th>Requires PHP</th><th>Your PHP</th><th>Status</th></tr></thead><tbody>';
				incompatible.forEach( function ( p ) {
					html += '<tr>';
					html += '<td>' + wphmEscHtml( p.name || '' ) + '</td>';
					html += '<td>' + wphmEscHtml( p.required_php || 'N/A' ) + '</td>';
					html += '<td>' + wphmEscHtml( p.current_php || '' ) + '</td>';
					html += '<td><span class="wphm-badge wphm-badge--error">Incompatible</span></td>';
					html += '</tr>';
				} );
				html += '</tbody></table></div>';
			}

			// Deprecated functions.
			if ( data.php_compat.deprecated_usage && data.php_compat.deprecated_usage.length > 0 ) {
				html += '<h4>Deprecated Function Usage</h4>';
				html += '<div class="wphm-table-wrap"><table class="wphm-table wphm-rpt__table">';
				html += '<thead><tr><th>Plugin</th><th>Function</th><th>Deprecated In</th><th>File</th></tr></thead><tbody>';
				data.php_compat.deprecated_usage.forEach( function ( d ) {
					html += '<tr>';
					html += '<td>' + wphmEscHtml( d.plugin || '' ) + '</td>';
					html += '<td><code>' + wphmEscHtml( d.function || '' ) + '</code></td>';
					html += '<td>WP ' + wphmEscHtml( d.deprecated_in || '?' ) + '</td>';
					html += '<td class="wphm-rpt__filepath">' + wphmEscHtml( d.file || '' ) + '</td>';
					html += '</tr>';
				} );
				html += '</tbody></table></div>';
			}
		}
		html += '</div>';

		// ── Debug Log Section ──
		html += '<div class="wphm-rpt__section">';
		html += '<h3 class="wphm-rpt__section-title"><span class="dashicons dashicons-clipboard"></span> Debug Log Analysis</h3>';

		if ( data.debug_log ) {
			if ( ! data.debug_log.exists ) {
				html += '<div class="wphm-rpt__status wphm-rpt__status--info">';
				html += '<span class="dashicons dashicons-info-outline"></span>';
				html += '<div><strong>No debug.log Found</strong><p>Debug logging is not active.</p></div>';
				html += '</div>';
			} else {
				var dlSummary = data.debug_log.summary || {};
				dlSummary.fatal = wphmSafeInt( dlSummary.fatal );
				dlSummary.warning = wphmSafeInt( dlSummary.warning );
				dlSummary.notice = wphmSafeInt( dlSummary.notice );
				var dlTotal   = dlSummary.fatal + dlSummary.warning + dlSummary.notice;

				if ( dlTotal === 0 ) {
					html += '<div class="wphm-rpt__status wphm-rpt__status--pass">';
					html += '<span class="dashicons dashicons-yes-alt"></span>';
					html += '<div><strong>Clean Log</strong><p>No PHP errors detected in the debug log.</p></div>';
					html += '</div>';
				} else {
					// Error type distribution bar.
					html += '<div class="wphm-rpt__dl-stats">';
					html += '<div class="wphm-rpt__dl-stat wphm-rpt__dl-stat--fatal"><span class="wphm-rpt__dl-stat-num">' + ( dlSummary.fatal || 0 ) + '</span><span>Fatal</span></div>';
					html += '<div class="wphm-rpt__dl-stat wphm-rpt__dl-stat--warning"><span class="wphm-rpt__dl-stat-num">' + ( dlSummary.warning || 0 ) + '</span><span>Warning</span></div>';
					html += '<div class="wphm-rpt__dl-stat wphm-rpt__dl-stat--notice"><span class="wphm-rpt__dl-stat-num">' + ( dlSummary.notice || 0 ) + '</span><span>Notice</span></div>';
					html += '</div>';

					// Distribution bar.
					html += '<div class="wphm-rpt__stacked-bar">';
					if ( dlSummary.fatal > 0 ) {
						html += '<div class="wphm-rpt__stacked-seg wphm-rpt__stacked-seg--fail" style="width:' + Math.round( ( dlSummary.fatal / dlTotal ) * 100 ) + '%" title="Fatal"></div>';
					}
					if ( dlSummary.warning > 0 ) {
						html += '<div class="wphm-rpt__stacked-seg wphm-rpt__stacked-seg--warn" style="width:' + Math.round( ( dlSummary.warning / dlTotal ) * 100 ) + '%" title="Warning"></div>';
					}
					if ( dlSummary.notice > 0 ) {
						html += '<div class="wphm-rpt__stacked-seg wphm-rpt__stacked-seg--unknown" style="width:' + Math.round( ( dlSummary.notice / dlTotal ) * 100 ) + '%" title="Notice"></div>';
					}
					html += '</div>';

					// Top offenders.
					if ( data.debug_log.top_plugins && data.debug_log.top_plugins.length > 0 ) {
						html += '<h4>Top Offending Plugins</h4>';
						html += '<div class="wphm-table-wrap"><table class="wphm-table wphm-rpt__table">';
						html += '<thead><tr><th>Plugin</th><th>Errors</th><th>Distribution</th></tr></thead><tbody>';
						var topMax = Math.max( 1, wphmSafeInt( data.debug_log.top_plugins[0].count, 1 ) );
						data.debug_log.top_plugins.forEach( function ( tp ) {
							var tpCount = wphmSafeInt( tp.count );
							var tpPct = Math.round( ( tpCount / topMax ) * 100 );
							html += '<tr>';
							html += '<td>' + wphmEscHtml( tp.plugin || '' ) + '</td>';
							html += '<td><strong>' + tpCount + '</strong></td>';
							html += '<td><div class="wphm-rpt__inline-bar"><div class="wphm-rpt__inline-fill" style="width:' + tpPct + '%"></div></div></td>';
							html += '</tr>';
						} );
						html += '</tbody></table></div>';
					}
				}

				if ( data.debug_log.file_size ) {
					html += '<p class="wphm-rpt__dl-filesize">Log file size: <strong>' + wphmFormatBytes( data.debug_log.file_size ) + '</strong></p>';
				}
			}
		}
		html += '</div>';

		// ── Footer ──
		html += '<div class="wphm-rpt__footer">';
		html += '<p>Generated by <strong>WP Plugin Health Monitor</strong> &mdash; ' + wphmEscHtml( data.generated_at || '' ) + '</p>';
		html += '<p class="wphm-rpt__footer-note">This report is a point-in-time snapshot. Run a new scan to get updated results.</p>';
		html += '</div>';

		html += '</div>'; // wphm-rpt.

		target.innerHTML = html;
	}

	/**
	 * Get a human-readable description for a health score.
	 *
	 * @param {number} score Score 0–100.
	 * @return {string}
	 */
	function wphmGetScoreDescription( score ) {
		if ( score >= 80 ) {
			return 'Your site is in excellent health. Plugins are well-managed with minimal conflicts and good performance.';
		}
		if ( score >= 60 ) {
			return 'Your site is in good shape overall, with a few areas that could be improved for better performance.';
		}
		if ( score >= 40 ) {
			return 'Your site has some health issues that should be addressed. Review the details below for recommendations.';
		}
		if ( score >= 20 ) {
			return 'Your site has significant health issues. Immediate attention is recommended to prevent instability.';
		}
		return 'Your site is in critical condition. Multiple serious issues have been detected that require urgent action.';
	}

	/**
	 * Store for the last generated report data (used by download handlers).
	 */
	var wphmLastReportData = null;

	/**
	 * Bind the download buttons on the report page.
	 */
	function wphmBindDownloadButtons() {
		var pdfBtn  = document.getElementById( 'wphm-dl-pdf' );
		var textBtn = document.getElementById( 'wphm-dl-text' );
		var jsonBtn = document.getElementById( 'wphm-dl-json' );

		if ( pdfBtn ) {
			pdfBtn.addEventListener( 'click', function () {
				if ( ! wphmLastReportData ) {
					return;
				}
				wphmDownloadPdf();
			} );
		}

		if ( textBtn ) {
			textBtn.addEventListener( 'click', function () {
				if ( ! wphmLastReportData ) {
					return;
				}
				wphmDownloadFile(
					wphmBuildPlainText( wphmLastReportData ),
					'health-report.txt',
					'text/plain'
				);
			} );
		}

		if ( jsonBtn ) {
			jsonBtn.addEventListener( 'click', function () {
				if ( ! wphmLastReportData ) {
					return;
				}
				wphmDownloadFile(
					JSON.stringify( wphmLastReportData, null, 2 ),
					'health-report.json',
					'application/json'
				);
			} );
		}
	}

	/**
	 * Trigger a file download from a string.
	 *
	 * @param {string} content  File content.
	 * @param {string} filename Suggested filename.
	 * @param {string} mime     MIME type.
	 */
	function wphmDownloadFile( content, filename, mime ) {
		var blob = new Blob( [ content ], { type: mime + ';charset=utf-8' } );
		var url  = URL.createObjectURL( blob );
		var a    = document.createElement( 'a' );

		a.href     = url;
		a.download = filename;
		a.style.display = 'none';
		document.body.appendChild( a );
		a.click();

		setTimeout( function () {
			document.body.removeChild( a );
			URL.revokeObjectURL( url );
		}, 100 );
	}

	/**
	 * Download the report as a PDF using a print-optimized popup window.
	 */
	function wphmDownloadPdf() {
		var reportEl = document.querySelector( '.wphm-rpt' );
		if ( ! reportEl ) {
			window.print();
			return;
		}

		var css = '';
		var sheets = document.querySelectorAll( 'link[rel="stylesheet"], style' );
		sheets.forEach( function ( s ) {
			css += s.outerHTML;
		} );

		var htmlDoc = '<!DOCTYPE html><html><head><meta charset="utf-8">'
			+ '<title>WordPress Site Health Report</title>'
			+ css
			+ '<style>'
			+ 'body{margin:0;padding:24px;background:#fff;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;font-size:13px;color:#1d2327;}'
			+ '.wphm-rpt{max-width:900px;margin:0 auto;border:1px solid #dcdcde;border-radius:4px;}'
			+ '@media print{body{padding:0;}.wphm-rpt{border:none;max-width:100%;}'
			+ '.wphm-rpt__score-hero{break-inside:avoid;}.wphm-rpt__section{break-inside:avoid;}}'
			+ '</style>'
			+ '</head><body>'
			+ reportEl.outerHTML
			+ '<script>window.onload=function(){window.print();window.onafterprint=function(){window.close();};};<\/script>'
			+ '</body></html>';

		var pdfWin = window.open( '', '_blank', 'width=900,height=700' );
		if ( pdfWin ) {
			pdfWin.document.open();
			pdfWin.document.write( htmlDoc );
			pdfWin.document.close();
		} else {
			// Fallback if popup blocked.
			window.print();
		}
	}

	/**
	 * Build a well-structured plain text report.
	 *
	 * @param {Object} data Full report data.
	 * @return {string} Formatted plain text.
	 */
	function wphmBuildPlainText( data ) {
		var SEP   = '═'.repeat( 72 );
		var LINE  = '─'.repeat( 72 );
		var THIN  = '·'.repeat( 72 );
		var lines = [];

		// ── Header ──
		lines.push( '' );
		lines.push( SEP );
		lines.push( wphmCenter( 'WORDPRESS SITE HEALTH REPORT', 72 ) );
		lines.push( SEP );
		lines.push( '' );
		lines.push( '  Site URL      : ' + ( data.site_url || 'N/A' ) );
		lines.push( '  Generated     : ' + ( data.generated_at || 'N/A' ) );
		lines.push( '  WordPress     : ' + ( data.wordpress || 'N/A' ) );
		lines.push( '  PHP Version   : ' + ( data.php_version || 'N/A' ) );
		lines.push( '' );

		// ── Health Score ──
		if ( data.health_score ) {
			var score = data.health_score.total || 0;
			lines.push( LINE );
			lines.push( '  OVERALL HEALTH SCORE' );
			lines.push( LINE );
			lines.push( '' );
			lines.push( '  Score  : ' + score + ' / 100  (' + wphmGetScoreLabel( score ) + ')' );
			lines.push( '  Rating : ' + wphmGetScoreDescription( score ) );
			lines.push( '' );

			// Dimension breakdown.
			lines.push( '  ┌─────────────────────┬───────┬──────────────────────────────────┐' );
			lines.push( '  │ Dimension           │ Score │ Visual                           │' );
			lines.push( '  ├─────────────────────┼───────┼──────────────────────────────────┤' );

			var dims = [
				{ label: 'Plugins',       val: data.health_score.plugins || 0,    max: 30 },
				{ label: 'Assets',        val: data.health_score.assets || 0,     max: 30 },
				{ label: 'DB Queries',    val: data.health_score.db_queries || 0, max: 20 },
				{ label: 'Autoload Size', val: data.health_score.autoload || 0,   max: 20 },
			];

			dims.forEach( function ( d ) {
				var pct      = Math.round( ( d.val / d.max ) * 100 );
				var barLen   = 30;
				var filled   = Math.round( ( pct / 100 ) * barLen );
				var bar      = '█'.repeat( filled ) + '░'.repeat( barLen - filled );
				var scoreStr = ( d.val + '/' + d.max );

				lines.push(
					'  │ ' + wphmPadRight( d.label, 19 ) + ' │ '
					+ wphmPadRight( scoreStr, 5 ) + ' │ '
					+ bar + ' ' + wphmPadLeft( pct + '%', 4 ) + ' │'
				);
			} );

			lines.push( '  └─────────────────────┴───────┴──────────────────────────────────┘' );

			// Raw metrics.
			if ( data.health_score.raw ) {
				var raw = data.health_score.raw;
				lines.push( '' );
				lines.push( '  Raw Metrics:' );
				lines.push( '    Active Plugins   : ' + ( raw.plugin_count || 0 ) );
				lines.push( '    Enqueued Assets  : ' + ( raw.asset_count || 0 ) );
				lines.push( '    DB Queries       : ' + ( raw.db_query_count || 0 ) );
				lines.push( '    Autoload Size    : ' + wphmFormatBytes( raw.autoload_size || 0 ) );
			}
			lines.push( '' );
		}

		// ── Plugin Conflicts ──
		lines.push( LINE );
		lines.push( '  PLUGIN CONFLICTS' );
		lines.push( LINE );
		lines.push( '' );

		if ( data.conflicts ) {
			var conflictItems = [];
			if ( data.conflicts.duplicate_assets ) {
				conflictItems = conflictItems.concat( data.conflicts.duplicate_assets );
			}
			if ( data.conflicts.hook_conflicts ) {
				conflictItems = conflictItems.concat( data.conflicts.hook_conflicts );
			}

			if ( conflictItems.length === 0 ) {
				lines.push( '  ✓ No conflicts detected. All plugins are operating normally.' );
			} else {
				lines.push( '  ✗ ' + conflictItems.length + ' conflict(s) found:' );
				lines.push( '' );
				conflictItems.forEach( function ( item, idx ) {
					lines.push( '  ' + ( idx + 1 ) + '. Handle/Hook : ' + ( item.handle || item.hook || 'N/A' ) );
					lines.push( '     Plugins   : ' + ( item.plugins || [] ).join( ', ' ) );
					lines.push( '     Type      : ' + ( item.type || 'N/A' ) );
					lines.push( '     Severity  : ' + ( item.severity || 'N/A' ) );
					lines.push( '' );
				} );
			}
		} else {
			lines.push( '  No conflict data available.' );
		}
		lines.push( '' );

		// ── Duplicate Assets ──
		lines.push( LINE );
		lines.push( '  DUPLICATE ASSETS' );
		lines.push( LINE );
		lines.push( '' );

		if ( data.duplicate_assets ) {
			var dupAll = [];
			if ( data.duplicate_assets.hash_duplicates ) {
				dupAll = dupAll.concat( data.duplicate_assets.hash_duplicates );
			}
			if ( data.duplicate_assets.url_duplicates ) {
				dupAll = dupAll.concat( data.duplicate_assets.url_duplicates );
			}
			if ( data.duplicate_assets.library_duplicates ) {
				dupAll = dupAll.concat( data.duplicate_assets.library_duplicates );
			}

			if ( dupAll.length === 0 ) {
				lines.push( '  ✓ No duplicates detected. Each asset is loaded only once.' );
			} else {
				lines.push( '  ✗ ' + dupAll.length + ' duplicate(s) found:' );
				lines.push( '' );
				dupAll.forEach( function ( item, idx ) {
					lines.push( '  ' + ( idx + 1 ) + '. Handle  : ' + ( item.handle || 'N/A' ) );
					lines.push( '     Plugins : ' + ( item.plugins || [] ).join( ', ' ) );
					lines.push( '     Type    : ' + ( item.type || 'N/A' ) );
					lines.push( '' );
				} );
			}

			// Asset inventory.
			if ( data.duplicate_assets.asset_inventory && data.duplicate_assets.asset_inventory.length > 0 ) {
				var inv      = data.duplicate_assets.asset_inventory;
				var scripts  = inv.filter( function ( a ) { return a.type === 'script'; } );
				var styles   = inv.filter( function ( a ) { return a.type === 'style'; } );
				var totalSz  = 0;
				inv.forEach( function ( a ) { totalSz += a.file_size || 0; } );

				lines.push( '  Asset Inventory:' );
				lines.push( '    Scripts    : ' + scripts.length );
				lines.push( '    Styles     : ' + styles.length );
				lines.push( '    Total Size : ' + wphmFormatBytes( totalSz ) );
			}
		} else {
			lines.push( '  No asset data available.' );
		}
		lines.push( '' );

		// ── PHP Compatibility ──
		lines.push( LINE );
		lines.push( '  PHP COMPATIBILITY' );
		lines.push( LINE );
		lines.push( '' );

		if ( data.php_compat && data.php_compat.plugins ) {
			var phpPlugins   = data.php_compat.plugins;
			var compatible   = phpPlugins.filter( function ( p ) { return p.status === 'compatible'; } );
			var incompatible = phpPlugins.filter( function ( p ) { return p.status === 'incompatible'; } );
			var unknown      = phpPlugins.filter( function ( p ) { return p.status === 'unknown'; } );

			lines.push( '  Compatible   : ' + compatible.length + ' plugin(s)' );
			lines.push( '  Incompatible : ' + incompatible.length + ' plugin(s)' );
			lines.push( '  Unknown      : ' + unknown.length + ' plugin(s)' );
			lines.push( '' );

			if ( incompatible.length > 0 ) {
				lines.push( '  Incompatible Plugins:' );
				lines.push( THIN );
				incompatible.forEach( function ( p, idx ) {
					lines.push( '  ' + ( idx + 1 ) + '. ' + ( p.name || 'Unknown' ) );
					lines.push( '     Requires PHP : ' + ( p.required_php || 'N/A' ) );
					lines.push( '     Your PHP     : ' + ( p.current_php || 'N/A' ) );
					lines.push( '' );
				} );
			}

			if ( data.php_compat.deprecated_usage && data.php_compat.deprecated_usage.length > 0 ) {
				lines.push( '  Deprecated Function Usage:' );
				lines.push( THIN );
				data.php_compat.deprecated_usage.forEach( function ( d, idx ) {
					lines.push( '  ' + ( idx + 1 ) + '. Plugin   : ' + ( d.plugin || 'N/A' ) );
					lines.push( '     Function : ' + ( d.function || 'N/A' ) );
					lines.push( '     Deprecated In : WP ' + ( d.deprecated_in || '?' ) );
					lines.push( '     File     : ' + ( d.file || 'N/A' ) );
					lines.push( '' );
				} );
			}
		} else {
			lines.push( '  No PHP compatibility data available.' );
		}
		lines.push( '' );

		// ── Debug Log ──
		lines.push( LINE );
		lines.push( '  DEBUG LOG ANALYSIS' );
		lines.push( LINE );
		lines.push( '' );

		if ( data.debug_log ) {
			if ( ! data.debug_log.exists ) {
				lines.push( '  ℹ No debug.log file found. Debug logging may not be active.' );
			} else {
				var dlSummary = data.debug_log.summary || {};
				var dlTotal   = ( dlSummary.fatal || 0 ) + ( dlSummary.warning || 0 ) + ( dlSummary.notice || 0 );

				lines.push( '  Fatal Errors : ' + ( dlSummary.fatal || 0 ) );
				lines.push( '  Warnings     : ' + ( dlSummary.warning || 0 ) );
				lines.push( '  Notices      : ' + ( dlSummary.notice || 0 ) );
				lines.push( '  Total        : ' + dlTotal );

				if ( data.debug_log.file_size ) {
					lines.push( '  File Size    : ' + wphmFormatBytes( data.debug_log.file_size ) );
				}
				lines.push( '' );

				if ( data.debug_log.top_plugins && data.debug_log.top_plugins.length > 0 ) {
					lines.push( '  Top Offending Plugins:' );
					lines.push( THIN );
					data.debug_log.top_plugins.forEach( function ( tp, idx ) {
						lines.push( '  ' + ( idx + 1 ) + '. ' + ( tp.plugin || 'Unknown' ) + ' — ' + parseInt( tp.count, 10 ) + ' error(s)' );
					} );
				}
			}
		} else {
			lines.push( '  No debug log data available.' );
		}

		lines.push( '' );
		lines.push( SEP );
		lines.push( wphmCenter( 'Generated by WP Plugin Health Monitor', 72 ) );
		lines.push( wphmCenter( data.generated_at || '', 72 ) );
		lines.push( SEP );
		lines.push( '' );

		return lines.join( '\n' );
	}

	/**
	 * Center-align text within a given width.
	 *
	 * @param {string} text  Text to center.
	 * @param {number} width Total width.
	 * @return {string}
	 */
	function wphmCenter( text, width ) {
		if ( text.length >= width ) {
			return text;
		}
		var pad = Math.floor( ( width - text.length ) / 2 );
		return ' '.repeat( pad ) + text;
	}

	/**
	 * Pad a string to the right.
	 *
	 * @param {string} str String to pad.
	 * @param {number} len Target length.
	 * @return {string}
	 */
	function wphmPadRight( str, len ) {
		str = String( str );
		while ( str.length < len ) {
			str += ' ';
		}
		return str;
	}

	/**
	 * Pad a string to the left.
	 *
	 * @param {string} str String to pad.
	 * @param {number} len Target length.
	 * @return {string}
	 */
	function wphmPadLeft( str, len ) {
		str = String( str );
		while ( str.length < len ) {
			str = ' ' + str;
		}
		return str;
	}

	/**
	 * Escape HTML entities in a string.
	 *
	 * @param {string} str Input string.
	 * @return {string} Escaped string.
	 */
	function wphmEscHtml( str ) {
		if ( typeof str === 'undefined' || str === null ) {
			return '';
		}
		var div       = document.createElement( 'div' );
		div.appendChild( document.createTextNode( String( str ) ) );
		return div.innerHTML;
	}

	/**
	 * Convert a value to a safe non-negative integer.
	 *
	 * @param {*} value Input value.
	 * @param {number} fallback Fallback integer value.
	 * @return {number}
	 */
	function wphmSafeInt( value, fallback ) {
		var parsed = parseInt( value, 10 );
		if ( Number.isNaN( parsed ) || parsed < 0 ) {
			return typeof fallback === 'number' ? fallback : 0;
		}
		return parsed;
	}

} )();
