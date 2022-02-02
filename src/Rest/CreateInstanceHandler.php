<?php

namespace TuleapIntegration\Rest;

use MediaWiki\Rest\Handler;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;
use MWStake\MediaWiki\Component\ProcessManager\ProcessManager;
use TuleapIntegration\ProcessStep\CreateInstanceVault;
use Wikimedia\ParamValidator\ParamValidator;

class CreateInstanceHandler extends Handler {
	private $manager;

	public function __construct( ProcessManager $manager ) {
		$this->manager = $manager;
	}

	public function execute() {
		$params = $this->getValidatedParams();

		$process = new ManagedProcess( [
			'create-vault' => [
				'class' => CreateInstanceVault::class,
				'args' => [ $params['name'] ],
				'services' => [ 'MainConfig' ]
			]
		] );

		return $this->getResponseFactory()->createJson( [
			'pid' => $this->manager->startProcess( $process )
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
