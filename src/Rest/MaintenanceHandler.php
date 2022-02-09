<?php

namespace TuleapIntegration\Rest;

use MediaWiki\Rest\Handler;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;
use MWStake\MediaWiki\Component\ProcessManager\ProcessManager;
use TuleapIntegration\InstanceEntity;
use TuleapIntegration\InstanceManager;
use TuleapIntegration\ProcessStep\Maintenance\RefreshLinks;
use TuleapIntegration\ProcessStep\Maintenance\RunJobs;
use TuleapIntegration\ProcessStep\Maintenance\SetGroups;
use TuleapIntegration\ProcessStep\Maintenance\Update;
use Wikimedia\ParamValidator\ParamValidator;

class MaintenanceHandler extends Handler {
	/** @var ProcessManager */
	private $processManager;
	/** @var InstanceManager */
	protected $instanceManager;

	// TODO: Registry?
	private $scriptMap = [
		'runjobs' => [
			'class' => RunJobs::class,
			'services' => [ "InstanceManager" ]
		],
		'update' => [
			'class' => Update::class,
			'services' => [ "InstanceManager" ]
		],
		'setUserGroups' => [
			'class' => SetGroups::class,
			'services' => [ "InstanceManager" ]
		],
		'refresh-links' => [
			'class' => RefreshLinks::class,
			'services' => [ "InstanceManager" ]
		]
	];

	public function __construct( ProcessManager $processManager, InstanceManager $instanceManager ) {
		$this->processManager = $processManager;
		$this->instanceManager = $instanceManager;
	}

	public function execute() {
		$params = $this->getValidatedParams();
		$script = $params['script'];
		if ( !isset( $this->scriptMap[$script] ) ) {
			throw new HttpException( "Unknown script: $script" );
		}
		$timeout = $params['timeout'];

		$spec = $this->scriptMap[$script];
		$spec['args'] = $spec['args'] ?? [];

		$instanceName = $params['instance'];

		if ( $instanceName === '*' ) {
			array_unshift( $spec['args'],-1 );
			if ( $timeout < 3600 ) {
				// Make sure enough time is given to big processes
				$timeout = 3600;
			}
		} else {
			if ( !$this->instanceManager->checkInstanceNameValidity( $instanceName ) ) {
				throw new HttpException( 'Invalid instance name: ' . $instanceName, 422 );
			}
			$instance = $this->instanceManager->getStore()->getInstanceByName( $instanceName );
			if ( !$instance || $instance->getStatus() !== InstanceEntity::STATE_READY ) {
				throw new HttpException( 'Instance not available or not ready', 400 );
			}
			array_unshift( $spec['args'], $instance->getId() );
		}

		$body = $this->getValidatedBody();
		$spec['args'][] = $body;

		$process = new ManagedProcess( [
			$script => $spec,
		], $timeout );

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
			],
			'timeout' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_DEFAULT => 300
			],
		];
	}
}
