<?php

namespace TuleapIntegration\ProcessStep;

use MWStake\MediaWiki\Component\ProcessManager\IProcessStep;
use Symfony\Component\Filesystem\Filesystem;

class CreateInstanceVault implements IProcessStep {
	/** @var string */
	private $instanceDir;
	/** @var string */
	private $name;

	public function __construct( $config, $name ) {
		$configuredDir = $config->get( 'TuleapInstanceDirectory' );
		if ( $configuredDir ) {
			$this->instanceDir = $configuredDir;
		} else {
			$this->instanceDir = $GLOBALS['IP'] . '/_instances';
		}
		$this->name = $name;
	}

	public function execute() {
		$fs = new Filesystem();
		$instanceVaultPath = $this->instanceDir . '/' . $this->name;
		if ( $fs->exists( $instanceVaultPath ) ) {
			throw new \Exception( "Instance {$this->name} already has a vault!" );
		}
		$fs->mkdir( $instanceVaultPath );
	}
}
