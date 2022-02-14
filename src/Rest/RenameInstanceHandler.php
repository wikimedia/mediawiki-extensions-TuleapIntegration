<?php

namespace TuleapIntegration\Rest;

use MediaWiki\Rest\HttpException;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;
use MWStake\MediaWiki\Component\ProcessManager\ProcessManager;
use TuleapIntegration\InstanceManager;
use TuleapIntegration\ProcessStep\Maintenance\RefreshLinks;
use TuleapIntegration\ProcessStep\Maintenance\Update;
use TuleapIntegration\ProcessStep\RenameInstance;
use Wikimedia\ParamValidator\ParamValidator;

class RenameInstanceHandler extends AuthorizedHandler {
	/** @var ProcessManager */
	private $processManager;
	/** @var InstanceManager */
	private $instanceManager;

	/**
	 * @param ProcessManager $processManager
	 * @param InstanceManager $instanceManager
	 */
	public function __construct(
		ProcessManager $processManager, InstanceManager $instanceManager
	) {
		$this->processManager = $processManager;
		$this->instanceManager = $instanceManager;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->assertRights();
		$params = $this->getValidatedParams();
		$source = $params['name'];
		$target = $params['newname'];

		if (
			!$this->instanceManager->checkInstanceNameValidity( $source ) ||
			!$this->instanceManager->getStore()->instanceExists( $source )
		) {
			throw new HttpException( 'Source instance invalid', 422 );
		}
		if ( !$this->instanceManager->isCreatable( $target ) ) {
			throw new HttpException( 'Target instance name invalid, or already exists', 422 );
		}

		$process = new ManagedProcess( [
			'rename-instance' => [
				'class' => RenameInstance::class,
				'args' => [ $source, $target ],
				'services' => [ 'InstanceManager' ]
			],
			'update' => [
				'class' => Update::class,
				'args' => [ null, '', true ],
				'services' => [ 'InstanceManager' ]
			],
			'refresh-links' => [
				'class' => RefreshLinks::class,
				'args' => [ null, '', true ],
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
			],
			'newname' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			]
		];
	}
}
