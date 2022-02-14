<?php

use MediaWiki\Installer\InstallException;
use TuleapIntegration\InstanceCliInstaller;

require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/maintenance/Maintenance.php';

class InstallInstance extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addOption( 'instanceName', '', true, true );
		$this->addOption( 'dbserver', '', true, true );
		$this->addOption( 'dbname', '', true, true );
		$this->addOption( 'dbuser', '', true, true );
		$this->addOption( 'dbpass', '', true, true );
		$this->addOption( 'server', '', true, true );
		$this->addOption( 'scriptpath', '', true, true );
		$this->addOption( 'lang', '', true, true );
		$this->addOption( 'adminuser', '', true, true );
		$this->addOption( 'adminpass', '', true, true );
		$this->addOption( 'instanceDir', '', true, true );
	}

	/**
	 * @return bool|void|null
	 * @throws InstallException
	 */
	public function execute() {
		$installer = new InstanceCliInstaller(
			$this->getOption( 'instanceName' ), $this->getOption( 'adminuser' ), [
				'scriptpath' => $this->getOption( 'scriptpath' ),
				'dbname' => $this->getOption( 'dbname' ),
				'dbserver' => $this->getOption( 'dbserver' ),
				'dbuser' => $this->getOption( 'dbuser' ),
				'dbpass' => $this->getOption( 'dbpass' ),
				'server' => $this->getOption( 'server' ),
				'pass' => $this->getOption( 'adminpass' ),
				'lang' => $this->getOption( 'lang' )
			]
		);

		$status = $installer->execute();

		if ( !$status->isOk() ) {
			$this->fatalError( $status->getMessage()->plain() );
		}

		$installer->writeConfigurationFile( $this->getOption( 'instanceDir' ) );
	}

}

$maintClass = 'InstallInstance';
require_once RUN_MAINTENANCE_IF_MAIN;
