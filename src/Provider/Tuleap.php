<?php

namespace TuleapIntegration\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ResponseInterface;

class Tuleap extends AbstractProvider {
	/** @var \Config */
	private $config;

	public function __construct( \Config $config ) {
		$this->config = $config;
		parent::__construct( $config->get( 'TuleapOAuth2Config' ), [] );
	}

	public function getBaseAuthorizationUrl() {
		return $this->getBaseUrl() . '/oauth2/authorize';
	}

	public function getBaseAccessTokenUrl( array $params ) {
		return $this->getBaseUrl() . '/oauth2/token';
	}

	public function getResourceOwnerDetailsUrl(AccessToken $token) {
		return $this->getBaseUrl() . '/oauth2/userinfo';
	}

	protected function getDefaultScopes() {
		return [
			"read:project",
			"read:user_membership",
			"read:tracker",
			"offline_access",
			"openid",
			"email",
			"profile"
		];
	}

	protected function checkResponse( ResponseInterface $response, $data ) {
		if ( $response->getStatusCode() !== 200 ) {
			throw new \Exception( "Invalid response from Tuleap authentication" );
		}
	}

	protected function createResourceOwner( array $response, AccessToken $token ) {
		error_log( var_export( $response, 1));
		return \User::newSystemUser( 'MediaWiki default' );
	}

	private function getBaseUrl() {
		$url = $this->config->get( 'TuleapUrl' );
		if ( !$url ) {
			throw new \Exception( 'Config variable \$wgTuleapUrl must be set' );
		}

		return rtrim( $url, '/' );
	}
}
