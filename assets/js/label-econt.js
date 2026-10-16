/**
 * Econt label box on the order screen. Data: nashenski_econt (Admin\Econt::meta_box()).
 */
( function () {
	'use strict';

	var node = document.getElementById( 'nashenski--econt-admin' );
	var data = window.nashenski_econt;

	if ( ! node || ! data ) {
		return;
	}

	var L = window.nashenskiLabelBox;
	var i18n = data.i18n || {};
	var cookie = L.copy( data.cookie_data ) || {};
	var label = L.copy( data.label ) || {};
	var sendFromData = data.sendFrom || {};
	var sendFromOffices = Object.values( sendFromData.offices || {} );
	var sendFromAddresses = Object.values( sendFromData.addresses || {} );
	var offices = Object.values( data.offices || {} );
	var streets = Object.values( data.streets || {} );
	var shipmentTypes = Object.values( data.shipmentTypes || {} );
	var tests = Object.values( data.testsOptions || {} );
	var sendFromTypes = [ { id: 'office', label: i18n.office }, { id: 'address', label: i18n.address } ];
	var types = [
		{ id: 'office', label: i18n.office },
		{ id: 'automat', label: i18n.automat },
		{ id: 'address', label: i18n.address },
	];
	var payers = [ { id: 'buyer', label: i18n.buyer }, { id: 'sender', label: i18n.sender }, { id: 'fixed', label: i18n.fixedPrice } ];
	var sizes = [
		{ id: 'label_size_default', value: '', text: ' ' + i18n.default },
		{ id: 'label_size_10x9', value: '10x9', text: '10x9' },
		{ id: 'label_size_10x15', value: '10x15', text: ' 10x15' },
	];
	var detailKeys = [ 'blockNumber', 'entranceNumber', 'floorNumber', 'apartmentNumber' ];
	// Tracking event icons, as the old timeline showed them.
	var icons = {
		office: 'trace_office',
		prepared: 'trace_office',
		courier_direction: 'trace_line',
		courier: 'trace_courier',
		client: 'trace_ok',
		return: 'trace_return',
	};

	function find( list, test ) {
		return list.filter( test )[ 0 ] || '';
	}

	function text( value ) {
		return null === value || undefined === value ? '' : String( value ).trim();
	}

	// PHP sends an empty array for an empty object.
	if ( Array.isArray( label.services ) || ! label.services ) {
		label.services = {};
	}

	var state = {
		sendFromType: find( sendFromTypes, function ( t ) { return t.id === sendFromData.type; } ),
		sendFrom: '',
		type: find( types, function ( t ) { return t.id === cookie.type; } ),
		office: '',
		street: '',
		streetNumber: '',
		blockNumber: '',
		entranceNumber: '',
		floorNumber: '',
		apartmentNumber: '',
		other: '',
		shipmentType: find( shipmentTypes, function ( t ) {
			return String( t.id ).toLowerCase() === String( label.shipmentType || '' ).toLowerCase();
		} ),
		paymentBy: payers[ 1 ],
		testOption: find( tests, function ( t ) { return t.id === data.testOption; } ),
		declaredValue: label.services.declaredValueAmount || '',
		partialDelivery: false,
		useInvoiceNumber: !! data.useInvoiceNumber,
		size: '10x9',
		shipmentStatus: data.shipmentStatus || '',
		message: '',
	};

	if ( 'office' === state.sendFromType.id ) {
		state.sendFrom = find( sendFromOffices, function ( o ) {
			return String( o.id ).toLowerCase() === String( sendFromData.currentOffice || '' ).toLowerCase();
		} );
	} else {
		state.sendFrom = find( sendFromAddresses, function ( a ) { return a.id === sendFromData.currentAddress; } );
	}

	if ( 'office' === cookie.type || 'automat' === cookie.type ) {
		state.office = find( offices, function ( o ) { return o.code === cookie.selectedOffice; } );
	} else {
		state.streetNumber = cookie.streetNumber || '';
		state.blockNumber = cookie.blockNumber || '';
		state.entranceNumber = cookie.entranceNumber || '';
		state.floorNumber = cookie.floorNumber || '';
		state.apartmentNumber = cookie.apartmentNumber || '';
		state.other = cookie.other || '';
		state.street = find( streets, function ( s ) { return cookie.selectedAddress && s.id === cookie.selectedAddress.id; } );
	}

	if ( label.paymentReceiverAmount ) {
		state.paymentBy = payers[ 2 ];
	} else if ( label.paymentReceiverMethod ) {
		state.paymentBy = payers[ 0 ];
	}

	if ( state.useInvoiceNumber && ! label.services.invoiceNum ) {
		label.services.invoiceNum = data.invoiceNumber;
	}

	// ---- packs ----

	function newPack( from ) {
		return from ? L.copy( from ) : { width: '20', height: '20', length: '20', weight: 1 };
	}

	function syncPackCount() {
		label.packCount = label.packs.length;
	}

	// A label made before packs existed has one set of dimensions and a weight.
	function normalizePacks() {
		if ( ! Array.isArray( label.packs ) || ! label.packs.length ) {
			label.packs = [ {
				width: label.shipmentDimensionsW || '20',
				height: label.shipmentDimensionsH || '20',
				length: label.shipmentDimensionsL || '20',
				weight: label.weight || 1,
			} ];
		}

		syncPackCount();
	}

	function addPack() {
		label.packs.push( newPack( label.packs[ 0 ] ) );
		syncPackCount();
		box.redraw();
	}

	function removePack( index ) {
		if ( label.packs.length > 1 ) {
			label.packs.splice( index, 1 );
			syncPackCount();
			box.redraw();
		}
	}

	normalizePacks();

	// ---- requests ----

	function failed() {
		state.message = ( window.nashenskiLabelBoxText || {} ).error || 'Error';
		box.loading( false );
		box.redraw();
	}

	function addressError() {
		var street = state.street;

		if ( 'address' !== state.type.id || ! street ) {
			return '';
		}

		if ( 'streets' === street.type && ! text( state.streetNumber ) ) {
			return i18n.streetNumberRequired;
		}

		// A label from before the detail fields kept the whole line in "other".
		var legacyOther = ! Object.prototype.hasOwnProperty.call( cookie, 'blockNumber' ) && text( state.other );

		if ( 'quarters' === street.type && ! legacyOther && ! detailKeys.some( function ( key ) { return text( state[ key ] ); } ) ) {
			return i18n.quarterDetailRequired;
		}

		return '';
	}

	function generate() {
		var error = addressError();

		if ( error ) {
			state.message = error;
			box.redraw();
			return;
		}

		normalizePacks();
		[ 'shipmentDimensionsW', 'shipmentDimensionsL', 'shipmentDimensionsH', 'weight' ].forEach( function ( key ) {
			delete label[ key ];
		} );
		label.shipmentType = state.shipmentType ? state.shipmentType.id : label.shipmentType;
		box.loading( true );
		state.message = '';

		L.post( 'nashenski_econt_generate_label', {
			type: state.type,
			send_from_type: state.sendFromType.id,
			send_from: state.sendFrom.id,
			label_data: label,
			shipmentType: state.shipmentType,
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
			partialDelivery: state.partialDelivery,
			cookie_data: cookie,
			orderId: data.orderId,
			declaredValue: state.declaredValue,
		}, data.nonce ).then( function ( reply ) {
			var r = reply.data || {};

			L.reloadItems();

			if ( r.message ) {
				state.message = r.message;
			} else if ( r.shipmentStatus ) {
				state.shipmentStatus = L.copy( r.shipmentStatus );
				label = L.copy( r.label ) || label;
				normalizePacks();
			}

			box.loading( false );
			box.redraw();
		} ).catch( failed );
	}

	function updateStatus() {
		box.loading( true );
		state.message = '';

		L.post( 'nashenski_econt_update_shipment_status', { orderId: data.orderId, shipmentStatus: state.shipmentStatus }, data.nonce ).then( function ( reply ) {
			var r = reply.data || {};

			if ( r.message ) {
				state.message = r.message;
			} else if ( r.shipmentStatus ) {
				state.shipmentStatus.label = L.copy( r.shipmentStatus );
			}

			box.loading( false );
			box.redraw();
		} ).catch( failed );
	}

	function remove() {
		box.loading( true );
		state.message = '';

		L.post( 'nashenski_econt_delete_label', { orderId: data.orderId, shipmentStatus: state.shipmentStatus }, data.nonce ).then( function () {
			state.shipmentStatus = '';
			box.loading( false );
			box.redraw();
		} ).catch( failed );
	}

	// ---- form ----

	function choose( key ) {
		return function ( value ) {
			state[ key ] = value;
			box.redraw();
		};
	}

	function chooseSendFromType( value ) {
		// The chosen office or address belongs to the other list.
		if ( value.id !== state.sendFromType.id ) {
			state.sendFrom = '';
		}

		state.sendFromType = value;
		box.redraw();
	}

	// A different street or quarter starts with empty details.
	function chooseStreet( value ) {
		if ( state.street && state.street.id && value.id !== state.street.id ) {
			state.streetNumber = state.other = '';
			detailKeys.forEach( function ( key ) { state[ key ] = ''; } );
		}

		state.street = value;
		box.redraw();
	}

	function recipientOffices() {
		if ( 'automat' === state.type.id ) {
			return offices.filter( function ( o ) { return true === o.isAPS; } );
		}

		return 'office' === state.type.id ? offices.filter( function ( o ) { return false === o.isAPS; } ) : offices;
	}

	function detail( key, id, title ) {
		var item = L.input( state, key, { class: 'input-text', placeholder: title } );

		item.id = 'nashenski-econt-admin-' + id;

		return L.el( 'p', { class: 'form-field' }, [ L.el( 'label', { for: item.id, text: title + ':' } ), item ] );
	}

	function addressFields() {
		var isStreet = 'streets' === state.street.type;
		var details = L.el( 'div', {
			class: 'nashenski-econt-address-details clear' + ( 'quarters' === state.street.type ? ' nashenski-econt-address-details--quarter' : '' ),
		}, [
			isStreet ? detail( 'streetNumber', 'street-number', i18n.streetNumber ) : null,
			detail( 'blockNumber', 'block-number', i18n.blockNumber ),
			detail( 'entranceNumber', 'entrance-number', i18n.entranceNumber ),
			detail( 'floorNumber', 'floor-number', i18n.floorNumber ),
			detail( 'apartmentNumber', 'apartment-number', i18n.apartmentNumber ),
		] );

		return L.el( 'div', {}, [
			L.field( i18n.streetQuarter, L.select( streets, state.street, chooseStreet, { placeholder: i18n.choose } ) ),
			state.street.type ? details : null,
		] );
	}

	function numberInput( obj, key, step ) {
		var item = L.input( obj, key, { type: 'number' } );

		item.setAttribute( 'step', step );

		return item;
	}

	function cell( title, control, style ) {
		return L.el( 'p', { class: 'form-field form-field--1-of-3', style: style }, [ L.el( 'label', { text: title + ':' } ), control ] );
	}

	function packFields( pack, index ) {
		return L.el( 'fieldset', {}, [
			L.el( 'legend', { text: i18n.pack + ' ' + ( index + 1 ) } ),
			cell( i18n.length + ' (cm)', numberInput( pack, 'length', '0.1' ), 'clear: none' ),
			cell( i18n.width + ' (cm)', numberInput( pack, 'width', '0.1' ), 'clear: none' ),
			cell( i18n.height + ' (cm)', numberInput( pack, 'height', '0.1' ), 'float: right; clear: none' ),
			L.field( i18n.weight, numberInput( pack, 'weight', '0.001' ) ),
			L.el( 'p', { class: 'form-field form-field-wide' }, [
				L.el( 'button', {
					type: 'button',
					class: 'button-secondary',
					text: i18n.removePack,
					disabled: 1 === label.packs.length,
					on: { click: function () { removePack( index ); } },
				} ),
			] ),
		] );
	}

	function partialDeliveryField() {
		var check = L.el( 'input', {
			type: 'checkbox',
			class: 'checkbox',
			checked: state.partialDelivery,
			on: { change: function ( event ) { state.partialDelivery = event.target.checked; } },
		} );

		return L.el( 'p', { class: 'form-field form-field-wide form-field--checkbox' }, [
			L.el( 'label', { text: i18n.partialDelivery + ' :' } ),
			check,
		] );
	}

	function buttons() {
		var primary = 'button-primary woocommerce-save-button';

		return state.shipmentStatus ? L.el( 'p', { class: 'form-field form-field-wide' }, [
			L.button( i18n.updateLabel, primary, generate ),
			' ',
			L.button( i18n.updateShipmentStatus, primary, updateStatus ),
		] ) : L.el( 'p', { class: 'form-field form-field-wide' }, [ L.button( i18n.generateLabel, primary, generate ) ] );
	}

	function form() {
		var sent = !! state.shipmentStatus;
		var type = state.type ? state.type.id : '';

		return L.el( 'form', {}, [
			sent ? null : L.field( i18n.sendFrom, L.select( sendFromTypes, state.sendFromType, chooseSendFromType, { placeholder: i18n.choose } ) ),
			sent || 'office' !== state.sendFromType.id ? null : L.field( i18n.office, L.select( sendFromOffices, state.sendFrom, choose( 'sendFrom' ), { placeholder: i18n.choose } ) ),
			sent || 'address' !== state.sendFromType.id ? null : L.field( i18n.address, L.select( sendFromAddresses, state.sendFrom, choose( 'sendFrom' ), { placeholder: i18n.choose } ) ),
			sent ? null : L.field( i18n.deliveryType, L.select( types, state.type, choose( 'type' ), { placeholder: i18n.choose } ) ),
			'office' === type || 'automat' === type ? L.field( 'automat' === type ? i18n.automat : i18n.office, L.select( recipientOffices(), state.office, choose( 'office' ), { label: 'name', placeholder: i18n.choose } ) ) : null,
			'address' === type ? addressFields() : null,
			L.field( i18n.shipmentType, L.select( shipmentTypes, state.shipmentType, choose( 'shipmentType' ), { placeholder: i18n.choose } ) ),
			label.packs.map( packFields ),
			L.el( 'p', { class: 'form-field form-field-wide' }, [ L.button( i18n.addPack, 'button-secondary', addPack ) ] ),
			'cod' === data.paymentType ? L.field( i18n.cd, L.input( label.services, 'cdAmount', { type: 'number' } ) ) : null,
			L.field( i18n.declaredValue, L.input( state, 'declaredValue', { type: 'number' } ) ),
			L.field( i18n.description, L.input( label, 'shipmentDescription' ) ),
			state.useInvoiceNumber ? L.field( i18n.invoiceNum, L.input( label.services, 'invoiceNum' ) ) : null,
			L.field( i18n.deliveryPayedBy, L.select( payers, state.paymentBy, choose( 'paymentBy' ), { placeholder: i18n.choose } ) ),
			L.field( i18n.reviewAndTest, L.select( tests, state.testOption, choose( 'testOption' ), { placeholder: i18n.choose } ) ),
			state.useInvoiceNumber || 'no' === state.testOption.id ? null : partialDeliveryField(),
			'fixed' === state.paymentBy.id ? L.field( i18n.fixedPrice, L.input( label, 'paymentReceiverAmount', { type: 'number' } ) ) : null,
			buttons(),
			L.el( 'p', { class: 'form-field form-field-wide' }, [
				sent ? L.button( i18n.deleteLabel, 'button-secondary', remove ) : null,
				' ',
				L.copyButton( i18n.copyLabelData, function () { return JSON.stringify( label ); }, i18n.copyLabelDataMessage ),
			] ),
		] );
	}

	// ---- label and timeline ----

	function radio( size ) {
		return L.el( 'span', { class: 'nashenski--radio' }, [
			L.el( 'input', { id: size.id, type: 'radio', name: 'label_size', value: size.value, checked: size.value === state.size } ),
			L.el( 'label', { for: size.id, text: size.text } ),
		] );
	}

	function pdfUrl() {
		return String( state.shipmentStatus.label.pdfURL || '' ).replace( /^https?:/, '' ) + '&label=' + state.size;
	}

	function pad( number ) {
		return ( '0' + number ).slice( -2 );
	}

	// Newest event first, "d/m/yyyy h:mm:ss" like the old timeline.
	function events() {
		var list = ( state.shipmentStatus && state.shipmentStatus.label.trackingEvents ) || [];

		return list.map( function ( event ) {
			var d = new Date( event.time );

			return {
				time: d.getDate() + '/' + ( d.getMonth() + 1 ) + '/' + d.getFullYear() + ' ' + d.getHours() + ':' + pad( d.getMinutes() ) + ':' + pad( d.getSeconds() ),
				icon: icons[ event.destinationType ] ? '//ee.econt.com/images/icons/' + icons[ event.destinationType ] + '.png' : '',
				details: event.destinationDetails,
			};
		} ).reverse();
	}

	function timeline() {
		var rows = events();

		if ( ! rows.length ) {
			return null;
		}

		return L.el( 'div', { class: 'woocommerce_order_status' }, [
			L.el( 'h3', { text: i18n.shipmentStatus } ),
			L.el( 'table', {}, [
				L.el( 'thead', {}, [ L.el( 'tr', {}, [ L.el( 'th', { text: i18n.time } ), L.el( 'th', { text: i18n.event } ), L.el( 'th', { text: i18n.details } ) ] ) ] ),
				L.el( 'tbody', {}, rows.map( function ( row ) {
					return L.el( 'tr', {}, [
						L.el( 'th', { text: row.time } ),
						L.el( 'th', {}, [ row.icon ? L.el( 'img', { src: row.icon, alt: '' } ) : null ] ),
						L.el( 'th', { text: String( row.details || '' ) } ),
					] );
				} ) ),
			] ),
		] );
	}

	function generated() {
		return L.el( 'div', { class: 'generated-label' }, [
			L.el( 'h3', { text: i18n.label + ': ' + state.shipmentStatus.label.shipmentNumber } ),
			L.el( 'br' ),
			L.el( 'div', {}, sizes.map( radio ) ),
			L.labelFrame( 'nashenski--econt-label-print', pdfUrl() ),
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
			timeline(),
			L.el( 'div', { class: 'clear' } ),
		] );
	}

	// The size radios sit in the box; the old box listened on the whole page.
	document.addEventListener( 'change', function ( event ) {
		if ( event.target && 'label_size' === event.target.name ) {
			state.size = event.target.value;
			box.redraw();
		}
	} );

	var box = L.mount( node, 'econt', view );
}() );
