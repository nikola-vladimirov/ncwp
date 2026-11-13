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
			var copy = cell.querySelector( '.nashenski-layout__template' ).content.cloneNode( true );
			var uid = 'f' + Date.now().toString( 36 );

			// The copied row carries "__uid__" in its field names; each new row gets its own id there.
			each( copy.querySelectorAll( '*' ), function ( node ) {
				each( node.attributes, function ( attr ) {
					if ( attr.value.indexOf( '__uid__' ) !== -1 ) {
						attr.value = attr.value.replace( /__uid__/g, uid );
					}
				} );
			} );
			cell.querySelector( '.nashenski-layout tbody' ).appendChild( copy );
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

	// "Field is value" for one condition, the same wording in the summary and the panel.
	function condText( cond, list ) {
		var strings = whenTemplate.dataset;
		var trigger = findTrigger( list, cond.field );
		var op = whenTemplate.content.querySelector( '.nashenski-when__op option[value="' + cond.operator + '"]' );
		var value = cond.value;

		if ( trigger && 'checkbox' === trigger.type ) {
			value = '1' === value ? strings.yes : strings.no;
		}

		return ( trigger ? trigger.label : cond.field ) + ' ' + ( op ? op.textContent : '' ) + ( /^(empty|not_empty)$/.test( cond.operator ) || '' === value ? '' : ' ' + value );
	}

	function summary( cell ) {
		var data = readWhen( cell );
		var set = data.sets[ 0 ] || [];
		var out = cell.querySelector( '.nashenski-when__summary' );
		var strings = whenTemplate ? whenTemplate.dataset : {};
		var list = triggers( cell.closest( 'tr' ) );
		var text = '';

		if ( set.length ) {
			text = ( data.hide ? strings.hidden : strings.shown ).replace( '%s', function () {
				return data.sets.map( function ( one ) {
					return one.map( function ( cond ) {
						return condText( cond, list );
					} ).join( ' ' + strings.and + ' ' );
				} ).join( ' ' + strings.or + ' ' );
			} );
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

	// Writes every group of the panel: conditions inside a group all hold (and), one group is enough (or).
	function collect( cell ) {
		var panel = cell.querySelector( '.nashenski-when__panel' );
		var data = readWhen( cell );
		var sets = [];

		each( panel.querySelectorAll( '.nashenski-when__group' ), function ( group ) {
			var set = [];

			each( group.querySelectorAll( '.nashenski-when__cond' ), function ( node ) {
				var field = node.querySelector( '.nashenski-when__field' ).value;
				var operator = node.querySelector( '.nashenski-when__op' ).value;
				var input = node.querySelector( '.nashenski-when__value input, .nashenski-when__value select' );

				if ( field ) {
					set.push( { field: field, operator: operator, value: input && ! /^(empty|not_empty)$/.test( operator ) ? input.value : '' } );
				}
			} );

			if ( set.length ) {
				sets.push( set );
			}
		} );

		data.hide = 'hide' === panel.querySelector( '.nashenski-when__mode' ).value;
		data.sets = sets;
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

	function addCond( cell, group, cond ) {
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
		group.querySelector( '.nashenski-when__list' ).appendChild( node );
	}

	// One group of conditions (and); the groups of a rule are alternatives (or).
	function addGroup( cell, set ) {
		var group = whenTemplate.content.querySelector( '.nashenski-when__group' ).cloneNode( true );

		cell.querySelector( '.nashenski-when__groups' ).appendChild( group );
		set.forEach( function ( cond ) {
			addCond( cell, group, cond );
		} );

		if ( ! set.length ) {
			addCond( cell, group, {} );
		}

		markOr( cell );
	}

	// The "or" line shows between groups, not above the first.
	function markOr( cell ) {
		each( cell.querySelectorAll( '.nashenski-when__group' ), function ( group, index ) {
			group.querySelector( '.nashenski-when__or' ).hidden = 0 === index;
		} );
	}

	function openPanel( cell ) {
		var panel = cell.querySelector( '.nashenski-when__panel' );
		var data = readWhen( cell );

		panel.textContent = '';
		panel.appendChild( whenTemplate.content.querySelector( '.nashenski-when__body' ).cloneNode( true ) );
		panel.querySelector( '.nashenski-when__mode' ).value = data.hide ? 'hide' : 'show';
		data.sets.forEach( function ( set ) {
			addGroup( cell, set );
		} );

		if ( ! data.sets.length ) {
			addGroup( cell, [] );
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
		var button = event.target.closest( '.nashenski-when__open, .nashenski-when__add, .nashenski-when__addgroup, .nashenski-when__del, .nashenski-when__close' );
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
			addCond( cell, button.closest( '.nashenski-when__group' ), {} );
		} else if ( button.classList.contains( 'nashenski-when__addgroup' ) ) {
			addGroup( cell, [] );
		} else if ( button.classList.contains( 'nashenski-when__del' ) ) {
			var group = button.closest( '.nashenski-when__group' );

			button.closest( '.nashenski-when__cond' ).remove();

			if ( ! group.querySelector( '.nashenski-when__cond' ) && cell.querySelectorAll( '.nashenski-when__group' ).length > 1 ) {
				group.remove();
				markOr( cell );
			}

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

	// A DOM node. "text" in attrs sets its text, the rest are attributes; children are nodes.
	function el( tag, attrs, children ) {
		var node = document.createElement( tag );

		Object.keys( attrs || {} ).forEach( function ( key ) {
			if ( 'text' === key ) {
				node.textContent = attrs[ key ];
			} else {
				node.setAttribute( key, attrs[ key ] );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			node.appendChild( child );
		} );

		return node;
	}

	// An inline notice with one line of text.
	function notice( type, text ) {
		return el( 'div', { 'class': 'notice notice-' + type + ' inline' }, [ el( 'p', { text: text } ) ] );
	}

	function priceInput( name, value, step ) {
		return el( 'input', { type: 'number', min: '0', step: step || '0.01', name: name, value: value == null ? '' : value } );
	}

	function priceRow( name, index, band ) {
		var cells = [ el( 'td', {}, [ priceInput( name + '[bands][' + index + '][to]', band.to, '0.001' ) ] ) ];

		KINDS.forEach( function ( kind ) {
			cells.push( el( 'td', {}, [ priceInput( name + '[bands][' + index + '][' + kind + ']', band[ kind ] ) ] ) );
		} );

		// The button shows the times sign.
		cells.push( el( 'td', { 'class': 'nashenski-rows__remove' }, [
			el( 'button', { type: 'button', 'class': 'button-link nashenski-price-list__delete', 'aria-label': i18n.removeRow || 'Remove row', text: '\u00D7' } ),
		] ) );

		return el( 'tr', {}, cells );
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

		body.textContent = '';
		list.bands.forEach( function ( band, i ) {
			body.appendChild( priceRow( name, i, band ) );
		} );

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

	// Shows the node as the result of the price list; nothing clears it.
	function showPriceResult( cell, node ) {
		var box = cell.querySelector( '.nashenski-price-list__result' );

		box.textContent = '';

		if ( node ) {
			box.appendChild( node );
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var cell = event.target.closest( '.nashenski-price-list' );

		if ( ! cell ) {
			return;
		}

		if ( event.target.closest( '.nashenski-price-list__add' ) ) {
			var body = cell.querySelector( '.nashenski-price-list__table tbody' );
			body.appendChild( priceRow( cell.getAttribute( 'data-name' ), body.rows.length, {} ) );
			body.rows[ body.rows.length - 1 ].querySelector( 'input' ).focus();
		}

		if ( event.target.closest( '.nashenski-price-list__delete' ) ) {
			event.target.closest( 'tr' ).remove();
			renumberPrices( cell );
		}

		if ( event.target.closest( '.nashenski-price-list__restore' ) ) {
			fillPrices( cell, JSON.parse( cell.getAttribute( 'data-defaults' ) ) );
			showPriceResult( cell, notice( 'info', i18n.restored || 'Built-in prices restored. Save to keep them.' ) );
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
			showPriceResult( cell, null );

			fetch( window.ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' } )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( json ) {
					if ( ! json || ! json.success ) {
						showPriceResult( cell, notice( 'warning', json && json.data && json.data.message ? json.data.message : i18n.failed ) );
						return;
					}

					var changes = json.data.changes || [];

					if ( ! changes.length ) {
						showPriceResult( cell, notice( 'success', i18n.pricesSame || 'Your prices match the courier website.' ) );
						return;
					}

					var use = el( 'button', { type: 'button', 'class': 'button button-primary nashenski-price-list__use', text: i18n.useThese || 'Use these prices' } );
					var changed = notice( 'info', i18n.pricesChanged || 'The courier website has different prices:' );

					changed.classList.add( 'nashenski-price-changes' );
					changed.appendChild( el( 'table', { 'class': 'widefat striped' }, [
						el( 'thead', {}, [ el( 'tr', {}, [ el( 'th' ), el( 'th', { text: i18n.now || 'Now' } ), el( 'th', { text: i18n.website || 'Website' } ) ] ) ] ),
						el( 'tbody', {}, changes.map( function ( c ) {
							return el( 'tr', {}, [ el( 'td', { text: c[ 0 ] } ), el( 'td', { text: c[ 1 ] } ), el( 'td', {}, [ el( 'strong', { text: c[ 2 ] } ) ] ) ] );
						} ) ),
					] ) );
					changed.appendChild( el( 'p', {}, [ use ] ) );
					showPriceResult( cell, changed );

					use.addEventListener( 'click', function () {
						fillPrices( cell, json.data.list );
						showPriceResult( cell, notice( 'success', i18n.filled || 'New prices filled in. Check them and save.' ) );
					} );
				} )
				.catch( function () {
					showPriceResult( cell, notice( 'warning', i18n.failed ) );
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

	/*
	 * The export handlers answer with a little HTML: a download link, line breaks, a list of errors, prices.
	 * It is parsed in a separate document and rebuilt here from these tags and attributes alone. Any other
	 * element is left out and only its text is kept (a script or a style goes whole); a link keeps its
	 * address only when it is a web address.
	 */
	var SAFE_TAGS = [ 'A', 'B', 'BDI', 'BR', 'CODE', 'DIV', 'EM', 'H2', 'H3', 'H4', 'I', 'LI', 'OL', 'P', 'SMALL', 'SPAN', 'STRONG', 'UL' ];
	var SAFE_ATTRS = [ 'class', 'download', 'href', 'rel', 'target' ];

	// True for an http or https address, on this site or another.
	function webAddress( href ) {
		try {
			return /^https?:$/.test( new URL( href, window.location.href ).protocol );
		} catch ( error ) {
			return false;
		}
	}

	function safeCopy( from, to ) {
		each( from.childNodes, function ( child ) {
			if ( 3 === child.nodeType ) {
				to.appendChild( document.createTextNode( child.nodeValue ) );
				return;
			}

			if ( 1 !== child.nodeType || 'SCRIPT' === child.tagName || 'STYLE' === child.tagName ) {
				return;
			}

			if ( SAFE_TAGS.indexOf( child.tagName ) === -1 ) {
				safeCopy( child, to );
				return;
			}

			var copy = document.createElement( child.tagName );

			SAFE_ATTRS.forEach( function ( name ) {
				if ( child.hasAttribute( name ) && ( 'href' !== name || webAddress( child.getAttribute( name ) ) ) ) {
					copy.setAttribute( name, child.getAttribute( name ) );
				}
			} );
			safeCopy( child, copy );
			to.appendChild( copy );
		} );
	}

	// Shows a result under the export: plain text, or the handler's HTML when isHtml is set. Nothing clears it.
	function show( form, message, type, isHtml ) {
		var box = form.querySelector( '.nashenski-export__result' );

		box.textContent = '';

		if ( ! message ) {
			return;
		}

		if ( ! isHtml ) {
			box.appendChild( notice( type, message ) );
			return;
		}

		// Parsed inside a paragraph, as the notice shows it: the parser ends the paragraph before a heading or a list.
		var wrap = el( 'div', { 'class': 'notice notice-' + type + ' inline' } );

		safeCopy( new DOMParser().parseFromString( '<p>' + message + '</p>', 'text/html' ).body, wrap );
		box.appendChild( wrap );
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
		data.append( 'period', month.value );
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

					show( form, message || i18n.failed || 'Something went wrong.', json && json.success ? 'info' : 'error', !! message );
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
