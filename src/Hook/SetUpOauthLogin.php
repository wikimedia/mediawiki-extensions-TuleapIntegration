<?php

namespace TuleapIntegration\Hook;

use Config;
use MediaWiki;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Hook\PersonalUrlsHook;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use SkinTemplate;
use Title;

class SetUpOauthLogin implements BeforeInitializeHook, SpecialPage_initListHook, PersonalUrlsHook {
	/** @var bool */
	private $enableLocalLogin;
	/** @var bool */
	private $enableAnonAccess;

	/**
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		$this->enableLocalLogin = (bool)$config->get( 'TuleapEnableLocalLogin' );
		$this->enableAnonAccess = $config->get( 'TuleapAccessPreset' ) === 'anonymous';
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
		if ( $this->enableAnonAccess ) {
			$personal_urls['login']['text'] = \Message::newFromKey( 'tuleap-login-button' )->text();

			$spf = MediaWiki\MediaWikiServices::getInstance()->getSpecialPageFactory();
			$loginPage = $spf->getPage( 'TuleapLogin' )->getPageTitle();
			$personal_urls['login']['href'] = $loginPage->getLocalURL( [ 'prompt' => 1 ] );
		} else {
			unset( $personal_urls['login'] );
		}
	}
}
