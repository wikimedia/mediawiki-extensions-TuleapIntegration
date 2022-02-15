<?php

namespace TuleapIntegration;

use MediaWiki\Session\ImmutableSessionProviderWithCookie;
use MediaWiki\Session\SessionInfo;
use MediaWiki\Session\UserInfo;
use WebRequest;

class PreSharedKeySessionProvider extends ImmutableSessionProviderWithCookie {
	/** @var int Tolerate timestamp difference in these many seconds */
	private $acceptableLeeway = 10;

	/**
	 * @inheritDoc
	 */
	public function provideSessionInfo( WebRequest $request ) {
		if ( !defined( 'MW_API' ) && !defined( 'MW_REST_API' ) ) {
			return null;
		}

		$header = $request->getHeader( 'Authorization' );
		if ( strpos( $header, 'Bearer' ) !== 0 ) {
			return null;
		}
		$token = substr( $header, 7 );

		if ( $this->tokenValid( $token ) ) {
			$user = \User::newSystemUser( 'Mediawiki default' );
			return new SessionInfo( SessionInfo::MAX_PRIORITY, [
				'provider' => $this,
				'id' => null,
				'userInfo' => UserInfo::newFromUser( $user, true ),
				'persisted' => false,
				'forceUse' => true,
				'metadata' => [
					'app' => 'tuleap',
					'rights' => \MWGrants::getGrantRights( [ 'farm-management' ] ),
				],
			] );
		}

		return null;
	}

	/**
	 * @param string $token Token provided via Authentication header
	 * @return bool
	 */
	private function tokenValid( $token ): bool {
		$secret = $this->config->get( 'TuleapPreSharedKey' );
		if ( !$secret ) {
			$this->logger->error( 'wgTuleapPreSharedKey is not set' );
			return false;
		}

		return $this->matchAny( $token, $this->getAcceptableTokens( time(), $secret ) );
	}

	/**
	 * @param int $timestamp Current timestamp
	 * @param string $secret Pre-shared key
	 * @return array
	 */
	private function getAcceptableTokens( int $timestamp, $secret ): array {
		$allowedTimestamps = range(
			$timestamp - $this->acceptableLeeway,
			$timestamp + $this->acceptableLeeway + 1
		);

		return array_map( function( $ts ) use ( $secret ) {
			return hash_hmac( 'sha256', $secret, $ts );
		}, $allowedTimestamps );
	}

	/**
	 * @param string $token
	 * @param array $valid Array of tolerated tokens
	 * @return bool
	 */
	private function matchAny( $token, $valid ) {
		foreach ( $valid as $potential ) {
			if ( hash_equals( $token, $potential ) ) {
				return true;
			}
		}

		return false;
	}
}
