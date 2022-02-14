<?php


require_once __DIR__ . '/TuleapIntegration.Dispatcher.php';
if ( FARMER_IS_ROOT_WIKI_CALL ) {
	wfLoadExtension( 'TuleapIntegration' );

	// Load OAuth in main instance only
	wfLoadExtension( 'OAuth' );
	$wgGroupPermissions['sysop']['mwoauthproposeconsumer'] = true;
	$wgGroupPermissions['sysop']['mwoauthupdateownconsumer'] = true;
	$wgGroupPermissions['sysop']['mwoauthmanageconsumer'] = true;
	$wgGroupPermissions['sysop']['mwoauthmanagemygrants'] = true;
	$wgGroupPermissions['sysop']['mwoauthviewprivate'] = true;
	$wgGroupPermissions['sysop']['mwoauthviewsuppressed'] = true;

	$wgGrantPermissionGroups['farm-management'] = 'administration';
	$wgGrantPermissions['farm-management']['tuleap-farm-manage'] = true;
}
