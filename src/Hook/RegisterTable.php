<?php

namespace TuleapIntegration\Hook;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class RegisterTable implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @param \DatabaseUpdater $updater
	 * @return bool|void
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionTable(
			'tuleap_instance',
			dirname( dirname( __DIR__ ) ) . '/db/tuleap_instances.sql'
		);
	}
}
