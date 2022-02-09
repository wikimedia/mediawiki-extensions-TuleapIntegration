<?php

namespace TuleapIntegration\Rest;

use Config;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;
use MWStake\MediaWiki\Component\ProcessManager\ProcessManager;
use TuleapIntegration\InstanceEntity;
use TuleapIntegration\InstanceManager;
use TuleapIntegration\ProcessStep\CreateInstanceVault;
use TuleapIntegration\ProcessStep\InstallInstance;
use TuleapIntegration\ProcessStep\RegisterInstance;
use TuleapIntegration\ProcessStep\SetInstanceStatus;
use Wikimedia\ParamValidator\ParamValidator;

class CreateInstanceHandler extends Handler {
	/** @var ProcessManager */
	private $processManager;
	/** @var InstanceManager */
	private $instanceManager;
	/** @var Config */
	private $config;

	public function __construct( ProcessManager $processManager, InstanceManager $instanceManager, Config $config ) {
		$this->processManager = $processManager;
		$this->instanceManager = $instanceManager;
		$this->config = $config;
	}

	public function execute() {
		$params = $this->getValidatedParams();
		if ( !$this->instanceManager->isCreatable( $params['name'] ) ) {
			throw new HttpException( 'Instance name not valid or instance exists', 422 );
		}

		$body = $this->getValidatedBody();
		$body['dbprefix'] = $this->config->get( 'DBprefix' );
		$body['server'] = $this->config->get( 'Server' );

		$process = new ManagedProcess( [
			'register-instance' => [
				'class' => RegisterInstance::class,
				'args' => [ $params['name'] ],
				'services' => [ 'InstanceManager' ]
			],
			'create-vault' => [
				'class' => CreateInstanceVault::class,
				'args' => [],
				'services' => [ 'InstanceManager' ]
			],
			'install-instance' => [
				'factory' => InstallInstance::class . '::factory',
				'args' => [ $body ],
				'services' => [ 'InstanceManager' ]
			],
			'set-instance-status' => [
				'class' => SetInstanceStatus::class,
				'args' => [ InstanceEntity::STATE_READY ],
				'services' => [ 'InstanceManager' ]
			]
		] );

		return $this->getResponseFactory()->createJson( [
			'pid' => $this->processManager->startProcess( $process )
		] );
	}

	public function getBodyValidator( $contentType ) {
		if ( $contentType === 'application/json' ) {
			return new JsonBodyValidator( [
				'lang' => [
					self::PARAM_SOURCE => 'body',
					ParamValidator::PARAM_REQUIRED => false,
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_DEFAULT => $this->config->get( 'LanguageCode' ),
				],
				'dbserver' => [
					self::PARAM_SOURCE => 'body',
					ParamValidator::PARAM_REQUIRED => false,
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_DEFAULT => $this->config->get( 'DBserver' ),
				],
				'dbuser' => [
					self::PARAM_SOURCE => 'body',
					ParamValidator::PARAM_REQUIRED => false,
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_DEFAULT => $this->config->get( 'DBuser' ),
				],
				'dbname' => [
					self::PARAM_SOURCE => 'body',
					ParamValidator::PARAM_REQUIRED => false,
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_DEFAULT => '',
				],
				'dbpass' => [
					self::PARAM_SOURCE => 'body',
					ParamValidator::PARAM_REQUIRED => false,
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_DEFAULT => $this->config->get( 'DBpassword' ),
				],
				'adminuser' => [
					self::PARAM_SOURCE => 'body',
					ParamValidator::PARAM_REQUIRED => false,
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_DEFAULT => 'WikiSysop'
				],
				'adminpass' => [
					self::PARAM_SOURCE => 'body',
					ParamValidator::PARAM_REQUIRED => true,
					ParamValidator::PARAM_TYPE => 'string',
				]
			] );
		}
		return parent::getBodyValidator( $contentType );
	}

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
