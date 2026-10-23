/**
 * Brigade Gravity Form Locator dashboard: runs batched scans over admin-ajax.
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

	function $( selector ) {
		return document.querySelector( selector );
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

	function setMessage( text, isError ) {
		var message = $( '[data-bgfl-message]' );
		if ( message ) {
			message.textContent = text;
			message.classList.toggle( 'is-error', !! isError );
		}
	}

	function setProgress( progress ) {
		var wrap = $( '[data-bgfl-progress]' );
		var bar = wrap && wrap.querySelector( '[role="progressbar"]' );
		var fill = wrap && wrap.querySelector( '.bgfl-progress__fill' );
		if ( ! wrap ) {
			return;
		}
		wrap.hidden = false;
		bar.setAttribute( 'aria-valuenow', String( progress.percent || 0 ) );
		fill.style.width = ( progress.percent || 0 ) + '%';

		var counts = i18n.progress.replace( '%1$s', progress.done || 0 ).replace( '%2$s', progress.total || 0 );
		setMessage( ( progress.label || '' ) + ' ' + counts + ' (' + ( progress.percent || 0 ) + '%)' );
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

	function finish() {
		setMessage( i18n.complete );
		window.setTimeout( function () {
			var url = new URL( window.location.href );
			url.searchParams.delete( 'paged' );
			window.location.assign( url.toString() );
		}, 600 );
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

		var notice = $( '[data-bgfl-interrupted]' );
		if ( notice ) {
			notice.hidden = true;
		}

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
			var wrap = $( '[data-bgfl-progress]' );
			if ( wrap ) {
				wrap.hidden = true;
			}
			setMessage( i18n.cancelled );
			window.location.reload();
		} ).catch( fail );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-bgfl-action]' );
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		var action = button.getAttribute( 'data-bgfl-action' );
		if ( 'cancel' === action ) {
			cancel();
		} else {
			start( action );
		}
	} );

	var reset = $( '[data-bgfl-reset]' );
	if ( reset ) {
		reset.addEventListener( 'submit', function ( event ) {
			if ( ! window.confirm( i18n.confirmReset ) ) {
				event.preventDefault();
			}
		} );
	}

	// Pick up a scan that is still running (for example after a page reload).
	if ( $( '[data-bgfl-progress]' ) && ! $( '[data-bgfl-progress]' ).hidden ) {
		start( 'resume' );
	}
}() );
