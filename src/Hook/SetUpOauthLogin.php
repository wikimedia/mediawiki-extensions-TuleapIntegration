<?php

namespace TuleapIntegration\Hook;

use Config;
use MediaWiki;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use Message;
use Title;
use User;

class SetUpOauthLogin implements
	BeforeInitializeHook,
	SpecialPage_initListHook,
	SkinTemplateNavigation__UniversalHook,
	GetUserPermissionsErrorsHook
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
	 * @inheritDoc
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( $this->enableLocalLogin ) {
			return;
		}
		if ( isset( $links['user-menu']['logout'] ) ) {
			unset( $links['user-menu']['logout'] );
		}
		if ( !isset( $links['user-menu']['login'] ) ) {
			return;
		}
		$links['user-menu']['login']['text'] = Message::newFromKey( 'tuleap-login-button' )->text();

		$spf = MediaWiki\MediaWikiServices::getInstance()->getSpecialPageFactory();
		$loginPage = $spf->getPage( 'TuleapLogin' )->getPageTitle();
		$links['user-menu']['login']['href'] = $loginPage->getLocalURL( [ 'prompt' => 1 ] );
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
			$result = [ 'tuleap-no-access' ];
			return false;
		}
		return true;
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
