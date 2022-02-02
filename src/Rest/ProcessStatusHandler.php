<?php

namespace TuleapIntegration\Rest;

use MediaWiki\Rest\Handler;
use MediaWiki\Rest\HttpException;
use MWStake\MediaWiki\Component\ProcessManager\ProcessInfo;
use MWStake\MediaWiki\Component\ProcessManager\ProcessManager;
use Wikimedia\ParamValidator\ParamValidator;

class ProcessStatusHandler extends Handler {
	private $manager;

	public function __construct( ProcessManager $manager ) {
		$this->manager = $manager;
	}

	public function execute() {
		$params = $this->getValidatedParams();
		$pid = $params['pid'];
		if ( !preg_match( '/[0-9a-f]{32}/i', $pid ) ) {
			throw new HttpException( "Invalid PID", 400 );
		}
		$info = $this->manager->getProcessInfo( $pid );

		if ( !$info ) {
			throw new HttpException( "Process with PID $pid not found", 404 );
		}
		return $this->getResponseFactory()->createJson(
			$this->format( $info )
		);
	}

	public function getParamSettings() {
		return [
			'pid' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			]
		];
	}

	private function format( ProcessInfo $info ) {
		$data = [ 'pid' => $info->getPid(), 'started_at' => $info->getStartDate()->format( 'YmdHis' ) ];
		$status = $info->getState() === 'started' ? 'running' : 'finished';
		if ( $info->getExitCode() !== null && $info->getExitCode() !== 0 ) {
			$status = 'error';
		}
		$data['status'] = $status;
		if ( $status !== 'running' && $info->getExitStateMessage() ) {
			$data['message'] = $info->getExitStateMessage();
		}

		return $data;
	}
}
