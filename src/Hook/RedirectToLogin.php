<?php

namespace TuleapIntegration\Hook;

use MediaWiki;
use MediaWiki\Hook\BeforeInitializeHook;

class RedirectToLogin implements BeforeInitializeHook {

	/**
	 * @inheritDoc
	 */
	public function onBeforeInitialize( $title, $unused, $output, $user, $request, $mediaWiki ) {
		if ( $user->isRegistered() ) {
			return true;
		}
		if ( $title->isSpecial( 'TuleapLogin' ) || $title->isSpecial( 'Logout' ) ) {
			return true;
		}
		$spf = MediaWiki\MediaWikiServices::getInstance()->getSpecialPageFactory();
		header( 'Location: ' . $spf->getPage( 'TuleapLogin' )->getPageTitle()->getFullURL() );
	}
}
