<?php

namespace TuleapIntegration\Rest;

use MediaWiki\Rest\HttpException;
use TuleapIntegration\InstanceManager;
use Wikimedia\ParamValidator\ParamValidator;

class InstanceStatusHandler extends AuthorizedHandler {
	/** @var InstanceManager */
	private $manager;

	/**
	 * @param InstanceManager $manager
	 */
	public function __construct( InstanceManager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->assertRights();
		$params = $this->getValidatedParams();
		if ( !$this->manager->checkInstanceNameValidity( $params['name'] ) ) {
			throw new HttpException( 'Instance name is not valid', 422 );
		}

		$store = $this->manager->getStore();
		$entity = $store->getInstanceByName( $params['name'] );
		if ( !$entity ) {
			throw new HttpException( 'Instance ' . $params['name'] . ' does not exist', 404 );
		}

		return $this->getResponseFactory()->createJson( [
			'status' => $entity->getStatus()
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
