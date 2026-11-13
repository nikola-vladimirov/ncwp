/**
 * Quick order: the form is fetched on each click and shown as chosen, with a light phone mask
 * that follows the configured Bulgarian format. The settings come from window.nashenskiQuickOrder
 * (Front_End\Quick_Order); the look is in assets/css/quick-order.css.
 */
( function () {
	'use strict';

	var cfg = window.nashenskiQuickOrder;
	var box = null;

	function close() {
		if ( ! box ) {
			return;
		}

		if ( 'function' === typeof box.close && 'DIALOG' === box.tagName ) {
			box.close();
		}

		if ( box._backdrop ) {
			box._backdrop.remove();
		}

		box.remove();
		box = null;
	}

	/*
	 * The form is our own escaped fragment from the server. It is parsed in a separate document, script
	 * elements and inline event handlers are dropped, and the nodes are moved into the box. A message
	 * (a text node in a paragraph) is added as an element.
	 */
	function fill( content ) {
		box.textContent = '';

		if ( 'string' !== typeof content ) {
			box.appendChild( content );
			return;
		}

		var parsed = new DOMParser().parseFromString( content, 'text/html' );

		Array.prototype.forEach.call( parsed.body.querySelectorAll( '*' ), function ( node ) {
			if ( 'SCRIPT' === node.tagName ) {
				node.remove();
				return;
			}

			Array.prototype.slice.call( node.attributes ).forEach( function ( attr ) {
				if ( 0 === attr.name.toLowerCase().indexOf( 'on' ) ) {
					node.removeAttribute( attr.name );
				}
			} );
		} );

		while ( parsed.body.firstChild ) {
			box.appendChild( parsed.body.firstChild );
		}
	}

	function para( text ) {
		var p = document.createElement( 'p' );

		p.textContent = text;

		return p;
	}

	function closeButton() {
		var button = document.createElement( 'button' );

		button.type = 'button';
		button.className = 'button nashenski-quick-order-close';
		button.textContent = cfg.closeText;

		return button;
	}

	function post( data ) {
		return fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams( data ) } );
	}

	function show( html, trigger ) {
		close();

		if ( 'modal' === cfg.appearance ) {
			box = document.createElement( 'dialog' );
			box.className = 'nashenski-quick-order';
			box.addEventListener( 'click', function ( e ) {
				if ( e.target === box ) {
					close();
				}
			} );
			document.body.appendChild( box );
			fill( html );
			box.showModal();
		} else if ( 'side' === cfg.appearance ) {
			box = document.createElement( 'div' );
			box.className = 'nashenski-quick-order nashenski-quick-order--side';
			box._backdrop = document.createElement( 'div' );
			box._backdrop.className = 'nashenski-quick-order__backdrop';
			box._backdrop.addEventListener( 'click', close );
			document.body.appendChild( box._backdrop );
			document.body.appendChild( box );
			fill( html );
		} else {
			box = document.createElement( 'div' );
			box.className = 'nashenski-quick-order nashenski-quick-order--inline';
			fill( html );

			if ( trigger && trigger.parentNode ) {
				trigger.parentNode.insertBefore( box, trigger.nextSibling );
			} else {
				document.body.appendChild( box );
			}
		}

		var actions = box.querySelector( '.nashenski-quick-order-actions' );

		if ( ! actions ) {
			actions = document.createElement( 'p' );
			actions.className = 'nashenski-quick-order-actions';
			box.appendChild( actions );
		}

		actions.appendChild( closeButton() );

		var phone = box.querySelector( '[data-nashenski-phone]' );

		if ( phone ) {
			mask( phone );
			phone.addEventListener( 'input', function () {
				mask( phone );
			} );
		}
	}

	function mask( input ) {
		var digits = input.value.replace( /[^0-9]/g, '' );
		var dial = cfg.phoneDial || '';
		var out = '';
		var parts;

		if ( 'international' === cfg.phoneFormat ) {
			if ( dial && 0 !== digits.indexOf( dial ) ) {
				digits = '0' === digits.charAt( 0 ) ? dial + digits.slice( 1 ) : dial + digits;
			}

			parts = digits.slice( dial.length, 20 ).match( /\d{1,4}/g ) || [];
			out = '+' + dial + ( parts.length ? ' ' + parts.join( ' ' ) : '' );
		} else {
			if ( '0' !== digits.charAt( 0 ) ) {
				digits = '0' + digits;
			}

			parts = digits.slice( 0, 14 ).match( /\d{1,4}/g ) || [];
			out = parts.join( ' ' );
		}

		if ( input.value !== out ) {
			input.value = out;
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var button = e.target.closest( '.nashenski-quick-order-open' );

		if ( ! button ) {
			if ( e.target.closest( '.nashenski-quick-order-close' ) ) {
				close();
			}

			return;
		}

		e.preventDefault();

		var qty = document.querySelector( 'form.cart input.qty' );
		var data = {
			context: button.getAttribute( 'data-context' ) || 'product',
			product_id: button.getAttribute( 'data-product-id' ) || '0',
			quantity: qty ? qty.value : '1',
		};

		// The form is read now, with its own nonce in it: one printed in a cached page would be stale.
		fetch( cfg.formUrl + ( cfg.formUrl.indexOf( '?' ) < 0 ? '?' : '&' ) + new URLSearchParams( data ), {
			credentials: 'same-origin',
			headers: cfg.restNonce ? { 'X-WP-Nonce': cfg.restNonce } : {},
		} ).then( function ( reply ) {
			return reply.json();
		} ).then( function ( result ) {
			show( result && result.html ? result.html : para( result && result.message ? result.message : cfg.errorText ), button );
		} ).catch( function () {
			show( para( cfg.errorText ), button );
		} );
	} );

	document.addEventListener( 'submit', function ( e ) {
		var form = e.target;

		if ( ! form.closest || ! form.closest( '.nashenski-quick-order' ) ) {
			return;
		}

		e.preventDefault();

		var button = form.querySelector( 'button[type=submit]' );

		if ( button ) {
			button.disabled = true;
		}

		post( new FormData( form ) ).then( function ( reply ) {
			return reply.json();
		} ).then( function ( result ) {
			var message = result && result.data && result.data.message ? result.data.message : cfg.errorText;

			if ( result && result.success ) {
				if ( box ) {
					var actions = document.createElement( 'p' );

					fill( para( message ) );
					actions.appendChild( closeButton() );
					box.appendChild( actions );
				}

				return;
			}

			if ( button ) {
				button.disabled = false;
			}

			var note = form.querySelector( '.nashenski-quick-order-error' );

			if ( note ) {
				note.textContent = message;
			}
		} ).catch( function () {
			if ( button ) {
				button.disabled = false;
			}
		} );
	} );
} )();
