<?php

namespace TuleapIntegration\ProcessStep\Maintenance;

class TerminateSessions extends MaintenanceScript {
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
		return 'extensions/TuleapIntegration/maintenance/terminateAllSessions.php';
	}
}
