<?php

namespace TuleapIntegration\Hook;

use Config;
use MediaWiki;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Hook\PersonalUrlsHook;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;
use MediaWiki\Permissions\Hook\UserCanHook;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use Message;
use SkinTemplate;
use Title;
use User;

class SetUpOauthLogin implements
	BeforeInitializeHook,
	SpecialPage_initListHook,
	PersonalUrlsHook,
	GetUserPermissionsErrorsHook,
	UserCanHook
{
	/** @var bool */
	private $enableLocalLogin;

	/**
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		$this->enableLocalLogin = (bool)$config->get( 'TuleapEnableLocalLogin' );
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforeInitialize( $title, $unused, $output, $user, $request, $mediaWiki ) {
		if ( $this->enableLocalLogin ) {
			return;
		}
		if ( $user->isRegistered() ) {
			return;
		}
		if ( $title->isSpecial( 'TuleapLogin' ) || $title->isSpecial( 'Logout' ) ) {
			return;
		}
		if ( $request->getSession()->get( 'tuleap-anon-auth-done' ) ) {
			// Already tried to authenticate, and tuleap allowed anon access
			return;
		}
		$returnto = '';
		if ( $title instanceof Title ) {
			$returnto = $title->getPrefixedDBkey();
		}
		$spf = MediaWiki\MediaWikiServices::getInstance()->getSpecialPageFactory();
		header( 'Location: ' . $spf->getPage( 'TuleapLogin' )->getPageTitle()
				->getFullURL( [ 'returnto' => $returnto ] ) );
		exit;
	}

	/**
	 * @param array &$list
	 * @return bool|void
	 */
	public function onSpecialPage_initList( &$list ) {
		unset( $list['createaccount'] );
		if ( $this->enableLocalLogin ) {
			return;
		}
		unset( $list['ChangePassword'] );
		unset( $list['Userlogin'] );
		unset( $list['Userlogout'] );
	}

	/**
	 * @param array &$personal_urls
	 * @param Title &$title
	 * @param SkinTemplate $skin
	 */
	public function onPersonalUrls( &$personal_urls, &$title, $skin ): void {
		if ( $this->enableLocalLogin ) {
			return;
		}
		if ( isset( $personal_urls['logout'] ) ) {
			unset( $personal_urls['logout'] );
		}
		if ( !isset( $personal_urls['login'] ) ) {
			return;
		}
		$personal_urls['login']['text'] = Message::newFromKey( 'tuleap-login-button' )->text();

		$spf = MediaWiki\MediaWikiServices::getInstance()->getSpecialPageFactory();
		$loginPage = $spf->getPage( 'TuleapLogin' )->getPageTitle();
		$personal_urls['login']['href'] = $loginPage->getLocalURL( [ 'prompt' => 1 ] );
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @param string $action
	 * @param array &$result
	 *
	 * @return bool
	 */
	public function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
		if ( $action !== 'read' ) {
			return true;
		}
		if ( $this->enableLocalLogin ) {
			return true;
		}
		if ( !$this->canCurrentUserRead( $title ) ) {
			$result[] = [ 'tuleap-no-access' ];
			return false;
		}
		return true;
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @param string $action
	 * @param bool &$result
	 *
	 * @return bool
	 */
	public function onUserCan( $title, $user, $action, &$result ) {
		if ( $action !== 'read' ) {
			return true;
		}
		if ( $this->enableLocalLogin ) {
			return true;
		}
		if ( $this->canCurrentUserRead( $title ) ) {
			$result = true;
			return false;
		}
		$result = false;
		return false;
	}

	/**
	 * @param Title $title
	 *
	 * @return bool
	 */
	private function canCurrentUserRead( $title ): bool {
		if ( $title->isSpecial( 'TuleapLogin' ) ) {
			return true;
		}
		$session = \RequestContext::getMain()->getRequest()->getSession();
		$permissions = $session->get( 'tuleap-permissions' );
		if ( !$permissions ) {
			return false;
		}
		if ( !isset( $permissions['is_reader'] ) || $permissions['is_reader'] === false ) {
			return false;
		}
		return true;
	}
}
