/**
 * BOX NOW label box on the order screen. Data: nashenski_boxnow (Admin\BoxNow::meta_box()).
 */
( function () {
	'use strict';

	var node = document.getElementById( 'nashenski--boxnow-admin' );
	var data = window.nashenski_boxnow;

	if ( ! node || ! data ) {
		return;
	}

	var L = window.nashenskiLabelBox;
	var i18n = data.i18n || {};
	var cookie = L.copy( data.cookie_data ) || {};
	var origins = Object.values( data.origins || {} );
	var destinations = Object.values( data.destinations || {} );
	var boxSizes = [ { id: '1', label: i18n.smallBox }, { id: '2', label: i18n.mediumBox }, { id: '3', label: i18n.largeBox } ];
	var label = L.copy( data.label ) || {};
	var ajaxUrl = ( window.woocommerce_admin && window.woocommerce_admin.ajax_url ) || window.ajaxurl || '';

	label.items = label.items || [];

	var state = {
		origin: origins.filter( function ( o ) { return String( o.id ) === String( data.origin ); } )[ 0 ] || '',
		destination: destinations.filter( function ( d ) { return String( d.id ) === String( cookie.selectedApm ); } )[ 0 ] || '',
		declaredValue: undefined !== label.amountToBeCollected ? label.amountToBeCollected : '',
		allowReturn: undefined !== data.allowReturn ? !! data.allowReturn : false,
		shipmentStatus: L.copy( data.shipmentStatus ) || '',
		otherLabels: L.copy( data.othersLabels ) || [],
		operations: data.operations || [],
		message: '',
	};

	// Oldest event last in the data, newest first on screen, "description - comment" like the old box.
	function statuses() {
		return ( state.operations || [] ).map( function ( o ) {
			return {
				time: new Date( o.dateTime ).toLocaleString(),
				details: o.description + ( undefined !== o.comment ? ' - ' + o.comment : '' ),
			};
		} ).reverse();
	}

	function failed() {
		state.message = ( window.nashenskiLabelBoxText || {} ).error || 'Error';
		box.loading( false );
		box.redraw();
	}

	function generate() {
		box.loading( true );
		state.message = '';

		L.post( 'nashenski_boxnow_generate_label', {
			orderId: data.orderId,
			origin: state.origin,
			destination: state.destination,
			declaredValue: state.declaredValue,
			items: label.items,
			allowReturn: state.allowReturn,
		}, data.nonce ).then( function ( reply ) {
			var r = reply.data || {};

			L.reloadItems();

			if ( r.message ) {
				state.message = r.message;
			} else {
				state.shipmentStatus = L.copy( r.shipmentStatus ) || '';
				label = L.copy( r.label ) || label;
				label.items = label.items || [];

				if ( r.otherLabels ) {
					state.otherLabels = L.copy( r.otherLabels );
				}
			}

			box.loading( false );
			box.redraw();
		} ).catch( failed );
	}

	function remove() {
		box.loading( true );
		state.message = '';

		L.post( 'nashenski_boxnow_delete_label', { orderId: data.orderId, shipmentStatus: state.shipmentStatus }, data.nonce ).then( function () {
			state.shipmentStatus = '';
			state.otherLabels = []; // the server deletes these too
			state.operations = [];
			box.loading( false );
			box.redraw();
		} ).catch( failed );
	}

	function addParcel() {
		var item = label.items.length
			? L.copy( label.items[ 0 ] )
			: { name: '', value: 1, weight: 1, compartmentSize: '2' };

		delete item.id;
		label.items.push( item );
		box.redraw();
	}

	function removeParcel( index ) {
		if ( label.items.length > 1 ) {
			label.items.splice( index, 1 );
			box.redraw();
		}
	}

	function choose( key ) {
		return function ( value ) {
			state[ key ] = value;
			box.redraw();
		};
	}

	// An input with a step, like the old number fields.
	function number( obj, key, step ) {
		var input = L.input( obj, key, { type: 'number' } );

		input.setAttribute( 'step', step );

		return input;
	}

	// The PDF of one parcel, built the way the old box built it.
	function printUrl( parcel ) {
		return ajaxUrl + '?cache-buster=' + Math.random() + '&action=nashenski_boxnow_print_label&nonce=' +
			encodeURIComponent( data.nonce || '' ) + '&parcel=' + encodeURIComponent( parcel );
	}

	function parcelLabel( parcel ) {
		return L.el( 'div', {}, [
			L.el( 'h3', { text: i18n.label + ': ' + parcel } ),
			L.labelFrame( 'nashenski--boxnow-label-print', printUrl( parcel ) ),
		] );
	}

	function labels() {
		var own = ( state.shipmentStatus.parcels || [] ).map( function ( p ) { return parcelLabel( p.id ); } );
		var others = ( state.otherLabels || [] ).map( function ( o ) {
			return parcelLabel( o.shipmentStatus.parcels[ 0 ].id );
		} );

		return own.concat( others );
	}

	function pack( item, index ) {
		var size = boxSizes.filter( function ( s ) { return String( s.id ) === String( item.compartmentSize ); } )[ 0 ] || '';
		var removeButton = L.button( i18n.removePack, 'button-secondary', function () { removeParcel( index ); } );

		if ( 1 === label.items.length ) {
			removeButton.setAttribute( 'disabled', '' );
		}

		return L.el( 'fieldset', {}, [
			L.el( 'legend', { text: i18n.pack + ' ' + ( index + 1 ) } ),
			L.field( i18n.boxSize, L.select( boxSizes, size, function ( chosen ) {
				item.compartmentSize = chosen.id;
				box.redraw();
			}, { placeholder: i18n.choose } ) ),
			undefined !== item.name ? L.field( i18n.name, L.input( item, 'name' ) ) : null,
			undefined !== item.weight ? L.field( i18n.weight, number( item, 'weight', '0.001' ) ) : null,
			L.el( 'p', { class: 'form-field form-field-wide' }, [ removeButton ] ),
		] );
	}

	function form() {
		var checkbox = L.el( 'input', {
			type: 'checkbox',
			checked: state.allowReturn,
			on: { change: function ( event ) { state.allowReturn = event.target.checked; } },
		} );

		return L.el( 'form', { on: { submit: function ( event ) { event.preventDefault(); } } }, [
			L.el( 'h4', { text: i18n.sendFrom } ),
			L.field( i18n.warehouseApm, L.select( origins, state.origin, choose( 'origin' ), { label: 'name', placeholder: i18n.choose } ) ),
			L.el( 'h4', { text: i18n.sendTo } ),
			L.field( i18n.apm, L.select( destinations, state.destination, choose( 'destination' ), { label: 'name', placeholder: i18n.choose } ) ),
			label.items.map( pack ),
			L.el( 'p', { class: 'form-field form-field-wide' }, [ L.button( i18n.addPack, 'button-secondary', addParcel ) ] ),
			L.field( i18n.total, number( state, 'declaredValue', '0.01' ) ),
			L.el( 'p', { class: 'form-field form-field--checkbox' }, [ L.el( 'label', { text: i18n.allowReturn + ':' } ), checkbox ] ),
			L.el( 'p', { class: 'form-field form-field-wide' }, [
				state.shipmentStatus
					? L.button( i18n.deleteLabel, 'button-secondary', remove )
					: L.button( i18n.generateLabel, 'button-primary woocommerce-save-button', generate ),
			] ),
		].reduce( function ( list, child ) { return list.concat( child ); }, [] ) );
	}

	function view() {
		return L.el( 'div', { class: 'panel woocommerce-order-data', id: 'order_data' }, [
			L.el( 'div', { class: 'order_data_column_container' }, [
				L.el( 'div', { class: 'order_data_column order_data_column--half' }, [
					L.el( 'h3', { text: i18n.labelData } ),
					form(),
					L.el( 'div', { class: 'clear' } ),
					L.errorNotice( state.message ),
				] ),
				L.el( 'div', { class: 'order_data_column order_data_column--half' }, state.shipmentStatus ? [
					L.el( 'div', { class: 'generated-label' }, labels() ),
				] : [] ),
			] ),
			L.statusTable( i18n, statuses() ),
		] );
	}

	var box = L.mount( node, 'boxnow', function () {
		var wrap = L.el( 'div', {}, [ view(), L.el( 'div', { class: 'clear' } ) ] );

		return wrap;
	} );
}() );
