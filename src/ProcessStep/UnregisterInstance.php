<?php

namespace TuleapIntegration\ProcessStep;

use MWStake\MediaWiki\Component\ProcessManager\IProcessStep;
use TuleapIntegration\InstanceManager;

class UnregisterInstance implements IProcessStep {
	/** @var InstanceManager */
	private $manager;
	/** @var int */
	private $id;

	public function __construct( InstanceManager $manager, $id ) {
		$this->manager = $manager;
		$this->id = $id;
	}

	public function execute( $data = [] ): array  {
		if ( !$this->manager->getStore()->deleteInstance( $this->id ) ) {
			throw new \Exception( 'Failed to delete instance entry' );
		}

		return [];
	}
}
