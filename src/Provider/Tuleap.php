<?php

namespace TuleapIntegration\Provider;

use Config;
use Exception;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use League\OAuth2\Client\OptionProvider\HttpBasicAuthOptionProvider;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use MediaWiki\Session\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use TuleapIntegration\TuleapResourceOwner;

class Tuleap extends AbstractProvider {
	use BearerAuthorizationTrait;

	/** @var Config */
	private $config;
	/** @var LoggerInterface */
	private $logger;
	/** @var Session */
	private $session;

	/**
	 * @param Config $config
	 * @param LoggerInterface $logger
	 */
	public function __construct( Config $config, LoggerInterface $logger ) {
		$this->config = $config;
		$this->logger = $logger;
		parent::__construct( $config->get( 'TuleapOAuth2Config' ), [
			'optionProvider' => new HttpBasicAuthOptionProvider()
		] );
	}

	/**
	 * @param Session $session
	 */
	public function setSession( Session $session ) {
		$this->session = $session;
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	public function getBaseAuthorizationUrl() {
		return $this->compileUrl( '/oauth2/authorize' );
	}

	/**
	 * @param array $params
	 * @return string
	 * @throws Exception
	 */
	public function getBaseAccessTokenUrl( array $params ) {
		return $this->compileUrl( '/oauth2/token' );
	}

	/**
	 * @param AccessToken $token
	 * @return string
	 * @throws Exception
	 */
	public function getResourceOwnerDetailsUrl( AccessToken $token ) {
		return $this->compileUrl( '/oauth2/userinfo' );
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
		if ( isset( $data['id_token'] ) ) {
			$this->verifyJWT( $data['id_token'] );
		}
		if ( $response->getStatusCode() !== 200 ) {
			$this->logger->error( 'Response verification failed: {code} {reason}', [
				'code' => $response->getStatusCode(),
				'reason' => $response->getReasonPhrase(),
			] );
			throw new Exception( "Invalid response from Tuleap authentication" );
		}
	}

	/**
	 * @param string $token
	 * @throws \SodiumException
	 */
	private function verifyJWT( $token ) {
		JWT::$leeway = 10;
		// Checks validity period, signature
		$d = JWT::decode( $token, $this->getJWTKeys() );

		if ( $d->iss !== $this->getBaseUrl() ) {
			$this->logger->error( 'Verify JWT: iss not valid: iss={iss} expected={base}', [
				'iss' => $d->iss,
				'base' => $this->getBaseUrl(),
			] );
			throw new Exception( 'Verify JWT: iss not valid' );
		}

		$clientId = $this->config->get( 'TuleapOAuth2Config' )['clientId'] ?? null;
		if ( $d->aud !== $clientId ) {
			$this->logger->error( 'Verify JWT: iss not valid: aud={aud} expected={client}', [
				'aud' => $d->aud,
				'client' => $clientId,
			] );
			throw new Exception( 'Verify JWT: aud not valid' );
		}

		if ( !property_exists( $d, 'sub' ) ) {
			$this->logger->error( 'Verify JWT: sub claim is missing' );
			throw new Exception( 'Verify JWT: sub claim missing' );
		}

		$nonce = $d->nonce ?? null;
		if ( !$this->session ) {
			throw new Exception( 'Session must be set before obtaining AccessToken' );
		}
		$storedNonce = $this->session->get( 'tuleapOauth2nonce' );
		if ( !$nonce || !hash_equals( $nonce, $storedNonce ) ) {
			throw new Exception( 'Verify JWT: nonce does not match' );
		}
	}

	/**
	 * @return array
	 */
	private function getJWTKeys() {
		$keyResponse = $this->getResponse(
			$this->getRequest( 'GET', $this->compileUrl( '/oauth2/jwks' ) )
		);
		if ( $keyResponse->getStatusCode() !== 200 ) {
			$this->logger->error( 'Failed to retrive JWKS: {code} {reason}', [
				'code' => $keyResponse->getStatusCode(),
				'reason' => $keyResponse->getReasonPhrase(),
			] );
			throw new Exception( "Could not retrieve JWT public key" );
		}
		$keys = json_decode( $keyResponse->getBody(), 1 );
		return JWK::parseKeySet( $keys );
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
			$this->logger->error( 'Config variable \$wgTuleapUrl must be set' );
			throw new Exception( 'Config variable \$wgTuleapUrl must be set' );
		}

		return rtrim( $url, '/' );
	}

	/**
	 * @param string $path
	 * @return string
	 * @throws Exception
	 */
	public function compileUrl( $path ) {
		return $this->getBaseUrl() . $path;
	}

	/**
	 * @return Config
	 */
	public function getConfig() {
		return $this->config;
	}
}
