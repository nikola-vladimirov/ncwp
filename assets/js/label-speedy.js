/**
 * Speedy label box on the order screen. Data: nashenski_speedy (Admin\Speedy::meta_box()).
 */
( function () {
	'use strict';

	var node = document.getElementById( 'nashenski--speedy-admin' );
	var data = window.nashenski_speedy;

	if ( ! node || ! data ) {
		return;
	}

	var L = window.nashenskiLabelBox;
	var i18n = data.i18n || {};
	var ajaxUrl = ( window.woocommerce_admin && window.woocommerce_admin.ajax_url ) || window.ajaxurl || '';
	var cookie = L.copy( data.cookie_data ) || {};
	var sendFromData = data.sendFrom || {};
	var offices = Object.values( data.offices || {} );
	var tests = Object.values( data.testsOptions || {} );
	var sendFromTypes = [ { id: 'office', label: i18n.office }, { id: 'address', label: i18n.address } ];
	var types = [ { id: 'office', label: i18n.office }, { id: 'automat', label: i18n.automat }, { id: 'address', label: i18n.address } ];
	var payers = [ { id: 'RECIPIENT', label: i18n.buyer }, { id: 'SENDER', label: i18n.sender }, { id: 'fixed', label: i18n.fixedPrice } ];
	var sizes = [
		{ id: 'label_size_default', value: 'A6', text: 'A6' },
		{ id: 'label_size_A4_4xA6', value: 'A4_4xA6', text: 'A4_4xA6' },
		{ id: 'label_size_a4', value: 'A4', text: 'A4' },
		{ id: 'label_size_a4_same', value: 'A4&awbsc=ON_SAME_PAGE', text: i18n.a4WithCopy },
		{ id: 'label_size_a4_single', value: 'A4&awbsc=ON_SINGLE_PAGE', text: i18n.a4OnSingle },
	];
	var maxParcels = data.maxParcels || 10;
	var label = L.copy( data.label ) || {};
	var sendFromOffices = Object.values( sendFromData.offices || {} );
	var sendFromAddresses = Object.values( sendFromData.addresses || {} );
	var streetHolder = null;

	function find( list, test ) {
		return list.filter( test )[ 0 ] || '';
	}

	var state = {
		sendFromType: find( sendFromTypes, function ( t ) { return t.id == sendFromData.type; } ), // eslint-disable-line eqeqeq
		sendFrom: '',
		type: find( types, function ( t ) { return t.id == cookie.type; } ), // eslint-disable-line eqeqeq
		office: '',
		street: '',
		streets: Object.values( data.streets || {} ),
		streetNumber: '',
		blockNumber: '',
		entranceNumber: '',
		floorNumber: '',
		apartmentNumber: '',
		other: '',
		paymentBy: payers[ 0 ],
		testOption: find( tests, function ( t ) { return String( t.id ) === String( data.testOption ); } ),
		declaredValue: '',
		shipmentStatus: data.shipmentStatus || '',
		operations: data.operations || [],
		size: 'A6',
		message: '',
	};

	if ( 'office' === state.sendFromType.id ) {
		state.sendFrom = find( sendFromOffices, function ( o ) {
			return String( o.id ).toLowerCase() === String( sendFromData.currentOffice || '' ).toLowerCase();
		} );
	} else {
		state.sendFrom = find( sendFromAddresses, function ( a ) { return a.id == sendFromData.currentAddress; } ); // eslint-disable-line eqeqeq
	}

	if ( 'office' === cookie.type || 'automat' === cookie.type ) {
		state.office = find( offices, function ( o ) { return o.id == cookie.selectedOffice; } ); // eslint-disable-line eqeqeq
	} else {
		[ 'streetNumber', 'blockNumber', 'entranceNumber', 'floorNumber', 'apartmentNumber', 'other' ].forEach( function ( key ) {
			state[ key ] = cookie[ key ] || '';
		} );
		state.street = find( state.streets, function ( s ) {
			return cookie.selectedAddress && s.orig_key == cookie.selectedAddress.orig_key; // eslint-disable-line eqeqeq
		} );
	}

	if ( cookie.fixed_price ) {
		state.paymentBy = payers[ 2 ];
	} else if ( label.payment && 'SENDER' === label.payment.courierServicePayer ) {
		state.paymentBy = payers[ 1 ];
	}

	var extra = ( label.service || {} ).additionalServices;

	if ( extra && undefined !== extra.declaredValue ) {
		state.declaredValue = extra.declaredValue.amount;
	}

	// ---- parcels ----

	function number( value ) {
		var n = parseFloat( value );

		return isFinite( n ) ? n : 0;
	}

	function weight( value ) {
		var n = parseFloat( value );

		return isFinite( n ) ? ( Math.round( 1000 * n ) / 1000 ).toFixed( 3 ) : '';
	}

	function parcels() {
		label.content = label.content || {};

		return Array.isArray( label.content.parcels ) ? label.content.parcels : [];
	}

	function ensureSize( parcel ) {
		parcel.size = parcel.size || {};
		[ 'depth', 'width', 'height' ].forEach( function ( key ) {
			if ( undefined === parcel.size[ key ] ) {
				parcel.size[ key ] = '';
			}
		} );
	}

	function volumetric( parcel ) {
		var s = parcel.size;

		if ( ! s || ! [ 'depth', 'width', 'height' ].every( function ( k ) { return number( s[ k ] ) > 0; } ) ) {
			return '';
		}

		return weight( ( number( s.depth ) * number( s.width ) * number( s.height ) ) / 5000 );
	}

	function chargeable( parcel ) {
		return volumetric( parcel ) || weight( parcel.weight );
	}

	parcels().forEach( ensureSize );

	function addParcel() {
		var list = parcels();
		var parcel;

		if ( list.length >= maxParcels ) {
			return;
		}

		if ( list.length ) {
			parcel = L.copy( list[ 0 ] );
			delete parcel.id;
		} else {
			parcel = { seqNo: 1, weight: 1, size: { depth: '', width: '', height: '' } };
		}

		ensureSize( parcel );
		parcel.seqNo = list.length + 1;
		list.push( parcel );
		label.content.parcels = list;
		box.redraw();
	}

	function removeParcel( index ) {
		var list = parcels();

		if ( list.length <= 1 ) {
			return;
		}

		list.splice( index, 1 );
		list.forEach( function ( parcel, i ) {
			parcel.seqNo = i + 1;
		} );
		box.redraw();
	}

	// ---- server calls ----

	function failed() {
		state.message = ( window.nashenskiLabelBoxText || {} ).error || 'Error';
		box.loading( false );
		box.redraw();
	}

	function generate() {
		var details = [ state.streetNumber, state.blockNumber, state.entranceNumber, state.floorNumber, state.apartmentNumber ];

		if ( 'address' === state.type.id && state.streets.length && ! details.some( Boolean ) ) {
			state.message = i18n.addressDetailRequired;
			box.redraw();
			return;
		}

		box.loading( true );
		state.message = '';

		L.post( 'nashenski_speedy_generate_label', {
			type: state.type,
			send_from_type: state.sendFromType.id,
			send_from: state.sendFrom.id,
			label_data: label,
			office: state.office,
			street: state.street,
			streetNumber: state.streetNumber,
			blockNumber: state.blockNumber,
			entranceNumber: state.entranceNumber,
			floorNumber: state.floorNumber,
			apartmentNumber: state.apartmentNumber,
			other: state.other,
			paymentBy: state.paymentBy,
			testOption: state.testOption,
			cookie_data: cookie,
			orderId: data.orderId,
			declaredValue: state.declaredValue,
		}, data.nonce ).then( function ( reply ) {
			var r = reply.data || {};

			L.reloadItems();

			if ( r.message ) {
				state.message = r.message;
			} else {
				state.shipmentStatus = L.copy( r.shipmentStatus );
				label = L.copy( r.label ) || label;
				parcels().forEach( ensureSize );
			}

			box.loading( false );
			box.redraw();
		} ).catch( failed );
	}

	function updateStatus() {
		box.loading( true );
		state.message = '';

		L.post( 'nashenski_speedy_update_shipment_status', { orderId: data.orderId, shipmentStatus: state.shipmentStatus }, data.nonce ).then( function ( reply ) {
			var r = reply.data || {};

			if ( r.message ) {
				state.message = r.message;
			} else {
				state.operations = r.operations || [];
			}

			box.loading( false );
			box.redraw();
		} ).catch( failed );
	}

	function remove() {
		box.loading( true );
		state.message = '';

		L.post( 'nashenski_speedy_delete_label', { orderId: data.orderId, shipmentStatus: state.shipmentStatus }, data.nonce ).then( function () {
			state.shipmentStatus = '';
			state.operations = [];
			box.loading( false );
			box.redraw();
		} ).catch( failed );
	}

	// ---- address search (as you type, debounced) ----

	var searchTimer = null;

	// The street list is redrawn alone, so the search box keeps its focus.
	function fillStreets() {
		if ( ! streetHolder ) {
			return;
		}

		var list = state.streets.slice();

		// A chosen street stays in the list when a search no longer returns it.
		if ( state.street && ! list.some( function ( s ) { return s.orig_key === state.street.orig_key && s.label === state.street.label; } ) ) {
			list.unshift( state.street );
		}

		var select = L.select( list, state.street, function ( value ) {
			state.street = value;
			box.redraw();
		}, { placeholder: i18n.choose, trackBy: 'orig_key' } );

		streetHolder.textContent = '';
		streetHolder.appendChild( select );
		select.nashenskiEnhance();
	}

	function search( query ) {
		clearTimeout( searchTimer );

		if ( ! query ) {
			return;
		}

		searchTimer = setTimeout( function () {
			L.post( 'nashenski_speedy_search_address', {
				query: query,
				country: cookie.country,
				state: cookie.state,
				city: cookie.city,
			}, data.nonce ).then( function ( reply ) {
				var r = reply.data || {};

				if ( r.cities ) {
					state.streets = Object.values( r.cities );
				} else if ( r.streets ) {
					state.streets = Object.values( r.streets );
				}

				fillStreets();
			} ).catch( function () {} );
		}, 300 );
	}

	// ---- view ----

	function choose( key ) {
		return function ( value ) {
			state[ key ] = value;
			box.redraw();
		};
	}

	function cell( text, control, cls, style ) {
		return L.el( 'p', { class: cls, style: style }, [ text ? L.el( 'label', { text: text + ':' } ) : null, control ] );
	}

	function numberInput( obj, key, step, onInput ) {
		var input = L.input( obj, key, { type: 'number', onInput: onInput } );

		if ( step ) {
			input.setAttribute( 'step', step );
		}

		return input;
	}

	function disabledInput( value ) {
		return L.el( 'input', { type: 'number', step: '0.001', disabled: true, value: value } );
	}

	function parcelFields( parcel, index, count ) {
		var third = 'form-field form-field--1-of-3';
		var volume = disabledInput( volumetric( parcel ) );
		var charge = disabledInput( chargeable( parcel ) );

		function recalc() {
			volume.value = volumetric( parcel );
			charge.value = chargeable( parcel );
		}

		return L.el( 'fieldset', {}, [
			L.el( 'legend', { text: i18n.pack + ' ' + ( index + 1 ) } ),
			cell( i18n.length, numberInput( parcel.size, 'depth', '0.1', recalc ), third, 'clear: none' ),
			cell( i18n.width, numberInput( parcel.size, 'width', '0.1', recalc ), third, 'clear: none' ),
			cell( i18n.height, numberInput( parcel.size, 'height', '0.1', recalc ), third, 'clear: none' ),
			undefined !== parcel.weight ? cell( i18n.weight, numberInput( parcel, 'weight', '0.001', recalc ), third, 'clear: none' ) : null,
			cell( i18n.volumetricWeight, volume, third, 'clear: none' ),
			cell( i18n.chargeableWeight, charge, third + ' form-field--highlight', 'float: right; clear: none' ),
			L.el( 'p', { class: 'form-field form-field-wide' }, [
				L.el( 'button', {
					type: 'button',
					class: 'button-secondary',
					text: i18n.removePack,
					disabled: 1 === count,
					on: { click: function () { removeParcel( index ); } },
				} ),
			] ),
		] );
	}

	function detailInput( key, id, text ) {
		var input = L.input( state, key, { class: 'input-text', placeholder: text } );

		input.id = 'nashenski-speedy-admin-' + id;

		return L.el( 'p', { class: 'form-field' }, [
			L.el( 'label', { for: 'nashenski-speedy-admin-' + id, text: text + ':' } ),
			input,
		] );
	}

	function addressFields() {
		var searchBox = L.el( 'input', {
			type: 'text',
			placeholder: i18n.streetQuarter,
			on: { input: function ( event ) { search( event.target.value ); } },
		} );
		var free = L.input( cookie, 'mysticQuarter', { class: 'input-text', placeholder: i18n.mysticQuarter } );

		free.id = 'nashenski-speedy-admin-address-line';
		streetHolder = L.el( 'span' );
		fillStreets();

		return L.el( 'div', {}, [
			L.field( i18n.streetQuarter, L.el( 'span', {}, [ searchBox, streetHolder ] ) ),
			state.streets.length ? L.el( 'div', { class: 'nashenski-speedy-address-details clear' }, [
				detailInput( 'streetNumber', 'street-number', i18n.streetNumber ),
				detailInput( 'blockNumber', 'block-number', i18n.blockNumber ),
				detailInput( 'entranceNumber', 'entrance-number', i18n.entranceNumber ),
				detailInput( 'floorNumber', 'floor-number', i18n.floorNumber ),
				detailInput( 'apartmentNumber', 'apartment-number', i18n.apartmentNumber ),
			] ) : L.el( 'p', { class: 'form-field form-field-wide' }, [
				L.el( 'label', { for: 'nashenski-speedy-admin-address-line', text: i18n.mysticQuarter + ':' } ),
				free,
			] ),
		] );
	}

	function recipientOffices() {
		if ( 'automat' === state.type.id ) {
			return offices.filter( function ( o ) { return 'APT' === o.type; } );
		}

		return 'office' === state.type.id ? offices.filter( function ( o ) { return 'APT' !== o.type; } ) : offices;
	}

	function form() {
		var done = !! state.shipmentStatus;
		var list = parcels();
		var services = ( label.service || {} ).additionalServices;
		var typeId = state.type.id;
		var content = label.content || {};
		var buttons;

		if ( done ) {
			buttons = [
				L.button( i18n.updateLabel, 'button-primary woocommerce-save-button', generate ),
				' ',
				L.button( i18n.updateShipmentStatus, 'button-primary woocommerce-save-button', updateStatus ),
			];
		} else {
			buttons = [ L.button( i18n.generateLabel, 'button-primary woocommerce-save-button', generate ) ];
		}

		return L.el( 'form', {}, [
			done ? null : L.field( i18n.sendFrom, L.select( sendFromTypes, state.sendFromType, choose( 'sendFromType' ), { placeholder: i18n.choose } ) ),
			done || 'office' !== state.sendFromType.id ? null : L.field( i18n.office, L.select( sendFromOffices, state.sendFrom, choose( 'sendFrom' ), { placeholder: i18n.choose } ) ),
			done || 'address' !== state.sendFromType.id ? null : L.field( i18n.address, L.select( sendFromAddresses, state.sendFrom, choose( 'sendFrom' ), { placeholder: i18n.choose } ) ),
			done ? null : L.field( i18n.deliveryType, L.select( types, state.type, choose( 'type' ), { placeholder: i18n.choose } ) ),
			'office' === typeId || 'automat' === typeId ? L.field( 'automat' === typeId ? i18n.automat : i18n.office, L.select( recipientOffices(), state.office, choose( 'office' ), { label: 'name', placeholder: i18n.choose } ) ) : null,
			'address' === typeId ? addressFields() : null,
			list.map( function ( parcel, index ) { return parcelFields( parcel, index, list.length ); } ),
			L.el( 'p', { class: 'form-field form-field-wide' }, [
				L.el( 'button', {
					type: 'button',
					class: 'button-secondary',
					text: i18n.addPack,
					disabled: list.length >= maxParcels,
					on: { click: addParcel },
				} ),
			] ),
			services && undefined !== services.cod && 'cod' === data.paymentType ? L.field( i18n.cd, numberInput( services.cod, 'amount' ) ) : null,
			L.field( i18n.declaredValue, numberInput( state, 'declaredValue' ) ),
			L.field( i18n.description, L.input( content, 'contents', { class: 'input-text' } ) ),
			cell( 'ref 1', L.input( label, 'ref1', { class: 'input-text' } ), 'form-field', 'clear: none' ),
			cell( 'ref 2', L.input( label, 'ref2', { class: 'input-text' } ), 'form-field', 'float: right; clear: none' ),
			L.field( i18n.deliveryPayedBy, L.select( payers, state.paymentBy, choose( 'paymentBy' ), { placeholder: i18n.choose } ) ),
			'fixed' === state.paymentBy.id ? L.field( i18n.fixedPrice, numberInput( cookie, 'fixed_price' ) ) : null,
			L.field( i18n.reviewAndTest, L.select( tests, state.testOption, choose( 'testOption' ), { placeholder: i18n.choose } ) ),
			L.el( 'p', { class: 'form-field form-field-wide' }, buttons ),
			L.el( 'p', { class: 'form-field form-field-wide' }, [
				done ? L.button( i18n.deleteLabel, 'button-secondary', remove ) : null,
				' ',
				L.copyButton( i18n.copyLabelData, function () { return JSON.stringify( label ); }, i18n.copyLabelDataMessage ),
			] ),
		] );
	}

	// Newest event first.
	function statuses() {
		if ( ! Array.isArray( state.operations ) ) {
			return [];
		}

		return state.operations.map( function ( op ) {
			var details = op.description;

			if ( undefined !== op.comment ) {
				details += ' - ' + op.comment;
			}

			return { time: new Date( op.dateTime ).toLocaleString(), details: details };
		} ).reverse();
	}

	function printUrl() {
		var ids = ( ( state.shipmentStatus || {} ).parcels || [] ).map( function ( p ) {
			return encodeURIComponent( p.id );
		} );

		if ( ! ids.length ) {
			return '';
		}

		return ajaxUrl + '?cache-buster=' + Math.random() + '&action=nashenski_speedy_print_labels&nonce=' + encodeURIComponent( data.nonce || '' ) + '&parcels=' + ids.join( '|' ) + '&size=' + state.size;
	}

	function sizeRadios() {
		return L.el( 'div', {}, sizes.map( function ( s ) {
			return L.el( 'span', { class: 'nashenski--radio' }, [
				L.el( 'input', {
					id: s.id,
					type: 'radio',
					name: 'label_size',
					value: s.value,
					checked: s.value === state.size,
					on: {
						change: function () {
							state.size = s.value;
							document.getElementById( 'nashenski--speedy-label-print' ).src = printUrl();
						},
					},
				} ),
				L.el( 'label', { for: s.id, text: s.text } ),
			] );
		} ) );
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
				L.el( 'div', { class: 'order_data_column order_data_column--half' }, [
					state.shipmentStatus ? L.el( 'div', { class: 'generated-label' }, [
						L.el( 'h3', { text: i18n.label + ': ' + ( state.shipmentStatus.id || '' ) } ),
						sizeRadios(),
						L.labelFrame( 'nashenski--speedy-label-print', printUrl() ),
					] ) : null,
				] ),
			] ),
			L.statusTable( i18n, statuses() ),
			L.el( 'div', { class: 'clear' } ),
		] );
	}

	var box = L.mount( node, 'speedy', view );
}() );
