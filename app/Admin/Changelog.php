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
pre { margin: 0; white-space: pre-wrap; font: 14px/1.6 -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
</style>
</head>
<body>
<pre><?php echo esc_html( $md ); ?></pre>
</body>
</html>
		<?php
		exit;
	}
}
