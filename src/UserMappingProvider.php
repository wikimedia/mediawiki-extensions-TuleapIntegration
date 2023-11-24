<?php

namespace TuleapIntegration;

use MediaWiki\User\UserFactory;
use User;
use Wikimedia\Rdbms\ILoadBalancer;

class UserMappingProvider {
	/** @var ILoadBalancer */
	private $lb;
	/** @var UserFactory */
	private $userFactory;

	/**	 *
	 * @param ILoadBalancer $loadBalancer
	 * @param UserFactory $userFactory
	 */
	public function __construct( ILoadBalancer $loadBalancer, UserFactory $userFactory ) {
		$this->lb = $loadBalancer;
		$this->userFactory = $userFactory;
	}

	/**
	 * @param int $id Tuleap user ID
	 *
	 * @return User|null
	 */
	public function provideUserForId( int $id ): ?User {
		$db = $this->lb->getConnection( DB_REPLICA );
		if ( !$db->tableExists( 'tuleap_user_mapping' ) ) {
			return null;
		}
		$row = $this->lb->getConnection( DB_REPLICA )->selectRow(
			'tuleap_user_mapping',
			[
				'tum_user_name'
			],
			[
				'tum_user_id' => $id
			]
		);

		if ( !$row ) {
			return null;
		}

		$uname = $row->tum_user_name;
		$user = $this->userFactory->newFromName( $uname );
		if ( $user instanceof User ) {
			return $user;
		}

		return null;
	}
}
