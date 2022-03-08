<?php

namespace TuleapIntegration;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use MediaWiki\Session\Session;
use TuleapIntegration\Provider\Tuleap;

class TuleapConnection {
	/** @var Tuleap */
	private $provider;
	/** @var Session */
	private $session;
	/** @var AccessToken|null */
	private $accessToken = null;
	/** @var array|null */
	private $integrationData = null;

	/**
	 * @param Tuleap $provider
	 * @param Session $session
	 */
	public function __construct( Tuleap $provider, Session $session ) {
		$this->provider = $provider;
		$this->provider->setSession( $session );
		$this->session = $session;
	}

	/**
	 * @param string $returnTo
	 * @return string
	 * @throws \SodiumException
	 */
	public function getAuthorizationUrl( $returnTo = '' ) {
		$this->session->persist();
		$this->session->set( 'returnto', $returnTo );

		$codeVerifier = bin2hex( random_bytes( 32 ) );
		$codeChallenge = sodium_bin2base64(
			hash( 'sha256', $codeVerifier, true ),
			SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING
		);
		$this->session->set( 'tuleapOauth2codeVerifier', $codeVerifier );
		$nonce = $this->getNonce();
		$this->session->set( 'tuleapOauth2nonce', $nonce );

		$url = $this->provider->getAuthorizationUrl( [
			'scope' => 'profile email openid',
			'code_challenge' => $codeChallenge,
			'code_challenge_method' => 'S256',
			'nonce' => $nonce
		] );

		$this->session->set( 'tuleapOauth2state', $this->provider->getState() );
		$this->session->save();

		return $url;
	}

	/**
	 * @param \WebRequest $request
	 * @return AccessToken|AccessTokenInterface|null
	 * @throws IdentityProviderException
	 */
	public function obtainAccessToken( \WebRequest $request ) {
		$storedState = $this->session->get( 'tuleapOauth2state' );
		$providedState = $request->getVal( 'state' );

		if ( !hash_equals( $storedState, $providedState ) ) {
			throw new \UnexpectedValueException(
				'Provided state param in the callback does not match original state'
			);
		}

		$this->accessToken = $this->provider->getAccessToken( 'authorization_code', [
			'code' => $request->getVal( 'code' ),
			'code_verifier' => $this->session->get(
				'tuleapOauth2codeVerifier'
			)
		] );

		$this->session->set( 'tuleapOauth2AT', $this->accessToken->jsonSerialize() );
		return $this->accessToken;
	}

	/**
	 * @return TuleapResourceOwner
	 * @throws \Exception
	 */
	public function getResourceOwner(): TuleapResourceOwner {
		if ( !$this->accessToken ) {
			throw new \Exception( 'Access token not yet obtained' );
		}

		$ro = $this->provider->getResourceOwner( $this->accessToken );
		if ( !( $ro instanceof TuleapResourceOwner ) ) {
			throw new \Exception( 'Could not retrieve resource owner' );
		}
		return $ro;
	}

	public function invalidateAccessToken() {
		$this->accessToken = null;
		// If this is being invoked, likely session is already killed
		// but just in case, unset it here as well
		$this->session->remove( 'tuleapOauth2AT' );
	}

	/**
	 * @param int $project
	 * @param string|null $key Specific key to retrieve
	 * @param mixed $default Value to return in case requested key is not available
	 * @return array
	 * @throws IdentityProviderException
	 */
	public function getIntegrationData( $project, $key = null, $default = null ): array {
		if ( $this->integrationData === null ) {
			$this->integrationData = [];
			$accessToken = $this->getAccessToken();
			if ( !$accessToken ) {
				throw new \Exception( 'Access token not yet obtained' );
			}

			$request = $this->provider->getRequest(
				'GET',
				$this->provider->compileUrl( "/api/projects/$project/3rd_party_integration_data" )
			);
			$response = $this->provider->getResponse( $request );
			if ( $response->getStatusCode() !== 200 ) {
				throw new \Exception( $response->getReasonPhrase() );
			}
			$this->integrationData = json_decode( $response->getBody()->getContents(), 1 );
		}

		if ( $key ) {
			return $this->integrationData[$key] ?? $default;
		}

		return $this->integrationData;
	}

	/**
	 * Currently unused, needed for the future
	 *
	 * @return AccessTokenInterface|null
	 * @throws IdentityProviderException
	 */
	private function getAccessToken(): ?AccessTokenInterface {
		if ( !$this->accessToken && !$this->trySetAccessTokenFromSession() ) {
			return null;
		}
		if ( $this->accessToken->hasExpired() ) {
			$this->refreshAccessToken();
		}

		return $this->accessToken;
	}

	private function trySetAccessTokenFromSession() {
		$token = $this->session->get( 'tuleapOauth2AT', null );
		if ( !$token ) {
			return false;
		}

		$this->accessToken = new AccessToken( $token );
		return true;
	}

	/**
	 * @throws IdentityProviderException
	 */
	private function refreshAccessToken() {
		$this->accessToken = $this->provider->getAccessToken( 'refresh_token', [
			'refresh_token' => $this->accessToken->getRefreshToken(),
			// Do we need this for RT grant?
			'code_verifier' => $this->session->get(
				'tuleapOauth2codeVerifier'
			)
		] );
	}

	/**
	 * @return string
	 * @throws \SodiumException
	 */
	private function getNonce() {
		return sodium_bin2base64(
			hash( 'sha256', $this->session->getId(), true ),
			SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING
		);
	}
}
