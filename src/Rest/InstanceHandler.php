<?php

namespace TuleapIntegration\Rest;

use MediaWiki\Rest\Handler;
use MediaWiki\Rest\HttpException;
use TuleapIntegration\InstanceEntity;
use TuleapIntegration\InstanceManager;
use Wikimedia\ParamValidator\ParamValidator;

abstract class InstanceHandler extends Handler {
	/** @var InstanceManager */
	protected $instanceManager;

	public function __construct( InstanceManager $instanceManager ) {
		$this->instanceManager = $instanceManager;
	}

	public function execute() {
		$params = $this->getValidatedParams();

		if ( !$this->instanceManager->checkInstanceNameValidity( $params['instance'] ) ) {
			throw new HttpException( 'Invalid instance name: ' . $params['instance'] );
		}
		$instance = $this->instanceManager->getStore()->getInstanceByName( $params['instance'] );
		if ( !$instance || $instance->getStatus() !== InstanceEntity::STATE_READY ) {
			throw new HttpException( 'Instance not available or not ready' );
		}

		return $this->doExecute( $instance );
	}

	abstract protected function doExecute( InstanceEntity $instance );

	public function getParamSettings() {
		return [
			'instance' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			]
		];
	}
}
