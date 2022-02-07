<?php

namespace TuleapIntegration\Rest;

use MediaWiki\Rest\Handler;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;
use MWStake\MediaWiki\Component\ProcessManager\ProcessManager;
use TuleapIntegration\InstanceEntity;
use TuleapIntegration\InstanceManager;
use TuleapIntegration\ProcessStep\CreateInstanceVault;
use TuleapIntegration\ProcessStep\InstallInstance;
use TuleapIntegration\ProcessStep\Maintenance\RunJobs;
use TuleapIntegration\ProcessStep\RegisterInstance;
use TuleapIntegration\ProcessStep\RunPostInstallScripts;
use TuleapIntegration\ProcessStep\SetInstanceStatus;
use Wikimedia\ParamValidator\ParamValidator;

class MaintenanceHandler extends Handler {
	/** @var ProcessManager */
	private $processManager;
	/** @var InstanceManager */
	private $instanceManager;

	// TODO: Registry?
	private $scriptMap = [
		'runjobs' => [
			'class' => RunJobs::class,
			'services' => [ "InstanceManager" ]
		]
	];

	public function __construct( ProcessManager $processManager, InstanceManager $instanceManager ) {
		$this->processManager = $processManager;
		$this->instanceManager = $instanceManager;
	}

	public function execute() {
		$params = $this->getValidatedParams();
		$body = $this->getValidatedBody();

		if ( !$this->instanceManager->checkInstanceNameValidity( $params['instance'] ) ) {
			throw new HttpException( 'Invalid instance name: ' . $params['instance'] );
		}
		$instance = $this->instanceManager->getStore()->getInstanceByName( $params['instance'] );
		if ( !$instance || $instance->getStatus() !== InstanceEntity::STATE_READY ) {
			throw new HttpException( 'Instance not available or not ready' );
		}

		$script = $params['script'];
		if ( !isset( $this->scriptMap[$script] ) ) {
			throw new HttpException( "Unknown script: $script" );
		}

		$instance->setStatus( InstanceEntity::STATE_MAINTENANCE );
		$this->instanceManager->getStore()->storeEntity( $instance );

		$spec = $this->scriptMap[$script];
		$spec['args'] = array_merge( [ $instance->getId() ], $spec['args'] ?? [], $body );
		$process = new ManagedProcess( [
			$script => $spec,
		] );

		return $this->getResponseFactory()->createJson( [
			'pid' => $this->processManager->startProcess( $process )
		] );
	}

	public function getBodyValidator( $contentType ) {
		if ( $contentType === 'application/json' ) {
			return new JsonBodyValidator( [] );
		}
		return parent::getBodyValidator( $contentType );
	}

	public function getParamSettings() {
		return [
			'instance' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			],
			'script' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			]
		];
	}
}
