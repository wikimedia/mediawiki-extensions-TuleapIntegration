<?php

namespace TuleapIntegration;

use DateTime;

class RootInstanceEntity extends InstanceEntity {
	public function __construct() {
		parent::__construct('w', new DateTime(), 0, '/w', '', '/w', InstanceEntity::STATE_READY );
	}
}
