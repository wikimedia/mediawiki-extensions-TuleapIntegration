<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use TuleapIntegration\Provider\Tuleap;
use TuleapIntegration\TuleapConnection;

return [
	'TuleapConnection' => static function ( MediaWikiServices $services ) {
		$logger = LoggerFactory::getInstance( 'tuleap-connection' );
		$provider = new Tuleap( $services->getMainConfig(), $logger );

		return new TuleapConnection(
			$provider,
			RequestContext::getMain()->getRequest()->getSession(),
			$logger
		);
	}
];
