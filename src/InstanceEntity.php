<?php

namespace TuleapIntegration;

use DateTime;

class InstanceEntity {
	public const STATE_INITIALIZING = 'initializing';
	public const STATE_READY = 'ready';
	public const STATE_MAINTENANCE = 'maintenance';

	private bool $dirty;
	/** @var int|null */
	private $id;
	/** @var string */
	private $name;
	/** @var string|null */
	private $directory;
	/** @var string|null */
	private $database;
	/** @var string|null */
	private $scriptPath;
	/** @var DateTime */
	private $created;
	/** @var string|null */
	private $status;
	/** @var array */
	private $data;

	/**
	 * @param string $name
	 * @param DateTime $created
	 * @param int|null $id
	 * @param string|null $directory
	 * @param string|null $database
	 * @param string|null $scriptPath
	 * @param string|null $status
	 * @param array|null $data
	 */
	public function __construct(
		$name, DateTime $created, $id = null, $directory = null, $database = null,
		$scriptPath = null, $status = 'initializing', $data = []
	) {
		$this->id = $id;
		$this->name = $name;
		$this->directory = $directory;
		$this->database = $database;
		$this->scriptPath = $scriptPath;
		$this->created = $created;
		$this->status = $status;
		$this->data = $data;
	}

	/**
	 * @return bool
	 */
	public function isDirty(): bool {
		return $this->dirty;
	}

	/**
	 * @param bool $dirty
	 */
	public function setDirty( bool $dirty ) {
		$this->dirty = $dirty;
	}

	/**
	 * @return int|null
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @param int $id
	 */
	public function setId( int $id ) {
		if ( $this->id !== null ) {
			throw new \BadMethodCallException( 'Cannot set ID on existing entities' );
		}
		$this->setDirty( true );
		$this->id = $id;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @return string|null
	 */
	public function getDirectory(): ?string {
		return $this->directory;
	}

	/**
	 * @param string $dir
	 */
	public function setDirectory( $dir ) {
		$this->setDirty( true );
		$this->directory = $dir;
	}

	/**
	 * @return string|null
	 */
	public function getScriptPath(): ?string {
		return $this->scriptPath;
	}

	/**
	 * @param string $scriptPath
	 */
	public function setScriptPath( $scriptPath ) {
		$this->setDirty( true );
		$this->scriptPath = $scriptPath;
	}

	/**
	 * @return DateTime
	 */
	public function getCreatedAt(): DateTime {
		return $this->created;
	}

	/**
	 * @return string
	 */
	public function getStatus(): string {
		return $this->status;
	}

	/**
	 * @param string $status
	 */
	public function setStatus( $status ) {
		$allowedStatus = [
			static::STATE_INITIALIZING, static::STATE_MAINTENANCE, static::STATE_READY
		];
		if ( !in_array( $status, $allowedStatus ) ) {
			return;
		}
		$this->setDirty( true );
		$this->status = $status;
	}

	/**
	 * @return string
	 */
	public function getDatabaseName(): string {
		return $this->database;
	}

	/**
	 * @param string $name
	 */
	public function setDatabaseName( $name ) {
		$this->setDirty( true );
		$this->database = $name;
	}

	/**
	 * @return array
	 */
	public function getData(): array {
		return $this->data;
	}

	/**
	 * @param string $key
	 * @param null $default
	 * @return mixed|null
	 */
	public function getDataItem( $key, $default = null ) {
		return $this->data[$key] ?? $default;
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 */
	public function setDataItem( $key, $value ) {
		$this->setDirty( true );
		$this->data[$key] = $value;
	}

	/**
	 * @return array
	 */
	public function dbSerialize() {
		return [
			'ti_name' => $this->name,
			'ti_directory' => $this->directory,
			'ti_database' => $this->database,
			'ti_script_path' => $this->scriptPath,
			'ti_created_at' => $this->created->format( 'YmdHis' ),
			'ti_status' => $this->status,
			'ti_data' => json_encode( $this->data )
		];
	}
}
