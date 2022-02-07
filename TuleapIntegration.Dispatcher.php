<?php

require_once dirname( dirname( __DIR__ ) )  . '/vendor/autoload.php';

error_log( class_exists( \TuleapIntegration\InstanceStore::class ) );
$dbLB = \MediaWiki\MediaWikiServices::getInstance()->getService( 'InstanceManager' );
