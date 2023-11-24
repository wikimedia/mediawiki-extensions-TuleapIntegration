<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use TuleapIntegration\Provider\Tuleap;
use TuleapIntegration\TuleapConnection;
use TuleapIntegration\UserMappingProvider;

return [
	'TuleapConnection' => static function ( MediaWikiServices $services ) {
		$logger = LoggerFactory::getInstance( 'tuleap-connection' );
		$provider = new Tuleap( $services->getMainConfig(), $logger );

		return new TuleapConnection(
			$provider,
			RequestContext::getMain()->getRequest()->getSession(),
			$logger
		);
	},
	'TuleapUserMappingProvider' => static function ( MediaWikiServices $services ) {
		$logger = LoggerFactory::getInstance( 'tuleap-connection' );
		return new UserMappingProvider(
			$services->getDBLoadBalancer(),
			$services->getUserFactory(),
			$logger
		);
	},
];
