<?php
namespace Nashenski\Admin\Order;

defined( 'ABSPATH' ) || exit;

class Accountant_Email extends \WC_Email {
	public function __construct() {
		$this->id          = 'nashenski_accountant_documents';
		$this->title       = __( 'Accountant Document Copies', 'nashenski-commerce' );
		$this->description = __( 'This email sends invoice and credit notice copies to the accountant.', 'nashenski-commerce' );
		$this->heading     = __( 'Document Copy', 'nashenski-commerce' );
		$this->subject     = __( '[{site_title}]: Document copy for order #{order_number}', 'nashenski-commerce' );

		$this->template_html  = '';
		$this->template_plain = '';

		$this->placeholders = array(
			'{order_date}'   => '',
			'{order_number}' => '',
			'{site_title}'   => $this->get_blogname(),
		);

		parent::__construct();

		$accountant_email = nashenski_get_option( 'invoice', 'accountant_email' );
		$this->recipient  = $accountant_email ? $accountant_email : '';
	}

	public function trigger( $order_id, $attachments = array() ) {
		$this->setup_locale();

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			$this->restore_locale();
			return;
		}

		$this->object                         = $order;
		$this->placeholders['{order_date}']   = wc_format_datetime( $this->object->get_date_created() );
		$this->placeholders['{order_number}'] = $this->object->get_order_number();

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			$this->restore_locale();
			return;
		}

		$this->send(
			$this->get_recipient(),
			$this->get_subject(),
			$this->get_content(),
			$this->get_headers(),
			$attachments
		);

		$this->restore_locale();
	}

	public function get_content_html() {
		$order_number = '';

		if ( $this->object ) {
			$order_number = $this->object->get_order_number();
		}

		return sprintf(
			'<p>%s</p>',
			sprintf(
				/* translators: %s: order number */
				__( 'Please find attached the document copies for order #%s.', 'nashenski-commerce' ),
				esc_html( $order_number )
			)
		);
	}

	public function get_content_plain() {
		$order_number = '';

		if ( $this->object ) {
			$order_number = $this->object->get_order_number();
		}

		return sprintf(
			/* translators: %s: order number */
			__( 'Please find attached the document copies for order #%s.', 'nashenski-commerce' ),
			$order_number
		);
	}

	public function get_default_subject() {
		return __( '[{site_title}]: Document copy for order #{order_number}', 'nashenski-commerce' );
	}

	public function get_default_heading() {
		return __( 'Document Copy', 'nashenski-commerce' );
	}
}
