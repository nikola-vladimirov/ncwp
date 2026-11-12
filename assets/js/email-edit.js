// WooCommerce > Settings > Emails: the Edit button of the abandoned cart reminder opens its classic
// settings page (nashenskiEmailEdit.url), because the block email editor cannot edit a classic email.
( function () {
	'use strict';

	if ( ! window.wp || ! wp.apiFetch || ! wp.apiFetch.use || ! window.nashenskiEmailEdit ) {
		return;
	}

	wp.apiFetch.use( function ( options, next ) {
		var path = options && options.path ? String( options.path ) : '';

		if ( path.indexOf( 'recreate-email-post' ) !== -1 && options.data && options.data.email_id === 'nashenski_abandoned_cart' ) {
			window.location.href = window.nashenskiEmailEdit.url;
			return new Promise( function () {} );
		}

		return next( options );
	} );
}() );
