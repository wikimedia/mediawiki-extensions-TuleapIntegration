<?php

namespace TuleapIntegration;

use MediaWiki\User\UserFactory;
use Psr\Log\LoggerInterface;
use User;
use Wikimedia\Rdbms\ILoadBalancer;

class UserMappingProvider {
	/** @var ILoadBalancer */
	private $lb;
	/** @var UserFactory */
	private $userFactory;
	/** @var LoggerInterface */
	private $logger;

	/**	 *
	 * @param ILoadBalancer $loadBalancer
	 * @param UserFactory $userFactory
	 */
	public function __construct( ILoadBalancer $loadBalancer, UserFactory $userFactory, LoggerInterface $logger ) {
		$this->lb = $loadBalancer;
		$this->userFactory = $userFactory;
		$this->logger = $logger;
	}

	/**
	 * @param int $id Tuleap user ID
	 *
	 * @return User|null
	 */
	public function provideUserForId( int $id ): ?User {
		$db = $this->lb->getConnection( DB_REPLICA );
		if ( !$db->tableExists( 'tuleap_user_mapping', __METHOD__ ) ) {
			$this->logger->info( 'Table `tuleap_user_mapping` does not exist' );
			return null;
		}
		$row = $db->selectRow(
			'tuleap_user_mapping',
			[
				'tum_user_name'
			],
			[
				'tum_user_id' => $id
			],
			__METHOD__
		);

		if ( !$row ) {
			$this->logger->debug( "Could not find local username mapping for id '$id'" );
			return null;
		}

		$uname = $row->tum_user_name;
		$user = $this->userFactory->newFromName( $uname );
		if ( $user instanceof User ) {
			return $user;
		}

		$this->logger->debug( "Could not create valid user from name '$uname' (ID:$id)" );
		return null;
	}
}
