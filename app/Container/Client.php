<?php
namespace Nashenski\Container;

defined( 'ABSPATH' ) || exit;

use Nashenski\Client as Api;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class Client implements ServiceProviderInterface {
	const ECONT            = 'client.econt';
	const ECONT_PROFILE    = 'client.econt.profile';
	const ECONT_COUNTRIES  = 'client.econt.countries';
	const ECONT_CITIES     = 'client.econt.cities';
	const ECONT_OFFICES    = 'client.econt.offices';
	const ECONT_STREETS    = 'client.econt.streets';
	const ECONT_QUARTERS   = 'client.econt.quarters';

	const SPEEDY           = 'client.speedy';
	const SPEEDY_PROFILE   = 'client.speedy.profile';
	const SPEEDY_CITIES    = 'client.speedy.cities';
	const SPEEDY_OFFICES   = 'client.speedy.offices';
	const SPEEDY_STREETS   = 'client.speedy.streets';
	const SPEEDY_QUARTERS  = 'client.speedy.quarters';
	const SPEEDY_COUNTRIES = 'client.speedy.countries';

	const BOXNOW              = 'client.boxnow';
	const BOXNOW_ORIGINS      = 'client.boxnow.origins';
	const BOXNOW_DESTINATIONS = 'client.boxnow.destinations';

	const CVC              = 'client.cvc';
	const CVC_PROFILE      = 'client.cvc.profile';
	const CVC_CITIES       = 'client.cvc.cities';
	const CVC_COUNTRIES    = 'client.cvc.countries';
	const CVC_STREETS      = 'client.cvc.streets';
	const CVC_QUARTERS     = 'client.cvc.quarters';
	const CVC_OFFICES      = 'client.cvc.offices';
	const CVC_HUBS         = 'client.cvc.hubs';

	const CONNECTIX        = 'client.connectix';

	const NEPOSTOP         = 'client.nepostop';

	const PIGEON           = 'client.pigeon';
	const PIGEON_CITIES    = 'client.pigeon.cities';
	const PIGEON_OFFICES   = 'client.pigeon.offices';
	const PIGEON_LOCKERS   = 'client.pigeon.lockers';
	const PIGEON_STREETS   = 'client.pigeon.streets';

	/**
	 * Service ID => class. Each service is created once, when first used, with the container.
	 */
	const SERVICES = array(
		self::ECONT => Api\Econt::class,
		self::ECONT_PROFILE => Api\Econt\Profile::class,
		self::ECONT_COUNTRIES => Api\Econt\Countries::class,
		self::ECONT_CITIES => Api\Econt\Cities::class,
		self::ECONT_OFFICES => Api\Econt\Offices::class,
		self::ECONT_STREETS => Api\Econt\Streets::class,
		self::ECONT_QUARTERS => Api\Econt\Quarters::class,
		self::SPEEDY => Api\Speedy::class,
		self::SPEEDY_PROFILE => Api\Speedy\Profile::class,
		self::SPEEDY_CITIES => Api\Speedy\Cities::class,
		self::SPEEDY_OFFICES => Api\Speedy\Offices::class,
		self::SPEEDY_STREETS => Api\Speedy\Streets::class,
		self::SPEEDY_QUARTERS => Api\Speedy\Quarters::class,
		self::SPEEDY_COUNTRIES => Api\Speedy\Countries::class,
		self::BOXNOW => Api\BoxNow::class,
		self::BOXNOW_ORIGINS => Api\BoxNow\Origins::class,
		self::BOXNOW_DESTINATIONS => Api\BoxNow\Destinations::class,
		self::CVC => Api\CVC::class,
		self::CVC_PROFILE => Api\CVC\Profile::class,
		self::CVC_CITIES => Api\CVC\Cities::class,
		self::CVC_COUNTRIES => Api\CVC\Countries::class,
		self::CVC_STREETS => Api\CVC\Streets::class,
		self::CVC_QUARTERS => Api\CVC\Quarters::class,
		self::CVC_OFFICES => Api\CVC\Offices::class,
		self::CVC_HUBS => Api\CVC\Hubs::class,
		self::CONNECTIX => Api\Connectix::class,
		self::NEPOSTOP => Api\NepoStop::class,
		self::PIGEON => Api\Pigeon::class,
		self::PIGEON_CITIES => Api\Pigeon\Cities::class,
		self::PIGEON_OFFICES => Api\Pigeon\Offices::class,
		self::PIGEON_LOCKERS => Api\Pigeon\Lockers::class,
		self::PIGEON_STREETS => Api\Pigeon\Streets::class,
	);

	public function register( Container $container ) {
		foreach ( self::SERVICES as $id => $class ) {
			$container[ $id ] = function ( Container $container ) use ( $class ) {
				return new $class( $container );
			};
		}
	}
}
