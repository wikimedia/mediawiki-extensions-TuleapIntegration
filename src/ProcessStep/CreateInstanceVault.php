<?php

namespace TuleapIntegration\ProcessStep;

use Config;
use MWStake\MediaWiki\Component\ProcessManager\IProcessStep;
use Symfony\Component\Filesystem\Filesystem;
use TuleapIntegration\InstanceEntity;
use TuleapIntegration\InstanceManager;

class CreateInstanceVault implements IProcessStep {
	/** @var string */
	private $instanceDir;
	/** @var InstanceManager */
	private $instanceManager;
	/** @var InstanceEntity|null */
	private $instance;

	public function __construct( Config $config, InstanceManager $instanceManager ) {
		$configuredDir = $config->get( 'TuleapInstanceDirectory' );
		if ( $configuredDir ) {
			$this->instanceDir = $configuredDir;
		} else {
			$this->instanceDir = $GLOBALS['IP'] . '/_instances';
		}
		$this->instanceManager = $instanceManager;
	}

	public function execute( $data = [] ): array {
		if ( !isset( $data['id'] ) ) {
			throw new \Exception( 'Trying to create vault for non-registered instance' );
		}
		$this->instance = $this->instanceManager->getStore()->getInstanceById( $data['id'] );
		if ( !$this->instance ) {
			throw new \Exception( 'Trying to create vault for non-registered instance' );
		}
		$directory = $this->getDirectoryName();
		$fs = new Filesystem();
		if ( $fs->exists( $directory ) ) {
			throw new \Exception( "Instance already has a vault!" );
		}
		$fs->mkdir( $directory );
		if ( !$fs->exists( $directory ) ) {
			throw new \Exception( "Cannot create vault" );
		}
		$this->instance->setDirectory( $directory );
		if ( !$this->instanceManager->getStore()->storeEntity( $this->instance ) ) {
			$fs->remove( $directory );
			throw new \Exception( "Cannot store vault location" );
		}

		return [ 'id' => $this->instance->getId() ];
	}

	private function getDirectoryName() {
		$dirName = str_replace( ' ', '_', $this->instance->getName() );
		return rtrim( $this->instanceDir, '/' ) . '/' . $dirName;
	}
}
