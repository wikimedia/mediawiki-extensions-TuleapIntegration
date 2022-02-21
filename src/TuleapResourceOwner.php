<?php

namespace TuleapIntegration;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class TuleapResourceOwner implements ResourceOwnerInterface {
	/** @var string */
	private $id;
	/** @var string */
	private $name;
	/** @var string */
	private $email;
	/** @var string */
	private $realname;

	/**
	 * @param string $id
	 * @param string $name
	 * @param string $realname
	 * @param string $email
	 */
	public function __construct( $id, $name, $realname, $email ) {
		$this->id = $id;
		$this->name = $name;
		$this->realname = $realname;
		$this->email = $email;
	}

	/**
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getUsername() {
		return $this->getUsername();
	}

	/**
	 * @return string
	 */
	public function getRealName() {
		return $this->realname;
	}

	/**
	 * @return string
	 */
	public function getEmail() {
		return $this->email;
	}

	/**
	 * @return array
	 */
	public function toArray() {
		return [
			'id' => $this->id,
			'name' => $this->name,
		];
	}
}
