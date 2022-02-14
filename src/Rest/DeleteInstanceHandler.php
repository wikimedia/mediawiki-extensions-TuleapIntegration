<?php

namespace TuleapIntegration\Rest;

use Config;
use MediaWiki\Rest\HttpException;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;
use MWStake\MediaWiki\Component\ProcessManager\ProcessManager;
use TuleapIntegration\InstanceManager;
use TuleapIntegration\ProcessStep\DeleteVault;
use TuleapIntegration\ProcessStep\DropDatabase;
use TuleapIntegration\ProcessStep\UnregisterInstance;
use Wikimedia\ParamValidator\ParamValidator;

class DeleteInstanceHandler extends AuthorizedHandler {
	/** @var ProcessManager */
	private $processManager;
	/** @var InstanceManager */
	private $instanceManager;
	/** @var Config */
	private $config;

	/**
	 * @param ProcessManager $processManager
	 * @param InstanceManager $instanceManager
	 * @param Config $config
	 */
	public function __construct(
		ProcessManager $processManager, InstanceManager $instanceManager, Config $config
	) {
		$this->processManager = $processManager;
		$this->instanceManager = $instanceManager;
		$this->config = $config;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->assertRights();
		$params = $this->getValidatedParams();
		$instance = $this->instanceManager->getStore()->getInstanceByName( $params['name'] );
		if ( !$instance ) {
			throw new HttpException( 'Instance not found', 404 );
		}

		$dbConnection = [
			'type' => $this->config->get( 'DBtype' ),
			'host' => $this->config->get( 'DBserver' ),
			'user' => $this->config->get( 'DBuser' ),
			'password' => $this->config->get( 'DBpassword' ),
		];
		$process = new ManagedProcess( [
			'delete-vault' => [
				'class' => DeleteVault::class,
				'args' => [ $instance->getId() ],
				'services' => [ 'InstanceManager' ]
			],
			'drop-database' => [
				'class' => DropDatabase::class,
				'args' => [ $instance->getId(), $dbConnection ],
				'services' => [ 'InstanceManager' ]
			],
			'unregister-instance' => [
				'class' => UnregisterInstance::class,
				'args' => [ $instance->getId() ],
				'services' => [ 'InstanceManager' ]
			]
		] );

		return $this->getResponseFactory()->createJson( [
			'pid' => $this->processManager->startProcess( $process )
		] );
	}

	/**
	 * @inheritDoc
	 */
	public function getParamSettings() {
		return [
			'name' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			]
		];
	}
}
