<?php

namespace TuleapIntegration;

// phpcs:disable Generic.Files.LineLength.TooLong
use Config;
use Exception;
use Html;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\OutputPageBeforeHTMLHook;
use OutputPage;
use Skin;

class ReferenceManager implements OutputPageBeforeHTMLHook, BeforePageDisplayHook {
	/** @var Config */
	private $config;
	/** @var TuleapConnection */
	private $connection;

	/**
	 * @param TuleapConnection $connection
	 * @param Config $config
	 */
	public function __construct( TuleapConnection $connection, Config $config ) {
		$this->connection = $connection;
		$this->config = $config;
	}

	/**
	 * @param OutputPage $out
	 * @param string &$text
	 * @return bool|void
	 */
	public function onOutputPageBeforeHTML( $out, &$text ) {
		if ( $out->getTitle()->isSpecialPage() ) {
			return;
		}
		$text = $this->renderReferences( $text );
	}

	/**
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$out->addModules( "ext.tuleap.forcereload.js" );
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function renderReferences( string $text ) {
		$allowed = $this->getAllowedReferenceKeywords(
			$this->connection->getIntegrationData( $this->config->get( 'TuleapProjectId' ) )
		);
		return preg_replace_callback(
			$this->getRegexp(),
			function ( $match ) use ( $allowed ) {
				if ( !$match['key'] || !in_array( $match['key'], $allowed ) ) {
					return $match[0];
				}
				return $this->getReferenceAnchor( $match );
			},
			$text
		);
	}

	/**
	 * @return string
	 */
	private function getRegexp() {
		return "`
            (?(DEFINE)
                (?<final_value_sequence>\w|&amp;|&)                         # Any word, & or &amp;
                (?<extended_value_sequence>(?&final_value_sequence)|-|_|\.) # <final_value_sequence>, -, _ or .
            )
            (?P<key>\w+)
            \s          #blank separator
            \#          #dash (2 en 1)
            (?P<project_name>[\w\-]+:)? #optional project name (followed by a colon)
            (?P<value>(?:(?:(?&extended_value_sequence)+/)*)?(?&final_value_sequence)+?) # Sequence of multiple '<extended_value_sequence>/' ending with <final_value_sequence>
            (?P<after_reference>&(?:\#(?:\d+|[xX][[:xdigit:]]+)|quot);|(?=[^\w&/])|$) # Exclude HTML dec, hex and some (quot) named entities from the end of the reference
        `x";
	}

	/**
	 * @param array $match
	 * @return string
	 * @throws Exception
	 */
	private function getReferenceAnchor( $match ) {
		return Html::element( 'a', [
			'href' => $this->makeRefUrl( $match ),
		], $match[0] );
	}

	/**
	 * @param array $match
	 * @return string
	 * @throws Exception
	 */
	private function makeRefUrl( $match ) {
		$base = $this->config->get( 'TuleapUrl' );
		if ( !$base ) {
			throw new Exception( 'TuleapUrl not set' );
		}
		$project = rtrim( $match['project_name'], ':' );
		if ( empty( $project ) ) {
			$project = $this->config->get( 'TuleapProjectId' );
		}
		return wfAppendQuery( rtrim( $base, '/' ) . '/goto', [
			'key' => $match['key'],
			'val' => $match['value'],
			'group_id' => $project
		] );
	}

	/**
	 * @param array $integrationData
	 * @return array
	 */
	private function getAllowedReferenceKeywords( $integrationData ) {
		if ( !is_array( $integrationData ) || !isset( $integrationData['references'] ) ) {
			return [];
		}
		return array_map( static function ( $reference ) {
			return $reference['keyword'];
		}, $integrationData['references'] );
	}
}
