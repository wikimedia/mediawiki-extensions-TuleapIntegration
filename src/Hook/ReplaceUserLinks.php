<?php

namespace TuleapIntegration\Hook;

use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Linker\Hook\HtmlPageLinkRendererEndHook;
use MediaWiki\User\UserFactory;
use User;

class ReplaceUserLinks implements HtmlPageLinkRendererEndHook, SkinTemplateNavigation__UniversalHook {
	/** @var UserFactory */
	private $userFactory;

	/**
	 * @param UserFactory $userFactory
	 */
	public function __construct( UserFactory $userFactory ) {
		$this->userFactory = $userFactory;
	}

	/**
	 * @inheritDoc
	 */
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
		if ( !( $user instanceof User ) || !$user->isRegistered() ) {
			return true;
		}
		$realName = $user->getRealName();
		if ( !empty( $realName ) ) {
			$text = $realName;
		}
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( !isset( $links['user-menu']['userpage'] ) ) {
			return;
		}
		$user = $this->userFactory->newFromName( $links['user-menu']['userpage']['text'] );
		if ( !( $user instanceof User ) || !$user->isRegistered() ) {
			return;
		}
		$realName = $user->getRealName();
		if ( !empty( $realName ) ) {
			$links['user-menu']['userpage']['text'] = $realName;
		}
	}
}
