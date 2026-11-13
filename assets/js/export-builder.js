/**
 * Nashenski Commerce: custom export builder (Exports section).
 *
 * Keeps the export settings in one object (config), mirrors it in the form, previews it and
 * downloads the file through a form posted to admin-post.php (outside WooCommerce's settings form).
 */
( function () {
	'use strict';

	var data = window.nashenskiExportBuilder;
	var root = document.getElementById( 'nashenski-builder' );

	if ( ! data || ! root ) {
		return;
	}

	var t = data.i18n;
	var config = clone( data.defaults );
	var currentKey = '';

	function clone( value ) {
		return JSON.parse( JSON.stringify( value ) );
	}

	function $( selector ) {
		return root.querySelector( selector );
	}

	function $$( selector ) {
		return Array.prototype.slice.call( root.querySelectorAll( selector ) );
	}

	function fieldInfo( id ) {
		if ( 0 === id.indexOf( 'meta:' ) ) {
			return { id: id, label: id.slice( 5 ), group: t.metaGroup, sources: [ 'orders', 'lines', 'documents' ] };
		}

		for ( var i = 0; i < data.fields.length; i++ ) {
			if ( data.fields[ i ].id === id ) {
				return data.fields[ i ];
			}
		}

		return null;
	}

	/* ---------- template picker ---------- */

	function fillTemplates() {
		var select = $( '.nashenski-builder__template' );
		var html = '<option value="">' + escapeHtml( t.newExport ) + '</option>';

		html += '<optgroup label="' + escapeHtml( t.readyMade ) + '">';
		Object.keys( data.presets ).forEach( function ( key ) {
			html += '<option value="preset:' + key + '">' + escapeHtml( data.presets[ key ].name ) + '</option>';
		} );
		html += '</optgroup>';

		var saved = Object.keys( data.templates || {} );

		if ( saved.length ) {
			html += '<optgroup label="' + escapeHtml( t.savedGroup ) + '">';
			saved.forEach( function ( key ) {
				html += '<option value="saved:' + key + '">' + escapeHtml( data.templates[ key ].name || key ) + '</option>';
			} );
			html += '</optgroup>';
		}

		select.innerHTML = html;
		select.value = currentKey ? 'saved:' + currentKey : '';
		$( '.nashenski-builder__delete' ).hidden = ! currentKey;
	}

	function escapeHtml( text ) {
		return String( text ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	/* ---------- config <-> form ---------- */

	function render() {
		$$( 'input[name="nashenski_builder_source"]' ).forEach( function ( input ) {
			input.checked = input.value === config.source;
		} );
		$$( 'input[name="nashenski_builder_format"]' ).forEach( function ( input ) {
			input.checked = input.value === config.format;
		} );
		$$( '[data-key]' ).forEach( function ( input ) {
			var key = input.getAttribute( 'data-key' );

			if ( 'checkbox' === input.type ) {
				input.checked = !! config[ key ];
			} else {
				input.value = null == config[ key ] ? '' : config[ key ];
			}
		} );
		$$( '[data-list]' ).forEach( function ( input ) {
			input.checked = ( config[ input.getAttribute( 'data-list' ) ] || [] ).indexOf( input.value ) !== -1;
		} );
		$$( '[data-only]' ).forEach( function ( node ) {
			node.hidden = node.getAttribute( 'data-only' ) !== config.source;
		} );
		$$( '[data-only-format]' ).forEach( function ( node ) {
			node.hidden = node.getAttribute( 'data-only-format' ) !== config.format;
		} );
		$$( '[data-summary]' ).forEach( function ( node ) {
			var count = ( config[ node.getAttribute( 'data-summary' ) ] || [] ).length;

			node.textContent = count ? '(' + count + ')' : '';
		} );

		renderFields();
		renderColumns();
	}

	function renderFields() {
		var box = $( '.nashenski-builder__fields' );
		var needle = ( $( '.nashenski-builder__search' ).value || '' ).toLowerCase().trim();
		var chosen = config.columns.map( function ( c ) {
			return c.field;
		} );
		var groups = {};

		data.fields.forEach( function ( field ) {
			if ( field.sources.indexOf( config.source ) === -1 || chosen.indexOf( field.id ) !== -1 ) {
				return;
			}

			if ( needle && field.label.toLowerCase().indexOf( needle ) === -1 && field.group.toLowerCase().indexOf( needle ) === -1 ) {
				return;
			}

			( groups[ field.group ] = groups[ field.group ] || [] ).push( field );
		} );

		var html = '';

		Object.keys( groups ).forEach( function ( group ) {
			html += '<div class="nashenski-builder__group"><h4>' + escapeHtml( group ) + '</h4>';
			groups[ group ].forEach( function ( field ) {
				html += '<button type="button" class="nashenski-builder__field" role="listitem" data-field="' + escapeHtml( field.id ) + '"><span aria-hidden="true">+</span> ' + escapeHtml( field.label ) + '</button>';
			} );
			html += '</div>';
		} );

		box.innerHTML = html;
	}

	function renderColumns() {
		var list = $( '.nashenski-builder__list' );

		if ( ! config.columns.length ) {
			list.innerHTML = '<li class="nashenski-builder__empty">' + escapeHtml( t.noColumns ) + '</li>';
			return;
		}

		list.innerHTML = config.columns.map( function ( column, index ) {
			var info = fieldInfo( column.field );

			return '<li class="nashenski-builder__column" draggable="true" data-index="' + index + '">' +
				'<span class="nashenski-builder__handle" aria-hidden="true">&#8942;&#8942;</span>' +
				'<input type="text" class="nashenski-builder__label" value="' + escapeHtml( column.label ) + '" aria-label="' + escapeHtml( t.heading ) + '">' +
				'<span class="nashenski-builder__source-field">' + escapeHtml( info ? info.label : column.field ) + '</span>' +
				'<button type="button" class="button-link" data-move="-1" aria-label="' + escapeHtml( t.up ) + '">&uarr;</button>' +
				'<button type="button" class="button-link" data-move="1" aria-label="' + escapeHtml( t.down ) + '">&darr;</button>' +
				'<button type="button" class="button-link button-link-delete" data-remove aria-label="' + escapeHtml( t.remove ) + '">&times;</button>' +
				'</li>';
		} ).join( '' );
	}

	function addColumn( id ) {
		var info = fieldInfo( id );

		if ( ! info ) {
			return;
		}

		config.columns.push( { field: id, label: info.label } );
		renderFields();
		renderColumns();
	}

	/* ---------- events ---------- */

	root.addEventListener( 'change', function ( e ) {
		var target = e.target;

		if ( 'nashenski_builder_source' === target.name ) {
			config.source = target.value;
			// Columns that do not fit the new record type go away.
			config.columns = config.columns.filter( function ( column ) {
				var info = fieldInfo( column.field );

				return info && info.sources.indexOf( config.source ) !== -1;
			} );
			render();
		} else if ( 'nashenski_builder_format' === target.name ) {
			config.format = target.value;
			render();
		} else if ( target.hasAttribute( 'data-key' ) ) {
			config[ target.getAttribute( 'data-key' ) ] = 'checkbox' === target.type ? target.checked : target.value;
		} else if ( target.hasAttribute( 'data-list' ) ) {
			var key = target.getAttribute( 'data-list' );

			config[ key ] = $$( '[data-list="' + key + '"]:checked' ).map( function ( input ) {
				return input.value;
			} );
			render();
		} else if ( target.classList.contains( 'nashenski-builder__template' ) ) {
			loadTemplate( target.value );
		}
	} );

	root.addEventListener( 'input', function ( e ) {
		var target = e.target;

		if ( target.classList.contains( 'nashenski-builder__search' ) ) {
			renderFields();
		} else if ( target.classList.contains( 'nashenski-builder__label' ) ) {
			var index = parseInt( target.closest( 'li' ).getAttribute( 'data-index' ), 10 );

			config.columns[ index ].label = target.value;
		} else if ( target.hasAttribute( 'data-key' ) && 'checkbox' !== target.type ) {
			config[ target.getAttribute( 'data-key' ) ] = target.value;
		}
	} );

	root.addEventListener( 'click', function ( e ) {
		var target = e.target.closest( 'button' );

		if ( ! target || ! root.contains( target ) ) {
			return;
		}

		if ( target.hasAttribute( 'data-field' ) ) {
			addColumn( target.getAttribute( 'data-field' ) );
		} else if ( target.hasAttribute( 'data-move' ) || target.hasAttribute( 'data-remove' ) ) {
			var index = parseInt( target.closest( 'li' ).getAttribute( 'data-index' ), 10 );

			if ( target.hasAttribute( 'data-remove' ) ) {
				config.columns.splice( index, 1 );
			} else {
				var to = index + parseInt( target.getAttribute( 'data-move' ), 10 );

				if ( to >= 0 && to < config.columns.length ) {
					config.columns.splice( to, 0, config.columns.splice( index, 1 )[ 0 ] );
				}
			}

			renderFields();
			renderColumns();
		} else if ( target.hasAttribute( 'data-range' ) ) {
			setRange( target.getAttribute( 'data-range' ) );
		} else if ( target.classList.contains( 'nashenski-builder__meta' ) ) {
			var key = window.prompt( t.metaPrompt );

			if ( key && key.replace( /[^a-zA-Z0-9_\-]/g, '' ) ) {
				addColumn( 'meta:' + key.replace( /[^a-zA-Z0-9_\-]/g, '' ) );
			}
		} else if ( target.classList.contains( 'nashenski-builder__save' ) ) {
			save();
		} else if ( target.classList.contains( 'nashenski-builder__delete' ) ) {
			remove();
		} else if ( target.classList.contains( 'nashenski-builder__preview-button' ) ) {
			preview();
		} else if ( target.classList.contains( 'nashenski-builder__download' ) ) {
			download();
		}
	} );

	/* Drag and drop to reorder columns. */
	var dragFrom = null;

	root.addEventListener( 'dragstart', function ( e ) {
		var item = e.target.closest && e.target.closest( '.nashenski-builder__column' );

		if ( item ) {
			dragFrom = parseInt( item.getAttribute( 'data-index' ), 10 );
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData( 'text/plain', String( dragFrom ) );
			item.classList.add( 'is-dragging' );
		}
	} );

	root.addEventListener( 'dragover', function ( e ) {
		if ( null !== dragFrom && e.target.closest && e.target.closest( '.nashenski-builder__column' ) ) {
			e.preventDefault();
		}
	} );

	root.addEventListener( 'drop', function ( e ) {
		var item = e.target.closest && e.target.closest( '.nashenski-builder__column' );

		if ( null === dragFrom || ! item ) {
			return;
		}

		e.preventDefault();
		var to = parseInt( item.getAttribute( 'data-index' ), 10 );

		config.columns.splice( to, 0, config.columns.splice( dragFrom, 1 )[ 0 ] );
		dragFrom = null;
		renderColumns();
	} );

	root.addEventListener( 'dragend', function () {
		dragFrom = null;
		$$( '.is-dragging' ).forEach( function ( node ) {
			node.classList.remove( 'is-dragging' );
		} );
	} );

	/* Enter in a builder field must not submit the WooCommerce settings form. */
	root.addEventListener( 'keydown', function ( e ) {
		if ( 'Enter' === e.key && 'INPUT' === e.target.tagName ) {
			e.preventDefault();
		}
	} );

	function pad( n ) {
		return ( n < 10 ? '0' : '' ) + n;
	}

	function ymd( date ) {
		return date.getFullYear() + '-' + pad( date.getMonth() + 1 ) + '-' + pad( date.getDate() );
	}

	function setRange( range ) {
		var now = new Date();
		var y = now.getFullYear();
		var m = now.getMonth();

		if ( 'this-month' === range ) {
			config.from = ymd( new Date( y, m, 1 ) );
			config.to = ymd( new Date( y, m + 1, 0 ) );
		} else if ( 'last-month' === range ) {
			config.from = ymd( new Date( y, m - 1, 1 ) );
			config.to = ymd( new Date( y, m, 0 ) );
		} else if ( 'this-year' === range ) {
			config.from = y + '-01-01';
			config.to = y + '-12-31';
		} else if ( 'last-year' === range ) {
			config.from = ( y - 1 ) + '-01-01';
			config.to = ( y - 1 ) + '-12-31';
		}

		render();
	}

	function loadTemplate( value ) {
		var dates = { from: config.from, to: config.to };

		currentKey = '';

		if ( 0 === value.indexOf( 'preset:' ) ) {
			config = clone( data.presets[ value.slice( 7 ) ] );
			config.name = '';
		} else if ( 0 === value.indexOf( 'saved:' ) ) {
			currentKey = value.slice( 6 );
			config = Object.assign( clone( data.defaults ), clone( data.templates[ currentKey ] ) );
		} else {
			config = clone( data.defaults );
		}

		// Keep the period the store was looking at.
		config.from = dates.from;
		config.to = dates.to;
		$( '.nashenski-builder__delete' ).hidden = ! currentKey;
		message( '' );
		$( '.nashenski-builder__preview' ).innerHTML = '';
		render();
	}

	/* ---------- server calls ---------- */

	function busy( on ) {
		$( '.spinner' ).classList.toggle( 'is-active', on );
		$$( '.nashenski-builder__actions .button' ).forEach( function ( button ) {
			button.disabled = on;
		} );
	}

	function message( text, isError ) {
		var box = $( '.nashenski-builder__message' );

		box.textContent = text;
		box.className = 'nashenski-builder__message' + ( text ? ( isError ? ' notice notice-error inline' : ' notice notice-success inline' ) : '' );
	}

	/*
	 * The settings as JSON. The server sanitizes it as text before decoding it, which would cut
	 * "<...>" and "%41" out of the middle of the JSON, so these three characters are written as
	 * JSON escapes: the value decodes to the very same text.
	 */
	function safeJson( value ) {
		return JSON.stringify( value ).replace( /[<>%]/g, function ( c ) {
			return '\\u00' + c.charCodeAt( 0 ).toString( 16 );
		} );
	}

	function post( action, extra ) {
		var body = new FormData();

		body.append( 'action', action );
		body.append( 'nonce', data.nonce );
		body.append( 'config', safeJson( config ) );
		Object.keys( extra || {} ).forEach( function ( key ) {
			body.append( key, extra[ key ] );
		} );

		return fetch( data.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } ).then( function ( response ) {
			return response.json();
		} );
	}

	function preview() {
		busy( true );
		message( '' );
		post( 'nashenski_export_preview' ).then( function ( json ) {
			busy( false );

			if ( ! json || ! json.success ) {
				message( ( json && json.data && json.data.message ) || t.failed, true );
				return;
			}

			var result = json.data;
			var box = $( '.nashenski-builder__preview' );

			if ( ! result.rows.length ) {
				box.innerHTML = '<p>' + escapeHtml( t.noRows ) + '</p>';
				return;
			}

			var html = '<p class="nashenski-builder__count">' + escapeHtml( t.previewOf.replace( '%1$d', result.rows.length ).replace( '%2$d', result.total ).replace( '%3$s', result.file ) ) + '</p>';

			html += '<div class="nashenski-builder__table"><table class="widefat striped"><thead><tr>';
			result.labels.forEach( function ( label ) {
				html += '<th>' + escapeHtml( label ) + '</th>';
			} );
			html += '</tr></thead><tbody>';
			result.rows.forEach( function ( row ) {
				html += '<tr>' + row.map( function ( cell ) {
					return '<td>' + escapeHtml( cell ) + '</td>';
				} ).join( '' ) + '</tr>';
			} );
			html += '</tbody></table></div>';
			box.innerHTML = html;
		} ).catch( function () {
			busy( false );
			message( t.failed, true );
		} );
	}

	function download() {
		if ( ! config.columns.length ) {
			message( t.noColumns, true );
			return;
		}

		var form = document.createElement( 'form' );

		form.method = 'post';
		form.action = data.postUrl;
		form.style.display = 'none';
		[ [ 'action', 'nashenski_export_download' ], [ 'nonce', data.nonce ], [ 'config', safeJson( config ) ] ].forEach( function ( pair ) {
			var input = document.createElement( 'input' );

			input.type = 'hidden';
			input.name = pair[ 0 ];
			input.value = pair[ 1 ];
			form.appendChild( input );
		} );
		document.body.appendChild( form );
		form.submit();
		window.setTimeout( function () {
			form.remove();
		}, 1000 );
	}

	function save() {
		busy( true );
		post( 'nashenski_export_save' ).then( function ( json ) {
			busy( false );

			if ( ! json || ! json.success ) {
				message( ( json && json.data && json.data.message ) || t.failed, true );
				return;
			}

			data.templates = json.data.templates;
			currentKey = json.data.key;
			fillTemplates();
			message( t.saved );
		} ).catch( function () {
			busy( false );
			message( t.failed, true );
		} );
	}

	function remove() {
		if ( ! currentKey || ! window.confirm( t.confirmDelete ) ) {
			return;
		}

		busy( true );
		post( 'nashenski_export_delete', { key: currentKey } ).then( function ( json ) {
			busy( false );
			data.templates = ( json && json.data && json.data.templates ) || {};
			currentKey = '';
			fillTemplates();
			message( t.deleted );
		} ).catch( function () {
			busy( false );
			message( t.failed, true );
		} );
	}

	fillTemplates();
	render();
} )();
