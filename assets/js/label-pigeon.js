/**
 * Pigeon Express label box on the order screen. Data: nashenski_pigeon (Admin\Pigeon::meta_box()).
 */
( function () {
	'use strict';

	var node = document.getElementById( 'nashenski--pigeon-admin' );
	var data = window.nashenski_pigeon;

	if ( ! node || ! data ) {
		return;
	}

	var L = window.nashenskiLabelBox;
	var i18n = data.i18n || {};
	var cookie = L.copy( data.cookie_data ) || {};
	var sendFrom = data.sendFrom || {};
	var sendFromOffices = Object.values( sendFrom.offices || {} );
	var offices = Object.values( data.offices || {} );
	var lockers = Object.values( data.lockers || {} );
	var types = [ { id: 'office', label: i18n.office }, { id: 'address', label: i18n.address }, { id: 'locker', label: i18n.locker } ];
	var sendFromTypes = [ { id: 'office', label: i18n.office }, { id: 'address', label: i18n.address } ];
	var payers = [ { id: 'receiver', label: i18n.buyer }, { id: 'sender', label: i18n.sender }, { id: 'fixed', label: i18n.fixedPrice } ];
	var label = L.copy( data.label ) || {};
	var ajaxUrl = ( window.woocommerce_admin && window.woocommerce_admin.ajax_url ) || window.ajaxurl || '';

	if ( Array.isArray( label ) ) {
		label = {};
	}

	function find( list, test ) {
		return list.filter( test )[ 0 ] || '';
	}

	var state = {
		type: find( types, function ( t ) { return t.id === cookie.type; } ) || {},
		sendFromType: find( sendFromTypes, function ( t ) { return t.id === sendFrom.type; } ),
		sendFrom: find( sendFromOffices, function ( o ) {
			return String( o.id ).toLowerCase() === String( sendFrom.currentOffice || '' ).toLowerCase();
		} ),
		sendFromAddress: sendFrom.currentAddress || '',
		office: '',
		locker: '',
		street: '',
		streets: Object.values( data.streets || {} ),
		other: '',
		paymentBy: payers[ 0 ],
		testOption: data.testOption,
		returnAtMyExpense: '',
		declaredValue: ( label.service_codes || {} ).declared_value || '',
		shipmentStatus: data.shipmentStatus || '',
		operations: data.operations || [],
		size: '',
		frameUrl: '',
		message: '',
	};

	if ( 'locker' === cookie.type ) {
		state.locker = find( lockers, function ( l ) { return l.id === 'lockerID-' + cookie.selectedLocker; } );
	} else if ( 'office' === cookie.type ) {
		state.office = find( offices, function ( o ) { return o.id === 'officeID-' + cookie.selectedOffice; } );
	} else if ( 'address' === cookie.type ) {
		if ( label.delivery_address && undefined !== label.delivery_address.additional_info ) {
			state.other = label.delivery_address.additional_info;
		} else if ( cookie.streetNumber ) {
			state.other = cookie.streetNumber;
		} else if ( undefined !== cookie.other ) {
			state.other = cookie.other;
		}

		state.street = find( state.streets, function ( s ) {
			return cookie.selectedAddress && s.orig_key === cookie.selectedAddress.orig_key;
		} );
	}

	if ( cookie.fixed_price ) {
		state.paymentBy = payers[ 2 ];
	} else if ( 'sender' === label.who_pays ) {
		state.paymentBy = payers[ 1 ];
	}

	if ( undefined !== label.return_at_my_expense ) {
		state.returnAtMyExpense = label.return_at_my_expense;
	}

	// The print link: the label size is only added when a size radio is chosen. A new random value reloads the PDF.
	function printUrl() {
		var url = ajaxUrl + '?cache-buster=' + Math.random() + '&action=nashenski_pigeon_print_labels&nonce=' +
			encodeURIComponent( data.nonce || '' ) + '&order-id=' + encodeURIComponent( data.orderId );

		return state.size ? url + '&size=' + encodeURIComponent( state.size ) : url;
	}

	if ( state.shipmentStatus ) {
		state.frameUrl = printUrl();
	}

	// Newest event first.
	function statuses() {
		if ( ! Array.isArray( state.operations ) ) {
			return [];
		}

		return state.operations.map( function ( o ) {
			return { time: new Date( o.created_at ).toLocaleString(), details: o.status };
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

		L.post( 'nashenski_pigeon_generate_label', {
			type: state.type,
			send_from_type: ( state.sendFromType || {} ).id,
			send_from: ( state.sendFrom || {} ).id,
			send_from_address: state.sendFromAddress,
			label_data: label,
			office: state.office,
			locker: state.locker,
			street: state.street,
			other: state.other,
			paymentBy: state.paymentBy,
			testOption: state.testOption,
			returnAtMyExpense: state.returnAtMyExpense,
			cookie_data: cookie,
			orderId: data.orderId,
			declaredValue: state.declaredValue,
		}, data.nonce ).then( function ( reply ) {
			var r = reply.data || {};

			L.reloadItems();

			if ( r.message ) {
				state.message = r.message;
			} else {
				state.shipmentStatus = L.copy( r.shipmentStatus ) || '';
				label = L.copy( r.label ) || label;
				state.frameUrl = state.shipmentStatus ? printUrl() : '';
			}

			box.loading( false );
			box.redraw();
		} ).catch( failed );
	}

	function updateStatus() {
		box.loading( true );
		state.message = '';

		L.post( 'nashenski_pigeon_update_shipment_status', { orderId: data.orderId, shipmentStatus: state.shipmentStatus }, data.nonce ).then( function ( reply ) {
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

		L.post( 'nashenski_pigeon_delete_label', { orderId: data.orderId, shipmentStatus: state.shipmentStatus }, data.nonce ).then( function () {
			state.shipmentStatus = '';
			state.operations = [];
			state.frameUrl = '';
			box.loading( false );
			box.redraw();
		} ).catch( failed );
	}

	function choose( key ) {
		return function ( value ) {
			state[ key ] = value;
			box.redraw();
		};
	}

	function addPack() {
		var packs = label.packages || [];
		var pack;

		if ( packs.length ) {
			pack = L.copy( packs[ 0 ] );
			delete pack.id;
		} else {
			pack = { seqNo: 1, weight: 1, length: '', width: '', height: '' };
		}

		packs.push( pack );
		label.packages = packs;
		box.redraw();
	}

	function removePack( index ) {
		var packs = label.packages;

		if ( ! Array.isArray( packs ) || packs.length <= 1 ) {
			return;
		}

		packs.splice( index, 1 );
		packs.forEach( function ( pack, i ) {
			pack.seqNo = i + 1;
		} );
		box.redraw();
	}

	function numberInput( obj, key, step ) {
		var input = L.input( obj, key, { type: 'number' } );

		input.setAttribute( 'step', step );

		return input;
	}

	function packField( text, obj, key, attrs ) {
		return L.el( 'p', Object.assign( { class: 'form-field form-field--1-of-3', style: 'clear:none' }, attrs ), [
			L.el( 'label', { text: text + ' (cm):' } ),
			numberInput( obj, key, '0.1' ),
		] );
	}

	function packs() {
		var list = label.packages || [];

		return list.map( function ( pack, index ) {
			return L.el( 'fieldset', {}, [
				L.el( 'legend', { text: i18n.pack + ' ' + ( index + 1 ) } ),
				packField( i18n.length, pack, 'length' ),
				packField( i18n.width, pack, 'width' ),
				packField( i18n.height, pack, 'height', { style: 'float:right;clear:none' } ),
				undefined !== pack.weight ? L.field( i18n.weight, numberInput( pack, 'weight', '0.001' ) ) : null,
				L.el( 'p', { class: 'form-field form-field-wide' }, [
					L.el( 'button', {
						type: 'button',
						class: 'button-secondary',
						text: i18n.removePack,
						disabled: 1 === list.length,
						on: { click: function () { removePack( index ); } },
					} ),
				] ),
			] );
		} );
	}

	// A checkbox that keeps a true/false value in obj[ key ].
	function checkbox( text, checked, onChange, style ) {
		return L.el( 'p', { class: 'form-field form-field--checkbox', style: style }, [
			L.el( 'label', { text: text + ':' } ),
			L.el( 'input', { type: 'checkbox', class: 'checkbox', checked: checked, on: { change: function ( event ) { onChange( event.target.checked ); } } } ),
		] );
	}

	// Street search: typing asks the server (debounced) and only the list below is replaced, so the field keeps focus.
	var streetHolder = null;
	var searchTimer = null;
	var searchQuery = '';

	function streetSelect() {
		var list = state.streets.slice();

		if ( state.street && ! find( list, function ( s ) { return s.id === state.street.id; } ) ) {
			list.unshift( state.street );
		}

		return L.select( list, state.street, choose( 'street' ), { placeholder: i18n.choose } );
	}

	function search( query ) {
		clearTimeout( searchTimer );
		searchQuery = query;

		if ( ! query ) {
			return;
		}

		searchTimer = setTimeout( function () {
			L.post( 'nashenski_pigeon_search_address', { query: query, state: cookie.state, city: cookie.city }, data.nonce ).then( function ( reply ) {
				var r = reply.data || {};
				var found = r.cities || r.streets;

				if ( query !== searchQuery || ! found || ! streetHolder ) {
					return;
				}

				state.streets = Object.values( found );
				streetHolder.textContent = '';

				var select = streetSelect();

				streetHolder.appendChild( select );
				select.nashenskiEnhance();
			} ).catch( function () {} );
		}, 300 );
	}

	function addressFields() {
		streetHolder = L.el( 'span', {}, [ streetSelect() ] );

		return L.el( 'div', {}, [
			L.field( i18n.streetQuarter, L.el( 'span', {}, [
				L.el( 'input', { type: 'text', class: 'nashenski-street-search', placeholder: i18n.streetQuarter, on: { input: function ( event ) { search( event.target.value.trim() ); } } } ),
				streetHolder,
			] ) ),
			L.el( 'p', { class: 'form-field form-field-wide' }, [
				L.input( state, 'other', { placeholder: i18n.other, class: 'nashenski-multiselect--additional-field' } ),
			] ),
		] );
	}

	function form() {
		var type = state.type.id;
		var sent = !! state.shipmentStatus;
		var codes = label.service_codes;

		return L.el( 'form', {}, [
			sent ? null : L.field( i18n.sendFrom, L.select( sendFromTypes, state.sendFromType, choose( 'sendFromType' ), { placeholder: i18n.choose } ) ),
			! sent && state.sendFromType && 'office' === state.sendFromType.id ? L.field( i18n.office, L.select( sendFromOffices, state.sendFrom, choose( 'sendFrom' ), { placeholder: i18n.choose } ) ) : null,
			! sent && state.sendFromType && 'address' === state.sendFromType.id ? L.field( i18n.address, L.input( state, 'sendFromAddress' ) ) : null,
			sent ? null : L.field( i18n.deliveryType, L.select( types, state.type, choose( 'type' ), { placeholder: i18n.choose } ) ),
			'office' === type ? L.field( i18n.office, L.select( offices, state.office, choose( 'office' ), { placeholder: i18n.choose } ) ) : null,
			'locker' === type ? L.field( i18n.locker, L.select( lockers, state.locker, choose( 'locker' ), { placeholder: i18n.choose } ) ) : null,
			'address' === type ? addressFields() : null,
			L.el( 'div', {}, packs() ),
			L.el( 'p', { class: 'form-field form-field-wide' }, [ L.button( i18n.addPack, 'button-secondary', addPack ) ] ),
			codes && undefined !== codes.cod_amount && 'cod' === data.paymentType ? L.field( i18n.cd, L.input( codes, 'cod_amount', { type: 'number' } ) ) : null,
			L.field( i18n.declaredValue, L.input( state, 'declaredValue', { type: 'number' } ) ),
			L.field( i18n.deliveryPayedBy, L.select( payers, state.paymentBy, choose( 'paymentBy' ), { placeholder: i18n.choose } ) ),
			'fixed' === state.paymentBy.id ? L.field( i18n.fixedPrice, L.input( cookie, 'fixed_price', { type: 'number' } ) ) : null,
			L.field( i18n.note, L.input( label, 'note' ) ),
			// The old box showed "no" as checked until it was clicked; the checkbox now follows the yes/no value.
			checkbox( i18n.reviewAndTest, 'yes' === state.testOption || true === state.testOption, function ( on ) { state.testOption = on ? 'yes' : 'no'; }, 'clear:none !important;float:left !important' ),
			checkbox( i18n.returnAtMyExpense, !! state.returnAtMyExpense, function ( on ) { state.returnAtMyExpense = on; }, 'float:right !important;clear:none !important' ),
			L.el( 'p', { class: 'form-field form-field-wide' }, sent ? [
				L.button( i18n.deleteLabel, 'button-secondary', remove ),
				' ',
				L.button( i18n.updateShipmentStatus, 'button-primary woocommerce-save-button', updateStatus ),
			] : [
				L.button( i18n.generateLabel, 'button-primary woocommerce-save-button', generate ),
			] ),
			L.el( 'p', { class: 'form-field form-field-wide' }, [
				L.copyButton( i18n.copyLabelData, function () { return JSON.stringify( label ); }, i18n.copyLabelDataMessage ),
			] ),
		] );
	}

	function radio( id, value, text ) {
		return L.el( 'span', { class: 'nashenski--radio' }, [
			L.el( 'input', { id: id, type: 'radio', name: 'label_size', value: value, checked: state.size === value } ),
			L.el( 'label', { for: id, text: value ? text : ' ' + text } ),
		] );
	}

	function generated() {
		var info = ( state.shipmentStatus || {} ).data || {};
		var tracking = ( info.packages || [] ).map( function ( p ) { return p.tracking_number; } ).join( ', ' );

		return L.el( 'div', { class: 'generated-label' }, [
			L.el( 'h3', { text: i18n.referenceNumber + ': ' + ( info.reference_number || '' ) } ),
			L.el( 'p', { text: i18n.trackingCodes + ': ' + tracking } ),
			L.el( 'div', {}, [ radio( 'pigeon_label_size_default', '', i18n.default ), radio( 'pigeon_label_size_a4', 'a4', 'A4' ) ] ),
			L.labelFrame( 'nashenski--pigeon-label-print', state.frameUrl ),
		] );
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
					state.shipmentStatus ? generated() : null,
				] ),
			] ),
			L.statusTable( i18n, statuses() ),
			L.el( 'div', { class: 'clear' } ),
		] );
	}

	// The size radios sit in the box; the old box listened on the whole page.
	document.addEventListener( 'change', function ( event ) {
		if ( event.target && 'label_size' === event.target.name ) {
			state.size = event.target.value;
			state.frameUrl = state.shipmentStatus ? printUrl() : '';
			box.redraw();
		}
	} );

	var box = L.mount( node, 'pigeon', view );
}() );
