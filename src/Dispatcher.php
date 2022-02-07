<?php

namespace TuleapIntegration;

use ExtensionRegistry;
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
	 * @var \Config
	 */
	private $config = [];

	/**
	 *
	 * @var string
	 */
	private $instanceName = '';

	/**
	 *
	 * @var string
	 */
	private $instanceVaultPathname = '';

	/**
	 *
	 * @param array $server $_SERVER
	 * @param array $request $_REQUEST
	 * @param array $globals $GLOBALS
	 * @param \Config $config
	 */
	public function __construct( $server, $request, &$globals, $config ) {
		$this->server = $server;
		$this->request = $request;
		$this->globals =& $globals;
		$this->config = $config;
	}

	/**
	 *
	 * @var string
	 */
	private $mainSettingsFile = '';

	/**
	 *
	 * @var string
	 */
	private $suspendFile = '';

	/**
	 *
	 * @var string
	 */
	private $customSettingsFile = '';

	/**
	 *
	 * @var string[]
	 */
	private $filesToRequire = [];

	/**
	 * @return string[]
	 */
	public function getFilesToRequire() {
		$this->initInstanceName();
		$this->defineConstants();
		if( $this->isCliInstallerContext() ) {
			return [];
		}

		$this->initInstanceVaultPathname();

		if( $this->isInstanceWikiCall() ) {
			$this->mainSettingsFile = "{$this->instanceVaultPathname}/LocalSettings.php";


			$this->redirectIfNonExisting();
			$this->redirectIfSuspended();

			$this->includeMainSettingsFile();
			$this->setupEnvironment();
		}

		$this->includeLocalSettingsAppend();
		$this->maybeIncludeLocalSettingsCustom();

		//Must be executed _after_ all calls to `wfLoadExtension/s` and `wfLoadSkin/s`
		// Must no use `\Hooks::register`, as it would initialize MediaWikiServices and
		// therefore break the DynamicSettings mechanism from BlueSpiceFoundation
		$GLOBALS['wgHooks']['SetupAfterCache'][] = function() {
			$this->onSetupAfterCache();
		};

		return $this->filesToRequire;
	}

	private function onSetupAfterCache() {
		$this->applyAdditionalDynamicConfiguration();
	}

	private function applyAdditionalDynamicConfiguration() {
		$dynamicConfigFactories = ExtensionRegistry::getInstance()
			->getAttribute( 'BlueSpiceSimpleFarmerDynamicConfigurationFactories' );

		foreach( $dynamicConfigFactories as $factoryKey => $factoryCallback ) {
			if( !is_callable( $factoryCallback ) ) {
				throw new \MWException( "Callback for '$factoryKey' not callable!" );
			}

			// Can not use `call_user_func_array` here as it has troubles with passing $GLOBALS
			$dynamicConfig = $factoryCallback( $this->instanceName, $this->globals );

			if( $dynamicConfig instanceof IDynamicConfiguration === false ) {
				throw new \MWException(
					"Callback for '$factoryKey' returned no 'IDynamicConfiguration'!"
				);
			}

			$dynamicConfig->apply();
		}
	}

	private function initInstanceName() {
		if ( $this->isMaintenance() ) {
			// this works for all maintenance scripts.
			// put an --sfr "WIKI_PATH_NAME" on the call and the settings
			// files of the right wiki will be included.
			//TODO: Inject like $_REQUEST
			$extractor = new CliArgInstanceNameExtractor();
			$this->instanceName = $extractor->extractInstanceName( $this->globals['argv'] );
			if ( !empty( $this->instanceName ) ) {
				echo ">>> Running maintenance script for instance '{$this->instanceName}'\n";

				// We need to reset let the maintenance script reload the arguments, as we now have
				// removed the "--sfr" flag, which would lead to an "Unexpected option" error
				/** @var Maintenance */
				$this->globals['maintenance']->clearParamsAndArgs();
				$this->globals['maintenance']->loadParamsAndArgs();
			}
		} else {
			$this->instanceName = isset( $this->request['ti'] ) ? $this->request['ti'] : '';
			unset( $this->request['ti'] );
			$this->redirectIfNoInstance();
		}
	}

	private function initInstanceVaultPathname() {
		$baseVaultDir = $this->config->get( 'instanceDirectory' );
		$this->instanceVaultPathname = "$baseVaultDir/{$this->instanceName}";
	}


	private function defineConstants() {
		//For "root"-wiki calls only
		if ( $this->isRootWikiCall() ) {
			define( 'FARMER_IS_ROOT_WIKI_CALL', true );
			define( 'FARMER_CALLED_INSTANCE', '' );
		}
		else {
			define( 'FARMER_IS_ROOT_WIKI_CALL', false );
			define( 'FARMER_CALLED_INSTANCE', $this->instanceName );
		}
	}

	private function isRootWikiCall() {
		if( empty( $this->instanceName ) ) {
			return true;
		}
		if( strtolower( $this->instanceName ) === 'index.php' ) {
			return true;
		}
		if( strtolower( $this->instanceName ) === 'w' ) {
			return true;
		}

		return false;
	}

	private function isInstanceWikiCall() {
		return !$this->isRootWikiCall();
	}

	private function setupEnvironment() {
		$this->globals['wgUploadPath'] = $this->config->get('instancePath').$this->instanceName.'/images';
		$this->globals['wgUploadDirectory'] = "{$this->instanceVaultPathname}/images";
		$this->globals['wgReadOnlyFile'] = "{$this->globals['wgUploadDirectory']}/lock_yBgMBwiR";
		$this->globals['wgFileCacheDirectory'] = "{$this->globals['wgUploadDirectory']}/cache";
		$this->globals['wgDeletedDirectory'] = "{$this->globals['wgUploadDirectory']}/deleted";
		$this->globals['wgCacheDirectory'] = "{$this->instanceVaultPathname}/cache";

		// Set up BlueSpice environment
		define( 'WIKI_FARMING', true);
		define( 'BSROOTDIR', "{$this->globals['IP']}/extensions/BlueSpiceFoundation" );
		define( 'BSCONFIGDIR', "{$this->instanceVaultPathname}/extensions/BlueSpiceFoundation/config" );
		define( 'BSDATADIR', "{$this->instanceVaultPathname}/extensions/BlueSpiceFoundation/data" ); //Present
		define( 'BS_DATA_DIR', "{$this->globals['wgUploadDirectory']}/bluespice" ); //Future
		define( 'BS_CACHE_DIR', "{$this->globals['wgFileCacheDirectory']}/bluespice" );
		define( 'BS_DATA_PATH', "{$this->globals['wgUploadPath']}/bluespice" );
		$this->globals['bsgPermissionManagerGroupSettingsFile'] = BSCONFIGDIR.'/pm-settings.php';
	}

	private function redirectIfNonExisting() {
		if( !file_exists( $this->mainSettingsFile ) ) {
			if ( $this->isMaintenance() ) {
				echo "No such instance\n";
				die();
			}
			header( 'Location: '. $this->config->get( 'invalidWikiRedirect' ) );
			die();
		}
	}

	private function redirectIfSuspended() {
		if( file_exists( $this->suspendFile ) ) {
			if ( $this->isMaintenance() ) {
				echo "Instance is suspended\n";
				die();
			}
			header( 'Location: '. $this->config->get( 'suspendedWikiRedirect' ) );
			die();
		}
	}

	private function redirectIfNoInstance() {
		if( empty( $this->instanceName ) ) {
			header( 'Location: '. $this->config->get( 'defaultRedirect' ) );
			die();
		}
	}

	private function includeMainSettingsFile() {
		$this->doInclude( $this->mainSettingsFile );
	}

	private function includeLocalSettingsAppend() {
		$this->doInclude( $this->config->get( 'LocalSettingsAppendPath' ) );
	}

	private function maybeIncludeLocalSettingsCustom() {
		if ( $this->isInstanceWikiCall() && file_exists( $this->customSettingsFile ) ) {
			$this->doInclude( $this->customSettingsFile );
		}
	}

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
		return defined( 'DO_MAINTENANCE' ) && is_file( DO_MAINTENANCE );
	}

}
