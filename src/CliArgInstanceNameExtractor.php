<?php

namespace TuleapIntegration;

class CliArgInstanceNameExtractor {

	public function extractInstanceName( &$args ) : string {
		$instanceName = '';
		$isSfrArg = false;
		$newArgv = [];
		foreach ( $args as $argVal ) {
			// Case "--sfr <instancename>"
			if ( $argVal === '--sfr' ) {
				$isSfrArg = true;
				continue;
			}
			if ( $isSfrArg ) {
				$instanceName = $argVal;
				$isSfrArg = false;
				continue;
			}

			// Case "--sfr=<instancename>" (and similar)
			if ( strpos( $argVal, '--sfr' ) === 0 ) {
				$parts = explode( '=', $argVal, 2 );
				if ( count( $parts ) !== 2 ) {
					continue;
				}
				$parts = array_map( function( $val ) {
					$val = trim( $val );
					$val = trim( $val, '"' );
					$val = trim( $val );
					return $val;
				}, $parts );

				$instanceName = $parts[1];
				continue;
			}
			$newArgv[] = $argVal;
		}
		$args = $newArgv;

		return $instanceName;
	}
}
