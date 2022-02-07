<?php

require_once __DIR__  . '/vendor/autoload.php';

$dbLB = \MediaWiki\MediaWikiServices::getInstance()->getDBLoadBalancer();

$store = new \TuleapIntegration\InstanceStore( $dbLB );
$manager = new \TuleapIntegration\InstanceManager( $store );

$dispatcher = new \TuleapIntegration\Dispatcher( $_SERVER, $_REQUEST, $GLOBALS, $manager );

foreach( $dispatcher->getFilesToRequire() as $pathname ) {
	require $pathname;
}
