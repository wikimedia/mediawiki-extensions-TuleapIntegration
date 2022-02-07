<?php

namespace TuleapIntegration\ProcessStep;

use MWStake\MediaWiki\Component\ProcessManager\IProcessStep;
use TuleapIntegration\InstanceManager;

class RegisterInstance implements IProcessStep {
	/** @var InstanceManager */
	private $manager;
	/** @var string */
	private $name;

	public function __construct( InstanceManager $manager, $name ) {
		$this->manager = $manager;
		$this->name = $name;
	}

	public function execute( $data = [] ): array  {
		$entity = $this->manager->getStore()->getNewInstance( $this->name );

		if ( !$this->manager->getStore()->storeEntity( $entity ) ) {
			throw new \Exception( "Could not register instance" );
		}

		return [ 'id' => $entity->getId() ];
	}
}
