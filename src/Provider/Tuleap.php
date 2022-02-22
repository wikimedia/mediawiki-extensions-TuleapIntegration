<?php

namespace TuleapIntegration\Provider;

use Config;
use Exception;
use League\OAuth2\Client\OptionProvider\HttpBasicAuthOptionProvider;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ResponseInterface;
use TuleapIntegration\TuleapResourceOwner;

class Tuleap extends AbstractProvider {
	/** @var Config */
	private $config;

	/**
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
		parent::__construct( $config->get( 'TuleapOAuth2Config' ), [
			'verify' => false,
			'optionProvider' => new HttpBasicAuthOptionProvider()
		] );
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	public function getBaseAuthorizationUrl() {
		return $this->getBaseUrl() . '/oauth2/authorize';
	}

	/**
	 * @param array $params
	 * @return string
	 * @throws Exception
	 */
	public function getBaseAccessTokenUrl( array $params ) {
		return $this->getBaseUrl() . '/oauth2/token';
	}

	/**
	 * @param AccessToken $token
	 * @return string
	 * @throws Exception
	 */
	public function getResourceOwnerDetailsUrl( AccessToken $token ) {
		return $this->getBaseUrl() . '/oauth2/userinfo';
	}

	/**
	 * @return string[]
	 */
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

	/**
	 * @param ResponseInterface $response
	 * @param array|string $data
	 * @throws Exception
	 */
	protected function checkResponse( ResponseInterface $response, $data ) {
		if ( $response->getStatusCode() !== 200 ) {
			throw new Exception( "Invalid response from Tuleap authentication" );
		}
	}

	/**
	 * @param array $response
	 * @param AccessToken $token
	 * @return TuleapResourceOwner
	 */
	protected function createResourceOwner( array $response, AccessToken $token ) {
		return TuleapResourceOwner::factory( $response );
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	private function getBaseUrl() {
		$url = $this->config->get( 'TuleapUrl' );
		if ( !$url ) {
			throw new Exception( 'Config variable \$wgTuleapUrl must be set' );
		}

		return rtrim( $url, '/' );
	}

	/**
	 * @param AccessToken|null $token
	 * @return array
	 */
	protected function getAuthorizationHeaders( $token = null ) {
		if ( $token ) {
			return [
				'Authorization' => 'Bearer ' . $token->getToken()
			];
		}

		return [];
	}
}
