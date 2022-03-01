<?php

namespace TuleapIntegration\Hook;

use MediaWiki\Hook\PersonalUrlsHook;
use MediaWiki\Linker\Hook\HtmlPageLinkRendererEndHook;
use MediaWiki\User\UserFactory;

class ReplaceUserLinks implements HtmlPageLinkRendererEndHook, PersonalUrlsHook {
	/** @var UserFactory */
	private $userFactory;

	public function __construct( UserFactory $userFactory ) {
		$this->userFactory = $userFactory;
	}

	public function onHtmlPageLinkRendererEnd(
		$linkRenderer, $target, $isKnown, &$text, &$attribs, &$ret
	) {
		if ( $target->getNamespace() !== NS_USER ) {
			return true;
		}
		if ( strpos( $target->getText(), '/' ) !== false ) {
			return true;
		}
		$user = $this->userFactory->newFromName( $target->getDBkey() );
		if ( !( $user instanceof $user) || !$user->isRegistered() ) {
			return true;
		}
		error_log( $user->getRealName() );
		$text = $user->getRealName();
		return true;
	}

	public function onPersonalUrls( &$personal_urls, &$title, $skin ): void {
		if ( !isset( $personal_urls['userpage'] ) ) {
			return;
		}
		$user = $this->userFactory->newFromName( $personal_urls['userpage']['text'] );
		if ( !( $user instanceof $user) || !$user->isRegistered() ) {
			return;
		}
		$personal_urls['userpage']['text'] = $user->getRealName();
	}
}
