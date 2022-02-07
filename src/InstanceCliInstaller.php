<?php

namespace TuleapIntegration;

use Status;

class InstanceCliInstaller extends \CliInstaller {
	/**
	 * Basically a copy of `CliInstaller::execute` but without the check for "$IP/LocalSettings.php"
	 * @return Status
	 */
	public function execute() {
		// If APC is available, use that as the MainCacheType, instead of nothing.
		// This is hacky and should be consolidated with WebInstallerOptions.
		// This is here instead of in __construct(), because it should run run after
		// doEnvironmentChecks(), which populates '_Caches'.
		if ( count( $this->getVar( '_Caches' ) ) ) {
			// We detected a CACHE_ACCEL implementation, use it.
			$this->setVar( '_MainCacheType', 'accel' );
		}

		// Disable upgrade-check
		/*
		$vars = Installer::getExistingLocalSettings();
		if ( $vars ) {
			$status = Status::newFatal( "config-localsettings-cli-upgrade" );
			$this->showStatusMessage( $status );
			return $status;
		}
		*/
		// // Disable upgrade-check - END

		$result = $this->performInstallation(
			[ $this, 'startStage' ],
			[ $this, 'endStage' ]
		);
		// PerformInstallation bails on a fatal, so make sure the last item
		// completed before giving 'next.' Likewise, only provide back on failure
		$lastStepStatus = end( $result );
		if ( $lastStepStatus->isOK() ) {
			return Status::newGood();
		} else {
			return $lastStepStatus;
		}
	}

	/**
	 *
	 * @param string $msg
	 */
	public function showMessage( $msg, ...$params ) {
		wfDebugLog( 'TuleapFarm', $msg );
		wfDebugLog( 'TuleapFarm', var_export( $params, true ) );
	}

	/**
	 *
	 * @param string $msg
	 */
	public function showError( $msg, ...$params ) {
		wfDebugLog( 'TuleapFarm', $msg );
		wfDebugLog( 'TuleapFarm', var_export( $params, true ) );
	}

	/**
	 *
	 * @param \Status $status
	 */
	public function showStatusMessage( \Status $status ) {
		if ( !$status->isGood() ) {
			wfDebugLog( 'TuleapFarm', $status->getMessage()->inLanguage( 'en' )->text() );
		}
	}
}
