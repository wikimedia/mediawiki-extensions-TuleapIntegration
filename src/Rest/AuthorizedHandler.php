<?php

namespace TuleapIntegration\Rest;

use MediaWiki\Rest\Handler;
use MediaWiki\Rest\HttpException;
use MediaWiki\Session\SessionManager;

abstract class AuthorizedHandler extends Handler {
	/**
	 * This will evaluate session created by AuthenticationHeader (if passed)
	 * @throws HttpException
	 */
	protected function assertRights() {
		$meta = SessionManager::getGlobalSession()->getProviderMetadata();
		if (
			!is_array( $meta ) || !isset( $meta['rights'] ) ||
			!in_array( 'tuleap-farm-manage', $meta['rights'] )
		) {
			throw new HttpException( 'permissiondenied', 401 );
		}
	}
}
