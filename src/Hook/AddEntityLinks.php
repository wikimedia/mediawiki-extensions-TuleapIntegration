<?php

namespace TuleapIntegration\Hook;

use Config;
use MediaWiki\Hook\OutputPageBeforeHTMLHook;

class AddEntityLinks implements OutputPageBeforeHTMLHook {
	/** @var string */
	private $group;

	/**
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		$this->group = $config->get( 'TuleapArtLinksGroupId' );
	}

	/**
	 * @inheritDoc
	 */
	public function onOutputPageBeforeHTML( $out, &$text ) {
		$reference_manager = \ReferenceManager::instance();
		$reference_manager->insertReferences( $text, $this->group );

		return true;
	}
}
