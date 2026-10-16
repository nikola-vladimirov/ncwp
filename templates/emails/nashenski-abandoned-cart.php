<?php
/**
 * Abandoned cart reminder email.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/nashenski-abandoned-cart.php.
 *
 * @package Nashenski\Templates\Emails
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

$email_improvements_enabled = class_exists( FeaturesUtil::class ) && FeaturesUtil::feature_is_enabled( 'email_improvements' );

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php echo $email_improvements_enabled ? '<div class="email-introduction">' : ''; ?>
<?php echo wp_kses_post( wpautop( make_clickable( wptexturize( $message ) ) ) ); ?>
<?php echo $email_improvements_enabled ? '</div>' : ''; ?>

<?php
/*
 * Show user-defined additional content - this is set in the email settings.
 */
if ( $additional_content ) {
	echo $email_improvements_enabled ? '<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation"><tr><td class="email-additional-content">' : '';
	echo wp_kses_post( wpautop( make_clickable( wptexturize( $additional_content ) ) ) );
	echo $email_improvements_enabled ? '</td></tr></table>' : '';
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
