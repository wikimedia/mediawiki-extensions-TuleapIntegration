<?php

namespace TuleapIntegration;

use Exception;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use MediaWiki\Session\Session;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use TuleapIntegration\Provider\Tuleap;

class TuleapConnection {
	/** @var Tuleap */
	private $provider;
	/** @var Session */
	private $session;
	/** @var LoggerInterface */
	private $logger;
	/** @var AccessToken|null */
	private $accessToken = null;
	/** @var array|null */
	private $integrationData = null;

	/**
	 * @param Tuleap $provider
	 * @param Session $session
	 * @param LoggerInterface $logger
	 */
	public function __construct( Tuleap $provider, Session $session, LoggerInterface $logger ) {
		$this->provider = $provider;
		$this->provider->setSession( $session );
		$this->session = $session;
		$this->logger = $logger;
	}

	/**
	 * @param string $returnTo
	 * @param bool $prompt
	 *
	 * @return string
	 * @throws \MWException
	 * @throws \SodiumException
	 */
	public function getAuthorizationUrl( $returnTo = '', bool $prompt = true ) {
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

		$params = [
			'scope' => 'profile email openid read:project read:mediawiki_standalone',
			'code_challenge' => $codeChallenge,
			'code_challenge_method' => 'S256',
			'nonce' => $nonce,
		];
		if ( $prompt === false ) {
			$params['prompt'] = 'none';
		}
		$url = $this->provider->getAuthorizationUrl( $params );

		$this->session->set( 'tuleapOauth2state', $this->provider->getState() );
		$this->session->save();

		$this->storeStateToGlobalStorage( $this->provider->getState() );

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
			$this->logger->error( "State mismatch: provided={provided} expected={exp}", [
				'provided' => $providedState,
				'exp' => $storedState,
			] );
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
	 * @throws Exception
	 */
	public function getResourceOwner(): TuleapResourceOwner {
		if ( !$this->accessToken ) {
			$this->logger->error(
				"Attempted to retrieve resource owner before obtaining the access token"
			);
			throw new Exception( 'Access token not yet obtained' );
		}

		$ro = $this->provider->getResourceOwner( $this->accessToken );
		if ( !( $ro instanceof TuleapResourceOwner ) ) {
			$this->logger->error(
				"Failed to retrieve or cast resource owner"
			);
			throw new Exception( 'Could not retrieve resource owner' );
		}
		return $ro;
	}

	/**
	 * @param int $project
	 * @param string|null $key Specific key to retrieve
	 * @param mixed $default Value to return in case requested key is not available
	 * @param bool $force
	 *
	 * @return mixed
	 * @throws IdentityProviderException
	 */
	public function getIntegrationData( $project, $key = null, $default = null, $force = false ) {
		if ( $this->integrationData === null || $force ) {
			$this->integrationData = $this->session->get( 'tuleap-integration-data' );
			if ( $this->integrationData === null || $force ) {
				$this->integrationData = [];
				$accessToken = $this->getAccessToken();
				$accessTokenValue = '';
				if ( $accessToken instanceof AccessTokenInterface ) {
					$accessTokenValue = $accessToken->getToken();
				}

				$queryString = "currently_active_service=plugin_mediawiki_standalone";
				$request = $this->provider->getAuthenticatedRequest(
					'GET',
					$this->provider->compileUrl(
						"/api/projects/$project/3rd_party_integration_data?$queryString"
					),
					$accessTokenValue
				);
				try {
					$response = $this->provider->getResponse( $request );
					if ( $response->getStatusCode() !== 200 ) {
						$this->logger->error( 'Cannot retrieve integration data: {status} - {body}', [
							'status' => $response->getStatusCode(),
							'body' => $response->getBody()->getContents(),
						] );
					} else {
						$this->integrationData = json_decode( $response->getBody()->getContents(), 1 );
					}
				} catch ( Exception $ex ) {
					$this->logger->error( 'Cannot retrieve integration data: {error}', [
						'error' => $ex->getMessage(),
					] );
					$this->integrationData = [];
				}

				$this->session->set( 'tuleap-integration-data', $this->integrationData );
			}
		}

		if ( $key ) {
			return $this->integrationData[$key] ?? $default;
		}

		return $this->integrationData;
	}

	/**
	 * @param int $project
	 * @return array|mixed
	 * @throws IdentityProviderException
	 */
	public function getPermissionConfig( $project ) {
		$accessToken = $this->getAccessToken();
		$accessTokenValue = '';
		if ( $accessToken instanceof AccessTokenInterface ) {
			$accessTokenValue = $accessToken->getToken();
		}

		$request = $this->provider->getAuthenticatedRequest(
			'GET',
			$this->provider->compileUrl( "/api/projects/$project/mediawiki_standalone_permissions" ),
			$accessTokenValue
		);
		$response = $this->provider->getResponse( $request );
		if ( $response->getStatusCode() !== 200 ) {
			throw new Exception( $response->getReasonPhrase() );
		}
		return json_decode( $response->getBody()->getContents(), 1 );
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
			// Cannot refresh
			return null;
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
	 * @return string
	 * @throws \SodiumException
	 */
	private function getNonce() {
		return sodium_bin2base64(
			random_bytes( 32 ),
			SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING
		);
	}

	/**
	 * @param string $state
	 * @throws \MWException
	 */
	private function storeStateToGlobalStorage( $state ) {
		if ( FARMER_IS_ROOT_WIKI_CALL ) {
			// No need to store state, as root is already the default target
			return;
		}
		$phpBinaryPath = $this->provider->getConfig()->get( 'PhpCli' );
		// We must run this in isolation, as to not override globals, services...
		$process = new Process( [
			$phpBinaryPath,
			$GLOBALS['IP'] . '/extensions/TuleapWikiFarm/maintenance/storeAuthInfo.php',
			'--instanceName', FARMER_CALLED_INSTANCE,
			'--state', $state,
		] );

		$process->run();
		if ( $process->getExitCode() !== 0 ) {
			$this->logger->error( 'Failed to store authentication state: {reason}', [
				'reason' => $process->getErrorOutput()
			] );
			throw new \MWException( 'Failed to store authentication state' );
		}
	}
}
