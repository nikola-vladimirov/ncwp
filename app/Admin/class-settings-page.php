<?php
/**
 * Settings page.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Nashenski settings as a native tab in WooCommerce > Settings.
 *
 * Every section is built from the existing tab classes (Tabs\*_Tab::load_fields()), so the
 * field definitions, the "save to show more options" flow and the stored options
 * (nashenski_settings_{group}_{name}) stay exactly as they were. Only the screen changed:
 * WooCommerce renders and saves the fields with its own settings API.
 */
class Settings_Page extends \WC_Settings_Page {
	const TAB    = Settings::TAB;
	const RELOAD = 'nashenski_reload_courier_data';

	/**
	 * Old tab objects keyed by their slug.
	 *
	 * @var Tabs\Base_Tab[]|null
	 */
	private $tabs = null;

	/**
	 * Courier options shown under "More options" (closed), by settings group. Names and values are unchanged.
	 */
	const MORE_OPTIONS = array(
		'econt'            => array( 'force_variations_in_desc', 'label_after_checkout', 'declared_value', 'auto_size', 'pay_options', 'sender_payment_type', 'invoice_or_packing_list' ),
		'speedy'           => array( 'ppp', 'kb', 'administrative_fee', 'force_variations_in_desc', 'label_after_checkout', 'declared_value', 'auto_size' ),
		'boxnow_send_from' => array( 'allow_return', 'label_after_checkout' ),
		'pigeon'           => array( 'label_after_checkout', 'auto_size' ),
		'pigeon_services'  => array( 'pay_with_pos', 'declared_value', 'paper_return_receipt', 'return_receipt', 'service_return_documents', 'id_verification_and_document_signature', 'cod_card_fee' ),
	);

	/**
	 * Sender details stored in the courier account group; shown in the "Where you send from" box (option names unchanged).
	 */
	const SENDER_FIELDS = array( 'name', 'phone', 'email', 'send_from' );

	/**
	 * Shown in place of a saved login, key or secret, so the page source never holds it. Posting it back keeps the saved value.
	 */
	const SECRET_MASK = '********';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = self::TAB;
		$this->label = __( 'Nashenski Commerce', 'nashenski-commerce' );

		parent::__construct();

		add_action( 'woocommerce_admin_field_nashenski_box', array( __CLASS__, 'output_box' ) );
		add_action( 'woocommerce_admin_field_nashenski_cols', array( __CLASS__, 'output_cols' ) );
		add_action( 'woocommerce_admin_field_nashenski_more', array( __CLASS__, 'output_more' ) );
		add_action( 'woocommerce_admin_field_nashenski_rows', array( __CLASS__, 'output_rows_field' ) );
		add_action( 'woocommerce_admin_field_nashenski_methods', array( __CLASS__, 'output_methods_field' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option', array( __CLASS__, 'keep_secret' ), 5, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option', array( __CLASS__, 'sanitize_rules_field' ), 6, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option', array( __CLASS__, 'sanitize_rows_field' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option', array( __CLASS__, 'sanitize_methods_field' ), 10, 3 );
		add_action( 'woocommerce_admin_field_nashenski_image', array( __CLASS__, 'output_image_field' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option', array( __CLASS__, 'sanitize_image_field' ), 10, 3 );
		add_action( 'woocommerce_admin_field_nashenski_price_list', array( __CLASS__, 'output_price_list_field' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option', array( __CLASS__, 'sanitize_price_list_field' ), 10, 3 );
		add_action( 'woocommerce_admin_field_nashenski_style', array( '\Nashenski\Front_End\Checkout\Appearance', 'output_style_field' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option', array( '\Nashenski\Front_End\Checkout\Appearance', 'sanitize_style_field' ), 10, 3 );
		add_action( 'woocommerce_admin_field_nashenski_field_layout', array( '\Nashenski\Front_End\Checkout\Field_Editor', 'output_layout_field' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option', array( '\Nashenski\Front_End\Checkout\Field_Editor', 'sanitize_layout_field' ), 10, 3 );
	}

	/**
	 * The tab objects that hold the field definitions, created once and only when this page is used.
	 */
	private function tabs() {
		if ( null === $this->tabs ) {
			$this->tabs = array();

			foreach ( Admin_Menus::get_tabs() as $tab ) {
				$this->tabs[ $tab->get_tab_slug() ] = $tab;
			}
		}

		return $this->tabs;
	}

	/**
	 * Tab.
	 *
	 * @param mixed $slug Slug.
	 * @return mixed Result.
	 */
	private function tab( $slug ) {
		$tabs = $this->tabs();

		return $tabs[ $slug ] ?? null;
	}

	/**
	 * Courier slugs that are turned on, in display order.
	 */
	private function couriers() {
		return array_values( array_intersect( \Nashenski\Plugin::COURIERS, array_keys( $this->tabs() ) ) );
	}

	/**
	 * Current courier.
	 *
	 * @return mixed Result.
	 */
	private function current_courier() {
		$couriers = $this->couriers();
		$courier  = self::requested_courier();

		return in_array( $courier, $couriers, true ) ? $courier : ( $couriers[0] ?? '' );
	}

	/**
	 * The courier the request names: the form being saved names its own (WooCommerce's settings
	 * nonce, checked again here), a link carries the nonce of Settings::courier_url(). Otherwise
	 * none, and the first courier shows.
	 *
	 * @return string
	 */
	private static function requested_courier() {
		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'woocommerce-settings' ) ) {
			return isset( $_POST['nashenski_courier'] ) ? sanitize_key( wp_unslash( $_POST['nashenski_courier'] ) ) : '';
		}

		if ( isset( $_GET['_nashenski_courier'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_nashenski_courier'] ) ), 'nashenski_courier' ) ) {
			return isset( $_GET['courier'] ) ? sanitize_key( wp_unslash( $_GET['courier'] ) ) : '';
		}

		return '';
	}

	/**
	 * The sections and what each one shows. A part is array( tab slug, groups or null for all groups, optional field names ).
	 */
	private function layout() {
		$layout = array(
			''                => array(
				'label' => __( 'Overview', 'nashenski-commerce' ),
				'parts' => array( array( 'settings', array( 'apis' ) ) ),
			),
			'couriers'        => array(
				'label' => __( 'Couriers', 'nashenski-commerce' ),
				'parts' => $this->current_courier() ? array( array( $this->current_courier(), null ) ) : array(),
			),
			'checkout'        => array(
				'label' => __( 'Checkout', 'nashenski-commerce' ),
				'parts' => array( array( 'shipping', null ), array( 'fields', null ) ),
			),
			// Always in the menu: the on/off switch first, the settings once it is on.
			'quick_order'     => array(
				'label' => __( 'Quick order', 'nashenski-commerce' ),
				'parts' => array( array( 'settings', array( 'apis' ), array( 'enable_quick_order' ) ), array( 'quick_order', null ) ),
			),
			'abandoned_carts' => array(
				'label' => __( 'Abandoned carts', 'nashenski-commerce' ),
				'parts' => array( array( 'settings', array( 'apis' ), array( 'enable_abandoned_carts' ) ), array( 'abandoned_carts', null ) ),
			),
			'documents'       => array(
				'label' => __( 'Documents', 'nashenski-commerce' ),
				'parts' => array( array( 'documents', null ) ),
			),
			'checks'          => array(
				'label' => __( 'Customer checks', 'nashenski-commerce' ),
				'parts' => array( array( 'settings', array( 'reports' ) ), array( 'nekorekten', null ), array( 'connectix', null ), array( 'nepostop', null ) ),
			),
			'exports'         => array(
				'label' => __( 'Exports', 'nashenski-commerce' ),
				'page'  => 'export',
			),
			'help'            => array(
				'label' => __( 'Help', 'nashenski-commerce' ),
				'page'  => 'help',
			),
		);

		foreach ( $layout as $id => $section ) {
			if ( isset( $section['page'] ) ) {
				continue;
			}

			$layout[ $id ]['parts'] = array_values(
				array_filter(
					$section['parts'],
					function ( $part ) {
						return (bool) $this->tab( $part[0] );
					}
				)
			);

			// With documents off the section would only hold the company fields, which nothing uses.
			if ( ( ! $layout[ $id ]['parts'] || ( 'documents' === $id && ! $this->tab( 'documents' ) ) ) && '' !== $id ) {
				unset( $layout[ $id ] );
			}
		}

		return $layout;
	}

	/**
	 * Get own sections.
	 *
	 * @return mixed Result.
	 */
	protected function get_own_sections() {
		return wp_list_pluck( $this->layout(), 'label' );
	}

	/**
	 * Section.
	 *
	 * @param mixed $id Id.
	 * @return mixed Result.
	 */
	private function section( $id ) {
		$layout = $this->layout();

		return $layout[ $id ] ?? null;
	}

	/**
	 * Section tabs.
	 *
	 * @param mixed $id Id.
	 * @return Tabs\Base_Tab[] The tab objects shown in a section.
	 */
	private function section_tabs( $id ) {
		$section = $this->section( $id );
		$tabs    = array();

		foreach ( $section['parts'] ?? array() as $part ) {
			$tabs[ $part[0] ] = $this->tab( $part[0] );
		}

		return array_filter( $tabs );
	}

	/**
	 * Get settings for section core.
	 *
	 * @param mixed $section_id Section id.
	 * @return mixed Result.
	 */
	protected function get_settings_for_section_core( $section_id ) {
		$section  = $this->section( $section_id );
		$settings = array();

		if ( ! $section || isset( $section['page'] ) ) {
			return $settings;
		}

		foreach ( $section['parts'] as $part ) {
			$tab = $this->tab( $part[0] );

			// Checkout reads top to bottom: countries, the field table, then order notes.
			if ( 'checkout' === $section_id && 'fields' === $part[0] ) {
				$settings = array_merge( $settings, \Nashenski\Front_End\Checkout\Field_Editor::settings() );
			}

			if ( ! $tab || ! method_exists( $tab, 'load_fields' ) ) {
				continue;
			}

			$tab->load_fields();
			$titles = method_exists( $tab, 'get_groups_titles' ) ? $tab->get_groups_titles() : array();
			// Courier options stay visible but greyed out until the login works; only the login itself is editable.
			$locked = method_exists( $tab, 'connected' ) && ! $tab->connected();
			$login  = array_merge( array( 'env' ), \Nashenski\Front_End\Checkout\Pickers::LOGIN[ $part[0] ] ?? array() );

			// Pairs of ( stored group, field ): a field shown in another box keeps its own option name.
			$groups = array();

			foreach ( (array) $tab->get_fields() as $group => $fields ) {
				foreach ( (array) $fields as $field ) {
					$groups[ $group ][] = array( $group, $field );
				}
			}

			// Couriers: the account box holds the connection, the send-from box the sender (name, phone, email, choice).
			$send_from = $part[0] . '_send_from';

			if ( 'couriers' === $section_id && isset( $groups[ $part[0] ], $groups[ $send_from ] ) ) {
				$sender = array();

				foreach ( $groups[ $part[0] ] as $i => $pair ) {
					if ( in_array( $pair[1]->get_name(), self::SENDER_FIELDS, true ) ) {
						$sender[] = $pair;
						unset( $groups[ $part[0] ][ $i ] );
					}
				}

				$groups[ $send_from ] = array_merge( $sender, $groups[ $send_from ] );
			}

			$columns = 'couriers' === $section_id && ! empty( $groups[ $part[0] ] ) && ! empty( $groups[ $send_from ] );

			foreach ( $groups as $group => $pairs ) {
				if ( empty( $pairs ) || ( null !== $part[1] && ! in_array( $group, $part[1], true ) ) ) {
					continue;
				}

				// Account and send-from boxes sit side by side (admin.css).
				if ( $columns && $group === $part[0] ) {
					$settings[] = array( 'type' => 'nashenski_cols' );
				}

				$settings[] = array(
					'type'  => 'title',
					'id'    => 'nashenski_' . $group,
					// A part that picks single fields (a feature switch) shows them without the group heading.
					'title' => isset( $titles[ $group ]['title'] ) && ! isset( $part[2] ) ? wp_strip_all_tags( $titles[ $group ]['title'] ) : '',
					'desc'  => trim( ( isset( $part[2] ) ? '' : ( $titles[ $group ]['desc'] ?? '' ) ) . ( $locked && $group !== $part[0] ? ' ' . __( 'Available once the courier login works.', 'nashenski-commerce' ) : '' ) ),
				);

				$more = array();

				foreach ( $pairs as list( $from, $field ) ) {
					if ( isset( $part[2] ) && ! in_array( $field->get_name(), $part[2], true ) ) {
						continue;
					}

					$setting = self::to_wc_setting( $from, $field );
					$setting = $locked && ! ( $from === $part[0] && in_array( $field->get_name(), $login, true ) ) ? self::lock( $setting ) : $setting;

					if ( 'couriers' === $section_id && in_array( $field->get_name(), self::MORE_OPTIONS[ $from ] ?? array(), true ) ) {
						$more[] = $setting;
					} else {
						$settings[] = $setting;
					}
				}

				// The rarer courier options sit in one closed group below the main fields.
				if ( $more ) {
					$settings = array_merge(
						$settings,
						array( array( 'type' => 'nashenski_more' ) ),
						$more,
						array(
							array(
								'type' => 'nashenski_more',
								'end'  => true,
							),
						)
					);
				}

				$settings[] = array(
					'type' => 'sectionend',
					'id'   => 'nashenski_' . $group,
				);

				if ( $columns && $group === $send_from ) {
					$settings[] = array(
						'type' => 'nashenski_cols',
						'end'  => true,
					);
				}
			}
		}

		if ( 'couriers' === $section_id ) {
			$settings = array_merge( $settings, $this->price_list_settings( $this->current_courier() ) );
		}

		if ( '' === $section_id ) {
			$settings = array_merge( $settings, \Nashenski\Shipping\Automation::settings() );
		}

		if ( 'checkout' === $section_id ) {
			$look = \Nashenski\Front_End\Checkout\Appearance::settings();
			// The colour and size fields (one run of rows) sit in a closed group under layout and delivery type.
			$styles = array_keys( wp_list_pluck( $look, 'type' ), 'nashenski_style', true );

			if ( $styles ) {
				array_splice(
					$look,
					end( $styles ) + 1,
					0,
					array(
						array(
							'type' => 'nashenski_more',
							'end'  => true,
						),
					)
				);
				array_splice(
					$look,
					$styles[0],
					0,
					array(
						array(
							'type'  => 'nashenski_more',
							'title' => __( 'Colours and sizes', 'nashenski-commerce' ),
						),
					)
				);
			}

			$settings = array_merge( $settings, $look, \Nashenski\Front_End\Withdrawal::settings() );
		}

		return self::boxes( Setting_Help::apply( $settings ) );
	}

	/**
	 * Greyed out until the courier login works. WooCommerce skips it on save, so the saved value stays.
	 *
	 * @param mixed $setting Setting.
	 */
	private static function lock( $setting ) {
		$setting['custom_attributes']['disabled'] = 'disabled';
		$setting['is_option']                     = false;

		if ( 'select' === $setting['type'] && empty( $setting['options'] ) ) {
			$setting['options'] = array( '' => __( 'Available once the courier login works', 'nashenski-commerce' ) );
		}

		return $setting;
	}

	/**
	 * Each group in its own box; the boxes flow in two columns on wide screens (admin.css).
	 *
	 * @param mixed $settings Settings.
	 */
	private static function boxes( $settings ) {
		$boxed = array();

		foreach ( $settings as $setting ) {
			if ( 'title' === ( $setting['type'] ?? '' ) ) {
				$boxed[] = array( 'type' => 'nashenski_box' );
			}

			$boxed[] = $setting;

			if ( 'sectionend' === ( $setting['type'] ?? '' ) ) {
				$boxed[] = array(
					'type' => 'nashenski_box',
					'end'  => true,
				);
			}
		}

		return $boxed;
	}

	/**
	 * Output more.
	 *
	 * @param mixed $value Value.
	 */
	public static function output_more( $value ) {
		echo empty( $value['end'] )
			? '</table><details class="nashenski-more"><summary>' . esc_html( ! empty( $value['title'] ) ? $value['title'] : __( 'More options', 'nashenski-commerce' ) ) . '</summary><table class="form-table">'
			: '</table></details><table class="form-table">';
	}

	/**
	 * Output cols.
	 *
	 * @param mixed $value Value.
	 */
	public static function output_cols( $value ) {
		echo empty( $value['end'] ) ? '<div class="nashenski-cols">' : '</div>';
	}

	/**
	 * Output box.
	 *
	 * @param mixed $value Value.
	 */
	public static function output_box( $value ) {
		echo empty( $value['end'] ) ? '<div class="card nashenski-box">' : '</div>';
	}

	/**
	 * Map one of our field objects to a WooCommerce settings array (same option name).
	 *
	 * @param mixed $group Group.
	 * @param mixed $field Field.
	 */
	public static function to_wc_setting( $group, $field ) {
		$setting = array(
			'id'       => 'nashenski_settings_' . $group . '_' . $field->get_name(),
			'title'    => $field->get_title(),
			'desc'     => $field->get_desc(),
			'desc_tip' => $field->get_help_text() ? $field->get_help_text() : false,
		);

		switch ( $field->get_type() ) {
			case 'true_false':
				$setting['type']    = 'checkbox';
				$setting['default'] = 'yes' === $field->get_default() ? 'yes' : 'no';

				// A checkbox reads best with its explanation as the label next to it.
				if ( '' === $setting['desc'] ) {
					$setting['desc'] = __( 'Enable', 'nashenski-commerce' );
				}
				break;

			case 'select':
				$options = array();

				foreach ( (array) $field->get_options() as $key => $option ) {
					$id             = is_array( $option ) && isset( $option['id'] ) ? (string) $option['id'] : (string) $key;
					$options[ $id ] = is_array( $option ) && isset( $option['label'] ) ? wp_strip_all_tags( $option['label'] ) : (string) $option;
				}

				$setting['type']    = 'select';
				$setting['class']   = 'wc-enhanced-select';
				$setting['css']     = 'width: 100%;';
				$setting['options'] = $options;
				$setting['default'] = null !== $field->get_default() ? (string) $field->get_default() : (string) array_key_first( $options );
				break;

			case 'color':
				$setting['type']    = 'color';
				$setting['css']     = 'width: 7em;';
				$setting['default'] = (string) $field->get_default();
				break;

			case 'image':
				$setting['type'] = 'nashenski_image';
				break;

			case 'methods':
				$setting['type']    = 'nashenski_methods';
				$setting['choices'] = (array) $field->get_choices();
				break;

			case 'multi':
			case 'multi_two':
				$setting['type']   = 'nashenski_rows';
				$setting['two']    = 'multi_two' === $field->get_type();
				$setting['labels'] = $field->get_fields_types();
				break;

			default:
				$subtype         = method_exists( $field, 'get_subtype' ) ? $field->get_subtype() : 'text';
				$rules           = (string) $field->get_validation_rules();
				$setting['type'] = in_array( $subtype, array( 'password', 'number' ), true ) ? $subtype : ( false !== strpos( $rules, 'email' ) ? 'email' : 'text' );

				if ( null !== $field->get_default() ) {
					$setting['default'] = $field->get_default();
				}

				$setting['nashenski_rules'] = $rules;

				if ( self::is_secret( $setting['id'], $setting['type'] ) && '' !== (string) get_option( $setting['id'], '' ) ) {
					$setting['value'] = self::SECRET_MASK;
				}
				break;
		}

		return array_merge( $setting, $field->get_wc() );
	}

	/**
	 * Output sections.
	 */
	public function output_sections() {
		?>
		<div class="nashenski-brand">
			<img src="<?php echo esc_url( nashenski()->plugin_dir_url() . 'assets/images/logo.png' ); ?>" width="24" height="24" alt="">
			<span><?php esc_html_e( 'Nashenski Commerce', 'nashenski-commerce' ); ?></span>
			<span class="nashenski-brand__version"><?php echo esc_html( \Nashenski\Plugin::VERSION ); ?></span>
		</div>
		<?php

		parent::output_sections();
	}

	/**
	 * Output.
	 */
	public function output() {
		global $current_section;

		$id      = (string) $current_section;
		$section = $this->section( $id );

		Migration::render();
		Import_Sources::render();
		self::output_price_update_notice();

		if ( ! $section ) {
			return;
		}

		if ( isset( $section['page'] ) ) {
			$GLOBALS['hide_save_button'] = true; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce's own switch for its Save button.

			if ( 'export' === $section['page'] ) {
				if ( $this->tab( 'export' ) ) {
					self::output_export();
				} else {
					echo '<p class="nashenski-builder__note">' . esc_html__( 'Turn on documents in the Overview to also get the NRA audit file, the invoice archive and the Microinvest Delta file here.', 'nashenski-commerce' ) . '</p>';
				}

				Export_Builder::output();
			} else {
				Help_Page::output();
			}

			return;
		}

		if ( 'couriers' === $id ) {
			// The save reads the courier from the form, not from a link that may have expired.
			echo '<input type="hidden" name="nashenski_courier" value="' . esc_attr( $this->current_courier() ) . '">';
			$this->output_courier_nav();
			$this->maybe_reload_courier_data( $this->current_courier() );
		}

		foreach ( $this->section_tabs( $id ) as $slug => $tab ) {
			$this->output_tab_notes( $slug, $tab );
		}

		// Checkout boxes stack in one column so the screen reads top to bottom (admin.css).
		echo '<div class="nashenski-boxes' . ( 'checkout' === $id ? ' nashenski-boxes--stack' : '' ) . '">';
		parent::output();
		echo '</div>';
	}

	/**
	 * Second row of links in the Couriers section: one per courier that is turned on.
	 */
	private function output_courier_nav() {
		$couriers = $this->couriers();
		$current  = $this->current_courier();
		$logos    = \Nashenski\Front_End\Checkout\Courier_Logos::get_logos();

		if ( count( $couriers ) < 2 ) {
			return;
		}

		echo '<nav class="nashenski-courier-nav" aria-label="' . esc_attr__( 'Couriers', 'nashenski-commerce' ) . '">';

		foreach ( $couriers as $courier ) {
			printf(
				'<a href="%1$s" class="nashenski-courier-nav__item%2$s"%3$s>%4$s<span>%5$s</span></a>',
				esc_url( Settings::courier_url( $courier ) ),
				$courier === $current ? ' is-current' : '',
				$courier === $current ? ' aria-current="page"' : '',
				isset( $logos[ $courier ] ) ? '<img src="' . esc_url( $logos[ $courier ] ) . '" alt="">' : '',
				esc_html( $this->tab( $courier )->get_name() )
			);
		}

		echo '</nav>';
	}

	/**
	 * Connection status and the reload button of courier and customer check parts.
	 *
	 * @param mixed $slug Slug.
	 * @param mixed $tab Tab.
	 */
	private function output_tab_notes( $slug, $tab ) {
		$is_courier = in_array( $slug, $this->couriers(), true );
		// auth_test() may call the service, so call it once.
		$error     = method_exists( $tab, 'auth_test' ) ? trim( (string) $tab->auth_test() ) : '';
		$has_error = '' !== trim( wp_strip_all_tags( $error, true ) );

		if ( $has_error ) {
			echo '<div class="notice notice-warning inline">' . wp_kses_post( $error ) . '</div>';
		} elseif ( $is_courier ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html(
				sprintf(
				/* translators: %s: courier name */
					__( 'Connected to %s. Prices and labels use your account.', 'nashenski-commerce' ),
					$tab->get_name()
				)
			) . '</p></div>';
		}

		// BOX NOW gives no rate until the price type is saved (Shipping\BoxNow\Method).
		if ( 'boxnow' === $slug && ! nashenski_get_option( 'boxnow_price', 'price_type' ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'BOX NOW delivery is off. Click Save to choose how prices are set. After you save, BOX NOW delivery will work.', 'nashenski-commerce' ) . '</p></div>';
		}

		if ( $is_courier ) {
			$url = wp_nonce_url( add_query_arg( self::RELOAD, 1, Settings::courier_url( $slug ) ), self::RELOAD );

			printf(
				'<p class="nashenski-section-actions"><a class="button" href="%1$s">%2$s</a> <span class="description">%3$s</span></p>',
				esc_url( $url ),
				esc_html__( 'Reload offices and cities', 'nashenski-commerce' ),
				esc_html__( 'Downloads the latest cities, offices and lockers (and your profile) from the courier. They also update by themselves once a week.', 'nashenski-commerce' )
			);
		}
	}

	/**
	 * Maybe reload courier data.
	 *
	 * @param mixed $courier Courier.
	 */
	private function maybe_reload_courier_data( $courier ) {
		// Only the Reload link carries this nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::RELOAD ) ) {
			return;
		}

		if ( ! in_array( $courier, \Nashenski\Plugin::COURIERS, true ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$class = nashenski()->container()[ 'client.' . $courier ];
		$class::clear_cache_folder();

		if ( method_exists( $class, 'clear_profile_data' ) ) {
			$class::clear_profile_data();
		}

		echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Offices and cities will be downloaded again the next time they are needed.', 'nashenski-commerce' ) . '</p></div>';
	}

	/**
	 * Save.
	 */
	public function save() {
		global $current_section;

		$id      = (string) $current_section;
		$section = $this->section( $id );

		if ( ! $section || isset( $section['page'] ) ) {
			return;
		}

		parent::save();

		// A feature switch may have changed: rebuild the tab list so its settings show on this same page load.
		$this->tabs = null;

		// WooCommerce read the settings before saving them, which built the courier clients from the old
		// login and environment. Rebuild them so the login check below and the page use the saved ones.
		$container = nashenski()->container();
		foreach ( array_keys( \Nashenski\Container\Client::SERVICES ) as $service ) {
			unset( $container[ $service ] );
		}
		$container->register( new \Nashenski\Container\Client() );

		// Courier and customer check parts check the login and fill profile data after saving.
		foreach ( $this->section_tabs( $id ) as $tab ) {
			self::run_after_save_fields( get_class( $tab ) );
		}
	}

	/**
	 * The weekly check found published prices that differ from the saved ones.
	 */
	private static function output_price_update_notice() {
		// Only the Dismiss link below carries this nonce.
		if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nashenski_dismiss_prices' ) ) {
			if ( current_user_can( 'manage_woocommerce' ) ) {
				\Nashenski\Pricing\Updater::forget();
			}
		}

		$found = get_option( \Nashenski\Pricing\Updater::NOTICE_OPTION, array() );

		if ( empty( $found ) || ! is_array( $found ) ) {
			return;
		}

		$names = nashenski_couriers();
		$links = array();

		foreach ( $found as $courier => $date ) {
			if ( isset( $names[ $courier ] ) ) {
				$links[] = '<a href="' . esc_url( Settings::courier_url( $courier ) ) . '">' . esc_html( $names[ $courier ] ) . '</a>';
			}
		}

		if ( $links ) {
			echo '<div class="notice notice-info inline"><p>' . wp_kses_post(
				sprintf(
				/* translators: %s: courier names with links */
					__( 'New published prices were found for %s. Open the courier, press "Check the courier website" and decide whether to use them.', 'nashenski-commerce' ),
					implode( ', ', $links )
				)
			) . ' <a href="' . esc_url( wp_nonce_url( add_query_arg( 'nashenski_dismiss_prices', 1 ), 'nashenski_dismiss_prices' ) ) . '">' . esc_html__( 'Dismiss', 'nashenski-commerce' ) . '</a></p></div>';
		}
	}

	/**
	 * Couriers section: the "Public prices" group of the current courier.
	 *
	 * @param mixed $courier Courier.
	 */
	private function price_list_settings( $courier ) {
		if ( ! in_array( $courier, \Nashenski\Pricing\Price_Lists::COURIERS, true ) ) {
			return array();
		}

		$list = \Nashenski\Pricing\Price_Lists::get( $courier );

		return array(
			array(
				'type'  => 'title',
				'id'    => 'nashenski_prices_' . $courier,
				'title' => __( 'Public prices', 'nashenski-commerce' ),
				'desc'  => sprintf(
					/* translators: 1: link to the courier price list, 2: date */
					__( 'Used to price orders when this courier is not connected or does not answer, so customers can always order. The built-in prices come from the %1$s (valid from %2$s). Edit them, or let Nashenski Commerce check the courier website for newer prices.', 'nashenski-commerce' ),
					'<a href="' . esc_url( $list['source'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'courier\'s published price list', 'nashenski-commerce' ) . '</a>',
					esc_html( $list['valid_from'] ? date_i18n( get_option( 'date_format' ), strtotime( $list['valid_from'] ) ) : __( 'unknown', 'nashenski-commerce' ) )
				),
			),
			array(
				'type'    => 'checkbox',
				'id'      => 'nashenski_settings_prices_use_public_' . $courier,
				'title'   => __( 'Use public prices', 'nashenski-commerce' ),
				'desc'    => __( 'Price orders with this list when there is no working courier login', 'nashenski-commerce' ),
				'default' => 'yes',
			),
			array(
				'type'    => 'nashenski_price_list',
				'id'      => \Nashenski\Pricing\Price_Lists::option_name( $courier ),
				'title'   => __( 'Price list', 'nashenski-commerce' ),
				'courier' => $courier,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'nashenski_prices_' . $courier,
			),
		);
	}

	/**
	 * Editable price list: weight rows with office, locker and address prices, the rule above the
	 * last row, VAT, surcharge and cash on delivery fee.
	 *
	 * @param mixed $value Value.
	 */
	public static function output_price_list_field( $value ) {
		$courier = $value['courier'];
		$list    = \Nashenski\Pricing\Price_Lists::get( $courier );
		$name    = $value['id'];
		$kinds   = array(
			'office'  => __( 'To an office', 'nashenski-commerce' ),
			'locker'  => __( 'To a locker', 'nashenski-commerce' ),
			'address' => __( 'To an address', 'nashenski-commerce' ),
		);
		$number  = function ( $field, $current, $step = '0.01' ) {
			return sprintf( '<input type="number" min="0" step="%1$s" name="%2$s" value="%3$s">', esc_attr( $step ), esc_attr( $field ), esc_attr( null === $current ? '' : $current ) );
		};
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><label><?php echo esc_html( $value['title'] ); ?></label></th>
			<td class="forminp nashenski-price-list" data-name="<?php echo esc_attr( $name ); ?>" data-courier="<?php echo esc_attr( $courier ); ?>" data-defaults="<?php echo esc_attr( wp_json_encode( \Nashenski\Pricing\Price_Lists::normalize( \Nashenski\Pricing\Price_Lists::defaults( $courier ), $courier ) ) ); ?>">
				<table class="widefat striped nashenski-price-list__table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Up to (kg)', 'nashenski-commerce' ); ?></th>
							<?php foreach ( $kinds as $label ) : ?>
								<th><?php echo esc_html( $label ); ?></th>
							<?php endforeach; ?>
							<th class="nashenski-rows__remove"><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'nashenski-commerce' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $list['bands'] as $i => $band ) : ?>
							<tr>
								<td><?php echo wp_kses( $number( $name . '[bands][' . $i . '][to]', $band['to'], '0.001' ), nashenski_allowed_html() ); ?></td>
								<?php foreach ( array_keys( $kinds ) as $kind ) : ?>
									<td><?php echo wp_kses( $number( $name . '[bands][' . $i . '][' . $kind . ']', $band[ $kind ] ), nashenski_allowed_html() ); ?></td>
								<?php endforeach; ?>
								<td class="nashenski-rows__remove"><button type="button" class="button-link nashenski-price-list__delete" aria-label="<?php esc_attr_e( 'Remove row', 'nashenski-commerce' ); ?>">&times;</button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<th><?php esc_html_e( 'Heavier: base', 'nashenski-commerce' ); ?></th>
							<?php foreach ( array_keys( $kinds ) as $kind ) : ?>
								<td><?php echo wp_kses( $number( $name . '[over][base][' . $kind . ']', $list['over']['base'][ $kind ] ), nashenski_allowed_html() ); ?></td>
							<?php endforeach; ?>
							<td></td>
						</tr>
						<tr>
							<th><?php esc_html_e( '+ per kg above the last row', 'nashenski-commerce' ); ?></th>
							<?php foreach ( array_keys( $kinds ) as $kind ) : ?>
								<td><?php echo wp_kses( $number( $name . '[over][per_kg][' . $kind . ']', $list['over']['per_kg'][ $kind ] ), nashenski_allowed_html() ); ?></td>
							<?php endforeach; ?>
							<td></td>
						</tr>
					</tfoot>
				</table>
				<p><button type="button" class="button nashenski-price-list__add"><?php esc_html_e( 'Add row', 'nashenski-commerce' ); ?></button></p>

				<div class="nashenski-price-list__extras">
					<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[vat_included]" value="1" <?php checked( $list['vat_included'] ); ?>> <?php esc_html_e( 'Prices include VAT', 'nashenski-commerce' ); ?></label>
					<label><?php esc_html_e( 'Extra surcharge (fuel, toll) %', 'nashenski-commerce' ); ?> <?php echo wp_kses( $number( $name . '[surcharge]', $list['surcharge'] ), nashenski_allowed_html() ); ?></label>
					<label><?php esc_html_e( 'Cash on delivery fee %', 'nashenski-commerce' ); ?> <?php echo wp_kses( $number( $name . '[cod][percent]', $list['cod']['percent'] ), nashenski_allowed_html() ); ?></label>
					<label><?php esc_html_e( 'at least', 'nashenski-commerce' ); ?> <?php echo wp_kses( $number( $name . '[cod][min]', $list['cod']['min'] ), nashenski_allowed_html() ); ?></label>
					<label><?php esc_html_e( 'Valid from', 'nashenski-commerce' ); ?> <input type="date" name="<?php echo esc_attr( $name ); ?>[valid_from]" value="<?php echo esc_attr( $list['valid_from'] ); ?>"></label>
					<input type="hidden" name="<?php echo esc_attr( $name ); ?>[source]" value="<?php echo esc_attr( $list['source'] ); ?>">
				</div>

				<p class="nashenski-price-list__actions">
					<button type="button" class="button nashenski-price-list__check" data-nonce="<?php echo esc_attr( wp_create_nonce( 'nashenski_check_prices' ) ); ?>"><?php esc_html_e( 'Check the courier website', 'nashenski-commerce' ); ?></button>
					<button type="button" class="button-link nashenski-price-list__restore"><?php esc_html_e( 'Restore the built-in prices', 'nashenski-commerce' ); ?></button>
					<span class="spinner"></span>
				</p>
				<div class="nashenski-price-list__result" aria-live="polite"></div>
				<p class="description"><?php esc_html_e( 'Prices are for parcels you hand in at a courier office. Changes take effect after you save.', 'nashenski-commerce' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Runs the after-save hook: one name for every tab, with the tab's class as its argument. The older
	 * names had the class in them; they still run for add-ons listening on those.
	 *
	 * @param string $tab_class Tab class name.
	 */
	public static function run_after_save_fields( $tab_class ) {
		\Nashenski\Hooks::action( 'nashenski_admin_settings_after_save_fields', 'nashenski/admin/settings/after_save_fields', $tab_class );
		do_action_deprecated( 'nashenski_admin_settings_after_save_fields_' . $tab_class, array(), '0.21.10', 'nashenski_admin_settings_after_save_fields' );
		do_action_deprecated( 'nashenski/admin/settings/after_save_fields/' . $tab_class, array(), '0.19.9', 'nashenski_admin_settings_after_save_fields' );
		do_action_deprecated( 'nashenski/admin/settings/afte_save_fields/' . $tab_class, array(), '0.19.9', 'nashenski_admin_settings_after_save_fields' );
	}

	/**
	 * Whether a setting holds a login, key or secret (the screen shows a mask for it, WP-CLI the word "set").
	 *
	 * @param string $id   Option name.
	 * @param string $type Setting type.
	 * @return bool
	 */
	public static function is_secret( $id, $type = '' ) {
		// The filters run on every WooCommerce settings page; only our own free-text settings are masked (a choice such as profile_key is no secret).
		if ( 0 !== strpos( (string) $id, 'nashenski_settings_' ) || in_array( $type, array( 'select', 'multiselect', 'checkbox' ), true ) ) {
			return false;
		}

		return 'password' === $type || (bool) preg_match( '/(key|token|secret|password|_user|client_id)$/', $id );
	}

	/**
	 * A login, key or secret: the mask posted back unchanged means "keep the saved value". A new value is
	 * kept as typed, as WooCommerce keeps a password field (it only trims it): a real password may hold any
	 * printable character, "<" and "%" too. Only what no password has is taken out: control characters and
	 * text that is not valid UTF-8. The value goes to the courier or service it belongs to and is shown masked.
	 *
	 * @param mixed $value     Value.
	 * @param array $option    Setting definition.
	 * @param mixed $raw_value Raw value.
	 * @return mixed
	 */
	public static function keep_secret( $value, $option, $raw_value ) {
		if ( ! self::is_secret( (string) ( $option['id'] ?? '' ), (string) ( $option['type'] ?? '' ) ) ) {
			return $value;
		}

		if ( self::SECRET_MASK === $raw_value ) {
			return get_option( $option['id'], '' );
		}

		return is_string( $value ) ? trim( (string) preg_replace( '/[\x00-\x1F\x7F]/', '', wp_check_invalid_utf8( $value ) ) ) : $value;
	}

	/**
	 * Server-side check of what a text field declares: "required", "email" and "digits" in its rules, and a number field is never negative.
	 * A value that fails keeps the earlier one and tells the user, because the browser check alone is no check.
	 *
	 * @param mixed $value     Value.
	 * @param array $option    Setting definition.
	 * @param mixed $raw_value Raw value.
	 * @return mixed
	 */
	public static function sanitize_rules_field( $value, $option, $raw_value ) {
		if ( self::rules_valid( $value, $option ) ) {
			return $value;
		}

		if ( class_exists( 'WC_Admin_Settings' ) ) {
			/* translators: %s: name of the setting */
			\WC_Admin_Settings::add_error( sprintf( __( '"%s" was not saved: the value is not valid, so the earlier one stays.', 'nashenski-commerce' ), $option['title'] ?? $option['id'] ) );
		}

		return get_option( $option['id'], '' );
	}

	/**
	 * Whether a value passes the rules its field declares (see sanitize_rules_field()). WP-CLI checks with it too.
	 *
	 * @param mixed $value  Value.
	 * @param array $option Setting definition.
	 * @return bool
	 */
	public static function rules_valid( $value, $option ) {
		// Runs on every WooCommerce settings page; leave other plugins' and WooCommerce's own fields alone.
		if ( 0 !== strpos( (string) ( $option['id'] ?? '' ), 'nashenski_settings_' ) ) {
			return true;
		}

		$rules = array_filter( explode( '|', (string) ( $option['nashenski_rules'] ?? '' ) ) );

		if ( 'number' === ( $option['type'] ?? '' ) ) {
			$rules[] = 'amount';
		}

		if ( ! $rules || ! is_string( $value ) ) {
			return true;
		}

		$text = trim( $value );

		if ( '' === $text ) {
			// A required field that was never filled in stays as it is, without a notice on every save; only wiping a saved value is refused. A login or key can be cleared on purpose.
			return ! in_array( 'required', $rules, true ) || self::is_secret( (string) $option['id'], (string) ( $option['type'] ?? '' ) ) || '' === trim( (string) get_option( $option['id'], '' ) );
		}

		return ( ! in_array( 'email', $rules, true ) || is_email( $text ) )
			&& ( ! in_array( 'digits', $rules, true ) || ctype_digit( $text ) )
			&& ( ! in_array( 'amount', $rules, true ) || '' !== \Nashenski\Pricing\Price_Lists::non_negative( $text ) );
	}

	/**
	 * Runs the sanitizers of the custom field types, the same ones the settings screen runs on save.
	 * The command line calls this instead of the WooCommerce filter. Each sanitizer ignores the types it does not handle.
	 *
	 * @param mixed $value  Value to clean.
	 * @param array $option Setting definition.
	 * @return mixed
	 */
	public static function sanitize_value( $value, $option ) {
		$value = self::keep_secret( $value, $option, $value );
		$value = self::sanitize_rules_field( $value, $option, $value );
		$value = self::sanitize_rows_field( $value, $option, $value );
		$value = self::sanitize_methods_field( $value, $option, $value );
		$value = self::sanitize_image_field( $value, $option, $value );
		$value = self::sanitize_price_list_field( $value, $option, $value );
		$value = \Nashenski\Front_End\Checkout\Appearance::sanitize_style_field( $value, $option, $value );
		$value = \Nashenski\Front_End\Checkout\Field_Editor::sanitize_layout_field( $value, $option, $value );

		return $value;
	}

	/**
	 * Sanitize price list field.
	 *
	 * @param mixed $value Value.
	 * @param mixed $option Option.
	 * @param mixed $raw_value Raw value.
	 * @return mixed Result.
	 */
	public static function sanitize_price_list_field( $value, $option, $raw_value ) {
		if ( 'nashenski_price_list' !== ( $option['type'] ?? '' ) ) {
			return $value;
		}

		// The prices were reviewed and saved: this courier leaves the "new published prices" notice.
		\Nashenski\Pricing\Updater::forget( $option['courier'] );

		return wp_json_encode( \Nashenski\Pricing\Price_Lists::normalize( (array) $raw_value, $option['courier'] ) );
	}

	/**
	 * Price table field: rows of "from / to / price" (and "from price / to price").
	 * Stored as JSON, exactly like before.
	 *
	 * @param mixed $value Value.
	 */
	public static function output_rows_field( $value ) {
		$labels   = (array) ( $value['labels'] ?? array() );
		$two      = ! empty( $value['two'] );
		$rows     = json_decode( (string) get_option( $value['id'], '' ), true );
		$rows     = is_array( $rows ) && $rows ? array_values( $rows ) : array( array() );
		$columns  = $two ? array( 'from', 'to', 'from_price', 'to_price', 'price' ) : array( 'from', 'to', 'price' );
		$headers  = $two
			? array( $labels['type'] ?? '', $labels['type'] ?? '', $labels['type2'] ?? '', $labels['type2'] ?? '', $labels['price_text'] ?? '' )
			: array( $labels['type'] ?? '', $labels['type'] ?? '', $labels['price_text'] ?? '' );
		$prefixes = $two
			? array( __( 'From', 'nashenski-commerce' ), __( 'To', 'nashenski-commerce' ), __( 'From', 'nashenski-commerce' ), __( 'To', 'nashenski-commerce' ), '' )
			: array( __( 'From', 'nashenski-commerce' ), __( 'To', 'nashenski-commerce' ), '' );
		$name     = $value['id'];
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="forminp">
				<fieldset <?php disabled( ! empty( $value['custom_attributes']['disabled'] ) ); ?>>
				<table class="widefat striped nashenski-rows" data-name="<?php echo esc_attr( $name ); ?>" data-columns="<?php echo esc_attr( implode( ',', $columns ) ); ?>">
					<thead>
						<tr>
							<?php foreach ( $columns as $i => $column ) : ?>
								<th><?php echo esc_html( trim( $prefixes[ $i ] . ' ' . $headers[ $i ] ) ); ?></th>
							<?php endforeach; ?>
							<th class="nashenski-rows__remove"><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'nashenski-commerce' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $r => $row ) : ?>
							<tr>
								<?php foreach ( $columns as $column ) : ?>
									<td><input type="number" min="0" step="0.001" name="<?php echo esc_attr( $name . '[' . $r . '][' . $column . ']' ); ?>" value="<?php echo esc_attr( $row[ $column ] ?? '' ); ?>"></td>
								<?php endforeach; ?>
								<td class="nashenski-rows__remove"><button type="button" class="button-link nashenski-rows__delete" aria-label="<?php esc_attr_e( 'Remove row', 'nashenski-commerce' ); ?>">&times;</button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button nashenski-rows__add"><?php esc_html_e( 'Add row', 'nashenski-commerce' ); ?></button></p>
				</fieldset>
				<?php if ( ! empty( $value['desc'] ) ) : ?>
					<p class="description"><?php echo wp_kses_post( $value['desc'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Sanitize rows field.
	 *
	 * @param mixed $value Value.
	 * @param mixed $option Option.
	 * @param mixed $raw_value Raw value.
	 * @return mixed Result.
	 */
	public static function sanitize_rows_field( $value, $option, $raw_value ) {
		if ( 'nashenski_rows' !== ( $option['type'] ?? '' ) ) {
			return $value;
		}

		$columns = ! empty( $option['two'] ) ? array( 'from', 'to', 'from_price', 'to_price', 'price' ) : array( 'from', 'to', 'price' );
		$rows    = array();
		$refused = false;

		foreach ( (array) $raw_value as $row ) {
			$clean = array();

			foreach ( $columns as $column ) {
				$clean[ $column ] = isset( $row[ $column ] ) && '' !== $row[ $column ] ? \Nashenski\Pricing\Price_Lists::non_negative( wc_format_decimal( $row[ $column ] ) ) : '';
				// A value that was typed but is not a number from zero up must not become an empty cell (the row is refused below).
				$refused = $refused || ( isset( $row[ $column ] ) && '' !== $row[ $column ] && '' === $clean[ $column ] );
			}

			// A row with a range but no price gives no rate: refuse it.
			$refused = $refused || ( '' === $clean['price'] && '' !== implode( '', $clean ) );

			if ( implode( '', $clean ) !== '' ) {
				$rows[] = $clean;
			}
		}

		if ( $refused ) {
			if ( class_exists( 'WC_Admin_Settings' ) ) {
				/* translators: %s: name of the setting */
				\WC_Admin_Settings::add_error( sprintf( __( '"%s" was not saved: a weight or price must be a number from zero up, and every band needs a price, so the earlier list stays.', 'nashenski-commerce' ), $option['title'] ?? $option['id'] ) );
			}

			return get_option( $option['id'], '' );
		}

		return wp_json_encode( $rows );
	}

	/**
	 * Choices with a checkbox each. Saved as a JSON list of the checked keys.
	 *
	 * @param mixed $value Value.
	 */
	public static function output_methods_field( $value ) {
		$saved   = json_decode( (string) get_option( $value['id'], '' ), true );
		$saved   = is_array( $saved ) ? $saved : array();
		$choices = (array) ( $value['choices'] ?? array() );
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="forminp nashenski-methods-field">
				<fieldset>
					<?php if ( $choices ) : ?>
						<?php foreach ( $choices as $key => $label ) : ?>
							<label class="nashenski-methods-field__item"><input type="checkbox" name="<?php echo esc_attr( $value['id'] ); ?>[]" value="<?php echo esc_attr( $key ); ?>"<?php checked( in_array( $key, $saved, true ) ); ?>> <?php echo esc_html( $label ); ?></label><br>
						<?php endforeach; ?>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No payment method is turned on in WooCommerce yet.', 'nashenski-commerce' ); ?></p>
					<?php endif; ?>
				</fieldset>
				<?php if ( ! empty( $value['desc'] ) ) : ?>
					<p class="description"><?php echo wp_kses_post( $value['desc'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Sanitize methods field.
	 *
	 * @param mixed $value Value.
	 * @param mixed $option Option.
	 * @param mixed $raw_value Raw value.
	 * @return mixed Result.
	 */
	public static function sanitize_methods_field( $value, $option, $raw_value ) {
		if ( 'nashenski_methods' !== ( $option['type'] ?? '' ) ) {
			return $value;
		}

		// Only the methods that are listed can be saved.
		$checked = array_map( 'sanitize_text_field', (array) $raw_value );

		return wp_json_encode( array_values( array_intersect( array_keys( (array) ( $option['choices'] ?? array() ) ), $checked ) ) );
	}
	/**
	 * Image from the media library (stores the attachment ID).
	 *
	 * @param mixed $value Value.
	 */
	public static function output_image_field( $value ) {
		$id  = absint( get_option( $value['id'], 0 ) );
		$src = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="forminp nashenski-image-field">
				<input type="hidden" id="<?php echo esc_attr( $value['id'] ); ?>" name="<?php echo esc_attr( $value['id'] ); ?>" value="<?php echo esc_attr( $id ? $id : '' ); ?>">
				<div class="nashenski-image-field__preview"<?php echo $src ? '' : ' hidden'; ?>>
					<img src="<?php echo esc_url( $src ); ?>" alt="">
				</div>
				<p>
					<button type="button" class="button nashenski-image-field__choose" data-title="<?php echo esc_attr( $value['title'] ); ?>"><?php esc_html_e( 'Choose image', 'nashenski-commerce' ); ?></button>
					<button type="button" class="button-link button-link-delete nashenski-image-field__remove"<?php echo $id ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'nashenski-commerce' ); ?></button>
				</p>
				<?php if ( ! empty( $value['desc'] ) ) : ?>
					<p class="description"><?php echo wp_kses_post( $value['desc'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Sanitize image field.
	 *
	 * @param mixed $value Value.
	 * @param mixed $option Option.
	 * @param mixed $raw_value Raw value.
	 * @return mixed Result.
	 */
	public static function sanitize_image_field( $value, $option, $raw_value ) {
		if ( 'nashenski_image' !== ( $option['type'] ?? '' ) ) {
			return $value;
		}

		$id = absint( $raw_value );

		return $id && wp_attachment_is_image( $id ) ? $id : '';
	}

	/**
	 * Output export.
	 */
	private static function output_export() {
		$month   = gmdate( 'Y-m', strtotime( 'first day of last month' ) );
		$nonce   = wp_create_nonce( 'nashenski_export_nap' );
		$exports = array();

		if ( 'yes' === nashenski_get_option( 'invoice', 'nra_n18' ) ) {
			$exports[] = array(
				'action'         => 'nashenski_export_nap',
				'title'          => __( 'Standardised audit file (Annex 38)', 'nashenski-commerce' ),
				'desc'           => __( 'The monthly XML file for the NRA under Ordinance N-18. There is no limit on the number of orders.', 'nashenski-commerce' ),
				'button'         => __( 'Create XML file', 'nashenski-commerce' ),
				'generate_files' => true,
			);
		}

		$exports[] = array(
			'action' => 'nashenski_export_invoice_archive',
			'title'  => __( 'Invoice archive', 'nashenski-commerce' ),
			'desc'   => __( 'A ZIP file with all invoices and credit notes of the month.', 'nashenski-commerce' ),
			'button' => __( 'Download archive', 'nashenski-commerce' ),
		);

		$exports[] = array(
			'action' => 'nashenski_export_microinvest',
			'title'  => __( 'Microinvest Delta', 'nashenski-commerce' ),
			'desc'   => __( 'The documents of the month in the import format of Microinvest Delta.', 'nashenski-commerce' ),
			'button' => __( 'Create file', 'nashenski-commerce' ),
		);

		?>
		<h2 class="nashenski-ready-title"><?php esc_html_e( 'Ready-made files', 'nashenski-commerce' ); ?></h2>
		<div class="nashenski-ready">
		<?php
		foreach ( $exports as $export ) {
			$id = 'nashenski-export-' . sanitize_html_class( $export['action'] );
			?>
			<div class="nashenski-ready__card">
			<h3><?php echo esc_html( $export['title'] ); ?></h3>
			<p><?php echo esc_html( $export['desc'] ); ?></p>
			<div class="nashenski-export" data-action="<?php echo esc_attr( $export['action'] ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( $id ); ?>-month"><?php esc_html_e( 'Month', 'nashenski-commerce' ); ?></label></th>
						<td><input type="month" id="<?php echo esc_attr( $id ); ?>-month" data-field="year" value="<?php echo esc_attr( $month ); ?>" max="<?php echo esc_attr( gmdate( 'Y-m' ) ); ?>" required></td>
					</tr>
					<?php if ( ! empty( $export['generate_files'] ) ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Older orders', 'nashenski-commerce' ); ?></th>
							<td>
								<label><input type="checkbox" data-field="generate_files" value="true"> <?php esc_html_e( 'Create missing documents for orders placed before the plugin was installed', 'nashenski-commerce' ); ?></label>
							</td>
						</tr>
					<?php endif; ?>
				</table>
				<p>
					<button type="button" class="button button-primary nashenski-export__submit"><?php echo esc_html( $export['button'] ); ?></button>
					<span class="spinner"></span>
				</p>
				<div class="nashenski-export__result" aria-live="polite"></div>
			</div>
			</div>
			<?php
		}

		echo '</div>';
	}
}
