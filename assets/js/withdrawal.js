/**
 * Withdrawal form: fills the empty fields with the order number from the link and, for signed-in
 * customers, their name and email. The values come from window.nashenskiWithdrawal (Front_End\Withdrawal).
 */
( function ( fill ) {
	Object.keys( fill || {} ).forEach( function ( key ) {
		var input = document.getElementById( 'order_withdrawal_' + key );

		if ( input && ! input.value ) {
			input.value = fill[ key ];
		}
	} );
} )( window.nashenskiWithdrawal );
