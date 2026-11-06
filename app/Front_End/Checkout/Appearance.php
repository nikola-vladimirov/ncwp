<?php
namespace Nashenski\Front_End\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * How the shipping options and pickers look.
 *
 * Each style setting is a CSS value. Empty settings follow the site: the design tokens of
 * TokenForge or another token-based CSS framework when one is active (var(--primary) and so on),
 * otherwise the theme's own text colour with light borders. The values become CSS custom
 * properties (--nashenski-*) used by assets/css/checkout.css.
 */
class Appearance {
	const GROUP = 'appearance';

	/**
	 * 'tokenforge', 'framework' (another token-based CSS framework) or '' (theme only).
	 */
	public static function framework() {
		if ( defined( 'TOKENFORGE_VERSION' ) || class_exists( '\TokenForge\Palette' ) ) {
			return 'tokenforge';
		}

		if ( defined( 'ACSS_PLUGIN_FILE' ) ) {
			return 'framework';
		}

		return '';
	}

	public static function has_builder() {
		return defined( 'BRICKS_VERSION' ) || 'bricks' === get_template();
	}

	public static function layout() {
		$layout = nashenski_get_option( self::GROUP, 'layout' );

		if ( in_array( $layout, array( 'cards', 'plain' ), true ) ) {
			return $layout;
		}

		return self::framework() || self::has_builder() ? 'cards' : 'plain';
	}

	public static function defaults() {
		if ( self::framework() ) {
			return array(
				'accent'       => 'var(--primary, currentColor)',
				'selected_bg'  => 'var(--primary-ultra-light, color-mix(in srgb, var(--primary, currentColor) 8%, transparent))',
				'border'       => 'var(--border-color-dark, var(--border-color, color-mix(in srgb, currentColor 20%, transparent)))',
				'muted'        => 'var(--text-dark-muted, color-mix(in srgb, currentColor 70%, transparent))',
				'radius'       => 'var(--radius-m, var(--radius, 6px))',
				'field_border' => 'var(--border-color-dark, var(--border-color, color-mix(in srgb, currentColor 30%, transparent)))',
				'field_bg'     => 'transparent',
				'field_radius' => 'var(--radius-s, var(--radius, 4px))',
				'gap'          => 'var(--space-xs, 0.75em)',
				'logo_height'  => '1.5em',
			);
		}

		return array(
			'accent'       => 'currentColor',
			'selected_bg'  => 'color-mix(in srgb, currentColor 5%, transparent)',
			'border'       => 'color-mix(in srgb, currentColor 20%, transparent)',
			'muted'        => 'color-mix(in srgb, currentColor 70%, transparent)',
			'radius'       => '6px',
			'field_border' => 'color-mix(in srgb, currentColor 30%, transparent)',
			'field_bg'     => 'transparent',
			'field_radius' => '4px',
			'gap'          => '0.75em',
			'logo_height'  => '1.4em',
		);
	}

	public static function value( $name ) {
		$saved    = trim( (string) nashenski_get_option( self::GROUP, $name ) );
		$defaults = self::defaults();

		return '' !== $saved ? $saved : ( $defaults[ $name ] ?? '' );
	}

	public static function css() {
		$lines = array();

		// Each setting becomes --nashenski-{name with dashes}, for example field_bg: --nashenski-field-bg.
		foreach ( array_keys( self::defaults() ) as $name ) {
			$value = self::sanitize_css_value( self::value( $name ) );

			if ( '' !== $value ) {
				$lines[] = '--nashenski-' . str_replace( '_', '-', $name ) . ':' . $value;
			}
		}

		return ':root{' . implode( ';', $lines ) . '}';
	}

	/**
	 * A single CSS value: colours, lengths, var(), calc(), color-mix(). Nothing that could close
	 * the declaration or the style element.
	 */
	public static function sanitize_css_value( $value ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );

		if ( '' === $value || strlen( $value ) > 200 || false !== strpos( $value, '/*' ) || false !== strpos( $value, '*/' ) || ! preg_match( '/^[a-zA-Z0-9#%.,()\s_+*\/-]+$/', $value ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Token names to suggest in the settings.
	 */
	private static function suggestions() {
		if ( ! self::framework() ) {
			return array( 'currentColor', 'transparent' );
		}

		return array( 'var(--primary)', 'var(--primary-hover)', 'var(--primary-light)', 'var(--primary-ultra-light)', 'var(--secondary)', 'var(--accent)', 'var(--base)', 'var(--base-ultra-light)', 'var(--border-color-dark)', 'var(--text-dark-muted)', 'var(--radius)', 'var(--radius-s)', 'var(--radius-m)', 'var(--radius-l)', 'var(--space-xs)', 'var(--space-s)', 'transparent', 'currentColor' );
	}

	/**
	 * Settings shown in Checkout > "Shipping options look".
	 */
	public static function settings() {
		$framework = self::framework();

		if ( 'tokenforge' === $framework ) {
			$source = __( 'Empty fields follow TokenForge, for example var(--primary), and change with it.', 'nashenski-commerce' );
		} elseif ( $framework ) {
			$source = __( 'Empty fields follow the design tokens of your CSS framework, for example var(--primary).', 'nashenski-commerce' );
		} else {
			$source = __( 'Empty fields follow your theme: its text colour with light borders.', 'nashenski-commerce' );
		}

		$id = function ( $name ) {
			return 'nashenski_settings_' . self::GROUP . '_' . $name;
		};

		$style = function ( $name, $title, $desc ) use ( $id ) {
			$defaults = self::defaults();

			return array(
				'type'        => 'nashenski_style',
				'id'          => $id( $name ),
				'title'       => $title,
				'desc'        => $desc,
				'placeholder' => $defaults[ $name ],
			);
		};

		return array(
			array(
				'type'  => 'title',
				'id'    => 'nashenski_appearance',
				'title' => __( 'Shipping options look', 'nashenski-commerce' ),
				'desc'  => __( 'How the courier options look at checkout.', 'nashenski-commerce' ) . ' ' . $source,
			),
			array(
				'type'    => 'select',
				'id'      => $id( 'layout' ),
				'title'   => __( 'Layout', 'nashenski-commerce' ),
				'class'   => 'wc-enhanced-select',
				'css'     => 'width: 100%;',
				'default' => self::layout(),
				'options' => array(
					'cards' => __( 'Cards: each option in its own box, the chosen one opens its office or address fields', 'nashenski-commerce' ),
					'plain' => __( 'Plain: keep the look of the theme or builder', 'nashenski-commerce' ),
				),
			),
			array(
				'type'    => 'checkbox',
				'id'      => $id( 'show_type' ),
				'title'   => __( 'Delivery type', 'nashenski-commerce' ),
				'desc'    => __( 'Show "To an office", "To a parcel locker" or "To your address" under each option', 'nashenski-commerce' ),
				'default' => 'yes',
			),
			$style( 'accent', __( 'Accent colour', 'nashenski-commerce' ), __( 'Border of the chosen option, focus ring and buttons.', 'nashenski-commerce' ) ),
			$style( 'selected_bg', __( 'Chosen option background', 'nashenski-commerce' ), '' ),
			$style( 'border', __( 'Option border', 'nashenski-commerce' ), '' ),
			$style( 'muted', __( 'Secondary text', 'nashenski-commerce' ), __( 'Delivery type and office addresses.', 'nashenski-commerce' ) ),
			$style( 'radius', __( 'Option corners', 'nashenski-commerce' ), '' ),
			$style( 'field_border', __( 'Field border', 'nashenski-commerce' ), __( 'Search, street and number fields.', 'nashenski-commerce' ) ),
			$style( 'field_bg', __( 'Field background', 'nashenski-commerce' ), '' ),
			$style( 'field_radius', __( 'Field corners', 'nashenski-commerce' ), '' ),
			$style( 'gap', __( 'Spacing', 'nashenski-commerce' ), '' ),
			$style( 'logo_height', __( 'Logo height', 'nashenski-commerce' ), '' ),
			array(
				'type' => 'sectionend',
				'id'   => 'nashenski_appearance',
			),
		);
	}

	public static function output_style_field( $value ) {
		$current = (string) get_option( $value['id'], '' );
		$list    = $value['id'] . '_tokens';
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label></th>
			<td class="forminp forminp-text">
				<input type="text" class="nashenski-style-input" id="<?php echo esc_attr( $value['id'] ); ?>" name="<?php echo esc_attr( $value['id'] ); ?>" value="<?php echo esc_attr( $current ); ?>" placeholder="<?php echo esc_attr( $value['placeholder'] ); ?>" list="<?php echo esc_attr( $list ); ?>" spellcheck="false" autocomplete="off">
				<datalist id="<?php echo esc_attr( $list ); ?>">
					<?php foreach ( self::suggestions() as $token ) : ?>
						<option value="<?php echo esc_attr( $token ); ?>"></option>
					<?php endforeach; ?>
				</datalist>
				<?php if ( ! empty( $value['desc'] ) ) : ?>
					<p class="description"><?php echo esc_html( $value['desc'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	public static function sanitize_style_field( $value, $option, $raw_value ) {
		if ( 'nashenski_style' !== ( $option['type'] ?? '' ) ) {
			return $value;
		}

		return self::sanitize_css_value( is_string( $raw_value ) ? wp_unslash( $raw_value ) : '' );
	}
}
