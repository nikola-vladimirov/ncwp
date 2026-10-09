/**
 * Nashenski Commerce: pickers in the WooCommerce block checkout.
 *
 * Shown under the shipping options (ExperimentalOrderShippingPackages slot) for the chosen
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

	function writePendingCss( state ) {
		var text = cssString( P.pending );
		var css = state.pending.map( function ( id ) {
			var option = '.wc-block-components-radio-control__option:has(input[value="' + id + '"])';

			return option + ' .wc-block-components-radio-control__secondary-label{visibility:hidden;position:relative}' +
				option + ' .wc-block-components-radio-control__secondary-label::after{content:' + text + ';visibility:visible;position:absolute;inset-inline-end:0;white-space:nowrap}';
		} ).join( '' );

		if ( state.chosenPending ) {
			css += '.wc-block-components-totals-shipping .wc-block-components-totals-item__value{visibility:hidden;position:relative}' +
				'.wc-block-components-totals-shipping .wc-block-components-totals-item__value::after{content:' + text + ';visibility:visible;position:absolute;inset-inline-end:0;white-space:nowrap}';
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

	function Pickers() {
		var data = useCheckoutData();

		wp.element.useEffect( function () {
			writePendingCss( pendingState( data.packages ) );
			P.decorateRates( pendingState( data.packages ).pending );
		} );

		if ( ! data.rate ) {
			return null;
		}

		var courier = P.courierOf( data.rate.rate_id );

		if ( ! courier ) {
			return null;
		}

		var type = 'boxnow' === courier ? 'apm' : ( metaOf( data.rate ).delivery_type || 'office' );

		return h( 'div', { className: 'nashenski-block-pickers' },
			h( P.Picker, {
				key: data.rate.rate_id,
				courier: courier,
				type: type,
				data: { address: data.address, payment: data.payment, company: {} },
				refresh: refresh,
				onChoice: function () {},
			} )
		);
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
		}, 30 );
	} ).observe( document.body, { childList: true, subtree: true } );
} )();
