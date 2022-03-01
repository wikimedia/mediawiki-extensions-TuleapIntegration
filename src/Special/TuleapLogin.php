<?php

namespace TuleapIntegration\Special;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserOptionsManager;
use Title;
use TitleFactory as TitleFactory;
use TuleapIntegration\Provider\Tuleap;
use TuleapIntegration\TuleapResourceOwner;
use UnexpectedValueException;

class TuleapLogin extends \SpecialPage {
	/** @var Tuleap */
	private $provider;
	/** @var TitleFactory */
	private $titleFactory;
	/** @var UserFactory */
	private $userFactory;
	/** @var UserOptionsManager */
	private $userOptionsManager;

	/**
	 * @param TitleFactory $titleFactory
	 * @param UserFactory $userFactory
	 * @param UserOptionsManager $userOptionsManager
	 */
	public function __construct(
		TitleFactory $titleFactory, UserFactory $userFactory,
		UserOptionsManager $userOptionsManager
	) {
		parent::__construct( 'TuleapLogin', '', false );
		$this->provider = new Tuleap( $this->getConfig() );
		$this->titleFactory = $titleFactory;
		$this->userFactory = $userFactory;
		$this->userOptionsManager = $userOptionsManager;
	}

	/**
	 * @inheritDoc
	 */
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

		$authorizationUrl = $this->provider->getAuthorizationUrl( [ 'scope' => 'profile email openid' ] );

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

			$resourceOwner = $this->provider->getResourceOwner( $accessToken );
			$this->setUser( $resourceOwner );
			$this->redirectToReturnTo();
		} catch ( IdentityProviderException | UnexpectedValueException | \Exception $e ) {
			$isDebug = $this->getRequest()->getBool( 'debug' );
			$message = $isDebug ? new \RawMessage( $e->getMessage() ) : 'tuleap-login-error-desc';
			$this->getOutput()->showErrorPage( 'tuleap-login-error', $message );
			return true;
		}

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
		$user = $this->userFactory->newFromName( $owner->getId() );
		$user->setRealName( $owner->getRealName() );
		$user->setEmail( $owner->getEmail() );
		$user->load();
		if ( !$user->isRegistered() ) {
			$user->addToDatabase();
		}
		if ( $owner->isEmailVerified() ) {
			$user->confirmEmail();
		}
		if ( $owner->getLocale() ) {
			$this->userOptionsManager->setOption( $user, 'language', $owner->getLocale() );
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
		if ( $this->getRequest()->getSession()->exists( 'returnto' ) ) {
			$title = $this->titleFactory->newFromText(
				$this->getRequest()->getSession()->get( 'returnto' )
			);
			if ( !( $title instanceof Title ) ) {
				$this->redirectToMainPage();
				return;
			}
			$this->getRequest()->getSession()->remove( 'returnto' );
			$this->getRequest()->getSession()->save();
			$this->getOutput()->redirect( $title->getFullURL() );
		}
	}

	private function redirectToMainPage() {
		$this->getOutput()->redirect( $this->titleFactory->newMainPage()->getFullURL() );
	}
}
