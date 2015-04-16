<?php

namespace sergshner\stomp\classes;

abstract class JobBase extends \yii\base\Component implements JobInterface
{
	public function ackMessage($destination, $message) {
		return true;
	}
	
	public function resubscribe() {
		posix_kill(posix_getpid(), SIGHUP);
	}
	
}