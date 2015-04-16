<?php

namespace sergshner\stomp\classes;

interface JobInterface
{
	/**
	 * @return array
	 */
	public function listenDestinations();
	
	/**
	 * @param string $destination 
	 * @param mixed $message 
	 */
	public function onMessage($destination, $message);
	
	/**
	 * 
	 * @param string $destination
	 * @param mixed $message
	 */
	public function ackMessage($destination, $message);
	
	public function onIdle();
}