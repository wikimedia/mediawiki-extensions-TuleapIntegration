<?php

namespace TuleapIntegration\Special;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserOptionsManager;
use Psr\Log\LoggerInterface;
use Title;
use TitleFactory as TitleFactory;
use TuleapIntegration\TuleapConnection;
use TuleapIntegration\TuleapResourceOwner;
use TuleapIntegration\UserMappingProvider;
use UnexpectedValueException;
use User;

class TuleapLogin extends \SpecialPage {
	/**
	 * @var string[]
	 */
	private $groupMapping = [
		'is_writer' => 'editor',
		'is_admin' => 'sysop',
		'is_bot' => 'bot'
	];

	/** @var TuleapConnection */
	private $tuleap;
	/** @var TitleFactory */
	private $titleFactory;
	/** @var UserFactory */
	private $userFactory;
	/** @var UserOptionsManager */
	private $userOptionsManager;
	/** @var UserGroupManager */
	private $groupManager;
	/** @var UserMappingProvider */
	private $userMappingProvider;
	/** @var array */
	private $permissionConfig = [];
	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param TuleapConnection $tuleap
	 * @param TitleFactory $titleFactory
	 * @param UserFactory $userFactory
	 * @param UserOptionsManager $userOptionsManager
	 * @param UserGroupManager $groupManager
	 * @param UserMappingProvider $userMappingProvider
	 */
	public function __construct(
		TuleapConnection $tuleap, TitleFactory $titleFactory, UserFactory $userFactory,
		UserOptionsManager $userOptionsManager, UserGroupManager $groupManager,
		UserMappingProvider $userMappingProvider
	) {
		parent::__construct( 'TuleapLogin', '', false );
		$this->tuleap = $tuleap;
		$this->titleFactory = $titleFactory;
		$this->userFactory = $userFactory;
		$this->userOptionsManager = $userOptionsManager;
		$this->groupManager = $groupManager;
		$this->userMappingProvider = $userMappingProvider;
		$this->logger = LoggerFactory::getInstance( 'tuleap-connection' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $subPage ) {
		$this->setHeaders();
		if ( $this->getUser()->isRegistered() ) {
			$this->redirectToReturnTo();
			$this->logger->debug( 'User already logged in' );
			return true;
		}

		if ( $subPage === 'callback' ) {
			$this->logger->debug( 'Run callback' );
			return $this->callback();
		}
		$url = $this->tuleap->getAuthorizationUrl(
			$this->getRequest()->getVal( 'returnto' ),
			$this->getRequest()->getBool( 'prompt' )
		);
		$this->getOutput()->redirect( $url );
		$this->logger->debug( 'Redirect to Tuleap' );

		return true;
	}

	/**
	 * Retrieve access token and log user in
	 *
	 * @return bool
	 * @throws \MWException
	 */
	public function callback() {
		$loginRequired = $this->getRequest()->getText( 'error' ) === 'login_required';
		if ( $loginRequired ) {
			if ( $this->canAnonsRead() ) {
				// If anons can read, allow them
				$this->getRequest()->getSession()->set( 'tuleap-anon-auth-done', true );
				$this->redirectToMainPage();
				return true;
			} elseif ( !$this->askedForLogin() ) {
				// Otherwise, if not already, ask user to login
				$this->askForLogin();
				return true;
			}
		}

		// User logged, in set the user
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
	 * @return bool|User
	 * @throws \MWException
	 */
	private function setUser( TuleapResourceOwner $owner ) {
		$user = $this->userMappingProvider->provideUserForId( $owner->getId() );
		if ( $user === null ) {
			$this->logger->info( "User not in legacy mapping table" );
			$user = $this->userFactory->newFromName( $owner->getId() );
		}
		// Neither `tuleap_user_mapping` nor `TuleapResourceOwner::getId` provided usable values
		if ( $user === null ) {
			throw new \MWException( 'Could not create user' );
		}

		$user->setRealName( $owner->getRealName() );
		$user->setEmail( $owner->getEmail() );
		$user->load();
		$this->logger->info( "Setting user: " . $user->getName() );
		if ( !$user->isRegistered() ) {
			$user->addToDatabase();
		}
		if ( $owner->isEmailVerified() ) {
			$user->confirmEmail();
		}
		if ( $owner->getLocale() ) {
			$this->userOptionsManager->setOption( $user, 'language', $owner->getLocale() );
			$this->userOptionsManager->saveOptions( $user );
		}
		$this->setUserGroups( $user );
		$user->setToken();

		$this->getRequest()->getSession()->persist();
		$user->setCookies();
		$this->getContext()->setUser( $user );
		$user->saveSettings();

		$GLOBALS['wgUser'] = $user;
		$sessionUser = User::newFromSession( $this->getRequest() );
		$sessionUser->load();

		// Retrieve required data and store to session
		$this->tuleap->getIntegrationData(
			$this->getConfig()->get( 'TuleapProjectId' ), null, null, true
		);

		return $user;
	}

	/**
	 * Assign users to appropriate groups
	 *
	 * @param User $user
	 * @throws IdentityProviderException
	 */
	private function setUserGroups( User $user ) {
		// Load permissions again, as now user context is set
		$this->loadPermissions();
		if ( !$this->permissionConfig ) {
			return;
		}
		foreach ( $this->permissionConfig as $key => $value ) {
			if ( !isset( $this->groupMapping[$key] ) ) {
				continue;
			}
			$group = $this->groupMapping[$key];
			if ( $value ) {
				$this->logger->debug( "Add user to group $group" );
				$this->groupManager->addUserToGroup( $user, $group );
			} else {
				$this->logger->debug( "Remove user from group $group" );
				$this->groupManager->removeUserFromGroup( $user, $group );
			}
		}
	}

	/**
	 * Load permission config
	 *
	 * @return void
	 * @throws IdentityProviderException
	 */
	private function loadPermissions() {
		$this->permissionConfig = [];
		$projectId = $this->getConfig()->get( 'TuleapProjectId' );
		$data = $this->tuleap->getPermissionConfig( $projectId );
		if ( isset( $data['permissions'] ) ) {
			$this->permissionConfig = $data['permissions'];
		}
		$this->getRequest()->getSession()->set(
			'tuleap-permissions', $this->permissionConfig
		);
	}

	/**
	 * After login, return to whatever user wanted to see
	 */
	private function redirectToReturnTo() {
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

	/**
	 * @return bool
	 */
	private function canAnonsRead(): bool {
		$this->loadPermissions();
		if ( !$this->permissionConfig ) {
			return false;
		}
		return $this->permissionConfig['is_reader'];
	}

	private function askForLogin() {
		$this->getRequest()->getSession()->set( 'tuleap-ask-for-login', true );
		header( 'Location:' . $this->getPageTitle()->getLocalURL( [ 'prompt' => 1 ] ) );
		exit;
	}

	/**
	 * Check if we already asked for login
	 * @return bool
	 */
	private function askedForLogin(): bool {
		return (bool)$this->getRequest()->getSession()->get( 'tuleap-ask-for-login' );
	}
}
