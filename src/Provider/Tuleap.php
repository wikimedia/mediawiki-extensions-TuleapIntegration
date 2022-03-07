<?php

namespace TuleapIntegration\Provider;

use Config;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use League\OAuth2\Client\OptionProvider\HttpBasicAuthOptionProvider;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use MediaWiki\Session\Session;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Math\BigInteger;
use Psr\Http\Message\ResponseInterface;
use TuleapIntegration\TuleapResourceOwner;

class Tuleap extends AbstractProvider {
	use BearerAuthorizationTrait;

	/** @var Config */
	private $config;
	/** @var Session */
	private $session;

	/**
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
		parent::__construct( $config->get( 'TuleapOAuth2Config' ), [
			'verify' => true,
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
		if ( isset( $data['id_token'] ) ) {
			$this->verifyJWT( $data['id_token'] );
		}
		if ( $response->getStatusCode() !== 200 ) {
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
			throw new Exception( 'Verify JWT: iss not valid' );
		}

		$clientId = $this->config->get( 'TuleapOAuth2Config' )['clientId'] ?? null;
		if ( $d->aud !== $clientId ) {
			throw new Exception( 'Verify JWT: aud not valid' );
		}

		$nonce = $d->nonce ?? null;
		if ( $nonce ) {
			if ( !$this->session ) {
				throw new Exception( 'Session must be set before obtaining AccessToken' );
			}
			$storedNonce = $this->session->get( 'tuleapOauth2nonce' );
			if ( !hash_equals( $nonce, $storedNonce ) ) {
				throw new Exception( 'Verify JWT: nonce does not match' );
			}
		}
	}

	/**
	 * @return array
	 * @throws \SodiumException
	 */
	private function getJWTKeys() {
		$keyResponse = $this->getResponse(
			$this->getRequest( 'GET', $this->getBaseUrl() . '/oauth2/jwks' )
		);
		if ( $keyResponse->getStatusCode() !== 200 ) {
			throw new Exception( "Could not retrieve JWT public key" );
		}
		$keys = json_decode( $keyResponse->getBody(), 1 );
		$res = [];
		foreach ( $keys['keys'] as $keyData ) {
			if ( $keyData['kty'] !== 'RSA' ) {
				// Dont know how to handle other types
				continue;
			}
			$modulus = sodium_base642bin( $keyData['n'], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING );
			$exponent = sodium_base642bin( $keyData['e'], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING );
			$key = PublicKeyLoader::loadPublicKey( [
				'e' => new BigInteger( $exponent, 256 ),
				'n' => new BigInteger( $modulus, 256 ),
			] );

			$res[$keyData['kid']] = new Key( $key->toString( 'pkcs8' ), $keyData['alg'] );
		}
		return $res;
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
}
