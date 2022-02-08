<?php

namespace TuleapIntegration\ProcessStep\Maintenance;

use Exception;
use MWStake\MediaWiki\Component\ProcessManager\IProcessStep;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use TuleapIntegration\InstanceEntity;
use TuleapIntegration\InstanceManager;

abstract class MaintenanceScript implements IProcessStep {
	/** @var InstanceManager */
	private $manager;
	/** @var int */
	private $instanceId;
	/** @var array */
	protected $args;
	/** @var bool */
	protected $noOutput;

	/**
	 * @param InstanceManager $manager
	 * @param null $id
	 * @param array $args
	 * @param bool $noOutput
	 */
	public function __construct( InstanceManager $manager, $id = null, $args = [], $noOutput = false ) {
		$this->manager = $manager;
		$this->instanceId = $id;
		$this->args = $args;
		$this->noOutput = $noOutput;
	}

	/**
	 * @param array $data
	 * @return array
	 * @throws Exception
	 */
	public function execute( $data = [] ): array {
		if ( !$this->instanceId && isset( $data['id'] ) ) {
			$this->instanceId = $data['id'];
		}
		$instance = $this->manager->getStore()->getInstanceById( $this->instanceId );
		if ( !$instance ) {
			throw new Exception( 'Invalid instance or cannot be retrieved' );
		}
		$phpBinaryFinder = new ExecutableFinder();
		$phpBinaryPath = $phpBinaryFinder->find( 'php' );
		$scriptPath = ltrim( $this->getScriptPath(), '/' );
		$process = new Process( array_merge(
			[
				$phpBinaryPath, $GLOBALS['IP'] . '/' . $scriptPath,
			],
			$this->getFormattedArgs(),
			[ '--sfr', $instance->getName() ]
		) );
		$this->modifyProcess( $process );
		$process->run();

		$instance->setStatus( InstanceEntity::STATE_READY );
		$this->manager->getStore()->storeEntity( $instance );

		if ( !$process->isSuccessful() ) {
			throw new Exception( $process->getErrorOutput() . $process->getCommandLine() );
		}

		if ( $this->noOutput ) {
			return [
				'id' => $this->instanceId,
				'warnings' => $data['warnings'] ?? [],
			];
		}
		return  [
			'id' => $this->instanceId,
			'command' => $process->getCommandLine(),
			'stdout' => $this->noOutput ? 'Output disabled' : $process->getOutput(),
			'stderr' => $process->getErrorOutput(),
			'warnings' => $data['warnings'] ?? [],
		];
	}

	/**
	 * @return array
	 */
	abstract protected function getFormattedArgs(): array;

	/**
	 * Path to the script file, relative to $IP
	 * @return string
	 */
	abstract protected function getScriptPath(): string;

	/**
	 * @param Process $process
	 */
	protected function modifyProcess( Process $process ) {
		// STUB
		return;
	}
}
