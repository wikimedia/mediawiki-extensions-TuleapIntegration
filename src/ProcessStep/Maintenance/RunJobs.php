<?php

namespace TuleapIntegration\ProcessStep\Maintenance;

use MWStake\MediaWiki\Component\ProcessManager\IProcessStep;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use TuleapIntegration\InstanceEntity;
use TuleapIntegration\InstanceManager;

class RunJobs implements IProcessStep {
	private $manager;
	private $instanceId;

	public function __construct( InstanceManager $manager, $id ) {
		$this->manager = $manager;
		$this->instanceId = $id;
	}

	public function execute( $data = [] ): array {
		$instance = $this->manager->getStore()->getInstanceById( $this->instanceId );

		$phpBinaryFinder = new ExecutableFinder();
		$phpBinaryPath = $phpBinaryFinder->find( 'php' );
		$process = new Process( [
			$phpBinaryPath, $GLOBALS['IP'] . '/maintenance/runJobs.php',
			'--sfr', $instance->getName(),
		] );

		$process->run();

		$instance->setStatus( InstanceEntity::STATE_READY );
		$this->manager->getStore()->storeEntity( $instance );

		if ( !$process->isSuccessful() ) {
			throw new \Exception( $process->getErrorOutput() );
		}

		return [ 'output' => $process->getOutput() ];
	}
}
