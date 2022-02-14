<?php

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/maintenance/Maintenance.php';

class RunForAll extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addOption( 'script', '', true, true );
		$this->addOption( 'args', '', false, true );
	}

	public function execute() {
		$manager = \MediaWiki\MediaWikiServices::getInstance()->getService( 'InstanceManager' );

		foreach ( $manager->getStore()->getInstanceNames() as $name ) {
			$phpBinaryFinder = new ExecutableFinder();
			$phpBinaryPath = $phpBinaryFinder->find( 'php' );
			$process = new Process( array_merge(
				[
					$phpBinaryPath, $this->getOption( 'script' ),
				],
				explode( ' ', $this->getOption( 'args' ) ),
				[ '--sfr', $name ]
			) );

			$this->output( "Executing for $name\n" );
			$process->run();
			if ( !$process->isSuccessful() ) {
				$this->error( $process->getErrorOutput() . "\n" );
			} else {
				$this->output( $process->getOutput() . "\n" );
			}
		}
	}
}

$maintClass = 'RunForAll';
require_once RUN_MAINTENANCE_IF_MAIN;
