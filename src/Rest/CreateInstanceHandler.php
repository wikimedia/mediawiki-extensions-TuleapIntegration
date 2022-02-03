<?php

namespace TuleapIntegration\Rest;

use MediaWiki\Rest\Handler;
use MediaWiki\Rest\HttpException;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;
use MWStake\MediaWiki\Component\ProcessManager\ProcessManager;
use TuleapIntegration\InstanceManager;
use TuleapIntegration\ProcessStep\CreateInstanceVault;
use Wikimedia\ParamValidator\ParamValidator;

class CreateInstanceHandler extends Handler {
	private $processManager;
	private $instanceManager;

	public function __construct( ProcessManager $processManager, InstanceManager $instanceManager ) {
		$this->processManager = $processManager;
		$this->instanceManager = $instanceManager;
	}

	public function execute() {
		$params = $this->getValidatedParams();
		if ( !$this->instanceManager->checkInstanceNameValidity( $params['name'] ) ) {
			throw new HttpException( 'Instance name is not valid', 422 );
		}

		$process = new ManagedProcess( [
			'create-vault' => [
				'class' => CreateInstanceVault::class,
				'args' => [ $params['name'] ],
				'services' => [ 'MainConfig' ]
			]
		] );

		return $this->getResponseFactory()->createJson( [
			'pid' => $this->processManager->startProcess( $process )
		] );
	}

	public function getParamSettings() {
		return [
			'name' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			],
			'lang' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => 'string',
			]
		];
	}
}
