<?php

namespace TuleapIntegration\ProcessStep;

use Exception;
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

	/**
	 * @param InstanceManager $instanceManager
	 * @throws Exception
	 */
	public function __construct( InstanceManager $instanceManager ) {
		$this->instanceDir = rtrim( $instanceManager->getInstanceDirBase(), '/' );
		if ( !$this->instanceDir ) {
			throw new Exception( "No base directory for instances set" );
		}
		$this->instanceManager = $instanceManager;
	}

	/**
	 * @param array $data
	 * @return array
	 * @throws Exception
	 */
	public function execute( $data = [] ): array {
		if ( !isset( $data['id'] ) ) {
			throw new Exception( 'Trying to create vault for non-registered instance' );
		}
		$this->instance = $this->instanceManager->getStore()->getInstanceById( $data['id'] );
		if ( !$this->instance ) {
			throw new Exception( 'Trying to create vault for non-registered instance' );
		}
		$instancePath = $this->instanceManager->generateInstanceDirectoryName( $this->instance );
		$directory = $this->instanceDir . $instancePath;
		$fs = new Filesystem();
		if ( $fs->exists( $directory ) ) {
			throw new Exception( "Instance already has a vault!" );
		}
		$fs->mkdir( [ $directory, "$directory/images", "$directory/cache" ] );
		if ( !$fs->exists( $directory ) ) {
			throw new Exception( "Cannot create vault" );
		}

		$this->instance->setDirectory( $instancePath );
		if ( !$this->instanceManager->getStore()->storeEntity( $this->instance ) ) {
			throw new Exception( "Cannot store vault location" );
		}

		return [ 'id' => $this->instance->getId() ];
	}
}
