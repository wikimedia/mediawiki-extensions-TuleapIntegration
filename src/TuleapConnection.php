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
	}

	/**
	 * Currently unused, needed for the future
	 *
	 * @return AccessTokenInterface|null
	 * @throws IdentityProviderException
	 */
	private function getAccessToken(): ?AccessTokenInterface {
		if ( !$this->accessToken ) {
			return null;
		}
		if ( $this->accessToken->hasExpired() ) {
			$this->refreshAccessToken();
		}

		return $this->accessToken;
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
