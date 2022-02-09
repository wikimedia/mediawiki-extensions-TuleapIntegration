<?php

namespace TuleapIntegration\ProcessStep;

use MWStake\MediaWiki\Component\ProcessManager\IProcessStep;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use TuleapIntegration\InstanceManager;

class InstallInstance implements IProcessStep {
	/** @var InstanceManager */
	private $manager;
	private $dbServer;
	private $dbUser;
	private $dbPass;
	private $dbPrefix;
	private $dbName;
	private $lang;
	private $server;
	private $adminUser;
	private $adminPass;

	public static function factory( InstanceManager $manager, $args ) {
		$required = [ 'dbserver', 'dbuser', 'dbpass', 'dbprefix', 'lang', 'server', 'adminuser', 'adminpass' ];
		foreach( $required as $key ) {
			if ( !isset( $args[$key] ) ) {
				throw new \Exception( "Argument $key must be set" );
			}
		}

		return new static(
			$manager, $args['dbserver'], $args['dbuser'], $args['dbpass'], $args['dbname'] ?? null,
			$args['dbprefix'], $args['lang'], $args['server'], $args['adminuser'], $args['adminpass']
		);
	}

	public function __construct(
		InstanceManager $manager, $dbserver, $dbuser, $dbpass, $dbname,
		$dbprefix, $lang, $server, $adminuser, $adminpass
	) {
		$this->manager = $manager;
		$this->dbServer = $dbserver;
		$this->dbUser = $dbuser;
		$this->dbPass = $dbpass;
		$this->dbPrefix = $dbprefix;
		$this->dbName = $dbname;
		$this->lang = $lang;
		$this->server = $server;
		$this->adminUser = $adminuser;
		$this->adminPass = $adminpass;
	}

	public function execute( $data = [] ): array  {
		$instance = $this->manager->getStore()->getInstanceById( $data['id'] );
		if ( !$instance ) {
			throw new \Exception( 'Failed to install non-registered instance' );
		}

		$scriptPath = $this->manager->generateScriptPath( $instance );
		if ( !$this->dbName ) {
			$this->dbName = $this->manager->generateDbName();
		}


		$phpBinaryFinder = new ExecutableFinder();
		$phpBinaryPath = $phpBinaryFinder->find( 'php' );
		// We must run this in isolation, as to not override globals, services...
		$process = new Process( [
			$phpBinaryPath, $GLOBALS['IP'] . '/extensions/TuleapIntegration/maintenance/installInstance.php',
			'--scriptpath', $scriptPath,
			'--dbname', $this->dbName,
			'--dbuser', $this->dbUser,
			'--dbpass', $this->dbPass,
			'--dbserver', $this->dbServer,
			'--server', $this->server,
			'--lang', $this->lang,
			'--instanceName', $instance->getName(),
			'--adminuser', $this->adminUser,
			'--adminpass', $this->adminPass,
			'--instanceDir', $this->manager->getDirectoryForInstance( $instance )
		] );

		$err = '';
		$process->run( static function ( $type, $buffer ) use ( &$err ) {
			if ( Process::ERR === $type ) {
				$err .= $buffer;
			}
		} );

		if ( $process->getExitCode() !== 0 ) {
			throw new \Exception( $err );
		}

		$instance->setDatabaseName( $dbName );
		$instance->setScriptPath( $scriptPath );
		$this->manager->getStore()->storeEntity( $instance );

		return [ 'id' => $instance->getId() ];
	}
}
