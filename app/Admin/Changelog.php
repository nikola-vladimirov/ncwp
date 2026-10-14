<?php
namespace Nashenski\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * A "Changelog" link on the Plugins screen that opens CHANGELOG.md in a popup,
 * like "View details" does for plugins from WordPress.org.
 */
class Changelog {
	const ACTION = 'nashenski_changelog';

	public function __construct() {
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_link' ), 10, 2 );
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'render' ) );
	}

	public static function row_link( $links, $file ) {
		if ( plugin_basename( nashenski()->plugin_dir_path() . 'nashenski-commerce.php' ) !== $file || ! current_user_can( 'activate_plugins' ) ) {
			return $links;
		}

		add_thickbox();

		$url = add_query_arg(
			array(
				'action' => self::ACTION,
				'_wpnonce' => wp_create_nonce( self::ACTION ),
				'TB_iframe' => 'true',
				'width' => 772,
				'height' => 700,
			),
			admin_url( 'admin-ajax.php' )
		);

		$links[] = '<a href="' . esc_url( $url ) . '" class="thickbox open-plugin-details-modal" aria-label="' . esc_attr__( 'Nashenski Commerce changelog', 'nashenski-commerce' ) . '">' . esc_html__( 'Changelog', 'nashenski-commerce' ) . '</a>';

		return $links;
	}

	public static function render() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view this page.', 'nashenski-commerce' ), 403 );
		}

		check_admin_referer( self::ACTION );

		$file = nashenski()->plugin_dir_path() . 'CHANGELOG.md';
		$md = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.

		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php esc_html_e( 'Nashenski Commerce changelog', 'nashenski-commerce' ); ?></title>
<style>
body { margin: 0; padding: 24px 32px; color: #1d2327; background: #fff; }
body { font: 14px/1.6 -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
h3 { margin: 28px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #dcdcde; font-size: 18px; }
h3:first-child { margin-top: 0; }
h4 { margin: 16px 0 4px; font-size: 14px; }
ul { margin: 0 0 8px; padding-left: 20px; }
li { margin: 0 0 4px; }
p { margin: 0 0 8px; }
</style>
</head>
<body>
<?php echo self::html( $md ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in html(). ?>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * CHANGELOG.md as HTML: version and section headings, lists, paragraphs and **bold**.
	 * Only tags the WordPress update details popup allows, so it shows there too.
	 */
	public static function html( $md ) {
		$html = '';
		$list = false;

		foreach ( preg_split( '/\R/', (string) $md ) as $line ) {
			$line = rtrim( $line );
			$item = 0 === strpos( $line, '- ' );

			if ( $list && ! $item ) {
				$html .= "</ul>\n";
				$list = false;
			}

			// Skip blank lines and the "# Nashenski Commerce changelog" title.
			if ( '' === $line || 0 === strpos( $line, '# ' ) ) {
				continue;
			}

			if ( preg_match( '/^(#{2,5}) (.*)$/', $line, $m ) ) {
				// "## 0.14.0 (date)" -> h3, "### Fixed" -> h4.
				$tag = 'h' . ( strlen( $m[1] ) + 1 );
				$html .= "<$tag>" . self::inline( $m[2] ) . "</$tag>\n";
			} elseif ( $item ) {
				$html .= ( $list ? '' : "<ul>\n" ) . '<li>' . self::inline( substr( $line, 2 ) ) . "</li>\n";
				$list = true;
			} else {
				$html .= '<p>' . self::inline( $line ) . "</p>\n";
			}
		}

		return $html . ( $list ? "</ul>\n" : '' );
	}

	private static function inline( $text ) {
		return preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', esc_html( $text ) );
	}
}
