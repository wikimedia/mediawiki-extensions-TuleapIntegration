<?php

return [
	'InstanceStore' => static function ( \MediaWiki\MediaWikiServices $services ) {
		return new \TuleapIntegration\InstanceStore( $services->getDBLoadBalancer() );
	},
	'InstanceManager' => static function ( \MediaWiki\MediaWikiServices $services ) {
		return new \TuleapIntegration\InstanceManager( $services->getService( 'InstanceStore' ) );
	}
];
