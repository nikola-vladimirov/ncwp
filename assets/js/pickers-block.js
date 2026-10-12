/**
 * Nashenski Commerce: pickers in the WooCommerce block checkout.
 *
 * Rendered from the ExperimentalOrderShippingPackages slot and shown right under the chosen
 * Nashenski Commerce option. A choice goes into the same cookie as in the classic checkout, then
 * extensionCartUpdate (namespace "nashenski") makes WooCommerce price the cart again.
 *
 * Options that still wait for a choice say "Complete the fields to calculate" instead of "Free":
 * a small style element changes their price text, so the checkout's own markup is left alone.
 */
( function () {
	'use strict';

	var P = window.nashenskiPickers;

	if ( ! P || ! P.Picker || ! window.wc || ! wc.blocksCheckout || ! wp.plugins || ! wp.data ) {
		return;
	}

	var h = wp.element.createElement;
	var useSelect = wp.data.useSelect;
	var Slot = wc.blocksCheckout.ExperimentalOrderShippingPackages;
	var cartUpdate = wc.blocksCheckout.extensionCartUpdate;

	function metaOf( rate ) {
		var meta = {};

		( rate.meta_data || [] ).forEach( function ( m ) {
			meta[ m.key ] = m.value;
		} );

		return meta;
	}

	function cssString( text ) {
		return '"' + String( text ).replace( /\\/g, '\\\\' ).replace( /"/g, '\\"' ).replace( /\n/g, ' ' ) + '"';
	}

	/* Rate IDs waiting for a choice, and whether the chosen one is among them. */
	function pendingState( packages ) {
		var pending = [];
		var chosenPending = false;

		packages.forEach( function ( pkg ) {
			( pkg.shipping_rates || [] ).forEach( function ( rate ) {
				if ( 'yes' === metaOf( rate ).nashenski_pending ) {
					pending.push( rate.rate_id );
					chosenPending = chosenPending || !! rate.selected;
				}
			} );
		} );

		return { pending: pending, chosenPending: chosenPending };
	}

	var lastCss = null;

	/* Put the text in place of an element's content, so it takes the room it needs and wraps. */
	function replaceText( selector, text, sizeFrom ) {
		var node = document.querySelector( sizeFrom );
		var size = node && window.getComputedStyle ? window.getComputedStyle( node ).fontSize : '0.875rem';

		return selector + '{font-size:0!important}' + selector + '>*{display:none}' +
			selector + '::after{content:' + text + ';font-size:' + size + '}';
	}

	function writePendingCss( state ) {
		var text = cssString( P.pending );
		var css = state.pending.map( function ( id ) {
			var option = '.wc-block-components-radio-control__option:has(input[value="' + id + '"])';

			return replaceText( option + ' .wc-block-components-radio-control__secondary-label', text, '.wc-block-components-radio-control__label' );
		} ).join( '' );

		// In the order summary the text takes the place of the shipping price.
		if ( state.chosenPending ) {
			css += replaceText( '.wc-block-components-totals-shipping .wc-block-components-totals-item__value', text, '.wc-block-components-totals-shipping .wc-block-components-totals-item__label' );
		}

		if ( css === lastCss ) {
			return;
		}

		lastCss = css;

		var style = document.getElementById( 'nashenski-pending-rates' );

		if ( ! style ) {
			style = document.createElement( 'style' );
			style.id = 'nashenski-pending-rates';
			document.head.appendChild( style );
		}

		style.textContent = css;
	}

	function useCheckoutData() {
		return useSelect( function ( select ) {
			var cart = select( 'wc/store/cart' );
			var packages = cart.getShippingRates() || [];
			var rate = null;

			packages.forEach( function ( pkg ) {
				( pkg.shipping_rates || [] ).forEach( function ( r ) {
					if ( r.selected ) {
						rate = r;
					}
				} );
			} );

			var payment = select( 'wc/store/payment' );
			var customer = cart.getCustomerData() || {};
			var shipping = customer.shippingAddress || {};
			var billing = customer.billingAddress || {};

			return {
				rate: rate,
				packages: packages,
				address: {
					first_name: shipping.first_name || billing.first_name || '',
					last_name: shipping.last_name || billing.last_name || '',
					phone: shipping.phone || billing.phone || '',
					state: shipping.state || '',
					city: shipping.city || '',
					postcode: shipping.postcode || '',
					country: shipping.country || 'BG',
					address_2: shipping.address_2 || '',
				},
				payment: payment && payment.getActivePaymentMethod ? payment.getActivePaymentMethod() : '',
			};
		}, [] );
	}

	function refresh() {
		cartUpdate( { namespace: 'nashenski', data: { changed: Date.now() } } );
	}

	/* The town from the picker goes into the checkout's address, so the order and WooCommerce have it. */
	function saveTown( town ) {
		var cart = wp.data.dispatch( 'wc/store/cart' );
		var customer = wp.data.select( 'wc/store/cart' ).getCustomerData() || {};
		var billing = String( ( customer.billingAddress || {} ).city || '' ).toLowerCase();
		var change = { city: town.name, state: town.state, postcode: town.postcode };

		cart.setShippingAddress( change );

		// A billing address in another town (typed by the customer) stays as it is.
		if ( ! billing || billing === String( ( customer.shippingAddress || {} ).city || '' ).toLowerCase() ) {
			cart.setBillingAddress( change );
		}
	}

	/*
	 * The picker sits right under the chosen shipping option, like in the classic checkout. The
	 * holder is moved there; when the option cannot be found it stays in the plugin's slot.
	 */
	var holder = document.createElement( 'div' );
	var shownRate = '';
	var slotNode = null;

	holder.className = 'nashenski-block-pickers';

	function place() {
		var input = shownRate ? document.querySelector( 'input.wc-block-components-radio-control__input[value="' + shownRate.replace( /"/g, '' ) + '"]' ) : null;
		var option = input ? input.closest( '.wc-block-components-radio-control__option' ) : null;

		if ( option ) {
			if ( option.nextElementSibling !== holder ) {
				option.after( holder );
			}
		} else if ( slotNode && holder.parentNode !== slotNode ) {
			slotNode.appendChild( holder );
		}
	}

	function Pickers() {
		var data = useCheckoutData();
		var rate = data.rate;
		var courier = rate ? P.courierOf( rate.rate_id ) : '';

		shownRate = courier ? rate.rate_id : '';

		wp.element.useLayoutEffect( function () {
			place();
			writePendingCss( pendingState( data.packages ) );
			P.decorateRates( pendingState( data.packages ).pending );
		} );

		if ( ! courier ) {
			return null;
		}

		var type = 'boxnow' === courier ? 'apm' : ( metaOf( rate ).delivery_type || 'office' );

		return h( 'div', {
			className: 'nashenski-block-pickers-slot',
			ref: function ( node ) {
				slotNode = node;
			},
		}, wp.element.createPortal( h( P.Picker, {
			key: rate.rate_id,
			courier: courier,
			type: type,
			data: { address: data.address, payment: data.payment, company: {} },
			refresh: refresh,
			onTown: saveTown,
			onChoice: function () {},
		} ), holder ) );
	}

	wp.plugins.registerPlugin( 'nashenski-block-pickers', {
		scope: 'woocommerce-checkout',
		render: function () {
			return h( Slot, null, h( Pickers ) );
		},
	} );

	// Logos and delivery types also on the cart page and while the checkout loads.
	var timer = null;

	new MutationObserver( function () {
		window.clearTimeout( timer );
		timer = window.setTimeout( function () {
			var cart = wp.data.select( 'wc/store/cart' );
			var state = pendingState( cart ? cart.getShippingRates() || [] : [] );

			writePendingCss( state );
			P.decorateRates( state.pending );
			// The checkout re-rendered the options: keep the picker under the chosen one.
			place();
		}, 30 );
	} ).observe( document.body, { childList: true, subtree: true } );
} )();
