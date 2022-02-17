<?php

namespace TuleapIntegration\Special;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use TuleapIntegration\Provider\Tuleap;
use UnexpectedValueException;

class TuleapLogin extends \SpecialPage {
	/** @var Tuleap */
	private $provider;

	public function __construct() {
		parent::__construct( 'TuleapLogin', '', false );
		$this->provider = new Tuleap( $this->getConfig() );
	}

	public function execute( $subPage ) {
		$this->setHeaders();

		if ( $subPage === 'callback' ) {
			return $this->callback();
		}

		$this->getRequest()->getSession()->persist();
		$this->getRequest()->getSession()->set( 'returnto', $this->getRequest()->getVal( 'returnto' ) );

		$authorizationUrl = $this->provider->getAuthorizationUrl( [ 'scope' => 'email profile' ] );

		$this->getRequest()->getSession()->set( 'tuleapOauth2state', $this->provider->getState() );
		$this->getRequest()->getSession()->save();

		$this->getOutput()->redirect( $authorizationUrl );
	}

	public function callback() {
		try {
			$storedState = $this->getRequest()->getSession()->get( 'tuleapOauth2state' );
			$providedState = $this->getRequest()->getVal( 'state' );

			if ( $storedState !== $providedState ) {
				throw new \UnexpectedValueException( 'Provided state param in the callback does not match original state' );
			}

			$accessToken = $this->provider->getAccessToken( 'authorization_code', [
				'code' => $this->getRequest()->getVal( 'code' )
			] );
		} catch ( IdentityProviderException $e ) {
			$this->getOutput()->showErrorPage( 'Login error', $e->getMessage() );
		} catch ( UnexpectedValueException $e ) {
			$this->getOutput()->showErrorPage( 'Login error', $e->getMessage() );
		}

		$resourceOwner = $this->provider->getResourceOwner( $accessToken );

		return true;
	}
}
