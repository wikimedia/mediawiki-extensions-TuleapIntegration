<?php

namespace TuleapIntegration\Rest;

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

class MaintenanceHandler extends InstanceHandler {
	/** @var ProcessManager */
	private $processManager;

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
		parent::__construct( $instanceManager );
	}

	protected function doExecute( InstanceEntity $instance ) {
		$params = $this->getValidatedParams();
		$body = $this->getValidatedBody();

		$script = $params['script'];
		if ( !isset( $this->scriptMap[$script] ) ) {
			throw new HttpException( "Unknown script: $script" );
		}

		$instance->setStatus( InstanceEntity::STATE_MAINTENANCE );
		$this->instanceManager->getStore()->storeEntity( $instance );

		$spec = $this->scriptMap[$script];
		$spec['args'] = array_merge( [ $instance->getId() ], $spec['args'] ?? [] );
		$spec['args'][] = $body;

		$process = new ManagedProcess( [
			$script => $spec,
		], 300 );

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
		return parent::getParamSettings() + [
			'script' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			]
		];
	}
}
