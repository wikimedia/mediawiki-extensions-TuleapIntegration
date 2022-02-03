<?php

namespace TuleapIntegration;

class InstanceManager {
	private $store;

	public function __construct( InstanceStore $store ) {
		$this->store = $store;
	}

	public function getStore(): InstanceStore {
		return $this->store;
	}

	public function checkInstanceNameValidity( $name ) {
		// TODO: Implement
		return true;
	}
}
