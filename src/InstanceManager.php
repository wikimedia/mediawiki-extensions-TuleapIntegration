<?php

namespace TuleapIntegration;

class InstanceManager {
	private $store;

	public function __construct( InstanceStore $store) {
		$this->store = $store;
	}

	public function getStore(): InstanceStore {
		return $this->store;
	}

	public function checkInstanceNameValidity( $name ) {
		// TODO: Implement
		return true;
	}

	public function isCreatable( string $name ) {
		return $this->checkInstanceNameValidity( $name ) && !$this->getStore()->instanceExists( $name );
	}

	public function generateScriptPath( InstanceEntity $instance ) {
		$name = $instance->getName();
		$name = str_replace( ' ', '-', $name );
		$name = str_replace( '_', '-', $name );

		return "/$name";
	}

	public function generateDbName( $prefix ) {
		$prefix = "{$prefix}tuleap_";

		return substr( uniqid( $prefix, true ), 0, 16 );
	}

	public function getDirectoryForInstance( InstanceEntity $instance ) {
		if ( $instance instanceof RootInstanceEntity ) {
			return $instance->getDirectory();
		}
		return $this->getInstanceDirBase() . $instance->getDirectory();
	}

	public function getInstanceDirBase() {
		return $GLOBALS['IP'] . '/_instances';
	}
}
