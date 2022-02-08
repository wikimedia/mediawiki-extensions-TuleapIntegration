<?php

namespace TuleapIntegration\ProcessStep\Maintenance;

class SetGroups extends MaintenanceScript {
	/**
	 * @inheritDoc
	 */
	protected function getFormattedArgs(): array {
		return [ '-d', json_encode( $this->args ) ];
	}

	/**
	 * @inheritDoc
	 */
	protected function getScriptPath(): string {
		return 'extensions/TuleapIntegration/maintenance/setUserGroups.php';
	}
}
