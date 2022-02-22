<?php

namespace TuleapIntegration\Hook;

use MediaWiki\Hook\PersonalUrlsHook;

class RemoveManualLogin implements PersonalUrlsHook {

	/**
	 * @inheritDoc
	 */
	public function onPersonalUrls( &$personal_urls, &$title, $skin ): void {
		if ( isset( $personal_urls['login'] ) ) {
			unset( $personal_urls['login'] );
		}
		if ( isset( $personal_urls['logout'] ) ) {
			unset( $personal_urls['logout'] );
		}
	}
}
