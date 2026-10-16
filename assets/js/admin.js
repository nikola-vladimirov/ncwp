/**
 * Nashenski Commerce: admin scripts for the order screens and the product screens.
 *
 * 1. The "Generate label" link in the order list.
 * 2. Dismissing the BOX NOW and Pigeon notices.
 * 3. The "mathematically impossible price" notice on the product screen and the scan page.
 *
 * The courier label boxes are separate scripts. This file is also the handle (nashenski-js-admin)
 * their PHP data (nashenski_<courier>) is printed on. No build step.
 */
( function ( $ ) {
	'use strict';

	var ajaxUrl = ( window.woocommerce_admin && woocommerce_admin.ajax_url ) || window.ajaxurl || '';
	var adminData = window.nashenskiAdmin || {};
	var P = window.nashenskiImpossiblePrices;

	/* 1. Order list: generate a label ------------------------------------- */

	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( '.nashenski--generate-label' );

		if ( ! link ) {
			return;
		}

		event.preventDefault();

		if ( link.classList.contains( 'js-ajax-loading' ) ) {
			return;
		}

		var body = new FormData();

		body.append( 'action', 'nashenski_generate_label_from_listing_page' );
		body.append( 'orderId', link.dataset.orderid );
		body.append( 'method', link.dataset.method );
		body.append( 'nonce', link.dataset.nonce );

		link.classList.add( 'js-ajax-loading' );

		fetch( ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( reply ) {
				// The handler answers { html } (empty when there is no label), or { success: false, data: { message } }.
				if ( typeof reply.html === 'string' ) {
					// The reply is the server's own label-column markup.
					$( link ).replaceWith( reply.html );
					return;
				}

				link.classList.remove( 'js-ajax-loading' );

				if ( reply.data && reply.data.message ) {
					window.alert( reply.data.message );
				}
			} )
			.catch( function () {
				link.classList.remove( 'js-ajax-loading' );
			} );
	} );

	/* 2. Dismiss the BOX NOW and Pigeon notices --------------------------- */

	function watchDismiss( noticeClass, action ) {
		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '.' + noticeClass + ' button.notice-dismiss' );

			if ( ! button ) {
				return;
			}

			var body = new FormData();

			body.append( 'action', action );
			body.append( 'nonce', adminData.dismissNonce || '' );

			fetch( ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } );
		} );
	}

	watchDismiss( 'notice-boxnow', 'nashenski_boxnow_message_dismiss' );
	watchDismiss( 'notice-pigeon-express', 'nashenski_pigeon_express_message_dismiss' );

	/* 3. Impossible VAT prices --------------------------------------------- */

	if ( ! P || ! $( 'body' ).hasClass( 'woocommerce_nashenski-impossible-vat-price' ) ) {
		return;
	}

	var text = P.i18n || {};

	function t( key, fallback ) {
		return text[ key ] || fallback || '';
	}

	// "%1$s" style placeholders.
	function fill( template, values ) {
		var result = template || '';

		values.forEach( function ( value, index ) {
			result = result.replace( '%' + ( index + 1 ) + '$s', value );
		} );

		return result;
	}

	function errorMessage( xhr, fallback ) {
		var message = fallback || t( 'ajaxError', 'AJAX error' );

		if ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
			return xhr.responseJSON.data.message;
		}

		if ( xhr && xhr.responseText ) {
			try {
				var parsed = JSON.parse( xhr.responseText );

				if ( parsed && parsed.data && parsed.data.message ) {
					return parsed.data.message;
				}
			} catch ( e ) {}
		}

		return message;
	}

	/* 3a. Product screen: the notice under a price field. */

	var timers = {};

	function decimalSeparator() {
		return String( P.decimalSeparator || '.' );
	}

	// A price as the owner typed it ("1 234,50") to a number, or null.
	function parsePrice( value ) {
		var thousand = String( P.thousandSeparator || ',' );
		var decimal = decimalSeparator();

		value = String( value || '' ).trim();

		if ( thousand ) {
			value = value.split( thousand ).join( '' );
		}

		if ( decimal && '.' !== decimal ) {
			value = value.replace( decimal, '.' );
		}

		value = value.replace( ',', '.' );

		if ( ! value || ! /^\d+(\.\d+)?$/.test( value ) ) {
			return null;
		}

		var number = parseFloat( value );

		return isFinite( number ) ? number : null;
	}

	function fieldKey( $input ) {
		var name = String( $input.attr( 'name' ) || '' );

		return name ? 'name:' + name : 'id:' + String( $input.attr( 'id' ) || '' );
	}

	function fieldRow( $input ) {
		var $row = $input.closest( '.form-field, p.form-row' );

		return $row.length ? $row : $input.parent();
	}

	// The paragraph that holds the notice for a field (made when missing).
	function anchorFor( $input ) {
		var key = fieldKey( $input );
		var $row = fieldRow( $input );
		var $variation = $input.closest( '.woocommerce_variation' );

		if ( $variation.length ) {
			var name = String( $input.attr( 'name' ) || '' );

			// In a variation, the notice for both prices sits under the sale price.
			if ( 0 === name.indexOf( 'variable_regular_price' ) || 0 === name.indexOf( 'variable_sale_price' ) ) {
				var $sale = $variation.find( 'input[name^="variable_sale_price"]' ).first();

				if ( $sale.length ) {
					$row = fieldRow( $sale );
				}
			}
		}

		var $existing = $row.next( 'p.form-field.nashenski-impossible-price-anchor' ).filter( function () {
			return $( this ).attr( 'data-field-key' ) === key;
		} );

		if ( $existing.length ) {
			return $existing;
		}

		return $( '<p class="form-field nashenski-impossible-price-anchor"></p>' ).attr( 'data-field-key', key ).insertAfter( $row );
	}

	function clearNotice( $input, keepSuccess ) {
		var $anchor = anchorFor( $input );

		if ( keepSuccess ) {
			$anchor.find( '.nashenski-impossible-price-notice' ).remove();
		} else {
			$anchor.empty();
		}
	}

	function showNotice( $input, suggested ) {
		var key = fieldKey( $input );
		var $anchor = anchorFor( $input );
		var $notice = $( '<div class="nashenski-impossible-price-notice"></div>' ).attr( 'data-field-key', key );
		var $text = $( '<div class="nashenski-impossible-price-notice__text"></div>' );
		var $actions = $( '<div class="nashenski-impossible-price-notice__actions"></div>' );

		$text.append( $( '<strong></strong>' ).text( t( 'warningPrefix' ) ), '<br>', document.createTextNode( t( 'suggested' ) + ' ' ), $( '<strong></strong>' ).text( suggested ) );
		$actions.append(
			$( '<button type="button" class="button button-primary nashenski-apply-corrected-price"></button>' )
				.attr( 'data-value', suggested )
				.attr( 'data-field-key', key )
				.text( t( 'apply' ) )
		);

		$anchor.find( '.nashenski-impossible-price-success, .nashenski-impossible-price-notice' ).remove();
		$anchor.append( $notice.append( $text, $actions ) );
	}

	function showSuccess( $input ) {
		var $anchor = anchorFor( $input );

		$anchor.find( '.nashenski-impossible-price-notice, .nashenski-impossible-price-success' ).remove();
		$anchor.append( $( '<div class="nashenski-impossible-price-success"></div>' ).attr( 'data-field-key', fieldKey( $input ) ).text( t( 'success' ) ) );
	}

	function taxRateFor( taxClass ) {
		taxClass = String( taxClass || '' );

		if ( Object.prototype.hasOwnProperty.call( P.taxRates, taxClass ) ) {
			var rate = parseFloat( P.taxRates[ taxClass ] );

			if ( isFinite( rate ) && rate >= 0 ) {
				return rate;
			}
		}

		return null;
	}

	// The VAT rate (0.2 = 20%) that applies to a price field.
	function rateFor( $input ) {
		var includesTax = !! P.pricesIncludeTax;
		var taxesOn = !! P.taxesEnabled;
		var fallback = parseFloat( P.fallbackTaxRate );

		if ( ! isFinite( fallback ) || fallback <= 0 ) {
			fallback = 0.2;
		}

		if ( ! taxesOn || ! includesTax ) {
			return fallback;
		}

		var parent = parseFloat( P.parentTaxRate );

		if ( ! isFinite( parent ) || parent < 0 ) {
			parent = 0;
		}

		var $variation = $input.closest( '.woocommerce_variation' );

		if ( $variation.length ) {
			var $select = $variation.find( 'select[name^="variable_tax_class"]' );
			var chosen = $select.length ? String( $select.val() || '' ) : '';

			if ( ! chosen || 'parent' === chosen ) {
				return parent > 0 ? parent : fallback;
			}

			var variationRate = taxRateFor( chosen );

			return null !== variationRate ? variationRate : fallback;
		}

		var $class = $( '#_tax_class' );
		var value = $class.length ? String( $class.val() || '' ) : '';
		var rate = taxRateFor( value );

		if ( null !== rate ) {
			return rate;
		}

		return '' === value && parent > 0 ? parent : fallback;
	}

	// The closest gross price that a net price with 'decimals' places can give, or null.
	function nearestGross( gross, rate, decimals ) {
		gross = parseFloat( gross );

		if ( ! isFinite( gross ) || gross <= 0 || ! isFinite( rate ) || rate <= 0 ) {
			return null;
		}

		var scale = Math.pow( 10, decimals );
		var multiplier = 1 + rate;
		var base = Math.floor( ( gross / multiplier ) * scale );
		var preferHigher = !! P.preferHigherOnTie;
		var candidates = [];

		for ( var offset = -3; offset <= 3; offset++ ) {
			var net = base + offset;

			if ( net < 0 ) {
				continue;
			}

			var exact = ( net / scale ) * multiplier;
			var rounded = Number( ( Math.round( exact * scale ) / scale ).toFixed( decimals ) );

			candidates.push( {
				gross: rounded,
				exactDiff: Math.abs( exact - gross ),
				roundedDiff: Math.abs( rounded - gross )
			} );
		}

		if ( ! candidates.length ) {
			return null;
		}

		candidates.sort( function ( a, b ) {
			if ( a.exactDiff !== b.exactDiff ) {
				return a.exactDiff - b.exactDiff;
			}

			if ( a.roundedDiff !== b.roundedDiff ) {
				return a.roundedDiff - b.roundedDiff;
			}

			return preferHigher ? b.gross - a.gross : a.gross - b.gross;
		} );

		return candidates[ 0 ].gross;
	}

	function formatPrice( number, decimals ) {
		var out = Number( number ).toFixed( decimals );
		var separator = decimalSeparator();

		return '.' !== separator ? out.replace( '.', separator ) : out;
	}

	function check( $input ) {
		clearNotice( $input, false );

		if ( $input.data( 'nashenskiApplyingCorrection' ) ) {
			return;
		}

		var typed = String( $input.val() || '' ).trim();

		if ( ! typed ) {
			return;
		}

		var decimals = parseInt( P.priceDecimals, 10 ) || 2;
		var entered = parsePrice( typed );
		var rate = rateFor( $input );

		if ( null === entered || ! rate || rate <= 0 ) {
			return;
		}

		var suggested = nearestGross( entered, rate, decimals );

		if ( null === suggested || Number( entered ).toFixed( decimals ) === Number( suggested ).toFixed( decimals ) ) {
			return;
		}

		showNotice( $input, formatPrice( suggested, decimals ) );
	}

	function checkLater( $input ) {
		var key = fieldKey( $input );

		if ( timers[ key ] ) {
			clearTimeout( timers[ key ] );
		}

		timers[ key ] = setTimeout( function () {
			check( $input );
			delete timers[ key ];
		}, 200 );
	}

	function bind( $input ) {
		if ( ! $input.length || $input.data( 'nashenskiImpossibleBound' ) ) {
			return;
		}

		$input.data( 'nashenskiImpossibleBound', true );

		$input.on( 'input.nashenskiImpossiblePrices', function () {
			var $field = $( this );

			clearNotice( $field, false );
			checkLater( $field );
		} );

		$input.on( 'blur.nashenskiImpossiblePrices change.nashenskiImpossiblePrices', function () {
			var $field = $( this );

			if ( ! $field.data( 'nashenskiApplyingCorrection' ) ) {
				check( $field );
			}
		} );

		check( $input );
	}

	function bindSimple() {
		bind( $( '#_regular_price' ) );
		bind( $( '#_sale_price' ) );
	}

	function bindVariations( scope ) {
		var $scope = $( scope || document );

		$scope.find( 'input[name^="variable_regular_price"]' ).each( function () {
			bind( $( this ) );
		} );
		$scope.find( 'input[name^="variable_sale_price"]' ).each( function () {
			bind( $( this ) );
		} );
	}

	// The input a notice belongs to.
	function findField( key, $button ) {
		var $variation = $button.closest( '.woocommerce_variation' );
		var $inputs = $variation.length ? $variation.find( 'input' ) : $( document ).find( '#_regular_price, #_sale_price, input[name^="variable_regular_price"], input[name^="variable_sale_price"]' );
		var $found = $();

		$inputs.each( function () {
			if ( fieldKey( $( this ) ) === key ) {
				$found = $( this );

				return false;
			}
		} );

		return $found;
	}

	// Keep focus in the field while the button is pressed, so blur does not recheck first.
	$( document ).on( 'mousedown', '.nashenski-apply-corrected-price', function ( event ) {
		event.preventDefault();
	} );

	$( document ).on( 'click', '.nashenski-apply-corrected-price', function ( event ) {
		event.preventDefault();

		var $button = $( this );
		var $field = findField( String( $button.data( 'field-key' ) || '' ), $button );

		if ( ! $field.length ) {
			return;
		}

		$field.data( 'nashenskiApplyingCorrection', true );
		$field.val( String( $button.data( 'value' ) || '' ) );
		showSuccess( $field );

		setTimeout( function () {
			$field.removeData( 'nashenskiApplyingCorrection' );
		}, 300 );
	} );

	$( document ).on( 'change', '#_tax_class', function () {
		bindSimple();
		bindVariations( document );
	} );

	$( document ).on( 'change', 'select[name^="variable_tax_class"]', function () {
		bindVariations( $( this ).closest( '.woocommerce_variation' ) );
	} );

	$( document ).on( 'woocommerce_variations_loaded', function () {
		bindVariations( document );
	} );

	$( document ).ajaxComplete( function ( event, xhr, settings ) {
		if ( settings && settings.data && -1 !== String( settings.data ).indexOf( 'load_variations' ) ) {
			bindVariations( document );
		}
	} );

	bindSimple();
	bindVariations( document );

	/* 3b. Scan page. */

	var $scan = $( '#nashenski-start-scan' );

	if ( ! $scan.length ) {
		return;
	}

	var $fixAll = $( '#nashenski-fix-all' );
	var $status = $( '#nashenski-scan-status' );
	var $bar = $( '#nashenski-progress-bar' );
	var $percent = $( '#nashenski-progress-text' );
	var $body = $( '#nashenski-results tbody' );
	var $wrap = $( '#nashenski-results-wrap' );
	var $summary = $( '#nashenski-results-summary' );
	var $fixSummary = $( '#nashenski-fix-summary' );
	var scanning = false;
	var fixing = false;
	var page = 1;
	var totalPages = 1;
	var checked = 0;
	var problems = 0;
	var perPage = 100;

	function fixableRows() {
		return $body.find( 'tr' ).filter( function () {
			return $( this ).find( '.nashenski-fix-row' ).length > 0;
		} );
	}

	function resultRow( row, status ) {
		var $tr = $( '<tr></tr>' )
			.attr( 'data-key', row.product_id + '||' + row.price_key )
			.attr( 'data-product-id', row.product_id )
			.attr( 'data-price-key', row.price_key )
			.attr( 'data-target-price', row.nearest_raw );
		var cells = [
			row.product_id,
			row.name,
			row.type,
			row.sku,
			row.price_label,
			row.entered_price_display,
			row.tax_percent_display + t( 'percentSuffix', '%' ),
			row.nearest_display,
			row.nearest_down_display,
			row.nearest_up_display
		];

		cells.forEach( function ( value ) {
			$tr.append( $( '<td></td>' ).text( null == value ? '' : value ) );
		} );

		$tr.append( $( '<td class="nashenski-row-status"></td>' ).text( status || t( 'problematic', 'Problematic' ) ) );
		$tr.append(
			$( '<td></td>' ).append(
				$( '<a class="button button-secondary"></a>' ).attr( 'href', row.edit_link || '' ).text( t( 'edit', 'Edit' ) ),
				' ',
				$( '<button type="button" class="button button-primary nashenski-fix-row"></button>' ).text( t( 'fix', 'Fix' ) )
			)
		);

		return $tr;
	}

	function messageRow( message ) {
		return $( '<tr></tr>' ).append( $( '<td colspan="12"></td>' ).text( message ) );
	}

	function showSummary() {
		$summary.text( fill( t( 'checkedFound', 'Checked: %1$s | Problematic: %2$s' ), [ checked, problems ] ) );
	}

	function refreshProblems() {
		var count = $body.find( 'tr' ).filter( function () {
			return $( this ).find( 'td' ).length > 1;
		} ).length;

		problems = count;
		showSummary();

		if ( ! count ) {
			$body.empty().append( messageRow( t( 'noProblematicRemaining', 'No problematic prices remain.' ) ) );
			$fixAll.prop( 'disabled', true );
		}
	}

	function scanDone() {
		scanning = false;
		$scan.prop( 'disabled', false ).text( t( 'scanProducts', 'Scan products' ) );
	}

	function scanBatch() {
		$.ajax( {
			url: P.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'nashenski_scan_impossible_prices_batch',
				nonce: P.scanNonce,
				page: page,
				per_page: perPage
			}
		} ).done( function ( reply ) {
			if ( ! reply || ! reply.success || ! reply.data ) {
				scanDone();
				$status.text( t( 'scanError', 'An error occurred during scanning.' ) );

				return;
			}

			var data = reply.data;

			totalPages = parseInt( data.total_pages || 1, 10 );
			checked += parseInt( data.scanned_in_batch || 0, 10 );

			if ( data.rows && data.rows.length ) {
				data.rows.forEach( function ( row ) {
					$body.append( resultRow( row, t( 'problematic', 'Problematic' ) ) );
				} );
				$wrap.show();
				problems += data.rows.length;
			}

			var percent = totalPages > 0 ? Math.min( 100, Math.round( ( ( page - 1 ) / totalPages ) * 100 ) ) : 0;

			$bar.css( 'width', percent + '%' );
			$percent.text( percent + '%' );
			showSummary();

			if ( data.done ) {
				scanDone();
				$bar.css( 'width', '100%' );
				$percent.text( '100%' );
				$status.text( t( 'scanFinished', 'Scanning finished.' ) );

				if ( ! problems ) {
					$wrap.show();
					$body.empty().append( messageRow( t( 'noProblematicFound', 'No problematic prices found.' ) ) );
					$fixAll.prop( 'disabled', true );

					return;
				}

				$fixAll.prop( 'disabled', false );

				return;
			}

			page++;
			$status.text( fill( t( 'pageOf', 'Scanning... page %1$s of %2$s' ), [ page - 1, totalPages ] ) );
			scanBatch();
		} ).fail( function ( xhr ) {
			scanDone();
			$status.text( errorMessage( xhr, t( 'scanAjaxError', 'AJAX error during scanning.' ) ) );
		} );
	}

	// Fix one row; done( true|false ) is called when the answer is in.
	function fixRow( $tr, done ) {
		var target = $tr.attr( 'data-target-price' );
		var $rowStatus = $tr.find( '.nashenski-row-status' );
		var $button = $tr.find( '.nashenski-fix-row' );

		if ( ! target ) {
			$rowStatus.text( t( 'missingTargetPrice', 'Missing target price' ) );

			if ( done ) {
				done( false );
			}

			return;
		}

		$button.prop( 'disabled', true ).text( t( 'fixing', 'Fixing...' ) );
		$rowStatus.text( t( 'statusFixing', 'Fixing...' ) );

		$.ajax( {
			url: P.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'nashenski_fix_impossible_price',
				nonce: P.fixNonce,
				product_id: $tr.attr( 'data-product-id' ),
				price_key: $tr.attr( 'data-price-key' ),
				target_price: target
			}
		} ).done( function ( reply ) {
			if ( reply && reply.success ) {
				var message = t( 'fixed', 'Fixed' );

				if ( reply.data && reply.data.adjustment_message ) {
					message = reply.data.adjustment_message;
				}

				if ( reply.data && reply.data.current_rows ) {
					replaceProductRows( reply.data.product_id, reply.data.current_rows, message );
				} else {
					$rowStatus.text( message );
					$tr.addClass( 'nashenski-row-fixed' );
					$button.remove();
				}

				if ( done ) {
					done( true );
				}

				return;
			}

			$rowStatus.text( reply && reply.data && reply.data.message ? reply.data.message : t( 'genericError', 'Error' ) );
			$button.prop( 'disabled', false ).text( t( 'fix', 'Fix' ) );

			if ( done ) {
				done( false );
			}
		} ).fail( function ( xhr ) {
			$rowStatus.text( errorMessage( xhr, t( 'ajaxError', 'AJAX error' ) ) );
			$button.prop( 'disabled', false ).text( t( 'fix', 'Fix' ) );

			if ( done ) {
				done( false );
			}
		} );
	}

	// After a fix, the product's rows are swapped for what is still wrong with it.
	function replaceProductRows( productId, rows, status ) {
		$body.find( 'tr' ).filter( function () {
			return $( this ).attr( 'data-product-id' ) === String( productId );
		} ).remove();

		if ( rows && rows.length ) {
			if ( $body.find( 'td[colspan="12"]' ).length ) {
				$body.empty();
			}

			rows.forEach( function ( row ) {
				$body.append( resultRow( row, status || t( 'problematic', 'Problematic' ) ) );
			} );
		}

		refreshProblems();
	}

	function fixNext( index, counts ) {
		if ( ! fixing ) {
			return;
		}

		var $rows = fixableRows();

		if ( ! $rows.length || index >= $rows.length ) {
			fixing = false;
			$fixAll.prop( 'disabled', false ).text( t( 'fixAll', 'Fix all' ) );
			$fixSummary.text( fill( t( 'fixAllSummary', 'Fixed: %1$s | Not fixed: %2$s' ), [ counts.success, counts.failed ] ) );
			refreshProblems();

			return;
		}

		$fixSummary.text( fill( t( 'fixAllProgress', 'Fixing all... %1$s / %2$s' ), [ index + 1, $rows.length ] ) );

		fixRow( $( $rows.get( index ) ), function ( ok ) {
			if ( ok ) {
				counts.success++;
			} else {
				counts.failed++;
			}

			fixNext( index + 1, counts );
		} );
	}

	$scan.on( 'click', function ( event ) {
		event.preventDefault();

		if ( scanning || fixing ) {
			return;
		}

		scanning = true;
		page = 1;
		totalPages = 1;
		checked = 0;
		problems = 0;
		$body.empty();
		$wrap.hide();
		$summary.text( '' );
		$fixSummary.text( '' );
		$status.text( t( 'preparing', 'Preparing...' ) );
		$bar.css( 'width', '0%' );
		$percent.text( '0%' );
		$fixAll.prop( 'disabled', true );
		$scan.prop( 'disabled', true ).text( t( 'scanning', 'Scanning...' ) );
		scanBatch();
	} );

	$( document ).on( 'click', '.nashenski-fix-row', function ( event ) {
		event.preventDefault();

		if ( ! scanning && ! fixing ) {
			fixRow( $( this ).closest( 'tr' ) );
		}
	} );

	$fixAll.on( 'click', function ( event ) {
		event.preventDefault();

		if ( scanning || fixing ) {
			return;
		}

		if ( ! fixableRows().length ) {
			$fixSummary.text( t( 'nothingToFix', 'There are no rows to fix.' ) );

			return;
		}

		fixing = true;
		$fixAll.prop( 'disabled', true ).text( t( 'fixing', 'Fixing...' ) );
		fixNext( 0, { success: 0, failed: 0 } );
	} );
}( jQuery ) );
