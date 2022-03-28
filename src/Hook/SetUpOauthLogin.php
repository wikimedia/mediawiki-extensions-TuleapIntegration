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
		$spf = MediaWiki\MediaWikiServices::getInstance()->getSpecialPageFactory();
		header( 'Location: ' . $spf->getPage( 'TuleapLogin' )->getPageTitle()->getFullURL() );
	}

	/**
	 * @param array &$list
	 * @return bool|void
	 */
	public function onSpecialPage_initList( &$list ) {
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

		if ( isset( $personal_urls['login'] ) ) {
			unset( $personal_urls['login'] );
		}
		if ( isset( $personal_urls['logout'] ) ) {
			unset( $personal_urls['logout'] );
		}
	}
}
