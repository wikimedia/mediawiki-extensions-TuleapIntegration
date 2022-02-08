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

	public function isCreatable( string $name ) {
		return $this->checkInstanceNameValidity( $name ) && !$this->getStore()->instanceExists( $name );
	}

	public function generateScriptPath( InstanceEntity $instance ) {
		$name = $instance->getName();
		$name = str_replace( ' ', '-', $name );
		$name = str_replace( '_', '-', $name );

		return "/$name";
	}

	public function getRenamedInstanceEntity( InstanceEntity $entity, $newName ) {
		$newEntity = new InstanceEntity(
			$newName,
			$entity->getCreatedAt(),
			$entity->getId(),
			null,
			$entity->getDatabaseName(),
			null,
			$entity->getStatus(),
			$entity->getData()
		);

		$newEntity->setDirectory( $this->generateInstanceDirectoryName( $newEntity ) );
		$newEntity->setScriptPath( $this->generateScriptPath( $newEntity ) );

		return $newEntity;
	}

	public function generateDbName( $prefix ) {
		$prefix = "{$prefix}tuleap_";

		return substr( uniqid( $prefix, true ), 0, 16 );
	}

	public function generateInstanceDirectoryName( InstanceEntity $entity ) {
		$dirName = str_replace( ' ', '_', $entity->getName() );
		return "/{$dirName}";
	}

	public function getDirectoryForInstance( InstanceEntity $instance, $path = '' ) {
		if ( $instance instanceof RootInstanceEntity ) {
			return $instance->getDirectory();
		}
		$base = $this->getInstanceDirBase() . $instance->getDirectory();
		if ( !$path ) {
			return $base;
		}

		return $base . '/' . $path;
	}

	public function getInstanceDirBase() {
		return $GLOBALS['IP'] . '/_instances';
	}

	/**
	 * @param InstanceEntity $entity
	 * @param string $var
	 * @param string $old
	 * @param string $new
	 * @return bool
	 */
	public function replaceConfigVar( InstanceEntity $entity, $var, $old, $new ): bool {
		$filePath = $this->getDirectoryForInstance( $entity, 'LocalSettings.php' );
		if ( !file_exists( $filePath ) ) {
			return false;
		}
		$content = file_get_contents( $filePath );
		$re = '/((\$GLOBALS\[\'' . $var . '\'\]|\$' . $var . ') = [\"\'])(.*?)([\"\'])/m';
		$content = preg_replace( $re, "$1{$new}$4", $content );

		if ( $content === null ) {
			throw new \Exception( preg_last_error_msg() );
		}
		return file_put_contents( $filePath, $content );
	}
}
