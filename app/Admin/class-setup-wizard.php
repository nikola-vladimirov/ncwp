<?php
/**
 * Setup wizard.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * First-run setup on one screen: company details, VAT, shipping methods, the checkout look and,
 * if wanted, the recommended settings. It can be run again; it never adds a rate or method twice.
 */
class Setup_Wizard {
	const SLUG           = 'nashenski-setup';
	const ACTION         = 'nashenski_setup';
	const NONCE          = 'nashenski_setup_nonce';
	const DISMISS        = 'nashenski_setup_dismiss';
	const DONE_OPTION    = 'nashenski_setup_done';
	const DISMISS_OPTION = 'nashenski_setup_dismissed';
	const REDUCED        = 'reduced-rate';

	/** Courier => wizard delivery type => the courier method's delivery_type. BOX NOW has one type. */
	const TYPES = array(
		'econt'  => array(
			'office'  => 'office',
			'locker'  => 'automat',
			'address' => 'address',
		),
		'speedy' => array(
			'office'  => 'office',
			'locker'  => 'automat',
			'address' => 'address',
		),
		'boxnow' => array( 'locker' => '' ),
		'pigeon' => array(
			'office'  => 'office',
			'locker'  => 'locker',
			'address' => 'address',
		),
	);

	/** Company fields of the settings group "nap". */
	const NAP = array( 'company_name', 'mol', 'eik', 'dds_number', 'phone_number', 'city', 'address', 'email', 'domain' );

	/**
	 * Construct.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_head', array( __CLASS__, 'hide_menu' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::DISMISS, array( __CLASS__, 'dismiss' ) );
	}

	/**
	 * The wizard screen.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	/**
	 * Menu.
	 */
	public static function menu() {
		add_submenu_page( 'woocommerce', __( 'Set up Nashenski Commerce', 'nashenski-commerce' ), __( 'Set up Nashenski Commerce', 'nashenski-commerce' ), 'manage_woocommerce', self::SLUG, array( __CLASS__, 'render' ) );
	}

	/**
	 * The page is reached from links only, so it stays out of the menu (removed after the access check).
	 */
	public static function hide_menu() {
		remove_submenu_page( 'woocommerce', self::SLUG );
	}

	/**
	 * Courier names.
	 *
	 * @return array
	 */
	private static function couriers() {
		return array(
			'econt'  => __( 'Econt', 'nashenski-commerce' ),
			'speedy' => __( 'Speedy', 'nashenski-commerce' ),
			'boxnow' => __( 'BOX NOW', 'nashenski-commerce' ),
			'pigeon' => __( 'Pigeon Express', 'nashenski-commerce' ),
		);
	}

	/**
	 * Labels of the company fields.
	 *
	 * @return array
	 */
	private static function nap_labels() {
		return array(
			'company_name' => __( 'Company Name', 'nashenski-commerce' ),
			'mol'          => __( 'MOL', 'nashenski-commerce' ),
			'eik'          => __( 'EIK', 'nashenski-commerce' ),
			'dds_number'   => __( 'VAT Number', 'nashenski-commerce' ),
			'phone_number' => __( 'Company phone Number', 'nashenski-commerce' ),
			'city'         => __( 'Settlement', 'nashenski-commerce' ),
			'address'      => __( 'Company Address', 'nashenski-commerce' ),
			'email'        => __( 'Contact email', 'nashenski-commerce' ),
			'domain'       => __( 'Domain Name', 'nashenski-commerce' ),
		);
	}

	/**
	 * Method title that names the courier and the delivery type.
	 *
	 * @param string $courier Courier slug.
	 * @param string $type    Courier delivery type.
	 * @return string
	 */
	private static function title( $courier, $type ) {
		if ( 'office' === $type ) {
			/* translators: %s: courier name */
			$format = __( '%s to an office', 'nashenski-commerce' );
		} elseif ( 'address' === $type ) {
			/* translators: %s: courier name */
			$format = __( '%s to an address', 'nashenski-commerce' );
		} else {
			/* translators: %s: courier name */
			$format = __( '%s to a parcel locker', 'nashenski-commerce' );
		}

		return sprintf( $format, self::couriers()[ $courier ] );
	}

	/* ---------- notice ---------- */

	/**
	 * Notice until the setup is done or dismissed, only on the Nashenski settings, Plugins and Dashboard screens.
	 */
	public static function notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) || get_option( self::DONE_OPTION ) || get_option( self::DISMISS_OPTION ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! Settings::is_current() && ! ( $screen && in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) ) ) {
			return;
		}
		?>
		<div class="notice notice-info">
			<p>
				<?php esc_html_e( 'Set up Nashenski Commerce in a few steps.', 'nashenski-commerce' ); ?>
				<a href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Start the setup', 'nashenski-commerce' ); ?></a>
				|
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS ), self::DISMISS ) ); ?>"><?php esc_html_e( 'Dismiss', 'nashenski-commerce' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Dismiss.
	 */
	public static function dismiss() {
		if ( current_user_can( 'manage_woocommerce' ) && check_admin_referer( self::DISMISS ) ) {
			update_option( self::DISMISS_OPTION, 1, false );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/* ---------- saving ---------- */

	/**
	 * Handle the form.
	 */
	public static function handle() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'nashenski-commerce' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION, self::NONCE );

		// Every value is sanitized and checked field by field in save().
		$input = isset( $_POST['nashenski_setup'] ) && is_array( $_POST['nashenski_setup'] ) ? wp_unslash( $_POST['nashenski_setup'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		update_option( self::DONE_OPTION, self::save( $input ), false );

		wp_safe_redirect( add_query_arg( 'nashenski_setup_saved', 1, self::url() ) );
		exit;
	}

	/**
	 * Apply the choices. Returns what the result screen shows.
	 *
	 * @param array $in Unslashed form values.
	 * @return array time, report (lines), errors (lines), couriers (slugs), reduced (bool).
	 */
	public static function save( $in ) {
		$in      = is_array( $in ) ? $in : array();
		$report  = array();
		$errors  = array();
		$changed = 0;
		$invalid = function ( $label ) {
			/* translators: %s: field name */
			return sprintf( __( 'Not saved, please check it: %s', 'nashenski-commerce' ), $label );
		};
		$set     = function ( $option, $value ) use ( &$changed ) {
			if ( (string) get_option( $option ) !== (string) $value ) {
				update_option( $option, $value );
				++$changed;
			}
		};
		$text    = function ( $key, $from = null ) use ( $in ) {
			$from = null === $from ? $in : $from;
			return isset( $from[ $key ] ) && is_string( $from[ $key ] ) ? sanitize_text_field( $from[ $key ] ) : '';
		};
		$list    = function ( $key ) use ( $in ) {
			return isset( $in[ $key ] ) && is_array( $in[ $key ] ) ? array_map( 'sanitize_key', array_filter( $in[ $key ], 'is_string' ) ) : array();
		};

		// First, so the VAT choice below decides whether prices are entered with VAT.
		if ( '' !== $text( 'recommended' ) ) {
			$applied = Recommended_Settings::apply( array_keys( Recommended_Settings::items() ) );
			/* translators: %d: number of settings */
			$report[] = sprintf( _n( '%d recommended setting applied', '%d recommended settings applied', $applied, 'nashenski-commerce' ), $applied );
		}

		// Company.
		$vat    = in_array( $text( 'vat' ), array( 'vat20', 'vat9', 'none' ), true ) ? $text( 'vat' ) : '';
		$nap    = isset( $in['nap'] ) && is_array( $in['nap'] ) ? $in['nap'] : array();
		$labels = self::nap_labels();

		foreach ( self::NAP as $name ) {
			// No VAT number without a VAT registration; an old one is left as it is.
			if ( ! isset( $nap[ $name ] ) || ! is_string( $nap[ $name ] ) || ( 'dds_number' === $name && ! in_array( $vat, array( 'vat20', 'vat9' ), true ) ) ) {
				continue;
			}

			$value = $text( $name, $nap );

			if ( 'email' === $name ) {
				$valid = '' === $value || is_email( $value );
				$value = sanitize_email( $value );
			} elseif ( 'eik' === $name ) {
				$value = preg_replace( '/\s+/', '', $value );
				$valid = '' === $value || preg_match( '/^\d{9}(\d{4})?$/', $value );
			} elseif ( 'dds_number' === $name ) {
				$value = strtoupper( preg_replace( '/\s+/', '', $value ) );
				$valid = '' === $value || preg_match( '/^BG\d{9,10}$/', $value );
			} else {
				$valid = true;
			}

			if ( $valid ) {
				$set( 'nashenski_settings_nap_' . $name, $value );
			} else {
				$errors[] = $invalid( $labels[ $name ] );
			}
		}

		// VAT.
		if ( 'none' === $vat ) {
			$set( 'woocommerce_calc_taxes', 'no' );
		} elseif ( $vat ) {
			$set( 'woocommerce_calc_taxes', 'yes' );
			$set( 'woocommerce_prices_include_tax', '' !== $text( 'prices_include_tax' ) ? 'yes' : 'no' );
			$set( 'woocommerce_tax_display_shop', 'incl' );
			$set( 'woocommerce_tax_display_cart', 'incl' );
			$report[] = self::add_rate( '', '20' );

			if ( 'vat9' === $vat ) {
				$class = \WC_Tax::get_tax_class_by( 'slug', self::REDUCED );

				if ( ! $class ) {
					$class = \WC_Tax::create_tax_class( __( 'Reduced rate', 'nashenski-commerce' ), self::REDUCED );
					/* translators: %s: tax class name */
					$report[] = is_wp_error( $class ) ? '' : sprintf( __( 'Tax class added: %s', 'nashenski-commerce' ), $class['name'] );
				}

				// Without the class a 9% rate would belong to nothing.
				if ( is_wp_error( $class ) ) {
					$errors[] = $class->get_error_message();
				} else {
					$report[] = self::add_rate( self::REDUCED, '9' );
				}
			}
		}

		// Documents.
		$invoices = $text( 'invoices' );

		if ( in_array( $invoices, array( 'always', 'only_for_company', 'disable' ), true ) ) {
			$set( 'nashenski_settings_invoice_invoices', $invoices );

			// Invoices are made only while documents are on.
			if ( 'disable' !== $invoices && 'yes' !== nashenski_get_option( 'apis', 'enable_documents' ) ) {
				nashenski_set_option( 'apis', 'enable_documents', 'yes' );
				$report[] = __( 'Invoices and N-18 documents turned on.', 'nashenski-commerce' );
			}
		}

		// Shipping.
		$couriers = array_values( array_intersect( array_keys( self::TYPES ), $list( 'couriers' ) ) );
		$types    = array_intersect( array( 'office', 'locker', 'address' ), $list( 'types' ) );
		$free     = str_replace( ',', '.', $text( 'free_over' ) );

		if ( '' !== $free && ( ! is_numeric( $free ) || (float) $free < 0 ) ) {
			$errors[] = $invalid( __( 'Free shipping over', 'nashenski-commerce' ) );
			$free     = '';
		} elseif ( '' !== $free ) {
			$free = wc_format_decimal( $free );
		}

		if ( array_diff( $couriers, array( 'boxnow' ) ) && ! $types ) {
			$errors[] = __( 'Choose at least one delivery type to add shipping methods.', 'nashenski-commerce' );
		} elseif ( $couriers ) {
			$report = array_merge( $report, self::shipping( $couriers, $types, $free ) );
		}

		// Checkout look.
		if ( in_array( $text( 'layout' ), array( 'plain', 'cards', 'buttons' ), true ) ) {
			$set( 'nashenski_settings_appearance_layout', $text( 'layout' ) );
		}

		$set( 'nashenski_settings_appearance_free_over_label', '' !== $text( 'free_over_label' ) ? 'yes' : 'no' );
		$set( 'nashenski_settings_appearance_free_progress', '' !== $text( 'free_progress' ) ? 'yes' : 'no' );

		if ( $changed ) {
			/* translators: %d: number of settings */
			$report[] = sprintf( _n( '%d setting saved', '%d settings saved', $changed, 'nashenski-commerce' ), $changed );
		}

		return array(
			'time'     => time(),
			'report'   => array_values( array_filter( $report ) ),
			'errors'   => $errors,
			'couriers' => $couriers,
			'reduced'  => 'vat9' === $vat,
		);
	}

	/**
	 * A Bulgarian rate in a tax class, unless the class has one. Returns the report line.
	 *
	 * @param string $tax_class Tax class slug, '' for the standard class.
	 * @param string $rate      Percent.
	 * @return string
	 */
	private static function add_rate( $tax_class, $rate ) {
		if ( self::has_bg_rate( $tax_class ) ) {
			/* translators: %s: VAT percent */
			return sprintf( __( 'A tax rate for Bulgaria was there already, so no %s%% rate was added.', 'nashenski-commerce' ), $rate );
		}

		\WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => 'BG',
				'tax_rate_state'    => '',
				'tax_rate'          => $rate,
				'tax_rate_name'     => __( 'VAT', 'nashenski-commerce' ),
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => $tax_class,
			)
		);

		/* translators: %s: VAT percent */
		return sprintf( __( 'Tax rate added: %s%% VAT for Bulgaria', 'nashenski-commerce' ), $rate );
	}

	/**
	 * Turn the couriers on and add their methods to the zone of Bulgaria. Returns the report lines.
	 *
	 * @param string[] $couriers Courier slugs.
	 * @param string[] $types    Wizard delivery types.
	 * @param string   $free     Free shipping total, '' for none.
	 * @return array
	 */
	private static function shipping( $couriers, $types, $free ) {
		$report = array();
		$names  = self::couriers();

		foreach ( $couriers as $courier ) {
			if ( 'yes' !== nashenski_get_option( 'apis', 'enable_' . $courier ) ) {
				nashenski_set_option( 'apis', 'enable_' . $courier, 'yes' );
				/* translators: %s: courier name */
				$report[] = sprintf( __( '%s turned on.', 'nashenski-commerce' ), $names[ $courier ] );
			}
		}

		$zone = Import_Sources::bulgaria_zone();

		if ( ! $zone->get_id() ) {
			$zone = new \WC_Shipping_Zone();
			$zone->set_zone_name( __( 'Bulgaria', 'nashenski-commerce' ) );
			$zone->save();
			$zone->add_location( 'BG', 'country' );
			$zone->save();
			/* translators: %s: zone name */
			$report[] = sprintf( __( 'Shipping zone added: %s', 'nashenski-commerce' ), $zone->get_zone_name() );
		}

		foreach ( $couriers as $courier ) {
			$wanted   = array_values( 'boxnow' === $courier ? self::TYPES['boxnow'] : array_intersect_key( self::TYPES[ $courier ], array_flip( $types ) ) );
			$settings = array();

			foreach ( $wanted as $type ) {
				// BOX NOW's free shipping total is one setting for all its methods.
				$settings[ $type ] = array(
					'title'              => self::title( $courier, $type ),
					'free_shipping_over' => 'boxnow' === $courier ? '' : $free,
				);
			}

			foreach ( Import_Sources::add_methods( $zone, $courier, $wanted, $settings ) as $type ) {
				/* translators: %s: shipping method title */
				$report[] = sprintf( __( 'Shipping method added: %s', 'nashenski-commerce' ), self::title( $courier, $type ) );
			}
		}

		if ( '' !== $free && in_array( 'boxnow', $couriers, true ) && '' === trim( (string) nashenski_get_option( 'boxnow_price', 'free_shipping_over' ) ) ) {
			nashenski_set_option( 'boxnow_price', 'free_shipping_over', $free );
			/* translators: %s: order total */
			$report[] = sprintf( __( 'BOX NOW free shipping over %s', 'nashenski-commerce' ), html_entity_decode( wp_strip_all_tags( wc_price( (float) $free ) ) ) );
		}

		\WC_Cache_Helper::get_transient_version( 'shipping', true );

		return $report;
	}

	/* ---------- screen ---------- */

	/**
	 * The wizard, or the result after saving.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$done = get_option( self::DONE_OPTION );

		echo '<div class="wrap nashenski-setup"><h1>' . esc_html__( 'Set up Nashenski Commerce', 'nashenski-commerce' ) . '</h1>';

		if ( nashenski_query_arg( 'nashenski_setup_saved' ) && is_array( $done ) ) {
			self::render_result( $done );
		} else {
			self::render_form();
		}

		echo '</div>';
	}

	/**
	 * What changed and what is left to do.
	 *
	 * @param array $done Result of save().
	 */
	private static function render_result( $done ) {
		$names = self::couriers();

		if ( ! empty( $done['errors'] ) ) {
			echo '<div class="notice notice-warning inline"><p>' . wp_kses_post( implode( '<br>', array_map( 'esc_html', (array) $done['errors'] ) ) ) . '</p></div>';
		}
		?>
		<h2><?php esc_html_e( 'What changed', 'nashenski-commerce' ); ?></h2>
		<?php if ( ! empty( $done['report'] ) ) : ?>
			<ul class="ul-disc">
				<?php foreach ( (array) $done['report'] as $line ) : ?>
					<li><?php echo esc_html( $line ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p><?php esc_html_e( 'Nothing needed to change.', 'nashenski-commerce' ); ?></p>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Next steps', 'nashenski-commerce' ); ?></h2>
		<ol>
			<?php foreach ( (array) ( $done['couriers'] ?? array() ) as $courier ) : ?>
				<?php // Only couriers with no login yet; the first login field of each is enough to tell. ?>
				<?php if ( isset( $names[ $courier ] ) && '' === trim( (string) nashenski_get_option( $courier, array( 'econt' => 'user', 'speedy' => 'user', 'boxnow' => 'client_id', 'pigeon' => 'api_key' )[ $courier ] ?? 'user' ) ) ) : ?>
					<li>
						<a href="<?php echo esc_url( add_query_arg( 'courier', $courier, Settings::url( 'couriers' ) ) ); ?>">
							<?php
							/* translators: %s: courier name */
							echo esc_html( sprintf( __( 'Enter your %s login.', 'nashenski-commerce' ), $names[ $courier ] ) );
							?>
						</a>
						<?php esc_html_e( 'The shipping methods cannot calculate prices without it.', 'nashenski-commerce' ); ?>
					</li>
				<?php endif; ?>
			<?php endforeach; ?>
			<?php if ( ! empty( $done['reduced'] ) ) : ?>
				<li>
					<?php esc_html_e( 'Set the tax class "Reduced rate" on the products you sell at 9% VAT.', 'nashenski-commerce' ); ?>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>"><?php esc_html_e( 'Products', 'nashenski-commerce' ); ?></a>
				</li>
			<?php endif; ?>
			<li>
				<?php esc_html_e( 'Check the other settings and the Help page.', 'nashenski-commerce' ); ?>
				<a href="<?php echo esc_url( Settings::url( 'help' ) ); ?>"><?php esc_html_e( 'Help', 'nashenski-commerce' ); ?></a>
			</li>
		</ol>
		<p><a class="button" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Run the setup again', 'nashenski-commerce' ); ?></a></p>
		<?php
	}

	/**
	 * The form.
	 */
	private static function render_form() {
		$nap    = array();
		$labels = self::nap_labels();
		$host   = wp_parse_url( home_url(), PHP_URL_HOST );
		$taxes  = 'yes' === get_option( 'woocommerce_calc_taxes' );
		$vat    = $taxes ? ( self::has_bg_rate( self::REDUCED ) ? 'vat9' : 'vat20' ) : 'none';
		$open   = Import_Sources::open_names();
		$zone   = Import_Sources::bulgaria_zone();
		$have   = array();

		// Empty fields start from the WooCommerce store address and the site.
		$fallback = array(
			'address' => trim( get_option( 'woocommerce_store_address' ) . ' ' . get_option( 'woocommerce_store_address_2' ) ),
			'city'    => (string) get_option( 'woocommerce_store_city' ),
			'email'   => (string) get_option( 'admin_email' ),
			'domain'  => is_string( $host ) ? $host : '',
		);

		foreach ( self::NAP as $name ) {
			$value        = trim( (string) nashenski_get_option( 'nap', $name ) );
			$nap[ $name ] = '' !== $value ? $value : ( $fallback[ $name ] ?? '' );
		}

		foreach ( $zone->get_id() ? $zone->get_shipping_methods() : array() as $method ) {
			if ( 0 === strpos( (string) $method->id, 'nashenski_' ) ) {
				$have[] = $method->get_title();
			}
		}

		$invoices = (string) nashenski_get_option( 'invoice', 'invoices' );
		$invoices = in_array( $invoices, array( 'always', 'only_for_company', 'disable' ), true ) ? $invoices : 'only_for_company';
		$layout   = (string) nashenski_get_option( 'appearance', 'layout' );
		$layout   = in_array( $layout, array( 'plain', 'cards', 'buttons' ), true ) ? $layout : \Nashenski\Front_End\Checkout\Appearance::layout();
		?>
		<p><?php esc_html_e( 'Fill in what you know; you can change everything later in the settings. Running the setup again never adds a tax rate or shipping method twice.', 'nashenski-commerce' ); ?></p>

		<?php if ( $open ) : ?>
			<div class="notice notice-info inline">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: plugin names */
							__( 'Moving from another plugin (%s)? Import first. It brings your courier logins, shipping methods and company details.', 'nashenski-commerce' ),
							implode( ', ', $open )
						)
					);
					?>
					<a href="<?php echo esc_url( Settings::url( 'help' ) . '#import' ); ?>"><?php esc_html_e( 'Review and import', 'nashenski-commerce' ); ?></a>
				</p>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
			<?php wp_nonce_field( self::ACTION, self::NONCE ); ?>

			<h2><?php esc_html_e( 'Company', 'nashenski-commerce' ); ?></h2>
			<p><?php esc_html_e( 'Printed on invoices and used in the NRA audit file.', 'nashenski-commerce' ); ?></p>
			<table class="form-table" role="presentation">
				<?php foreach ( self::NAP as $name ) : ?>
					<?php
					if ( 'dds_number' === $name ) {
						continue; // Asked in the VAT part.
					}
					?>
					<tr>
						<th scope="row"><label for="nashenski-setup-<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $labels[ $name ] ); ?></label></th>
						<td><input type="<?php echo 'email' === $name ? 'email' : 'text'; ?>" class="regular-text" id="nashenski-setup-<?php echo esc_attr( $name ); ?>" name="nashenski_setup[nap][<?php echo esc_attr( $name ); ?>]" value="<?php echo esc_attr( $nap[ $name ] ); ?>"></td>
					</tr>
				<?php endforeach; ?>
			</table>

			<h2><?php esc_html_e( 'VAT', 'nashenski-commerce' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'VAT registration', 'nashenski-commerce' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'VAT registration', 'nashenski-commerce' ); ?></legend>
							<label><input type="radio" name="nashenski_setup[vat]" value="vat20"<?php checked( 'vat20' === $vat ); ?>> <?php esc_html_e( 'Registered for VAT, 20%', 'nashenski-commerce' ); ?></label><br>
							<label><input type="radio" name="nashenski_setup[vat]" value="vat9"<?php checked( 'vat9' === $vat ); ?>> <?php esc_html_e( 'Registered for VAT, some goods at 9%', 'nashenski-commerce' ); ?></label><br>
							<label><input type="radio" name="nashenski_setup[vat]" value="none"<?php checked( 'none' === $vat ); ?>> <?php esc_html_e( 'Not registered for VAT', 'nashenski-commerce' ); ?></label>
							<p class="description"><?php esc_html_e( 'Registered: WooCommerce taxes are turned on, prices are shown with VAT and a 20% rate for Bulgaria is added. With 9%, the tax class "Reduced rate" also gets a 9% rate. Not registered: taxes are turned off and your tax rates are kept.', 'nashenski-commerce' ); ?></p>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Prices', 'nashenski-commerce' ); ?></th>
					<td><label><input type="checkbox" name="nashenski_setup[prices_include_tax]" value="yes"<?php checked( ! $taxes || 'yes' === get_option( 'woocommerce_prices_include_tax' ) ); ?>> <?php esc_html_e( 'Prices are entered with VAT', 'nashenski-commerce' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="nashenski-setup-dds_number"><?php echo esc_html( $labels['dds_number'] ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="nashenski-setup-dds_number" name="nashenski_setup[nap][dds_number]" value="<?php echo esc_attr( $nap['dds_number'] ); ?>" aria-describedby="nashenski-setup-dds_number-description">
						<p class="description" id="nashenski-setup-dds_number-description"><?php esc_html_e( 'Only when registered for VAT, for example BG123456789.', 'nashenski-commerce' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Invoices', 'nashenski-commerce' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Invoices', 'nashenski-commerce' ); ?></legend>
							<label><input type="radio" name="nashenski_setup[invoices]" value="always"<?php checked( 'always' === $invoices ); ?>> <?php esc_html_e( 'Always', 'nashenski-commerce' ); ?></label><br>
							<label><input type="radio" name="nashenski_setup[invoices]" value="only_for_company"<?php checked( 'only_for_company' === $invoices ); ?>> <?php esc_html_e( 'Only for companies', 'nashenski-commerce' ); ?></label><br>
							<label><input type="radio" name="nashenski_setup[invoices]" value="disable"<?php checked( 'disable' === $invoices ); ?>> <?php esc_html_e( 'No invoices', 'nashenski-commerce' ); ?></label>
						</fieldset>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Shipping', 'nashenski-commerce' ); ?></h2>
			<p>
				<?php
				if ( ! $zone->get_id() ) {
					esc_html_e( 'No shipping zone covers Bulgaria yet. A zone "Bulgaria" will be added.', 'nashenski-commerce' );
				} elseif ( $have ) {
					/* translators: 1: zone name, 2: shipping method titles */
					echo esc_html( sprintf( __( 'Already in the zone %1$s: %2$s. These are not added again.', 'nashenski-commerce' ), $zone->get_zone_name(), implode( ', ', $have ) ) );
				} else {
					/* translators: %s: zone name */
					echo esc_html( sprintf( __( 'The methods are added to the zone %s.', 'nashenski-commerce' ), $zone->get_zone_name() ) );
				}
				?>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Couriers', 'nashenski-commerce' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Couriers', 'nashenski-commerce' ); ?></legend>
							<?php foreach ( self::couriers() as $courier => $name ) : ?>
								<label><input type="checkbox" name="nashenski_setup[couriers][]" value="<?php echo esc_attr( $courier ); ?>"<?php checked( 'yes' === nashenski_get_option( 'apis', 'enable_' . $courier ) ); ?>> <?php echo esc_html( $name ); ?></label><br>
							<?php endforeach; ?>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Delivery types', 'nashenski-commerce' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Delivery types', 'nashenski-commerce' ); ?></legend>
							<label><input type="checkbox" name="nashenski_setup[types][]" value="office" checked> <?php esc_html_e( 'To an office', 'nashenski-commerce' ); ?></label><br>
							<label><input type="checkbox" name="nashenski_setup[types][]" value="locker" checked> <?php esc_html_e( 'To a parcel locker', 'nashenski-commerce' ); ?></label><br>
							<label><input type="checkbox" name="nashenski_setup[types][]" value="address" checked> <?php esc_html_e( 'To an address', 'nashenski-commerce' ); ?></label>
							<p class="description"><?php esc_html_e( 'One shipping method per courier and type. BOX NOW delivers to parcel lockers only.', 'nashenski-commerce' ); ?></p>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nashenski-setup-free_over"><?php esc_html_e( 'Free shipping over', 'nashenski-commerce' ); ?></label></th>
					<td>
						<input type="number" min="0" step="0.01" class="small-text" id="nashenski-setup-free_over" name="nashenski_setup[free_over]" value="" aria-describedby="nashenski-setup-free_over-description">
						<p class="description" id="nashenski-setup-free_over-description"><?php esc_html_e( 'Optional. Orders at or above this total ship for free with the new methods. Leave empty for no free shipping.', 'nashenski-commerce' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Checkout look', 'nashenski-commerce' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="nashenski-setup-layout"><?php esc_html_e( 'Shipping options', 'nashenski-commerce' ); ?></label></th>
					<td>
						<select id="nashenski-setup-layout" name="nashenski_setup[layout]">
							<option value="plain"<?php selected( $layout, 'plain' ); ?>><?php esc_html_e( 'Plain: keep the look of the theme or builder', 'nashenski-commerce' ); ?></option>
							<option value="cards"<?php selected( $layout, 'cards' ); ?>><?php esc_html_e( 'Cards: each option in its own box, the chosen one opens its office or address fields', 'nashenski-commerce' ); ?></option>
							<option value="buttons"<?php selected( $layout, 'buttons' ); ?>><?php esc_html_e( 'Side by side: the options as buttons in a row, the fields of the chosen one below them', 'nashenski-commerce' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Free shipping', 'nashenski-commerce' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Free shipping', 'nashenski-commerce' ); ?></legend>
							<label><input type="checkbox" name="nashenski_setup[free_over_label]" value="yes"<?php checked( 'yes' === nashenski_get_option( 'appearance', 'free_over_label' ) ); ?>> <?php esc_html_e( 'Show "Free shipping over" under each option', 'nashenski-commerce' ); ?></label><br>
							<label><input type="checkbox" name="nashenski_setup[free_progress]" value="yes"<?php checked( 'yes' === nashenski_get_option( 'appearance', 'free_progress' ) ); ?>> <?php esc_html_e( 'Show a free shipping progress bar', 'nashenski-commerce' ); ?></label>
						</fieldset>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Recommended settings', 'nashenski-commerce' ); ?></h2>
			<p>
				<label><input type="checkbox" name="nashenski_setup[recommended]" value="yes"> <?php esc_html_e( 'Apply the recommended WooCommerce settings', 'nashenski-commerce' ); ?></label>
				<a href="<?php echo esc_url( Settings::url( 'help' ) . '#recommended' ); ?>"><?php esc_html_e( 'See the list', 'nashenski-commerce' ); ?></a>
			</p>

			<?php submit_button( __( 'Save and finish', 'nashenski-commerce' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Whether a tax class has a rate for Bulgaria.
	 *
	 * @param string $tax_class Tax class slug.
	 * @return bool
	 */
	private static function has_bg_rate( $tax_class ) {
		return in_array( 'BG', wp_list_pluck( \WC_Tax::get_rates_for_tax_class( $tax_class ), 'tax_rate_country' ), true );
	}
}
