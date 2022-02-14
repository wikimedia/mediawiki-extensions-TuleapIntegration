<?php

namespace TuleapIntegration\ProcessStep;

use Exception;
use MWStake\MediaWiki\Component\ProcessManager\IProcessStep;
use TuleapIntegration\InstanceManager;

class DropDatabase implements IProcessStep {
	/** @var InstanceManager */
	private $manager;
	/** @var int */
	private $id;
	/** @var array */
	private $dbConnection;

	/**
	 * @param InstanceManager $manager
	 * @param string $id
	 * @param array $dbConnection
	 */
	public function __construct( InstanceManager $manager, $id, $dbConnection ) {
		$this->manager = $manager;
		$this->id = $id;
		$this->dbConnection = $dbConnection;
	}

	/**
	 * @param array $data
	 * @return array
	 * @throws Exception
	 */
	public function execute( $data = [] ): array {
		$instance = $this->manager->getStore()->getInstanceById( $this->id );
		$dbName = $instance->getDatabaseName();

		$db = \Database::factory( $this->dbConnection['type'], [
			'host' => $this->dbConnection['host'],
			'user' => $this->dbConnection['user'],
			'password' => $this->dbConnection['password'],
		] );

		if ( !$db->query( 'DROP DATABASE ' . $dbName ) ) {
			throw new Exception( 'Cannot drop instance database' );
		}

		return [ 'id' => $instance->getId(), 'dbname' => $dbName ];
	}
}
