<?php

namespace TuleapIntegration;

use DateTime;
use Wikimedia\Rdbms\ILoadBalancer;

class InstanceStore {
	private const TABLE = 'tuleap_instances';
	private const FIELDS = [
		'ti_id',
		'ti_name',
		'ti_directory',
		'ti_status',
		'ti_created_at',
		'ti_script_path',
		'ti_database',
		'ti_data'
	];

	private $loadBalancer;

	public function __construct( ILoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	public function getInstanceEntity( $name ): ?InstanceEntity {
		$entities = $this->query( [ 'ti_name' => $name ] );
		if ( !empty( $entities ) ) {
			return $entities[0];
		}
		return null;
	}

	public function instanceExists( $name ): bool {
		return count( $this->query( [ 'ti_name' => $name ] ) ) > 0;
	}

	public function storeEntity( InstanceEntity $entity ): bool {
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );

		if ( $entity->getId() !== null ) {
			$res = $db->update(
				static::TABLE,
				$entity->dbSerialize(),
				[ 'ti_id' => $entity->getId() ],
				__METHOD__
			);

			if ( $res ) {
				$entity->setDirty( false );
			}

			return $res;
		}

		$res = $db->insert(
			static::TABLE,
			$entity->dbSerialize(),
			__METHOD__
		);

		if ( !$res ) {
			return false;
		}

		$entity->setId( $db->insertId() );
		$entity->setDirty( false );
		return $res;
	}

	public function query( array $conditions, $raw = false ): array {
		$res = $this->loadBalancer->getConnection( DB_REPLICA )->select(
			static::TABLE,
			static::FIELDS,
			$conditions,
			__METHOD__
		);

		if ( !$res ) {
			return [];
		}

		$return = [];
		foreach ( $res as $row ) {
			if ( $raw ) {
				$return[] = $row;
			} else {
				$instance = $this->entityFromRow( $row );
				if ( $instance ) {
					$return[] = $instance;
				}
			}
		}

		return $return;
	}

	public function getNewInstance( $name ) {
		return new InstanceEntity( $name, new DateTime() );
	}

	private function entityFromRow( $row ): ?InstanceEntity {
		foreach ( static::FIELDS as $field ) {
			if ( !property_exists( $row, $field ) ) {
				return null;
			}
		}

		return new InstanceEntity(
			$row->ti_name,
			\DateTime::createFromFormat( 'YmdHis', $row->ti_created_at ),
			(int)$row->ti_id,
			$row->ti_directory,
			$row->ti_database,
			$row->ti_script_path,
			$row->ti_status,
			$row->ti_data ? json_decode( $row->ti_data, 1 ) : []
		);
	}
}
