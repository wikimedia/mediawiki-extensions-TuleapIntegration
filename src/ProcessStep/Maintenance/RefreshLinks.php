<?php

namespace TuleapIntegration\ProcessStep\Maintenance;

class RefreshLinks extends MaintenanceScript {
	/**
	 * @inheritDoc
	 */
	protected function getFormattedArgs(): array {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	protected function getScriptPath(): string {
		return 'maintenance/refreshLinks.php';
	}
}
