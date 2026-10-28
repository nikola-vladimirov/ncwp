/**
 * Classic checkout: shows or hides a field by the value of another checkout field.
 * The rules come from window.nashenskiConditions (Front_End\Checkout\Field_Editor).
 */
( function ( $ ) {
	var config = window.nashenskiConditions;

	if ( ! config ) {
		return;
	}

	// The same plain rule as Conditions::clean_text() on the server: ASCII spaces, tabs and line breaks only.
	function clean( text ) {
		return String( text ).replace( /[ \t\r\n]+/g, ' ' ).replace( /^ | $/g, '' );
	}

	function value( key ) {
		var $input = $( '[name="' + config.names[ key ] + '"]' );

		if ( ! $input.length ) {
			return '';
		}

		if ( $input.is( ':checkbox' ) ) {
			return $input.is( ':checked' ) ? '1' : '';
		}

		return clean( $input.val() || '' );
	}

	function isNumber( text ) {
		// The same pattern as Conditions::is_number() on the server.
		return /^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/.test( text );
	}

	function test( condition ) {
		var current = value( condition.field ),
			operand = clean( condition.value );

		switch ( condition.operator ) {
			case 'value_eq':
				return current === operand;
			case 'value_ne':
				return current !== operand;
			case 'value_gt':
				return isNumber( current ) && isNumber( operand ) && parseFloat( current ) > parseFloat( operand );
			case 'value_le':
				// "Value less than", as the classic script of the other plugin reads it.
				return isNumber( current ) && isNumber( operand ) && parseFloat( current ) < parseFloat( operand );
			case 'empty':
				return '' === current;
			case 'not_empty':
				return '' !== current;
		}

		return false;
	}

	function shown( when ) {
		if ( ! when.sets || ! when.sets.length ) {
			return true;
		}

		var met = when.sets.some( function ( set ) {
			return set.every( test );
		} );

		return when.hide ? ! met : met;
	}

	function clear( $row ) {
		$row.find( 'input[type=checkbox]' ).prop( 'checked', false );
		$row.find( 'input:not([type=checkbox]), textarea' ).val( '' );
		// The namespace keeps select2 from re-running the other change handlers of the form.
		$row.find( 'select' ).val( '' ).trigger( 'change.select2' );
	}

	// One pass: returns true when a row changed, so a chain of rules is repeated until it settles.
	// Only a row this script hid is shown again; a row hidden by WooCommerce (a state list, say) stays hidden.
	function pass() {
		var changed = false;

		config.targets.forEach( function ( target ) {
			var $row = $( '#' + target.name + '_field' ),
				hiddenHere = $row.data( 'nashenskiHidden' );

			if ( ! $row.length ) {
				return;
			}

			if ( shown( target.when ) ) {
				if ( hiddenHere ) {
					$row.show().removeData( 'nashenskiHidden' );
					changed = true;
				}
			} else {
				if ( ! hiddenHere ) {
					$row.hide().data( 'nashenskiHidden', true );
					changed = true;
				}

				clear( $row );
			}
		} );

		return changed;
	}

	function update() {
		for ( var i = 0; i <= config.targets.length && pass(); i++ ) {
			// Repeat until no row changes.
		}
	}

	$( document.body ).on( 'updated_checkout', update );
	$( document.body ).on( 'change input', 'form.checkout :input', update );
	update();
} )( jQuery );
