<?php

require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/maintenance/Maintenance.php';

class SetUserGroups extends Maintenance {
	/** @var \MediaWiki\User\UserFactory */
	private $userFactory;
	/** @var \MediaWiki\User\UserGroupManager */
	private $groupManager;
	/** @var array */
	private $invalidUsers = [];

	public function __construct() {
		parent::__construct();

		$this->addOption( 'data', 'Group mappings', true, true, 'd' );
	}

	public function execute() {
		$data = $this->getOption( 'data' );
		$status = FormatJson::parse( $data, FormatJson::FORCE_ASSOC );
		if ( !$status->isOK() ) {
			$this->error( 'Invalid input data', 1 );
		}
		$this->userFactory = \MediaWiki\MediaWikiServices::getInstance()->getUserFactory();
		$this->groupManager = \MediaWiki\MediaWikiServices::getInstance()->getUserGroupManager();
		$data = $status->getValue();

		if ( isset( $data['add'] ) && is_array( $data['add'] ) ) {
			$this->changeGroups( $data['add'], 'add' );
		}
		if ( isset( $data['remove'] ) && is_array( $data['remove'] ) ) {
			$this->changeGroups( $data['remove'], 'remove' );
		}

		if ( !empty( $this->invalidUsers ) ) {
			$this->output( 'Warning: Invalid users: ' . implode( ',', $this->invalidUsers ) );
		}

		$this->output( "Completed!" );
	}

	/**
	 * @param array $mapping
	 * @param string $type
	 */
	private function changeGroups( $mapping, $type ) {
		foreach ( $mapping as $username => $groups ) {
			$user = $this->userFactory->newFromName( $username );
			if ( !( $user instanceof \User ) || !$user->isRegistered() ) {
				$this->invalidUsers[] = $username;
				continue;
			}
			if ( $type === 'add' ) {
				foreach ( $groups as $group ) {
					$this->groupManager->addUserToGroup( $user, $group );
				}
			}
			if ( $type === 'remove' ) {
				foreach ( $groups as $group ) {
					$this->groupManager->removeUserFromGroup( $user, $group );
				}
			}
		}
	}
}

$maintClass = 'SetUserGroups';
require_once RUN_MAINTENANCE_IF_MAIN;
