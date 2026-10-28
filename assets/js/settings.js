/**
 * Nashenski Commerce: WooCommerce > Settings > Nashenski.
 * Price table rows (add / remove) and the export forms. No build step.
 */
( function () {
	'use strict';

	var i18n = window.nashenskiSettings || {};

	/* Price table rows ---------------------------------------------------- */

	function renumber( table ) {
		var name = table.getAttribute( 'data-name' );
		var columns = table.getAttribute( 'data-columns' ).split( ',' );

		Array.prototype.forEach.call( table.tBodies[ 0 ].rows, function ( row, r ) {
			var inputs = row.querySelectorAll( 'input' );

			Array.prototype.forEach.call( inputs, function ( input, c ) {
				input.name = name + '[' + r + '][' + columns[ c ] + ']';
			} );
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var add = event.target.closest( '.nashenski-rows__add' );
		var remove = event.target.closest( '.nashenski-rows__delete' );

		if ( add ) {
			var table = add.closest( 'td' ).querySelector( '.nashenski-rows' );
			var body = table.tBodies[ 0 ];
			var row = body.rows[ body.rows.length - 1 ].cloneNode( true );

			Array.prototype.forEach.call( row.querySelectorAll( 'input' ), function ( input ) {
				input.value = '';
			} );

			body.appendChild( row );
			renumber( table );
			row.querySelector( 'input' ).focus();
		}

		if ( remove ) {
			var rows = remove.closest( 'tbody' );
			var tableEl = remove.closest( 'table' );

			if ( rows.rows.length > 1 ) {
				remove.closest( 'tr' ).remove();
			} else {
				Array.prototype.forEach.call( rows.querySelectorAll( 'input' ), function ( input ) {
					input.value = '';
				} );
			}

			renumber( tableEl );
		}
	} );

	/* Checkout field order (rows are sent in table order) ------------------- */

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.nashenski-layout__up, .nashenski-layout__down, .nashenski-layout__remove, .nashenski-layout__add' );

		if ( ! button ) {
			return;
		}

		var row = button.closest( 'tr' );

		if ( button.classList.contains( 'nashenski-layout__add' ) ) {
			var cell = button.closest( 'td' );
			var html = cell.querySelector( '.nashenski-layout__template' ).innerHTML.replace( /__uid__/g, 'f' + Date.now().toString( 36 ) );

			cell.querySelector( '.nashenski-layout tbody' ).insertAdjacentHTML( 'beforeend', html );
			cell.querySelector( '.nashenski-layout tbody tr:last-child input[type="text"]' ).focus();
		} else if ( button.classList.contains( 'nashenski-layout__remove' ) ) {
			row.remove();
		} else if ( button.classList.contains( 'nashenski-layout__up' ) && row.previousElementSibling ) {
			row.parentNode.insertBefore( row, row.previousElementSibling );
			button.focus();
		} else if ( button.classList.contains( 'nashenski-layout__down' ) && row.nextElementSibling ) {
			row.parentNode.insertBefore( row.nextElementSibling, row );
			button.focus();
		}
	} );

	// The choices box only shows for a list.
	document.addEventListener( 'change', function ( event ) {
		if ( event.target.matches( '.nashenski-layout__row--custom select[name$="[type]"]' ) ) {
			event.target.closest( 'tr' ).classList.toggle( 'is-select', 'select' === event.target.value );
		}
	} );

	/* Checkout field conditions (one set, all conditions must hold) ---------- */

	var whenTemplate = document.querySelector( '.nashenski-when__template' );

	function each( list, callback ) {
		Array.prototype.forEach.call( list, callback );
	}

	// The same plain rule as Conditions::clean_text() on the server.
	function cleanText( text ) {
		return String( text ).replace( /[ \t\r\n]+/g, ' ' ).replace( /^ | $/g, '' );
	}

	function readWhen( cell ) {
		var raw = cell.querySelector( '.nashenski-when__json' ).value;
		var data = null;

		try {
			data = raw ? JSON.parse( raw ) : null;
		} catch ( e ) {
			data = null;
		}

		return data && Array.isArray( data.sets ) ? data : { hide: false, sets: [] };
	}

	function triggers( row ) {
		var list = [];

		each( row.parentNode.children, function ( tr ) {
			var keyInput = tr.querySelector( 'input[name$="[key]"]' );
			// A new row gets this key when the table is saved (sanitize_layout_field()), so it can already be a trigger.
			var key = keyInput.value || 'custom_' + keyInput.name.replace( /^.*\[([^\]]+)\]\[key\]$/, '$1' ).toLowerCase().replace( /[^a-z0-9_-]/g, '' );
			var label = tr.querySelector( 'input[name$="[label]"]' );
			var strong = tr.querySelector( 'strong' );
			var type = tr.querySelector( 'select[name$="[type]"]' );
			var options = tr.querySelector( 'textarea[name$="[options]"]' );
			var rule = tr.querySelector( 'select[name$="[rule]"]' );

			if ( tr === row || ! key ) {
				return;
			}

			list.push( {
				key: key,
				label: strong ? strong.textContent : ( label && label.value ) || key,
				type: type ? type.value : 'text',
				choices: options && type && 'select' === type.value ? options.value.split( '\n' ).map( cleanText ).filter( Boolean ) : [],
				hidden: !! rule && 'hidden' === rule.value
			} );
		} );

		return list;
	}

	function findTrigger( list, key ) {
		return list.filter( function ( item ) {
			return item.key === key;
		} )[ 0 ];
	}

	function summary( cell ) {
		var data = readWhen( cell );
		var set = data.sets[ 0 ] || [];
		var out = cell.querySelector( '.nashenski-when__summary' );
		var strings = whenTemplate ? whenTemplate.dataset : {};
		var list = triggers( cell.closest( 'tr' ) );
		var text = '';

		if ( set.length > 1 ) {
			text = data.hide ? strings.hiddenAll : strings.shownAll;
		} else if ( 1 === set.length ) {
			var trigger = findTrigger( list, set[ 0 ].field );
			var op = whenTemplate.content.querySelector( '.nashenski-when__op option[value="' + set[ 0 ].operator + '"]' );
			var value = set[ 0 ].value;

			if ( trigger && 'checkbox' === trigger.type ) {
				value = '1' === value ? strings.yes : strings.no;
			}

			text = ( data.hide ? strings.hidden : strings.shown ).replace( '%s', ( trigger ? trigger.label : set[ 0 ].field ) + ' ' + ( op ? op.textContent : '' ) + ( /^(empty|not_empty)$/.test( set[ 0 ].operator ) || '' === value ? '' : ' ' + value ) );
		}

		out.textContent = text;

		// An open panel drives the dropdown itself (collect()); a closed one follows the saved rule.
		if ( cell.querySelector( '.nashenski-when__panel' ).hidden ) {
			cell.querySelector( '.nashenski-when__rule' ).value = set.length ? ( data.hide ? 'hide' : 'show' ) : '';
			cell.querySelector( '.nashenski-when__open' ).hidden = ! set.length;
		}
	}

	function refreshSummaries() {
		each( document.querySelectorAll( '.nashenski-layout .nashenski-when' ), summary );
	}

	// Writes the first condition set from the panel; other alternatives of an imported rule stay as they were.
	function collect( cell ) {
		var panel = cell.querySelector( '.nashenski-when__panel' );
		var data = readWhen( cell );
		var set = [];

		each( panel.querySelectorAll( '.nashenski-when__cond' ), function ( node ) {
			var field = node.querySelector( '.nashenski-when__field' ).value;
			var operator = node.querySelector( '.nashenski-when__op' ).value;
			var input = node.querySelector( '.nashenski-when__value input, .nashenski-when__value select' );

			if ( field ) {
				set.push( { field: field, operator: operator, value: input && ! /^(empty|not_empty)$/.test( operator ) ? input.value : '' } );
			}
		} );

		data.hide = 'hide' === panel.querySelector( '.nashenski-when__mode' ).value;
		data.sets = [ set ].concat( panel.rest || [] ).filter( function ( one ) {
			return one.length;
		} );
		cell.querySelector( '.nashenski-when__json' ).value = data.sets.length ? JSON.stringify( data ) : '';
		cell.querySelector( '.nashenski-when__rule' ).value = data.hide ? 'hide' : 'show';
		summary( cell );
	}

	// The value control follows the trigger: a list for a list, ticked or not for a checkbox, text otherwise.
	function valueControl( node, trigger, value ) {
		var box = node.querySelector( '.nashenski-when__value' );
		var operator = node.querySelector( '.nashenski-when__op' ).value;
		var strings = whenTemplate.dataset;
		var el;

		box.textContent = '';

		if ( /^(empty|not_empty)$/.test( operator ) ) {
			return;
		}

		function choose( pairs ) {
			el = document.createElement( 'select' );
			pairs.forEach( function ( pair ) {
				el.appendChild( new Option( pair[ 1 ], pair[ 0 ] ) );
			} );
			el.value = value;
		}

		if ( ! node.querySelector( '.nashenski-when__field' ).value ) {
			// No field chosen yet: a locked list, so the value is never an empty box with nothing to pick.
			choose( [ [ '', strings.pick ] ] );
			el.disabled = true;
		} else if ( trigger && 'checkbox' === trigger.type ) {
			value = '1' === value ? '1' : '';
			choose( [ [ '1', strings.yes ], [ '', strings.no ] ] );
		} else if ( trigger && trigger.choices.length ) {
			choose( trigger.choices.concat( value && -1 === trigger.choices.indexOf( value ) ? [ value ] : [] ).map( function ( choice ) {
				return [ choice, choice ];
			} ) );
			el.value = value || trigger.choices[ 0 ];
		} else {
			el = document.createElement( 'input' );
			el.type = 'text';
			el.value = value;
		}

		box.appendChild( el );
	}

	function condNote( node, trigger, key ) {
		var strings = whenTemplate.dataset;
		var note = node.querySelector( '.nashenski-when__note' );

		if ( ! key ) {
			note.textContent = '';
		} else if ( ! trigger ) {
			note.textContent = strings.missing.replace( '%s', key );
		} else {
			note.textContent = trigger.hidden ? strings.isHidden.replace( '%s', trigger.label ) : '';
		}
	}

	function addCond( cell, cond ) {
		var row = cell.closest( 'tr' );
		var list = triggers( row );
		var node = whenTemplate.content.querySelector( '.nashenski-when__cond' ).cloneNode( true );
		var select = node.querySelector( '.nashenski-when__field' );
		var trigger = findTrigger( list, cond.field );

		select.appendChild( new Option( whenTemplate.dataset.pick, '' ) );
		list.forEach( function ( item ) {
			select.appendChild( new Option( item.label, item.key ) );
		} );

		if ( cond.field && ! trigger ) {
			select.appendChild( new Option( cond.field, cond.field ) );
		}

		select.value = cond.field || '';
		node.querySelector( '.nashenski-when__op' ).value = cond.operator || 'value_eq';
		valueControl( node, trigger, cond.value || '' );
		condNote( node, trigger, cond.field );
		cell.querySelector( '.nashenski-when__list' ).appendChild( node );
	}

	function openPanel( cell ) {
		var panel = cell.querySelector( '.nashenski-when__panel' );
		var data = readWhen( cell );
		var set = data.sets[ 0 ] || [];

		panel.textContent = '';
		panel.appendChild( whenTemplate.content.querySelector( '.nashenski-when__body' ).cloneNode( true ) );
		panel.rest = data.sets.slice( 1 );
		panel.querySelector( '.nashenski-when__mode' ).value = data.hide ? 'hide' : 'show';
		panel.querySelector( '.nashenski-when__other' ).hidden = ! panel.rest.length;
		cell.querySelector( '.nashenski-when__list' ).textContent = '';
		set.forEach( function ( cond ) {
			addCond( cell, cond );
		} );

		if ( ! set.length ) {
			addCond( cell, {} );
		}

		panel.hidden = false;
		cell.querySelector( '.nashenski-when__open' ).setAttribute( 'aria-expanded', 'true' );
	}

	function closePanel( cell ) {
		cell.querySelector( '.nashenski-when__panel' ).hidden = true;
		cell.querySelector( '.nashenski-when__open' ).setAttribute( 'aria-expanded', 'false' );
		refreshSummaries();
	}

	// "Always shown" clears the rule; "Show when..." and "Hide when..." open the panel to fill it in.
	function ruleChosen( cell, mode ) {
		var data = readWhen( cell );
		var panel = cell.querySelector( '.nashenski-when__panel' );

		if ( ! mode ) {
			cell.querySelector( '.nashenski-when__json' ).value = '';
			panel.hidden = true;
			panel.textContent = '';
			cell.querySelector( '.nashenski-when__open' ).setAttribute( 'aria-expanded', 'false' );
			refreshSummaries();
			return;
		}

		if ( panel.hidden ) {
			data.hide = 'hide' === mode;
			cell.querySelector( '.nashenski-when__json' ).value = data.sets.length ? JSON.stringify( data ) : '';
			openPanel( cell );
		}

		panel.querySelector( '.nashenski-when__mode' ).value = mode;
		collect( cell );
		cell.querySelector( '.nashenski-when__open' ).hidden = false;
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.nashenski-when__open, .nashenski-when__add, .nashenski-when__del, .nashenski-when__close' );
		var cell = button && button.closest( '.nashenski-when' );

		if ( ! cell || ! whenTemplate ) {
			return;
		}

		if ( button.classList.contains( 'nashenski-when__open' ) ) {
			if ( cell.querySelector( '.nashenski-when__panel' ).hidden ) {
				openPanel( cell );
			} else {
				closePanel( cell );
			}
		} else if ( button.classList.contains( 'nashenski-when__add' ) ) {
			addCond( cell, {} );
		} else if ( button.classList.contains( 'nashenski-when__del' ) ) {
			button.closest( '.nashenski-when__cond' ).remove();
			collect( cell );
		} else {
			closePanel( cell );
		}
	} );

	document.addEventListener( 'change', function ( event ) {
		var target = event.target;
		var cell = target.closest( '.nashenski-when' );
		var node = target.closest( '.nashenski-when__cond' );
		var trigger;

		if ( ! whenTemplate || ! target.closest( '.nashenski-layout' ) ) {
			return;
		}

		if ( ! cell ) {
			refreshSummaries();
			return;
		}

		if ( target.matches( '.nashenski-when__rule' ) ) {
			ruleChosen( cell, target.value );
			return;
		}

		if ( node && target.matches( '.nashenski-when__field, .nashenski-when__op' ) ) {
			trigger = findTrigger( triggers( cell.closest( 'tr' ) ), node.querySelector( '.nashenski-when__field' ).value );
			valueControl( node, trigger, '' );
			condNote( node, trigger, node.querySelector( '.nashenski-when__field' ).value );
		}

		if ( target.closest( '.nashenski-when__panel' ) ) {
			collect( cell );
		}
	} );

	document.addEventListener( 'input', function ( event ) {
		var cell = event.target.closest( '.nashenski-when' );

		if ( whenTemplate && cell && event.target.closest( '.nashenski-when__value' ) ) {
			collect( cell );
		} else if ( whenTemplate && event.target.closest( '.nashenski-layout' ) && ! cell ) {
			refreshSummaries();
		}
	} );

	if ( whenTemplate ) {
		refreshSummaries();
	}

	// The public price list (and its options) shows only while "Use public prices" is ticked.
	function syncPublicPrices() {
		Array.prototype.forEach.call( document.querySelectorAll( 'td.nashenski-price-list' ), function ( cell ) {
			var box = document.getElementById( 'nashenski_settings_prices_use_public_' + cell.getAttribute( 'data-courier' ) );
			if ( box ) {
				cell.closest( 'tr' ).style.display = box.checked ? '' : 'none';
			}
		} );
	}
	document.addEventListener( 'change', function ( event ) {
		if ( 0 === ( event.target.id || '' ).indexOf( 'nashenski_settings_prices_use_public_' ) ) {
			syncPublicPrices();
		}
	} );
	syncPublicPrices();

	/* Public price lists ---------------------------------------------------- */

	var KINDS = [ 'office', 'locker', 'address' ];

	function escapeHtml( text ) {
		var div = document.createElement( 'div' );
		div.textContent = text == null ? '' : String( text );
		return div.innerHTML;
	}

	function priceInput( name, value, step ) {
		return '<input type="number" min="0" step="' + ( step || '0.01' ) + '" name="' + escapeHtml( name ) + '" value="' + escapeHtml( value == null ? '' : value ) + '">';
	}

	function priceRow( name, index, band ) {
		var html = '<tr><td>' + priceInput( name + '[bands][' + index + '][to]', band.to, '0.001' ) + '</td>';

		KINDS.forEach( function ( kind ) {
			html += '<td>' + priceInput( name + '[bands][' + index + '][' + kind + ']', band[ kind ] ) + '</td>';
		} );

		return html + '<td class="nashenski-rows__remove"><button type="button" class="button-link nashenski-price-list__delete" aria-label="' + escapeHtml( i18n.removeRow || 'Remove row' ) + '">&times;</button></td></tr>';
	}

	function renumberPrices( cell ) {
		var name = cell.getAttribute( 'data-name' );

		Array.prototype.forEach.call( cell.querySelectorAll( '.nashenski-price-list__table tbody tr' ), function ( row, r ) {
			var inputs = row.querySelectorAll( 'input' );
			inputs[ 0 ].name = name + '[bands][' + r + '][to]';

			KINDS.forEach( function ( kind, k ) {
				inputs[ k + 1 ].name = name + '[bands][' + r + '][' + kind + ']';
			} );
		} );
	}

	function setValue( cell, field, value ) {
		var input = cell.querySelector( '[name="' + cell.getAttribute( 'data-name' ) + field + '"]' );

		if ( ! input ) {
			return;
		}

		if ( 'checkbox' === input.type ) {
			input.checked = !! value;
		} else {
			input.value = value == null ? '' : value;
		}
	}

	function fillPrices( cell, list ) {
		var name = cell.getAttribute( 'data-name' );
		var body = cell.querySelector( '.nashenski-price-list__table tbody' );

		body.innerHTML = list.bands.map( function ( band, i ) {
			return priceRow( name, i, band );
		} ).join( '' );

		KINDS.forEach( function ( kind ) {
			setValue( cell, '[over][base][' + kind + ']', list.over.base[ kind ] );
			setValue( cell, '[over][per_kg][' + kind + ']', list.over.per_kg[ kind ] );
		} );

		setValue( cell, '[vat_included]', list.vat_included );
		setValue( cell, '[surcharge]', list.surcharge );
		setValue( cell, '[cod][percent]', list.cod.percent );
		setValue( cell, '[cod][min]', list.cod.min );
		setValue( cell, '[valid_from]', list.valid_from );
		setValue( cell, '[source]', list.source );
	}

	function showPriceResult( cell, html ) {
		cell.querySelector( '.nashenski-price-list__result' ).innerHTML = html;
	}

	document.addEventListener( 'click', function ( event ) {
		var cell = event.target.closest( '.nashenski-price-list' );

		if ( ! cell ) {
			return;
		}

		if ( event.target.closest( '.nashenski-price-list__add' ) ) {
			var body = cell.querySelector( '.nashenski-price-list__table tbody' );
			body.insertAdjacentHTML( 'beforeend', priceRow( cell.getAttribute( 'data-name' ), body.rows.length, {} ) );
			body.rows[ body.rows.length - 1 ].querySelector( 'input' ).focus();
		}

		if ( event.target.closest( '.nashenski-price-list__delete' ) ) {
			event.target.closest( 'tr' ).remove();
			renumberPrices( cell );
		}

		if ( event.target.closest( '.nashenski-price-list__restore' ) ) {
			fillPrices( cell, JSON.parse( cell.getAttribute( 'data-defaults' ) ) );
			showPriceResult( cell, '<div class="notice notice-info inline"><p>' + escapeHtml( i18n.restored || 'Built-in prices restored. Save to keep them.' ) + '</p></div>' );
		}

		var check = event.target.closest( '.nashenski-price-list__check' );

		if ( check ) {
			var spinner = cell.querySelector( '.spinner' );
			var data = new FormData();

			data.append( 'action', 'nashenski_check_prices' );
			data.append( 'courier', cell.getAttribute( 'data-courier' ) );
			data.append( 'nonce', check.getAttribute( 'data-nonce' ) );
			check.disabled = true;
			spinner.classList.add( 'is-active' );
			showPriceResult( cell, '' );

			fetch( window.ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' } )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( json ) {
					if ( ! json || ! json.success ) {
						showPriceResult( cell, '<div class="notice notice-warning inline"><p>' + escapeHtml( json && json.data && json.data.message ? json.data.message : i18n.failed ) + '</p></div>' );
						return;
					}

					var changes = json.data.changes || [];

					if ( ! changes.length ) {
						showPriceResult( cell, '<div class="notice notice-success inline"><p>' + escapeHtml( i18n.pricesSame || 'Your prices match the courier website.' ) + '</p></div>' );
						return;
					}

					var rows = changes.map( function ( c ) {
						return '<tr><td>' + escapeHtml( c[ 0 ] ) + '</td><td>' + escapeHtml( c[ 1 ] ) + '</td><td><strong>' + escapeHtml( c[ 2 ] ) + '</strong></td></tr>';
					} ).join( '' );

					showPriceResult( cell,
						'<div class="notice notice-info inline nashenski-price-changes"><p>' + escapeHtml( i18n.pricesChanged || 'The courier website has different prices:' ) + '</p>' +
						'<table class="widefat striped"><thead><tr><th></th><th>' + escapeHtml( i18n.now || 'Now' ) + '</th><th>' + escapeHtml( i18n.website || 'Website' ) + '</th></tr></thead><tbody>' + rows + '</tbody></table>' +
						'<p><button type="button" class="button button-primary nashenski-price-list__use">' + escapeHtml( i18n.useThese || 'Use these prices' ) + '</button></p></div>'
					);

					cell.querySelector( '.nashenski-price-list__use' ).addEventListener( 'click', function () {
						fillPrices( cell, json.data.list );
						showPriceResult( cell, '<div class="notice notice-success inline"><p>' + escapeHtml( i18n.filled || 'New prices filled in. Check them and save.' ) + '</p></div>' );
					} );
				} )
				.catch( function () {
					showPriceResult( cell, '<div class="notice notice-warning inline"><p>' + escapeHtml( i18n.failed ) + '</p></div>' );
				} )
				.then( function () {
					check.disabled = false;
					spinner.classList.remove( 'is-active' );
				} );
		}
	} );

	/* Image field (media library) ----------------------------------------- */

	document.addEventListener( 'click', function ( event ) {
		var choose = event.target.closest( '.nashenski-image-field__choose' );
		var remove = event.target.closest( '.nashenski-image-field__remove' );
		var cell = ( choose || remove ) && ( choose || remove ).closest( '.nashenski-image-field' );

		if ( ! cell ) {
			return;
		}

		event.preventDefault();

		var input = cell.querySelector( 'input[type="hidden"]' );
		var preview = cell.querySelector( '.nashenski-image-field__preview' );
		var removeButton = cell.querySelector( '.nashenski-image-field__remove' );

		if ( remove ) {
			input.value = '';
			preview.hidden = true;
			removeButton.hidden = true;
			return;
		}

		if ( ! window.wp || ! wp.media ) {
			return;
		}

		var frame = wp.media( {
			title: choose.getAttribute( 'data-title' ),
			library: { type: 'image' },
			multiple: false,
		} );

		frame.on( 'select', function () {
			var image = frame.state().get( 'selection' ).first().toJSON();
			var size = image.sizes && image.sizes.medium ? image.sizes.medium : image;

			input.value = image.id;
			preview.querySelector( 'img' ).src = size.url;
			preview.hidden = false;
			removeButton.hidden = false;
		} );

		frame.open();
	} );

	/* Exports -------------------------------------------------------------- */

	function show( form, html, type ) {
		var box = form.querySelector( '.nashenski-export__result' );

		box.innerHTML = html ? '<div class="notice notice-' + type + ' inline"><p>' + html + '</p></div>' : '';
	}

	function download( blob, response ) {
		var disposition = response.headers.get( 'Content-Disposition' ) || '';
		var match = /filename="?([^";]+)"?/.exec( disposition );
		var link = document.createElement( 'a' );

		link.href = URL.createObjectURL( blob );
		link.download = match ? match[ 1 ] : 'export.zip';
		document.body.appendChild( link );
		link.click();
		link.remove();
		setTimeout( function () {
			URL.revokeObjectURL( link.href );
		}, 1000 );
	}

	// The export blocks sit inside WooCommerce's settings form, so they are not forms themselves.
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.nashenski-export__submit' );

		if ( ! button ) {
			return;
		}

		event.preventDefault();

		var form = button.closest( '.nashenski-export' );
		var spinner = form.querySelector( '.spinner' );
		var month = form.querySelector( '[data-field="year"]' );
		var files = form.querySelector( '[data-field="generate_files"]' );
		var data = new FormData();

		if ( ! month.value ) {
			month.reportValidity();
			return;
		}

		data.append( 'action', form.getAttribute( 'data-action' ) );
		data.append( 'nonce', form.getAttribute( 'data-nonce' ) );
		data.append( 'year', month.value );
		data.append( 'generate_files', files && files.checked ? 'true' : 'false' );

		button.disabled = true;
		spinner.classList.add( 'is-active' );
		show( form, '', '' );

		fetch( window.ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' } )
			.then( function ( response ) {
				var type = response.headers.get( 'Content-Type' ) || '';

				if ( response.ok && type.indexOf( 'application/zip' ) !== -1 ) {
					return response.blob().then( function ( blob ) {
						download( blob, response );
						show( form, i18n.downloaded || 'Done.', 'success' );
					} );
				}

				return response.json().then( function ( json ) {
					var message = json && json.data && json.data.message ? json.data.message : ( json && json.data && json.data.errors ? json.data.errors : '' );

					show( form, message || i18n.failed || 'Something went wrong.', json && json.success ? 'info' : 'error' );
				} );
			} )
			.catch( function () {
				show( form, i18n.failed || 'Something went wrong.', 'error' );
			} )
			.then( function () {
				button.disabled = false;
				spinner.classList.remove( 'is-active' );
			} );
	} );
} )();
