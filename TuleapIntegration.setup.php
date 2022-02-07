<?php


require_once ( __DIR__ . '/TuleapIntegration.Dispatcher.php' );
if ( FARMER_IS_ROOT_WIKI_CALL ) {
	wfLoadExtension( 'TuleapIntegration' );
}
