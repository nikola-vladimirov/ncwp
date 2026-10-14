<?php
namespace Nashenski;

defined( 'ABSPATH' ) || exit;

/**
 * Updates from GitHub: the latest release of the repository (tag vX.Y.Z with the plugin zip attached)
 * shows up in Dashboard > Updates and on the Plugins screen like any other update, auto-updates included.
 * A private repository needs a read-only access token in wp-config.php:
 * define( 'NASHENSKI_UPDATE_TOKEN', 'github_pat_...' );
 * Not for the WordPress.org build: that one drops this file and the Update URI header.
 */
class Updater {
	const SLUG = 'nashenski-commerce';
	const API = 'https://api.github.com/repos/nikola-vladimirov/nashenski-commerce';
	const AUTHOR = 'https://pristadigital.com/';

	public function __construct() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'details' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'download' ), 10, 2 );
	}

	private static function get( $url, $accept = 'application/vnd.github+json', $redirection = 5 ) {
		$headers = array( 'Accept' => $accept );

		if ( defined( 'NASHENSKI_UPDATE_TOKEN' ) && NASHENSKI_UPDATE_TOKEN ) {
			$headers['Authorization'] = 'Bearer ' . NASHENSKI_UPDATE_TOKEN;
		}

		return wp_remote_get( $url, array( 'headers' => $headers, 'timeout' => 15, 'redirection' => $redirection ) );
	}

	/**
	 * The latest release with its zip, or null.
	 */
	private static function release() {
		$release = json_decode( (string) wp_remote_retrieve_body( self::get( self::API . '/releases/latest' ) ), true );

		foreach ( $release['assets'] ?? array() as $asset ) {
			if ( '.zip' === substr( $asset['name'], -4 ) && ! empty( $release['tag_name'] ) ) {
				return array(
					'version' => ltrim( $release['tag_name'], 'v' ),
					'tag' => $release['tag_name'],
					'url' => $release['html_url'],
					'package' => $asset['url'],
					'notes' => (string) $release['body'],
					'date' => (string) $release['published_at'],
				);
			}
		}

		return null;
	}

	public static function check( $update, $plugin_data, $plugin_file ) {
		if ( self::SLUG . '/nashenski-commerce.php' !== $plugin_file ) {
			return $update;
		}

		$release = self::release();

		return $release ? array(
			'slug' => self::SLUG,
			'version' => $release['version'],
			'url' => $release['url'],
			'package' => $release['package'],
			'icons' => array( '1x' => nashenski()->plugin_dir_url() . 'assets/images/logo.png' ),
		) : $update;
	}

	/**
	 * A file of the release from the repository, or null.
	 */
	private static function file( $release, $path ) {
		$response = self::get( self::API . '/contents/' . $path . '?ref=' . rawurlencode( $release['tag'] ), 'application/vnd.github.raw+json' );

		return 200 === wp_remote_retrieve_response_code( $response ) ? (string) wp_remote_retrieve_body( $response ) : null;
	}

	/**
	 * "View version details" on the update row: the changelog.
	 * WordPress strips attributes there (a style on <pre> is dropped), so it gets plain headings and lists.
	 */
	public static function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || self::SLUG !== ( $args->slug ?? '' ) || ! ( $release = self::release() ) ) {
			return $result;
		}

		// "Requires at least", "Tested up to" and "Requires PHP" of the new version.
		$readme = (string) self::file( $release, 'readme.txt' );
		$header = function ( $name ) use ( $readme ) {
			return preg_match( '/^' . $name . ':[ \t]*(\S+)/mi', $readme, $m ) ? $m[1] : '';
		};
		$images = nashenski()->plugin_dir_url() . 'assets/images/';

		return (object) array(
			'name' => 'Nashenski Commerce',
			'slug' => self::SLUG,
			'version' => $release['version'],
			'author' => '<a href="' . esc_url( self::AUTHOR ) . '">PristaDigital</a>',
			'homepage' => self::AUTHOR,
			'requires' => $header( 'Requires at least' ),
			'tested' => $header( 'Tested up to' ),
			'requires_php' => $header( 'Requires PHP' ),
			'last_updated' => $release['date'],
			'download_link' => $release['package'],
			'banners' => array( 'low' => $images . 'banner-772x250.png' ),
			'icons' => array( '1x' => $images . 'logo.png' ),
			// The whole CHANGELOG.md of the release, or its notes when that can't be read.
			'sections' => array( 'changelog' => Admin\Changelog::html( self::file( $release, 'CHANGELOG.md' ) ?? $release['notes'] ) ),
		);
	}

	/**
	 * The release asset address answers with a redirect to a signed download address. The token goes
	 * to GitHub only, never to that address, so the redirect is read here and downloaded without it.
	 */
	public static function download( $reply, $package ) {
		if ( 0 !== strpos( (string) $package, self::API . '/releases/assets/' ) ) {
			return $reply;
		}

		$location = wp_remote_retrieve_header( self::get( $package, 'application/octet-stream', 0 ), 'location' );

		return $location ? download_url( $location ) : new \WP_Error( 'nashenski_update', __( 'The update could not be downloaded from GitHub. For a private repository, add NASHENSKI_UPDATE_TOKEN to wp-config.php.', 'nashenski-commerce' ) );
	}
}
