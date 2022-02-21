<?php

namespace TuleapIntegration\Special;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use MediaWiki\User\UserFactory;
use Title;
use TuleapIntegration\Provider\Tuleap;
use TuleapIntegration\TuleapResourceOwner;
use UnexpectedValueException;

class TuleapLogin extends \SpecialPage {
	/** @var Tuleap */
	private $provider;
	/** @var \TitleFactory */
	private $titleFactory;
	/** @var UserFactory */
	private $userFactory;

	/**.
	 * @param \TitleFactory $titleFactory
	 * @param UserFactory $userFactory
	 */
	public function __construct( \TitleFactory $titleFactory, UserFactory $userFactory ) {
		parent::__construct( 'TuleapLogin', '', false );
		$this->provider = new Tuleap( $this->getConfig() );
		$this->titleFactory = $titleFactory;
		$this->userFactory = $userFactory;
	}

	public function execute( $subPage ) {
		$this->setHeaders();
		if ( $this->getUser()->isRegistered() ) {
			$this->redirectToReturnTo();
			return true;
		}

		if ( $subPage === 'callback' ) {
			return $this->callback();
		}

		$this->getRequest()->getSession()->persist();
		$this->getRequest()->getSession()->set( 'returnto', $this->getRequest()->getVal( 'returnto' ) );

		$authorizationUrl = $this->provider->getAuthorizationUrl( [ 'scope' => 'profile' ] );

		$this->getRequest()->getSession()->set( 'tuleapOauth2state', $this->provider->getState() );
		$this->getRequest()->getSession()->save();

		$this->getOutput()->redirect( $authorizationUrl );

		return true;
	}

	/**
	 * Retrieve access token and log user in
	 *
	 * @return bool
	 * @throws \MWException
	 */
	public function callback() {
		try {
			$storedState = $this->getRequest()->getSession()->get( 'tuleapOauth2state' );
			$providedState = $this->getRequest()->getVal( 'state' );

			if ( $storedState !== $providedState ) {
				throw new \UnexpectedValueException(
					'Provided state param in the callback does not match original state'
				);
			}

			$accessToken = $this->provider->getAccessToken( 'authorization_code', [
				'code' => $this->getRequest()->getVal( 'code' )
			] );
		} catch ( IdentityProviderException | UnexpectedValueException | \Exception $e ) {
			error_log( "EX" );
			$isDebug = $this->getRequest()->getBool( 'debug' );
			$message = $isDebug ? new \RawMessage( $e->getMessage() ) : 'tuleap-login-error-desc';
			$this->getOutput()->showErrorPage( 'tuleap-login-error', $message );
			return true;
		}

		$resourceOwner = $this->provider->getResourceOwner( $accessToken );
		$this->setUser( $resourceOwner );
		$this->redirectToReturnTo();

		return true;

	}

	/**
	 * Set session
	 *
	 * @param TuleapResourceOwner $owner
	 * @return bool|\User
	 * @throws \MWException
	 */
	private function setUser( TuleapResourceOwner $owner ) {
		$user = $this->userFactory->newFromName( $owner->getUsername() );
		$user->setRealName( $owner->toArray()['name'] );
		$user->setEmail( $owner->getEmail() );
		$user->load();
		if ( !$user->isRegistered() ) {
			$user->addToDatabase();
			$user->confirmEmail();
		}
		$user->setToken();

		$this->getRequest()->getSession()->persist();
		$user->setCookies();
		$this->getContext()->setUser( $user );
		$user->saveSettings();

		$GLOBALS['wgUser'] = $user;
		$sessionUser = \User::newFromSession( $this->getRequest() );
		$sessionUser->load();

		return $user;
	}

	/**
	 * After login, return to whatever user wanted to see
	 */
	private function redirectToReturnTo() {
		$title = null;
		if( $this->getRequest()->getSession()->exists('returnto') ) {
			$title = $this->titleFactory->newFromText( $this->getRequest()->getSession()->get('returnto' ) );
			$this->getRequest()->getSession()->remove('returnto');
			$this->getRequest()->getSession()->save();
		}

		if( !$title instanceof Title || 0 > $title->getArticleID() ) {
			$title = $this->titleFactory->newMainPage();
		}
		$this->getOutput()->redirect( $title->getFullURL() );
	}
}
