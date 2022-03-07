<?php

use MediaWiki\MediaWikiServices;
use TuleapIntegration\Provider\Tuleap;
use TuleapIntegration\TuleapConnection;

return [
	'TuleapConnection' => static function ( MediaWikiServices $services ) {
		$provider = new Tuleap( $services->getMainConfig() );

		return new TuleapConnection(
			$provider,
			RequestContext::getMain()->getRequest()->getSession()
		);
	}
];
