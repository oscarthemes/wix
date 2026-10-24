/**
 * Brigade Gravity Form Locator admin screens.
 *
 * Dashboard: runs batched scans over admin-ajax and loads the results region in place
 * (refresh, scan completion, pagination, sorting, tabs, search and filters) without
 * reloading the page. Settings: unsaved-changes bar and dependent controls.
 */
( function () {
	'use strict';

	var config = window.bgflAdmin;
	if ( ! config ) {
		return;
	}

	var i18n = config.i18n;
	var running = false;
	var stopped = false;
	var loadToken = 0;

	function $( selector, root ) {
		return ( root || document ).querySelector( selector );
	}

	function request( action, data ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', config.nonce );
		Object.keys( data || {} ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );

		return fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} ).then( function ( response ) {
			return response.json().catch( function () {
				throw new Error( i18n.network );
			} );
		} ).then( function ( json ) {
			if ( ! json || ! json.success ) {
				throw new Error( json && json.data && json.data.message ? json.data.message : i18n.failed );
			}
			return json.data;
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Dashboard                                                          */
	/* ------------------------------------------------------------------ */

	var results = $( '[data-bgfl-results]' );

	function setMessage( text, isError ) {
		var message = $( '[data-bgfl-message]' );
		if ( message ) {
			message.textContent = text;
			message.classList.toggle( 'is-error', !! isError );
		}
	}

	function setProgress( progress ) {
		var wrap = $( '[data-bgfl-progress]' );
		if ( ! wrap ) {
			return;
		}
		wrap.hidden = false;
		wrap.querySelector( '[role="progressbar"]' ).setAttribute( 'aria-valuenow', String( progress.percent || 0 ) );
		wrap.querySelector( '.bgfl-progress__fill' ).style.width = ( progress.percent || 0 ) + '%';

		var counts = i18n.progress.replace( '%1$s', progress.done || 0 ).replace( '%2$s', progress.total || 0 );
		setMessage( ( progress.label || '' ) + ' ' + counts + ' (' + ( progress.percent || 0 ) + '%)' );
	}

	function hideProgress() {
		var wrap = $( '[data-bgfl-progress]' );
		if ( wrap ) {
			wrap.hidden = true;
		}
	}

	function setBusy( busy ) {
		running = busy;
		document.querySelectorAll( '[data-bgfl-action]' ).forEach( function ( button ) {
			if ( 'cancel' === button.getAttribute( 'data-bgfl-action' ) ) {
				button.hidden = ! busy;
				return;
			}
			button.disabled = busy;
		} );
	}

	function resetPrimary() {
		var primary = $( '[data-bgfl-primary]' );
		if ( primary ) {
			primary.setAttribute( 'data-bgfl-action', 'scan' );
			primary.textContent = i18n.scanLabel;
		}
	}

	function clearStaleAlerts() {
		document.querySelectorAll( '[data-bgfl-stale-alert]' ).forEach( function ( alert ) {
			alert.remove();
		} );
	}

	/**
	 * Loads the results region for a dashboard URL.
	 *
	 * @param {string}  href    Dashboard URL whose query decides view, filters and page.
	 * @param {boolean} push    Add a browser history entry.
	 * @return {Promise}
	 */
	function loadResults( href, push ) {
		if ( ! results ) {
			return Promise.resolve();
		}

		var url = new URL( href, window.location.href );
		var token = ++loadToken;

		results.setAttribute( 'aria-busy', 'true' );
		results.classList.add( 'is-loading' );

		return request( 'bgfl_results', { query: url.search } ).then( function ( data ) {
			if ( token !== loadToken ) {
				return;
			}
			results.innerHTML = data.html;

			var lastScan = $( '[data-bgfl-last-scan]' );
			if ( lastScan ) {
				lastScan.textContent = data.lastScan;
			}

			var exportLink = $( '[data-bgfl-export]' );
			if ( exportLink ) {
				exportLink.hidden = ! data.exportUrl;
				if ( data.exportUrl ) {
					exportLink.href = data.exportUrl;
				}
			}

			if ( push ) {
				window.history.pushState( { bgfl: true }, '', data.url );
			}
		} ).finally( function () {
			if ( token === loadToken ) {
				results.setAttribute( 'aria-busy', 'false' );
				results.classList.remove( 'is-loading' );
			}
		} );
	}

	function finish() {
		setMessage( i18n.complete );
		loadResults( window.location.href, false ).then( function () {
			setBusy( false );
			hideProgress();
			resetPrimary();
			setMessage( i18n.scanComplete );
		} ).catch( function () {
			setBusy( false );
			hideProgress();
			resetPrimary();
			setMessage( i18n.loadFailed, true );
		} );
	}

	function fail( error ) {
		setBusy( false );
		setMessage( error && error.message ? error.message : i18n.failed, true );
	}

	function step() {
		if ( stopped ) {
			return;
		}
		request( 'bgfl_scan_step' ).then( function ( progress ) {
			if ( stopped ) {
				return;
			}
			if ( 'complete' === progress.status ) {
				setProgress( progress );
				finish();
				return;
			}
			if ( 'failed' === progress.status ) {
				fail( new Error( progress.error || i18n.failed ) );
				return;
			}
			setProgress( progress );
			// A busy response means another request holds the step lock; wait briefly.
			window.setTimeout( step, progress.busy ? 1500 : 50 );
		} ).catch( fail );
	}

	function start( mode ) {
		if ( running ) {
			return;
		}
		if ( 'rescan' === mode && ! window.confirm( i18n.confirmRescan ) ) {
			return;
		}

		stopped = false;
		setBusy( true );
		setMessage( i18n.starting );
		clearStaleAlerts();

		request( 'bgfl_scan_start', { mode: mode } ).then( function ( progress ) {
			setProgress( progress );
			if ( 'complete' === progress.status ) {
				finish();
				return;
			}
			step();
		} ).catch( fail );
	}

	function cancel() {
		if ( ! window.confirm( i18n.confirmCancel ) ) {
			return;
		}
		stopped = true;
		request( 'bgfl_scan_cancel' ).then( function () {
			setBusy( false );
			hideProgress();
			resetPrimary();
			clearStaleAlerts();
			setMessage( i18n.cancelled );
		} ).catch( fail );
	}

	function refresh() {
		if ( running ) {
			return;
		}
		setMessage( i18n.refreshing );
		loadResults( window.location.href, false ).then( function () {
			setMessage( i18n.refreshed );
		} ).catch( function () {
			setMessage( i18n.loadFailed, true );
		} );
	}

	/**
	 * Whether a link points to this dashboard and can be loaded in place.
	 *
	 * @param {HTMLAnchorElement} link Link.
	 * @return {boolean}
	 */
	function isDashboardLink( link ) {
		if ( ! link.href || link.target || link.hasAttribute( 'download' ) ) {
			return false;
		}
		var url = new URL( link.href, window.location.href );
		var here = new URL( window.location.href );
		return url.origin === here.origin && url.pathname === here.pathname && url.searchParams.get( 'page' ) === here.searchParams.get( 'page' );
	}

	if ( results ) {
		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-bgfl-action]' );
			if ( button ) {
				event.preventDefault();
				var action = button.getAttribute( 'data-bgfl-action' );
				if ( 'cancel' === action ) {
					cancel();
				} else if ( 'refresh' === action ) {
					refresh();
				} else {
					start( action );
				}
				return;
			}

			// Pagination, sorting, view tabs and form links inside the results region.
			var link = event.target.closest( 'a' );
			if ( ! link || ! results.contains( link ) || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
				return;
			}
			if ( ! isDashboardLink( link ) ) {
				return;
			}
			event.preventDefault();
			loadResults( link.href, true ).catch( function () {
				window.location.assign( link.href );
			} );
		} );

		// Search box, filters and the page-number field submit the table form.
		results.addEventListener( 'submit', function ( event ) {
			var form = event.target.closest( '.bgfl-table-form' );
			if ( ! form ) {
				return;
			}
			event.preventDefault();

			var params = new URLSearchParams();
			new FormData( form ).forEach( function ( value, key ) {
				if ( '' !== value && -1 === [ '_wpnonce', '_wp_http_referer', 'filter_action', 'action', 'action2' ].indexOf( key ) ) {
					params.set( key, value );
				}
			} );

			// A new search or filter starts at page 1; keep the page only when it was typed.
			var active = document.activeElement;
			if ( ! active || 'current-page-selector' !== active.id ) {
				params.delete( 'paged' );
			}

			var url = new URL( window.location.href );
			url.search = params.toString();
			loadResults( url.toString(), true ).catch( function () {
				window.location.assign( url.toString() );
			} );
		} );

		window.addEventListener( 'popstate', function () {
			loadResults( window.location.href, false ).catch( function () {
				window.location.reload();
			} );
		} );

		// Pick up a scan that is still running (for example after a page reload).
		var progressWrap = $( '[data-bgfl-progress]' );
		if ( progressWrap && ! progressWrap.hidden ) {
			start( 'resume' );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Settings                                                           */
	/* ------------------------------------------------------------------ */

	var settings = $( '[data-bgfl-settings]' );
	if ( settings ) {
		var savebar = $( '[data-bgfl-savebar]' );
		var initial = new URLSearchParams( new FormData( settings ) ).toString();

		var syncSchedule = function () {
			var toggle = $( '[data-bgfl-schedule-toggle]', settings );
			var group = $( '[data-bgfl-schedule-frequency]', settings );
			// Dimmed rather than disabled, so the chosen frequency is still saved.
			if ( toggle && group ) {
				group.toggleAttribute( 'data-off', ! toggle.checked );
			}
		};

		settings.addEventListener( 'change', function () {
			syncSchedule();
			var dirty = new URLSearchParams( new FormData( settings ) ).toString() !== initial;
			if ( savebar ) {
				savebar.classList.toggle( 'is-dirty', dirty );
				var status = $( '[data-bgfl-savebar-status]', savebar );
				if ( status ) {
					status.textContent = dirty ? i18n.unsaved : '';
				}
			}
		} );
		syncSchedule();
	}

	var reset = $( '[data-bgfl-reset]' );
	if ( reset ) {
		var confirmBox = reset.querySelector( 'input[type="checkbox"]' );
		var resetButton = reset.querySelector( 'button[type="submit"]' );
		var syncReset = function () {
			resetButton.disabled = ! confirmBox.checked;
		};
		confirmBox.addEventListener( 'change', syncReset );
		syncReset();

		reset.addEventListener( 'submit', function ( event ) {
			if ( ! window.confirm( i18n.confirmReset ) ) {
				event.preventDefault();
			}
		} );
	}
}() );
