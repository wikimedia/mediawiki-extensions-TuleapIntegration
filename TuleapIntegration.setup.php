<?php


require_once __DIR__ . '/TuleapIntegration.Dispatcher.php';
if ( FARMER_IS_ROOT_WIKI_CALL ) {
	wfLoadExtension( 'TuleapIntegration' );

	// Enable this code for OAuth authentication between Tuleap and wiki farm
	// This will only load extension OAuth in the main instance
	/*wfLoadExtension( 'OAuth' );
	$wgGroupPermissions['sysop']['mwoauthproposeconsumer'] = true;
	$wgGroupPermissions['sysop']['mwoauthupdateownconsumer'] = true;
	$wgGroupPermissions['sysop']['mwoauthmanageconsumer'] = true;
	$wgGroupPermissions['sysop']['mwoauthmanagemygrants'] = true;
	$wgGroupPermissions['sysop']['mwoauthviewprivate'] = true;
	$wgGroupPermissions['sysop']['mwoauthviewsuppressed'] = true;*/
}
