<?php

namespace TuleapIntegration\Hook\BeforePageDisplay;

use BlueSpice\Hook\BeforePageDisplay;

class AddModules extends BeforePageDisplay {

	protected function doProcess() {
		$this->out->addModuleStyles( 'ext.tuleapIntegration.styles' );
		$this->out->addModules( 'ext.tuleapIntegration' );

		return  true;
	}
}
