<?php
namespace Nashenski\Invoice\Document;

use Nashenski\Invoice\Order\Order;
use Nashenski\Invoice\PDF\PDF;
use Nashenski\File;
use Clegginabox\PDFMerger\PDFMerger;

defined( 'ABSPATH' ) || exit;

class BaseDocument {
	public $order, $pdf, $woo_order, $nap_number, $company_name, $mol, $eik, $vat_number, $address, $city, $phone_number, $email, $color, $add_shipping, $prepared_by, $identification_code, $footer_text, $meta, $payment_method, $transaction_id, $title, $document_number_meta, $document_number_option, $logo, $qr;

	function __construct( $order ) {
		$this->woo_order    = $order;
		$this->order        = new Order( $order );
		$this->company_name = nashenski_get_option( 'nap', 'company_name' );
		$this->mol          = nashenski_get_option( 'nap', 'mol' );
		$this->eik          = nashenski_get_option( 'nap', 'eik' );
		$this->vat_number   = nashenski_get_option( 'nap', 'dds_number' );
		$this->address      = nashenski_get_option( 'nap', 'address' );
		$this->city         = nashenski_get_option( 'nap', 'city' );
		$this->phone_number = nashenski_get_option( 'nap', 'phone_number' );
		$this->email        = nashenski_get_option( 'nap', 'email' );
		$this->nap_number   = nashenski_get_option( 'nap', 'nap_number' );

		$this->document_number_meta = 'nashenski_order_number';
		$this->document_number_option = 'next_invoice_number';

		$this->color               = nashenski_get_option( 'invoice', 'color' );
		$this->add_shipping        = nashenski_get_option( 'invoice', 'add_shipping' );
		$this->prepared_by         = nashenski_get_option( 'invoice', 'prepared_by' );
		$this->identification_code = nashenski_get_option( 'invoice', 'identification_code' );
		$this->footer_text         = nashenski_get_option( 'invoice', 'footer_text' );

		if ( ! is_a( $this->woo_order, '\Automattic\WooCommerce\Admin\Overrides\OrderRefund' ) ) {
			$this->payment_method         = $this->woo_order->get_payment_method_title();
			$this->transaction_id         = $this->woo_order->get_transaction_id();
		}

		$this->set_pdf_printer();

		if( ! preg_match('/^#[a-f0-9]{6}$/i', $this->color ) ){
			$this->color = '#007fff';
		}
	}

	public function get_head_items() {
		$items = array();

		if ( $document_number = $this->get_document_number() ) {
			$items[ 'reference' ] = array(
				'label' => '№',
				'value' => str_pad( $document_number, 10, '0', STR_PAD_LEFT ),
			);
		}

		$items = array_merge( $items, array(
			'date' => array(
				'label' => __( 'Order date', 'nashenski-commerce' ),
				'value' => $this->format_date( $this->get_document_date() ),
			),
			'date-of-tax' => array(
				'label' => __( 'Date of tax. event', 'nashenski-commerce' ),
				'value' => $this->format_date( $this->get_document_date() ),
			),
			'due-date' => array(
				'label' => __( 'Due date', 'nashenski-commerce' ),
				'value' => $this->format_date( $this->get_document_due_date() ),
			),
			'address' => array(
				'label' => __( 'Place of transaction', 'nashenski-commerce' ),
				'value' => $this->city,
			),
			'order-number' => array(
				'label' => __( 'Order Number', 'nashenski-commerce' ),
				'value' => $this->woo_order->get_order_number(),
			),
		) );

		return apply_filters( 'nashenski/invoice/head_items', $items , $this );
	}

	public function get_cart_headers() {
		return apply_filters( 'nashenski/invoice/cart_headers', array(
			array(
				'label' => __( 'Product', 'nashenski-commerce' ),
				'class' => '',
			),
			array(
				'label' => __( 'Qty', 'nashenski-commerce' ),
				'class' => '',
			),
			array(
				'label' => __( 'Vat', 'nashenski-commerce' ),
				'class' => '',
			),
			array(
				'label' => __( 'Price', 'nashenski-commerce' ),
				'class' => '',
			),
			array(
				'label' => __( 'Total amount', 'nashenski-commerce' ),
				'class' => '',
			),
		), $this );
	}

	/**
	 * The order of the customer: the parent order for refund documents.
	 */
	protected function customer_order() {
		return ! empty( $this->parent_order ) ? $this->parent_order : $this->woo_order;
	}

	/**
	 * Lines of a From or To box without the empty ones.
	 */
	protected static function lines( $lines ) {
		return array_filter( array_map( function ( $line ) {
			return trim( (string) $line );
		}, $lines ), 'strlen' );
	}

	public function get_from_items() {
		$contacts = 'no' !== \Nashenski\Invoice\PDF\Style::get()['show_contacts'];

		return apply_filters( 'nashenski/invoice/from_items', self::lines( array(
			'company_name' => $this->company_name,
			/* translators: %s: company EIK */
			'eik' => $this->eik ? sprintf( __( 'EIK: %s', 'nashenski-commerce' ), $this->eik ) : '',
			/* translators: %s: VAT number */
			'vat_number' => $this->vat_number ? sprintf( __( 'VAT Number: %s', 'nashenski-commerce' ), $this->vat_number ) : '',
			/* translators: %s: name of the responsible person (MOL) */
			'mol' => $this->mol ? sprintf( __( 'MOL: %s', 'nashenski-commerce' ), $this->mol ) : '',
			'address' => implode( ', ', self::lines( array( $this->address, $this->city ) ) ),
			/* translators: %s: phone number */
			'phone' => $contacts && $this->phone_number ? sprintf( __( 'Phone: %s', 'nashenski-commerce' ), $this->phone_number ) : '',
			/* translators: %s: email address */
			'email' => $contacts && $this->email ? sprintf( __( 'E-mail: %s', 'nashenski-commerce' ), $this->email ) : '',
		) ), $this );
	}

	public function get_to_items() {
		$order = $this->customer_order();
		$contacts = 'no' !== \Nashenski\Invoice\PDF\Style::get()['show_contacts'];
		/* translators: %s: phone number */
		$phone = $contacts && $order->get_billing_phone() ? sprintf( __( 'Phone: %s', 'nashenski-commerce' ), $order->get_billing_phone() ) : '';
		/* translators: %s: email address */
		$email = $contacts && $order->get_billing_email() ? sprintf( __( 'E-mail: %s', 'nashenski-commerce' ), $order->get_billing_email() ) : '';

		if ( $order->get_meta( '_billing_to_company' ) === '1' ) {
			$eik = $order->get_meta( '_billing_company_eik' );
			$vat = $order->get_meta( '_billing_vat_number' );
			$mol = $order->get_meta( '_billing_company_mol' );

			$items = apply_filters( 'nashenski/invoice/set_to_company', array(
				$order->get_billing_company(),
				/* translators: %s: company EIK */
				$eik ? sprintf( __( 'EIK: %s', 'nashenski-commerce' ), $eik ) : '',
				/* translators: %s: VAT number */
				$vat ? sprintf( __( 'VAT Number: %s', 'nashenski-commerce' ), $vat ) : '',
				/* translators: %s: name of the responsible person (MOL) */
				$mol ? sprintf( __( 'MOL: %s', 'nashenski-commerce' ), $mol ) : '',
				implode( ', ', self::lines( array( $order->get_meta( '_billing_company_address' ), $order->get_meta( '_billing_company_settlement' ) ) ) ),
				$phone,
				$email,
			), $order );
		} else {
			$items = apply_filters( 'nashenski/invoice/set_to_person', array(
				$order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				implode( ', ', self::lines( array( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(), $order->get_billing_city() . ' ' . $order->get_billing_postcode() ) ) ),
				$phone,
				$email,
			), $order );
		}

		return apply_filters( 'nashenski/invoice/to_items', self::lines( $items ), $this );
	}

	/**
	 * The "Received" box: Documents > PDF appearance.
	 */
	protected function shows_received() {
		return 'no' !== \Nashenski\Invoice\PDF\Style::get()['show_received'];
	}

	public function get_additional_items_labels() {
		$items = array();

		if ( $this->payment_method ) {
			$items[] = __( 'Payment method', 'nashenski-commerce' );
		}

		if ( $this->pays_by_bank() ) {
			$items[] = __( 'Bank account', 'nashenski-commerce' );
		}

		if ( $this->transaction_id ) {
			$items[] = __( 'Transaction ID', 'nashenski-commerce' );
		}

		if ( $this->prepared_by || $this->identification_code ) {
			$items[] = __( 'Compiled by', 'nashenski-commerce' );
		}

		if ( $this->shows_received() ) {
			$items[] = __( 'Received', 'nashenski-commerce' );
		}

		return apply_filters( 'nashenski/invoice/additional_items_labels', $items, $this );
	}

	private function pays_by_bank() {
		return method_exists( $this->woo_order, 'get_payment_method' ) && $this->woo_order->get_payment_method() === 'bacs';
	}

	public function get_additional_items() {
		$items = array();

		if ( $this->payment_method ) {
			$items[] = $this->payment_method;
		}

		if ( $this->pays_by_bank() ) {
			$bacs_info  = get_option( 'woocommerce_bacs_accounts');

			ob_start();

			if ( !empty( $bacs_info ) ) {
				foreach ( $bacs_info as $account ) {
					?>
					<strong><?php esc_html_e( 'Bank name', 'nashenski-commerce' ) ?>:</strong> <?php echo esc_html( $account['bank_name'] ) ?> <br>
					<strong>IBAN:</strong> <?php echo esc_html( $account['iban'] ) ?> <br>
					<strong>BIC:</strong> <?php echo esc_html( $account['bic'] ) ?> <br>
					<?php
				}
			}

			$items[] = ob_get_clean();
		}

		if ( $this->transaction_id ) {
			$items[] = $this->transaction_id;
		}

		if ( $this->prepared_by || $this->identification_code ) {
			$items[] = $this->prepared_by . " " . $this->identification_code;
		}

		if ( $this->shows_received() ) {
			$items[] = $this->customer_order()->get_billing_first_name() . ' ' . $this->customer_order()->get_billing_last_name();
		}

		return apply_filters( 'nashenski/invoice/additional_items', $items, $this );
	}

	public function get_document_number() {
		return \Nashenski\Invoice\Number_Allocator::allocate( $this->woo_order, $this->document_number_meta, $this->document_number_option );
	}

	public function get_document_date() {
		return $this->woo_order->get_date_created();
	}

	public function get_document_due_date() {
		return $this->get_document_date();
	}

	private function set_pdf_printer() {
		$this->pdf = new PDF( $this );
	}

	public function set_title( $title ) {
		$this->title = $title;
	}

	public function after_file_generated() {}

	/**
	 * Make the document twice, whatever the site or user language: in Bulgarian (saved in
	 * $this->meta, the file that is emailed and exported) and in English ($this->meta . '_en').
	 */
	public function generate_file() {
		$class = get_class( $this );
		$order = $this->woo_order;

		foreach ( array( 'bg_BG' => '', 'en_US' => '_en' ) as $locale => $suffix ) {
			nashenski_in_locale( $locale, function () use ( $class, $order, $suffix ) {
				$document = new $class( $order );
				$document->meta .= $suffix;
				$document->build_file();
			} );
		}

		// Refund documents load their own copy of the refund; pick up the saved files.
		$this->woo_order->read_meta_data( true );
	}

	/**
	 * One language of the document. Invoices and credit notes put the original and the copy in one PDF.
	 */
	protected function build_file() {
		$this->save_pdf( $this->pdf->generate() );
	}

	/**
	 * A document date in the format of Documents > PDF appearance.
	 *
	 * @param \WC_DateTime|string $date Date.
	 */
	public function format_date( $date ) {
		$time = $date instanceof \WC_DateTime ? $date->getTimestamp() : strtotime( (string) $date );

		return $time ? date_i18n( \Nashenski\Invoice\PDF\Style::get()['date_format'], $time ) : (string) $date;
	}

	/**
	 * Original and copy in one PDF; only the original when the store keeps copies as separate
	 * documents (Documents > Generate separate copy documents).
	 */
	protected function generate_original_and_copy( $copy_title ) {
		if ( nashenski_get_option( 'invoice', 'separate_copy_documents' ) === 'yes' ) {
			$this->save_pdf( $this->pdf->generate() );
			return;
		}

		// Unique files in the system temp folder: never web-served, never shared between requests.
		$original = wp_tempnam( 'nashenski-original.pdf' );
		$copy = wp_tempnam( 'nashenski-copy.pdf' );

		try {
			File::put_to_file( $original, $this->pdf->generate() );
			$this->set_title( $copy_title );
			File::put_to_file( $copy, $this->pdf->generate() );

			$merger = new PDFMerger();
			$merger->addPDF( $original );
			$merger->addPDF( $copy );
			$merged = $merger->merge( 'string' );
		} finally {
			wp_delete_file( $original );
			wp_delete_file( $copy );
		}

		$this->save_pdf( $merged );
	}

	/**
	 * Store the PDF privately (Document_Storage), attach it to the order (replacing the old file)
	 * and run after_file_generated().
	 */
	protected function save_pdf( $contents ) {
		$name = apply_filters( 'nashenski/admin/invoice/file_name', uniqid( wp_rand(), true ), $this );
		$attach_id = \Nashenski\Document_Storage::save( $name, 'pdf', $contents );

		if ( ! $attach_id ) {
			return;
		}

		if ( $old_file = $this->woo_order->get_meta( $this->meta ) ) {
			wp_delete_attachment( $old_file );
		}

		$this->woo_order->update_meta_data( $this->meta, $attach_id );
		$this->woo_order->save();

		$this->after_file_generated();
	}

	/**
	 * The store logo: Documents > Logo, else the theme's site logo, else the site icon.
	 *
	 * @return int Attachment ID or 0.
	 */
	public static function get_logo_id() {
		$id = absint( nashenski_get_option( 'pdf', 'logo' ) );

		if ( ! $id ) {
			$id = absint( get_theme_mod( 'custom_logo' ) );
		}

		if ( ! $id ) {
			$id = absint( get_option( 'site_icon' ) );
		}

		return (int) apply_filters( 'nashenski/invoice/logo_id', $id );
	}

	/**
	 * The logo as an <img> with a data URI, sized to fit 220 x 70, so the PDF never loads files over the web.
	 */
	public static function get_logo_img() {
		$id = self::get_logo_id();
		$file = $id ? get_attached_file( $id ) : '';

		if ( ! $file || ! is_readable( $file ) ) {
			return '';
		}

		$type = wp_check_filetype( $file );
		$mime = $type['type'] ? $type['type'] : 'image/png';
		$data = base64_encode( file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- local logo file embedded as a data URI.
		$size = 'image/svg+xml' === $mime ? false : @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- unreadable images just get no size.
		list( $box_w, $box_h ) = \Nashenski\Invoice\PDF\Style::logo_box();
		$attrs = sprintf( 'height="%d"', $box_h );

		if ( $size && $size[0] && $size[1] ) {
			$scale = min( $box_w / $size[0], $box_h / $size[1], 1 );
			$attrs = sprintf( 'width="%d" height="%d"', round( $size[0] * $scale ), round( $size[1] * $scale ) );
		}

		return sprintf( '<img class="site-logo" src="data:%s;base64,%s" %s alt="">', esc_attr( $mime ), $data, $attrs );
	}

	/**
	 * Website name, address, email and phone under the logo.
	 */
	public function get_site_details() {
		if ( 'no' === nashenski_get_option( 'pdf', 'show_site_details' ) ) {
			return array();
		}

		$email = $this->email ? $this->email : get_option( 'woocommerce_email_from_address' );

		return apply_filters( 'nashenski/invoice/site_details', array_filter( array(
			'name' => get_bloginfo( 'name' ),
			'url' => untrailingslashit( wp_parse_url( home_url(), PHP_URL_HOST ) . wp_parse_url( home_url(), PHP_URL_PATH ) ),
			'email' => $email,
			'phone' => $this->phone_number,
		) ), $this );
	}

	public function render_pdf_logo() {
		$logo = self::get_logo_img();
		$details = $this->get_site_details();

		if ( $logo ) {
			echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in get_logo_img().
		}

		if ( $details ) {
			echo '<div class="site-details">';

			foreach ( $details as $key => $line ) {
				printf( '<p class="site-details__%1$s">%2$s</p>', esc_attr( $key ), esc_html( $line ) );
			}

			echo '</div>';
		}
	}

	/**
	 * QR code of the N-18 order document (empty for other documents).
	 */
	public function render_pdf_qr() {
		$qr = apply_filters( 'nashenski/invoice/pdf/default_template/qr', $this->qr, $this );

		if ( ! $qr ) {
			return;
		}

		echo '<div class="qr">' . wp_kses( $qr, 'post', array_merge( wp_allowed_protocols(), array( 'data' ) ) ) . '</div>';
	}
}
