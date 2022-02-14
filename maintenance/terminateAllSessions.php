<?php

require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/maintenance/Maintenance.php';

class TerminateAllSessions extends Maintenance {
	private $batchSize = 1000;

	public function execute() {
		$lbFactory = \MediaWiki\MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		$sessionManager = \MediaWiki\Session\SessionManager::singleton();
		$userFactory = \MediaWiki\MediaWikiServices::getInstance()->getUserFactory();

		$users = $lbFactory->getMainLB()->getConnection( DB_REPLICA )->select(
			'user',
			[ 'user_name' ],
			[],
			__METHOD__
		);

		$i = 0;
		foreach ( $users as $userRow ) {
			$i++;
			$user = $userFactory->newFromName( $userRow->user_name );
			try {
				$sessionManager->invalidateSessionsForUser( $user );
				if ( $user->getId() ) {
					$this->output( 'Invalidated session for user ' . $user->getName() . "\n" );
				} else {
					$this->output( "Cannot find user {$user->getName()}, tried to invalidate anyways\n" );
				}
			} catch ( Exception $ex ) {
				$this->output( "Failed to invalidate sessions for user {$user->getName()} | "
					. str_replace( [ "\r", "\n" ], ' ', $ex->getMessage() ) . "\n" );
			}

			if ( $i % $this->batchSize ) {
				$lbFactory->waitForReplication();
			}
		}
	}

}

$maintClass = 'TerminateAllSessions';
require_once RUN_MAINTENANCE_IF_MAIN;
