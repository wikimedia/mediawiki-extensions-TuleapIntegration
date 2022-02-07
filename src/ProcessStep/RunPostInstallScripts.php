<?php

namespace TuleapIntegration\ProcessStep;

use MWStake\MediaWiki\Component\ProcessManager\IProcessStep;
use TuleapIntegration\InstanceManager;

class RunPostInstallScripts implements IProcessStep {
	/** @var InstanceManager */
	private $manager;

	public function __construct( InstanceManager $manager ) {
		$this->manager = $manager;
	}

	public function execute( $data = [] ): array  {
		$instance = $this->manager->getStore()->getInstanceById( $data['id'] );
		if ( !$instance ) {
			throw new \Exception( 'Failed to run updates on non-existing instance' );
		}

		return [ 'id' => $instance->getId() ];
	}
}
