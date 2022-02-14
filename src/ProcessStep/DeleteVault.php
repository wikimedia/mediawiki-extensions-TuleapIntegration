<?php

namespace TuleapIntegration\ProcessStep;

use Exception;
use MWStake\MediaWiki\Component\ProcessManager\IProcessStep;
use Symfony\Component\Filesystem\Filesystem;
use TuleapIntegration\InstanceManager;

class DeleteVault implements IProcessStep {
	/** @var InstanceManager */
	private $manager;
	/** @var int */
	private $id;

	/**
	 * @param InstanceManager $manager
	 * @param int $id InstanceID
	 */
	public function __construct( InstanceManager $manager, $id ) {
		$this->manager = $manager;
		$this->id = $id;
	}

	/**
	 * @param array $data
	 * @return array
	 * @throws Exception
	 */
	public function execute( $data = [] ): array {
		$instance = $this->manager->getStore()->getInstanceById( $this->id );
		$dir = $this->manager->getDirectoryForInstance( $instance );

		$fs = new Filesystem();
		if ( !$fs->exists( $dir ) ) {
			throw new Exception( "Vault does not exist at " . $dir );
		}

		$fs->remove( $dir );
		if ( $fs->exists( $dir ) ) {
			throw new Exception( 'Could not completely instance vault' );
		}

		return [ 'id' => $instance->getId(), 'vault_dir' => $dir ];
	}
}
