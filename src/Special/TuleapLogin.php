<?php

namespace TuleapIntegration\Special;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserOptionsManager;
use Title;
use TitleFactory as TitleFactory;
use TuleapIntegration\TuleapConnection;
use TuleapIntegration\TuleapResourceOwner;
use UnexpectedValueException;

class TuleapLogin extends \SpecialPage {
	/** @var TuleapConnection */
	private $tuleap;
	/** @var TitleFactory */
	private $titleFactory;
	/** @var UserFactory */
	private $userFactory;
	/** @var UserOptionsManager */
	private $userOptionsManager;

	/**
	 * @param TuleapConnection $tuleap
	 * @param TitleFactory $titleFactory
	 * @param UserFactory $userFactory
	 * @param UserOptionsManager $userOptionsManager
	 */
	public function __construct(
		TuleapConnection $tuleap, TitleFactory $titleFactory,
		UserFactory $userFactory, UserOptionsManager $userOptionsManager
	) {
		parent::__construct( 'TuleapLogin', '', false );
		$this->tuleap = $tuleap;
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
		$url = $this->tuleap->getAuthorizationUrl( $this->getRequest()->getVal( 'returnto' ) );
		$this->getOutput()->redirect( $url );

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
			$this->tuleap->obtainAccessToken( $this->getRequest() );
			$resourceOwner = $this->tuleap->getResourceOwner();
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

		// Retrieve required data and store to session
		$this->tuleap->getIntegrationData( $this->getConfig()->get( 'TuleapProjectId' ) );

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
