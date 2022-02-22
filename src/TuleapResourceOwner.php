<?php

namespace TuleapIntegration;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class TuleapResourceOwner implements ResourceOwnerInterface {
	/** @var string */
	private $username;
	/** @var string */
	private $email;
	/** @var string */
	private $realname;
	/** @var bool */
	private $emailVerified;
	/** @var string */
	private $locale;

	/**
	 * @param array $data
	 * @return static
	 */
	public static function factory( array $data ) {
		return new static(
			$data['preferred_username'],
			$data['name'],
			$data['email'],
			$data['email_verified'],
			$data['locale']
		);
	}

	/**
	 * @param string $username
	 * @param string $realname
	 * @param string $email
	 * @param bool $emailVerified
	 * @param string $locale
	 */
	public function __construct( $username, $realname, $email, $emailVerified, $locale ) {
		$this->username = $username;
		$this->realname = $realname;
		$this->email = $email;
		$this->emailVerified = $emailVerified;
		$this->locale = $locale;
	}

	/**
	 * @return string
	 */
	public function getId() {
		return $this->getUsername();
	}

	/**
	 * @return string
	 */
	public function getUsername() {
		return $this->username;
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
	 * @return bool
	 */
	public function isEmailVerified() {
		return $this->emailVerified;
	}

	/**
	 * @return string
	 */
	public function getLocale() {
		return $this->locale;
	}

	/**
	 * @return array
	 */
	public function toArray() {
		return [
			'id' => $this->getId(),
			'username' => $this->getUsername(),
			'email' => $this->getEmail(),
			'email_verified' => $this->isEmailVerified(),
			'realname' => $this->getRealName(),
			'locale' => $this->getLocale()
		];
	}
}
