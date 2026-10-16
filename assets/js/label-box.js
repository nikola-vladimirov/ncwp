/**
 * Shared parts of the courier label boxes on the order screen (label-<courier>.js).
 *
 * The boxes talk to the same AJAX handlers as the old compiled bundles, with the same fields:
 * nested values are sent the way the old code sent them (qs style, label_data[rec][name]=...),
 * so the PHP side reads them unchanged.
 */
window.nashenskiLabelBox = ( function ( $ ) {
	'use strict';

	var ajaxUrl = ( window.woocommerce_admin && window.woocommerce_admin.ajax_url ) || window.ajaxurl || '';

	// { a: { b: 1 }, c: [ 'x' ] } as a[b]=1&c[0]=x. null is sent empty, undefined is left out.
	function encode( data, params, prefix ) {
		params = params || new URLSearchParams();

		Object.keys( data ).forEach( function ( key ) {
			var value = data[ key ];
			var name = prefix ? prefix + '[' + key + ']' : key;

			if ( undefined === value ) {
				return;
			}

			if ( null !== value && 'object' === typeof value ) {
				encode( value, params, name );
			} else {
				params.append( name, null === value ? '' : String( value ) );
			}
		} );

		return params;
	}

	/**
	 * Posts an action and resolves with the JSON reply. Rejects on a network or server error.
	 */
	function post( action, data, nonce ) {
		var body = encode( Object.assign( {}, data, { action: action, nonce: nonce } ) );

		return fetch( ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } ).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( response.status + ' ' + response.statusText );
			}

			return response.json();
		} );
	}

	/**
	 * A DOM element. attrs: plain attributes, plus 'class', 'text' (textContent) and 'on' ({ event: handler }).
	 * Children are elements, strings (as text) or falsy (skipped).
	 */
	function el( tag, attrs, children ) {
		var node = document.createElement( tag );

		Object.keys( attrs || {} ).forEach( function ( key ) {
			var value = attrs[ key ];

			if ( null === value || undefined === value || false === value ) {
				return;
			}

			if ( 'class' === key ) {
				node.className = value;
			} else if ( 'text' === key ) {
				node.textContent = value;
			} else if ( 'on' === key ) {
				Object.keys( value ).forEach( function ( event ) {
					node.addEventListener( event, value[ event ] );
				} );
			} else if ( 'value' === key ) {
				node.value = value;
			} else {
				node.setAttribute( key, true === value ? '' : value );
			}
		} );

		( children || [] ).forEach( function ( child ) {
			if ( child ) {
				node.appendChild( 'string' === typeof child ? document.createTextNode( child ) : child );
			}
		} );

		return node;
	}

	// A labelled row, like the old boxes: <p class="form-field form-field-wide"><label>Text:</label> ...</p>
	function field( label, control ) {
		return el( 'p', { class: 'form-field form-field-wide' }, [
			label ? el( 'label', { text: label + ':' } ) : null,
			control,
		] );
	}

	/**
	 * A text or number input bound to obj[ key ]. Typing only updates the value (no redraw, so focus stays).
	 */
	function input( obj, key, opts ) {
		opts = opts || {};

		return el( 'input', {
			type: opts.type || 'text',
			class: opts.class,
			placeholder: opts.placeholder,
			value: null === obj[ key ] || undefined === obj[ key ] ? '' : obj[ key ],
			on: {
				input: function ( event ) {
					obj[ key ] = event.target.value;

					if ( opts.onInput ) {
						opts.onInput( event.target.value );
					}
				},
			},
		} );
	}

	/**
	 * A searchable select over a list of option objects. selected is the chosen object (or '').
	 * opts.label: the key shown (default 'label'), opts.trackBy: the key compared (default 'id').
	 * onChange receives the chosen object. Uses selectWoo when WooCommerce has loaded it.
	 */
	function select( options, selected, onChange, opts ) {
		opts = opts || {};

		var label = opts.label || 'label';
		var trackBy = opts.trackBy || 'id';
		var list = Array.isArray( options ) ? options : Object.values( options || {} );
		var current = selected && 'object' === typeof selected ? selected[ trackBy ] : undefined;
		var node = el( 'select', { class: 'nashenski-label-select' }, [
			el( 'option', { value: '', text: opts.placeholder || '' } ),
		] );

		list.forEach( function ( option, index ) {
			var item = el( 'option', { value: String( index ), text: String( option[ label ] ) } );

			if ( undefined !== current && String( option[ trackBy ] ) === String( current ) ) {
				item.selected = true;
			}

			node.appendChild( item );
		} );

		function changed() {
			var chosen = list[ parseInt( node.value, 10 ) ];

			// The old boxes did not allow an empty choice once one was made.
			if ( chosen ) {
				onChange( chosen );
			}
		}

		node.addEventListener( 'change', changed );

		// selectWoo needs the element in the page, so it is applied after the box is drawn.
		node.nashenskiEnhance = function () {
			if ( $.fn.selectWoo && list.length > 8 ) {
				$( node ).selectWoo( { width: '100%', placeholder: opts.placeholder || '' } ).on( 'select2:select', changed );
			}
		};

		return node;
	}

	function button( text, cls, onClick ) {
		return el( 'button', {
			type: 'button',
			class: cls,
			text: text,
			on: {
				click: function ( event ) {
					event.preventDefault();
					onClick( event );
				},
			},
		} );
	}

	// Copies the label data as JSON, as "Copy label data" did.
	function copyButton( text, getJSON, message ) {
		return el( 'a', {
			href: '#',
			class: 'button-secondary',
			text: text,
			on: {
				click: function ( event ) {
					event.preventDefault();

					var done = function () {
						window.alert( message );
					};

					if ( navigator.clipboard && window.isSecureContext ) {
						navigator.clipboard.writeText( getJSON() ).then( done );
					} else {
						var area = el( 'textarea', { value: getJSON() } );

						document.body.appendChild( area );
						area.select();
						document.execCommand( 'copy' );
						area.remove();
						done();
					}
				},
			},
		} );
	}

	function errorNotice( message ) {
		return message ? el( 'div', { class: 'notice notice-error notice-alt' }, [ el( 'p', { text: message } ) ] ) : null;
	}

	// The shipment history: rows of { time, details }.
	function statusTable( i18n, rows ) {
		if ( ! rows || ! rows.length ) {
			return null;
		}

		return el( 'div', { class: 'woocommerce_order_status' }, [
			el( 'h3', { text: i18n.shipmentStatus } ),
			el( 'table', {}, [
				el( 'thead', {}, [ el( 'tr', {}, [ el( 'th', { text: i18n.time } ), el( 'th', { text: i18n.details } ) ] ) ] ),
				el( 'tbody', {}, rows.map( function ( row ) {
					return el( 'tr', {}, [ el( 'th', { text: String( row.time || '' ) } ), el( 'th', { text: String( row.details || '' ) } ) ] );
				} ) ),
			] ),
		] );
	}

	// The PDF of the label, protocol-relative like the old boxes.
	function labelFrame( id, url ) {
		return el( 'iframe', { id: id, src: String( url || '' ).replace( /^https?:/, '' ) } );
	}

	/**
	 * Draws a box: view() returns the content, which replaces the mount's content.
	 * Returns redraw(), to call after a choice or a reply changes what is shown.
	 */
	function mount( node, courier, view ) {
		var wrap = el( 'div', { class: 'panel-wrap woocommerce woocommerce--' + courier + ' ajax-container' } );

		node.appendChild( wrap );

		function redraw() {
			wrap.textContent = '';
			wrap.appendChild( view() );
			wrap.querySelectorAll( 'select.nashenski-label-select' ).forEach( function ( item ) {
				item.nashenskiEnhance();
			} );
		}

		redraw();

		return {
			redraw: redraw,
			loading: function ( on ) {
				wrap.setAttribute( 'data-loading', on ? 'true' : 'false' );
			},
		};
	}

	// WooCommerce redraws the order items (new shipping price, totals) after a label.
	function reloadItems() {
		$( '#woocommerce-order-items' ).trigger( 'wc_order_items_reload' );
	}

	// A deep copy of localized data, so the box can change it freely.
	function copy( value ) {
		return undefined === value ? value : JSON.parse( JSON.stringify( value ) );
	}

	return {
		encode: encode,
		post: post,
		el: el,
		field: field,
		input: input,
		select: select,
		button: button,
		copyButton: copyButton,
		errorNotice: errorNotice,
		statusTable: statusTable,
		labelFrame: labelFrame,
		mount: mount,
		reloadItems: reloadItems,
		copy: copy,
	};
}( jQuery ) );
