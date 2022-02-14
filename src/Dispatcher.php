<?php

namespace TuleapIntegration;

use Maintenance;

class Dispatcher {

	/**
	 * @var array
	 */
	private $server = [];

	/**
	 * @var array
	 */
	private $request = [];

	/**
	 * @var array
	 */
	private $globals = [];

	/**
	 * @var InstanceManager
	 */
	private $manager;

	/**
	 *
	 * @var InstanceEntity|null
	 */
	private $instance = null;

	/**
	 *
	 * @var string
	 */
	private $instanceVaultPathname = '';

	/**
	 * @param array $server $_SERVER
	 * @param array $request $_REQUEST
	 * @param array &$globals $GLOBALS
	 * @param InstanceManager $manager
	 */
	public function __construct( $server, $request, &$globals, InstanceManager $manager ) {
		$this->server = $server;
		$this->request = $request;
		$this->globals =& $globals;
		$this->manager = $manager;
	}

	/**
	 *
	 * @var string
	 */
	private $mainSettingsFile = '';

	/**
	 *
	 * @var string[]
	 */
	private $filesToRequire = [];

	/**
	 * @return string[]
	 */
	public function getFilesToRequire() {
		$this->initInstance();
		$this->defineConstants();
		if ( $this->isCliInstallerContext() ) {
			return [];
		}

		if ( $this->isInstanceWikiCall() ) {
			$this->initInstanceVaultPathname();
			$this->mainSettingsFile = "{$this->instanceVaultPathname}/LocalSettings.php";

			$this->redirectIfNoInstance();
			$this->redirectIfNotReady();

			$this->includeMainSettingsFile();
			$this->setupEnvironment();
		}

		$this->includeTuleapFile();

		return $this->filesToRequire;
	}

	private function initInstance() {
		if ( $this->isMaintenance() ) {
			// this works for all maintenance scripts.
			// put an --sfr "WIKI_PATH_NAME" on the call and the settings
			// files of the right wiki will be included.
			//TODO: Inject like $_REQUEST
			$extractor = new CliArgInstanceNameExtractor();
			$name = $extractor->extractInstanceName( $this->globals['argv'] );
			if ( !empty( $name ) ) {
				$this->instance = $this->manager->getStore()->getInstanceByName( $name );

				if ( !$this->instance ) {
					echo "Invalid instance: $name";
					die();
				}
				// We need to reset let the maintenance script reload the arguments, as we now have
				// removed the "--sfr" flag, which would lead to an "Unexpected option" error
				/** @var Maintenance */
				$this->globals['maintenance']->clearParamsAndArgs();
				$this->globals['maintenance']->loadParamsAndArgs();
			}
		} else {
			$name = isset( $this->request['sfr'] ) ? $this->request['sfr'] : 'w';
			$this->instance = $this->manager->getStore()->getInstanceByName( $name );
			unset( $this->request['sfr'] );
			$this->redirectIfNoInstance();
		}
	}

	private function initInstanceVaultPathname() {
		$this->instanceVaultPathname = $this->manager->getDirectoryForInstance( $this->instance );
	}

	private function defineConstants() {
		// For "root"-wiki calls only
		if ( $this->isRootWikiCall() ) {
			define( 'FARMER_IS_ROOT_WIKI_CALL', true );
			define( 'FARMER_CALLED_INSTANCE', '' );
		} else {
			define( 'FARMER_IS_ROOT_WIKI_CALL', false );
			define( 'FARMER_CALLED_INSTANCE', $this->instance->getName() );
		}
	}

	private function isRootWikiCall() {
		return !$this->instance || $this->instance instanceof RootInstanceEntity;
	}

	private function isInstanceWikiCall() {
		return $this->instance &&
			$this->instance instanceof InstanceEntity && !( $this->instance instanceof RootInstanceEntity );
	}

	private function setupEnvironment() {
		// TODO: Hardcoded path
		$this->globals['wgUploadPath'] = '/w/_instances/' . $this->instance->getScriptPath() . '/images';
		$this->globals['wgUploadDirectory'] = "{$this->instanceVaultPathname}/images";
		$this->globals['wgReadOnlyFile'] = "{$this->globals['wgUploadDirectory']}/lock_yBgMBwiR";
		$this->globals['wgFileCacheDirectory'] = "{$this->globals['wgUploadDirectory']}/cache";
		$this->globals['wgDeletedDirectory'] = "{$this->globals['wgUploadDirectory']}/deleted";
		$this->globals['wgCacheDirectory'] = "{$this->instanceVaultPathname}/cache";

		define( 'WIKI_FARMING', true );
	}

	private function redirectIfNotReady() {
		$redir = null;
		switch ( $this->instance->getStatus() ) {
			case InstanceEntity::STATE_INITIALIZING:
				$redir = '/w/ti_init.html';
				break;
			case InstanceEntity::STATE_MAINTENANCE:
				if ( !$this->isMaintenance() ) {
					$redir = '/w/ti_maintenance.html';
				}
				break;
		}

		if ( $redir ) {
			if ( $this->isMaintenance() ) {
				echo "Instance is not ready\n";
				die();
			}
			header( 'Location: ' . $redir );
			die();
		}
	}

	private function redirectIfNoInstance() {
		if ( $this->instance === null ) {
			echo "No such instance";
			die();
		}
	}

	private function includeTuleapFile() {
		$this->doInclude( $this->globals['IP'] . '/LocalSettings.Tuleap.php' );
	}

	private function includeMainSettingsFile() {
		$this->doInclude( $this->mainSettingsFile );
	}

	/**
	 * @param string $pathname
	 */
	private function doInclude( $pathname ) {
		$this->filesToRequire[] = $pathname;
	}

	private function isCliInstallerContext() {
		return defined( 'MEDIAWIKI_INSTALL' );
	}

	/**
	 * @return bool
	 */
	private function isMaintenance() {
		return defined( 'DO_MAINTENANCE' ) && is_file( RUN_MAINTENANCE_IF_MAIN );
	}
}
