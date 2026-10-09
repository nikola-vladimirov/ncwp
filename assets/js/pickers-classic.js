/**
 * Nashenski Commerce: pickers in the classic checkout, the cart and page builder checkouts.
 *
 * WooCommerce (and builders) replace the shipping options after every update, so the picker is
 * mounted again into the holder of the chosen option each time; its choice is kept in the
 * browser, so nothing is lost. A new choice triggers "update_checkout" to price the cart again.
 */
( function ( $ ) {
	'use strict';

	var P = window.nashenskiPickers;

	if ( ! P || ! P.Picker || ! window.wp || ! wp.element ) {
		return;
	}

	var h = wp.element.createElement;
	var roots = [];

	function value( selector ) {
		var node = document.querySelector( selector );

		return node ? String( node.value || '' ) : '';
	}

	function checked( selector ) {
		var node = document.querySelector( selector );

		return !! ( node && node.checked );
	}

	/* What the cookie needs, from the checkout form. */
	function readData() {
		var prefix = checked( '#ship-to-different-address-checkbox' ) ? 'shipping' : 'billing';

		function field( name ) {
			return value( '#' + prefix + '_' + name ) || value( '#billing_' + name );
		}

		var payment = document.querySelector( 'input[name="payment_method"]:checked' ) || document.querySelector( 'input[name="payment_method"][type="hidden"]' );

		return {
			address: {
				first_name: field( 'first_name' ),
				last_name: field( 'last_name' ),
				phone: value( '#billing_phone' ) || value( '#shipping_phone' ),
				state: field( 'state' ),
				city: field( 'city' ),
				country: field( 'country' ) || 'BG',
				address_2: field( 'address_2' ),
			},
			payment: payment ? payment.value : '',
			company: {
				to_company: checked( '#woo-billing-to-company' ),
				company: value( '#billing_company' ),
				mol: value( '#billing_company_mol' ),
				vat: value( '#billing_vat_number' ),
			},
		};
	}

	function refresh() {
		$( document.body ).trigger( 'update_checkout' );
	}

	/* Stores that show the street field get the office or address written into it. */
	function fillAddress( text ) {
		[ 'billing', 'shipping' ].forEach( function ( prefix ) {
			var input = document.getElementById( prefix + '_address_1' );

			if ( ! input || ! text ) {
				return;
			}

			if ( '' === input.value || input.getAttribute( 'data-nashenski-filled' ) === input.value ) {
				input.value = text;
				input.setAttribute( 'data-nashenski-filled', text );
			}
		} );
	}

	function chosenRate() {
		var input = document.querySelector( 'input[name^="shipping_method"]:checked' ) || document.querySelector( 'input[name^="shipping_method"][type="hidden"]' );

		return input ? input.value : '';
	}

	var rootCount = 0;

	/* Each root gets its own ID prefix, so useId() IDs stay unique when an option is shown twice. */
	function createRoot( node ) {
		rootCount += 1;

		return wp.element.createRoot( node, { identifierPrefix: 'nashenski' + rootCount + '-' } );
	}

	/* Holders that sit in a flex column with a gap get it as a negative margin, so they attach to their option. */
	function attach( node ) {
		var parent = node.parentElement;

		if ( parent && window.getComputedStyle ) {
			var gap = parseFloat( window.getComputedStyle( parent ).rowGap ) || 0;

			node.style.setProperty( '--nashenski-attach', gap ? -gap + 'px' : '0px' );
		}
	}

	function mount() {
		var rate = chosenRate();
		var data = readData();

		roots = roots.filter( function ( entry ) {
			if ( ! document.body.contains( entry.node ) || entry.node.getAttribute( 'data-rate' ) !== rate ) {
				entry.root.unmount();
				return false;
			}

			return true;
		} );

		if ( ! rate || ! P.courierOf( rate ) ) {
			return;
		}

		var nodes = document.querySelectorAll( '.nashenski-additional-fields[data-rate="' + rate + '"]' );

		Array.prototype.forEach.call( nodes, function ( node ) {
			var entry = null;

			roots.forEach( function ( e ) {
				if ( e.node === node ) {
					entry = e;
				}
			} );

			if ( ! entry ) {
				entry = { node: node, root: createRoot( node ) };
				roots.push( entry );
				attach( node );
			}

			entry.root.render( h( P.Picker, {
				courier: node.getAttribute( 'data-courier' ),
				type: node.getAttribute( 'data-type' ),
				data: data,
				refresh: refresh,
				onChoice: fillAddress,
			} ) );
		} );
	}

	/* ---------- company invoice fields and the store's own fields ---------- */

	// Builder checkouts made of single field elements: fields the template does not have go after
	// the billing fields. Each template is placed once.
	function insertExtraFields() {
		Array.prototype.forEach.call( document.querySelectorAll( 'template.nashenski-extra-fields:not([data-placed])' ), insertTemplate );
	}

	function insertTemplate( template ) {
		var form = document.querySelector( 'form.checkout' );

		if ( ! form ) {
			return;
		}

		var wrappers = form.querySelectorAll( '.woocommerce-billing-fields__field-wrapper' );
		var target = wrappers.length ? wrappers[ wrappers.length - 1 ] : null;

		if ( ! target ) {
			return;
		}

		template.setAttribute( 'data-placed', '1' );

		var fragment = template.content.cloneNode( true );
		var model = target.querySelector( '.form-row input.input-text' );
		var modelClasses = model ? Array.prototype.filter.call( model.closest( '.form-row' ).classList, function ( name ) {
			return /^brxe-/.test( name );
		} ) : [];

		Array.prototype.forEach.call( fragment.querySelectorAll( 'input, select, textarea' ), function ( input ) {
			var row = input.closest( '.form-row' );

			// Leave out fields the template already has.
			if ( input.name && form.querySelector( '[name="' + input.name + '"]' ) ) {
				if ( row ) {
					row.remove();
				}

				return;
			}

			// Text fields take the look of the template's own fields (classes and floating labels).
			if ( row && 'checkbox' !== input.type ) {
				modelClasses.forEach( function ( name ) {
					row.classList.add( name );
				} );

				var label = row.querySelector( 'label' );

				if ( label && ! input.placeholder && model && model.placeholder ) {
					input.placeholder = label.textContent.replace( /\*|\(.*?\)/g, '' ).trim();
				}
			}
		} );

		target.appendChild( fragment );
	}

	function toggleCompanyFields() {
		var box = document.getElementById( 'woo-billing-to-company' );

		if ( ! box ) {
			return;
		}

		box.value = box.checked ? '1' : '0';

		Array.prototype.forEach.call( document.querySelectorAll( '.nashenski-company-info' ), function ( row ) {
			row.style.display = box.checked ? '' : 'none';
		} );
	}

	/* ---------- wiring ---------- */

	var timer = null;

	function schedule() {
		window.clearTimeout( timer );
		// A timer, not requestAnimationFrame: that one pauses in background tabs.
		timer = window.setTimeout( function () {
			insertExtraFields();
			toggleCompanyFields();
			P.decorateRates( null );
			mount();
		}, 30 );
	}

	function start() {
		schedule();

		$( document.body ).on( 'updated_checkout updated_shipping_method updated_cart_totals updated_wc_div', schedule );
		$( document.body ).on( 'change', 'form.checkout input, form.checkout select', function ( e ) {
			if ( 'woo-billing-to-company' === e.target.id ) {
				toggleCompanyFields();
				refresh();
				return;
			}

			if ( ! e.target.closest( '.nashenski-additional-fields' ) ) {
				schedule();
			}
		} );

		new MutationObserver( function ( records ) {
			for ( var i = 0; i < records.length; i++ ) {
				var target = records[ i ].target;

				// Changes inside our own pickers do not need a new mount.
				if ( ! ( target.closest && target.closest( '.nashenski-additional-fields' ) ) ) {
					schedule();
					return;
				}
			}
		} ).observe( document.body, { childList: true, subtree: true } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )( window.jQuery );
