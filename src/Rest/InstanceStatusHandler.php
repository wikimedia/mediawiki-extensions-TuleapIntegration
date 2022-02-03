<?php

namespace TuleapIntegration\Rest;

use MediaWiki\Rest\Handler;
use MediaWiki\Rest\HttpException;
use TuleapIntegration\InstanceEntity;
use TuleapIntegration\InstanceManager;
use Wikimedia\ParamValidator\ParamValidator;

class InstanceStatusHandler extends Handler {
	private $manager;

	public function __construct( InstanceManager $manager ) {
		$this->manager = $manager;
	}

	public function execute() {
		$params = $this->getValidatedParams();
		if ( !$this->manager->checkInstanceNameValidity( $params['name'] ) ) {
			throw new HttpException( 'Instance name is not valid', 422 );
		}

		$store = $this->manager->getStore();
		if ( $store->instanceExists( $params['name'] ) ) {
			throw new HttpException( "Exists" );
		}
		$entity = $store->getNewInstance( $params['name'] );

		$res = $store->storeEntity( $entity );
		error_log( "SAVING: " . $res );

		$entity->setDatabaseName( 'sf_test' );
		$store->storeEntity( $entity );

		$entity->setStatus( InstanceEntity::STATE_READY );
		$store->storeEntity( $entity );


		return $this->getResponseFactory()->createJson(
			[ "t" => $entity->getId() ]
		);
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
