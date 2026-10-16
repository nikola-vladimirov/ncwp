/**
 * Nashenski Commerce: the checkout pickers in the Delivery box of a quick order (order screen).
 *
 * The owner picks a shipping option; the picker for it writes the courier cookie as at checkout,
 * and the cookie value is copied into the box's hidden field, which the order save reads.
 */
( function () {
	'use strict';

	var P = window.nashenskiPickers;
	var box = document.getElementById( 'nashenski-delivery' );

	if ( ! P || ! P.Picker || ! box || ! window.wp || ! wp.element ) {
		return;
	}

	var h = wp.element.createElement;
	var select = box.querySelector( 'select[name="nashenski_delivery_rate"]' );
	var holder = box.querySelector( '.nashenski-delivery-picker' );
	var field = box.querySelector( 'input[name="nashenski_delivery_choice"]' );
	var data = JSON.parse( box.getAttribute( 'data-order' ) || '{}' );
	var root = wp.element.createRoot( holder, { identifierPrefix: 'nashenski-delivery-' } );
	var current = '';

	function readCookie( name ) {
		var match = document.cookie.match( new RegExp( '(?:^|; )' + name.replace( /[-]/g, '\\-' ) + '=([^;]*)' ) );

		return match ? decodeURIComponent( match[ 1 ] ) : '';
	}

	function forget( name ) {
		document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;SameSite=Lax';
	}

	function render() {
		var option = select.options[ select.selectedIndex ];
		var courier = option ? option.getAttribute( 'data-courier' ) : '';
		var cookie = courier && P.COURIERS[ courier ] ? P.COURIERS[ courier ].cookie : '';

		field.value = '';
		current = cookie;

		if ( ! cookie ) {
			root.render( null );

			return;
		}

		// A choice left from the owner's own shopping must not end up on this order.
		forget( cookie );

		root.render( h( P.Picker, {
			key: select.value,
			courier: courier,
			type: option.getAttribute( 'data-type' ),
			data: data,
			refresh: function () {
				field.value = readCookie( cookie );
			},
			onChoice: function () {},
			onTown: function ( town ) {
				data = Object.assign( {}, data, { address: Object.assign( {}, data.address, { city: town.name, state: town.state, postcode: town.postcode || '' } ) } );
			},
		} ) );
	}

	select.addEventListener( 'change', render );

	// The picker waits for a text field to lose focus before it reports; saving must not wait.
	if ( box.closest( 'form' ) ) {
		box.closest( 'form' ).addEventListener( 'submit', function () {
			if ( current ) {
				field.value = readCookie( current );
			}
		} );
	}
} )();
