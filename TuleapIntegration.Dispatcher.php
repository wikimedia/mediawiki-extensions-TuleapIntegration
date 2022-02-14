<?php

require_once $GLOBALS['IP'] . '/vendor/autoload.php';

$dbLB = \MediaWiki\MediaWikiServices::getInstance()->getDBLoadBalancer();

$store = new \TuleapIntegration\InstanceStore( $dbLB );
$manager = new \TuleapIntegration\InstanceManager( $store );

$dispatcher = new \TuleapIntegration\Dispatcher( $_SERVER, $_REQUEST, $GLOBALS, $manager );

foreach ( $dispatcher->getFilesToRequire() as $pathname ) {
	require $pathname;
}
