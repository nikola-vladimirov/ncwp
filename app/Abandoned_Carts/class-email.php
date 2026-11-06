<?php
/**
 * Abandoned cart reminder email.
 *
 * @package Nashenski
 */

namespace Nashenski\Abandoned_Carts;

defined( 'ABSPATH' ) || exit;

/**
 * The abandoned cart reminder, as a native WooCommerce email. It shows up under
 * WooCommerce > Settings > Emails, uses the store's email look, and its subject and body are editable
 * with placeholders. The body is one textarea; {items} becomes the list of things left in the cart.
 *
 * @var object|null $cart The cart row being reminded.
 * @var array       $data Values for the placeholders (name, items, cart_total, coupon, resume_link).
 */
class Email extends \WC_Email {
	/**
	 * The cart row being reminded.
	 *
	 * @var object|null
	 */
	public $cart = null;

	/**
	 * Values for the placeholders.
	 *
	 * @var array
	 */
	public $data = array();

	/**
	 * Set up the email.
	 */
	public function __construct() {
		$this->id             = 'nashenski_abandoned_cart';
		$this->customer_email = true;
		$this->title          = __( 'Abandoned cart reminder', 'nashenski-commerce' );
		$this->description    = __( 'A reminder with the items left in the cart, a link that refills the cart and a single-use coupon. Sent by the Abandoned carts feature. Turn the feature on and set it up under Nashenski Commerce > Abandoned carts.', 'nashenski-commerce' );

		$this->template_html  = 'emails/nashenski-abandoned-cart.php';
		$this->template_plain = 'emails/plain/nashenski-abandoned-cart.php';
		$this->template_base  = nashenski()->plugin_dir_path() . 'templates/';

		$this->placeholders = array_merge(
			array(
				'{name}'             => '',
				'{items}'            => '',
				'{cart_total}'       => '',
				'{coupon}'           => '',
				'{coupon_line}'      => '',
				'{resume_link}'      => '',
				'{unsubscribe_link}' => '',
			),
			(array) $this->placeholders
		);

		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		/* translators: %s: site name (the {site_title} placeholder) */
		return __( 'You left items in your cart at {site_title}', 'nashenski-commerce' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'You left something in your cart', 'nashenski-commerce' );
	}

	/**
	 * Default body.
	 *
	 * @return string
	 */
	public function get_default_message() {
		return __( "Hello {name},\n\nYou left these items in your cart:\n\n{items}\n\nTotal: {cart_total}\n\n{coupon_line}\n\n<a href=\"{resume_link}\">Go back to your cart</a>\n\nDo not want these emails? <a href=\"{unsubscribe_link}\">Unsubscribe here</a>", 'nashenski-commerce' );
	}

	/**
	 * The standard email fields, plus the editable body.
	 */
	public function init_form_fields() {
		parent::init_form_fields();

		$this->form_fields['message'] = array(
			'title'       => __( 'Message', 'nashenski-commerce' ),
			'type'        => 'textarea',
			'css'         => 'width:400px; height:150px;',
			'description' => __( 'The body of the reminder. Placeholders: {name}, {items}, {cart_total}, {coupon}, {coupon_line} (the whole sentence about the coupon, empty when there is none), {resume_link}, {unsubscribe_link}, {site_title}. Each line becomes a paragraph.', 'nashenski-commerce' ),
			'default'     => $this->get_default_message(),
			'desc_tip'    => true,
		);
	}

	/**
	 * The body with the placeholders filled.
	 *
	 * @return string
	 */
	public function get_message() {
		return $this->format_string( (string) $this->get_option( 'message', $this->get_default_message() ) );
	}

	/**
	 * Fills the placeholders and sends one reminder to one cart.
	 *
	 * @param object $row  The cart row.
	 * @param array  $data Placeholder values.
	 * @return bool
	 */
	public function trigger( $row, $data ) {
		$this->setup_locale();

		$this->cart      = $row;
		$this->object    = $row;
		$this->data      = (array) $data;
		$this->recipient = isset( $row->email ) ? $row->email : '';

		foreach ( array( '{name}', '{items}', '{cart_total}', '{coupon}', '{coupon_line}', '{resume_link}', '{unsubscribe_link}' ) as $key ) {
			$name                       = trim( $key, '{}' );
			$this->placeholders[ $key ] = isset( $this->data[ $name ] ) ? $this->data[ $name ] : '';
		}

		$sent = false;

		if ( $this->is_enabled() && is_email( $this->get_recipient() ) ) {
			$sent = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();

		return (bool) $sent;
	}

	/**
	 * HTML content.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array_merge(
				$this->template_args(),
				array(
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => false,
					'email'              => $this,
				)
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Plain text content.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array_merge(
				$this->template_args(),
				array(
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => true,
					'email'              => $this,
				)
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Template variables shared by both formats.
	 *
	 * @return array
	 */
	private function template_args() {
		return array(
			'message' => $this->get_message(),
			'cart'    => $this->cart,
			'data'    => $this->data,
		);
	}
}
