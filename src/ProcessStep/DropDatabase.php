<?php

namespace TuleapIntegration\ProcessStep;

use MWStake\MediaWiki\Component\ProcessManager\IProcessStep;
use Symfony\Component\Filesystem\Filesystem;
use TuleapIntegration\InstanceManager;

class DropDatabase implements IProcessStep {
	/** @var InstanceManager */
	private $manager;
	/** @var int */
	private $id;
	/** @var array */
	private $dbConnection;

	public function __construct( InstanceManager $manager, $id, $dbConnection ) {
		$this->manager = $manager;
		$this->id = $id;
		$this->dbConnection = $dbConnection;
	}

	public function execute( $data = [] ): array {
		$instance = $this->manager->getStore()->getInstanceById( $this->id );
		$dbName = $instance->getDatabaseName();

		$db = \Database::factory( $this->dbConnection['type'], [
			'host' => $this->dbConnection['host'],
			'user' => $this->dbConnection['user'],
			'password' => $this->dbConnection['password'],
		] );

		if ( !$db->query( 'DROP DATABASE ' . $dbName ) ) {
			throw new \Exception( 'Cannot drop instance database' );
		}

		return [ 'id' => $instance->getId(), 'dbname' => $dbName ];
	}
}
