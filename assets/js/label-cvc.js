/**
 * CVC label box on the order screen. Data: nashenski_cvc (Admin\CVC::meta_box()).
 */
( function () {
	'use strict';

	var node = document.getElementById( 'nashenski--cvc-admin' );
	var data = window.nashenski_cvc;

	if ( ! node || ! data ) {
		return;
	}

	var L = window.nashenskiLabelBox;
	var i18n = data.i18n || {};
	var cookie = L.copy( data.cookie_data ) || {};
	var offices = Object.values( data.offices || {} );
	var streets = Object.values( data.streets || {} );
	var types = [ { id: 'office', label: i18n.office }, { id: 'address', label: i18n.address } ];
	var payers = [ { id: 'rec', label: i18n.buyer }, { id: 'sender', label: i18n.sender }, { id: 'fixed', label: i18n.fixedPrice } ];
	var tests = Object.values( data.testsOptions || {} );
	var label = L.copy( data.label ) || {};

	var state = {
		type: types.filter( function ( t ) { return t.id === cookie.type; } )[ 0 ] || '',
		office: '',
		street: '',
		streetNumber: '',
		other: '',
		paymentBy: payers[ 0 ],
		testOption: tests.filter( function ( t ) { return String( t.id ) === String( data.testOption ); } )[ 0 ] || '',
		declaredValue: label.os_value || '',
		shipmentStatus: data.shipmentStatus || '',
		actions: data.actions || [],
		message: '',
	};

	if ( 'office' === cookie.type ) {
		state.office = offices.filter( function ( o ) { return String( o.id ) === String( cookie.selectedOffice ); } )[ 0 ] || '';
	} else {
		state.streetNumber = cookie.streetNumber || '';
		state.other = cookie.other || '';
		state.street = streets.filter( function ( s ) {
			return cookie.selectedAddress && s.orig_key === cookie.selectedAddress.orig_key;
		} )[ 0 ] || '';
	}

	if ( cookie.fixed_price ) {
		state.paymentBy = payers[ 2 ];
	} else if ( 'sender' === label.payer || 'contract' === label.payer ) {
		state.paymentBy = payers[ 1 ];
	}

	// Newest event first, "status : person" like the old box.
	function statuses() {
		return state.actions.map( function ( a ) {
			return { time: a.status_date, details: a.status + ( a.by_person ? ' : ' + a.by_person : '' ) };
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

		L.post( 'nashenski_cvc_generate_label', {
			type: state.type,
			label_data: label,
			office: state.office,
			street: state.street,
			streetNumber: state.streetNumber,
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
			}

			box.loading( false );
			box.redraw();
		} ).catch( failed );
	}

	function updateActions() {
		box.loading( true );
		state.message = '';

		L.post( 'nashenski_cvc_update_actions', { orderId: data.orderId, shipmentStatus: state.shipmentStatus }, data.nonce ).then( function ( reply ) {
			var r = reply.data || {};

			if ( r.message ) {
				state.message = r.message;
			} else {
				state.actions = r.actions || [];
			}

			box.loading( false );
			box.redraw();
		} ).catch( failed );
	}

	function remove() {
		box.loading( true );
		state.message = '';

		L.post( 'nashenski_cvc_delete_label', { orderId: data.orderId, shipmentStatus: state.shipmentStatus }, data.nonce ).then( function () {
			state.shipmentStatus = '';
			state.actions = [];
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

	function addressFields() {
		var isStreet = 'streets' === state.street.type;
		var isQuarter = 'quarters' === state.street.type;
		var extra = { class: 'nashenski-multiselect--additional-field' };

		return L.el( 'div', {}, [
			L.field( i18n.streetQuarter, L.select( streets, state.street, choose( 'street' ), { placeholder: i18n.choose } ) ),
			L.el( 'p', { class: 'form-field form-field-wide' }, [
				isStreet ? L.input( state, 'streetNumber', Object.assign( { placeholder: i18n.streetNumber }, extra ) ) : null,
				isQuarter ? L.input( state, 'other', Object.assign( { placeholder: i18n.blVhEt }, extra ) ) : null,
			] ),
			isStreet ? L.el( 'p', { class: 'form-field form-field-wide' }, [ L.input( state, 'other', Object.assign( { placeholder: i18n.blVhEt }, extra ) ) ] ) : null,
		] );
	}

	function form() {
		var type = state.type ? state.type.id : '';

		return L.el( 'form', {}, [
			'office' === type ? L.field( i18n.office, L.select( offices, state.office, choose( 'office' ), { label: 'name_bg', placeholder: i18n.choose } ) ) : null,
			'address' === type ? addressFields() : null,
			label.total_parcels ? L.field( i18n.packCount, L.input( label, 'total_parcels', { type: 'number' } ) ) : null,
			'cod' === data.paymentType ? L.field( i18n.cd, L.input( label, 'cod_amount', { type: 'number' } ) ) : null,
			L.field( i18n.declaredValue, L.input( state, 'declaredValue', { type: 'number' } ) ),
			label.total_kgs ? L.field( i18n.weight, L.input( label, 'total_kgs', { type: 'number' } ) ) : null,
			L.field( i18n.description, L.input( label, 'description' ) ),
			L.field( i18n.deliveryPayedBy, L.select( payers, state.paymentBy, choose( 'paymentBy' ), { placeholder: i18n.choose } ) ),
			'fixed' === state.paymentBy.id ? L.field( i18n.fixedPrice, L.input( cookie, 'fixed_price', { type: 'number' } ) ) : null,
			L.field( i18n.reviewAndTest, L.select( tests, state.testOption, choose( 'testOption' ), { placeholder: i18n.choose } ) ),
			L.el( 'p', { class: 'form-field form-field-wide' }, [
				state.shipmentStatus
					? L.button( i18n.updateShipmentStatus, 'button-primary woocommerce-save-button', updateActions )
					: L.button( i18n.generateLabel, 'button-primary woocommerce-save-button', generate ),
			] ),
			L.el( 'p', { class: 'form-field form-field-wide' }, [
				state.shipmentStatus ? L.button( i18n.deleteLabel, 'button-secondary', remove ) : null,
				' ',
				L.copyButton( i18n.copyLabelData, function () { return JSON.stringify( label ); }, i18n.copyLabelDataMessage ),
			] ),
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
					state.shipmentStatus ? L.el( 'div', { class: 'generated-label' }, [
						L.el( 'h3', { text: i18n.label + ': ' + ( state.shipmentStatus.wb || '' ) } ),
						L.labelFrame( 'nashenski--cvc-label-print', state.shipmentStatus.pdf ),
					] ) : null,
				] ),
			] ),
			L.statusTable( i18n, statuses() ),
			L.el( 'div', { class: 'clear' } ),
		] );
	}

	var box = L.mount( node, 'cvc', view );
}() );
