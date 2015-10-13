<?php

namespace sergshner\stomp\controllers;

use Yii;
use yii\console\Controller;
use yii\helpers\Console;
use sergshner\stomp\classes\JobInterface;
use yii\helpers\Json;
use yii\di\Container;

class StompController extends Controller
{	
	/**
	 * 
	 * @var string
	 */
	public $stompComponent = 'stomp';
	
	/**
	 * @var bool
	 */
	private $kill = false;

	private $listeners = [];
	
	private $component;

	private $ackStore = [];
	
	private $jobStore;

	public function actionStart()
	{		
		$this->runApplication();		
	}

	public function actionStop()
	{
		foreach ($this->listeners as $destination => $jobs) {
			$this->component->getStomp()->unsubscribe($destination);
		}
		unset($this->component);
		$this->stdout("Success: Process is stopped\n", Console::FG_GREEN);
	}

	public function actionRestart()
	{

	}

	public function options($id)
	{
		$options = [];
		if(in_array($id, ['start', 'restart'])) {
			$options = ['fork'];
		}

		return array_merge(parent::options($id), $options);
	}

	protected function runApplication()
	{			
		$this->jobStore = new Container();
		
		$msg = '';
		while (strpos($msg, 'Unable to connect') !== false || $msg == '') {
			try {
				$this->component = Yii::$app->get($this->stompComponent);
				break;
			} catch (\Exception $ex) {
				$msg = $ex->getMessage();
				print($msg . PHP_EOL);
				sleep(1);									
			}
		}
		
		$this->component->getStomp()->setReadTimeout(0, 10000);
		
		$this->resubscribe();		
		$this->signalHandlers();
        
		$this->stdout("Success: Process is started\n", Console::FG_GREEN);

		$this->createLoop();        
	}
	
	public function resubscribe() 
	{						
		foreach ($this->listeners as $destination => $jobs) {
			$this->component->getStomp()->unsubscribe($destination);
			foreach ($jobs as $job) {
				unset($job);
			}
		}
		
		$this->component->reconnect();
		
		$this->listeners = [];
		
		$this->component = Yii::$app->get($this->stompComponent);
		foreach($this->component->jobs as $name => $job) {;
			if (!$this->jobStore->has($name)) {
				$this->jobStore->setSingleton($name, $job);
			}
			$job = &$this->jobStore->get($name);
			if(!($job instanceof JobInterface)) {
				throw new \yii\base\InvalidConfigException('Gearman job must be instance of JobInterface.');
			}
			$listenDestinations = $job->listenDestinations();
			foreach ($listenDestinations as $destination) {
				$this->component->getStomp()->subscribe($destination);
				$this->listeners[$destination][] = $job;
			}
		}
	}
	
    /**
     * @return $this
     */
    private function signalHandlers()
    {
        $root = $this;
        pcntl_signal(SIGUSR1, function () use ($root) {
            $root->setKill(true);
        });
        
        pcntl_signal(SIGHUP, function () use ($root) {
        	$root->resubscribe();
        });
        return $this;
    }
    
    /**
     * @param bool $restart
     * @return $this
     */
    private function createLoop($restart = false) {
    	while (true) {
    		if ($this->getKill()) {
    			break;
    		}
    		
    		pcntl_signal_dispatch();
		
    		$frame = $this->component->getStomp()->readFrame();
    		if (!empty($frame) && isset($frame->headers['destination']) && isset($this->listeners[$frame->headers['destination']])) {
    			foreach ($this->listeners[$frame->headers['destination']] as &$job) {
					try {
						$ack = false;
						$ack = $job->ackMessage($frame->headers['destination'], Json::decode($frame->body));
					} catch (\Exception $ex) {
						if (method_exists($job, 'onError')) {
							$job->onError($ex, $frame);
						}
						$this->component->getStomp()->ack($frame);
					}

    				if ($ack) {    						
    					//$this->component->getStomp()->ack($frame, ['receipt' => $frame->headers['message-id']]);    						
    					$this->component->getStomp()->ack($frame);
    					$job->onMessage($frame->headers['destination'], Json::decode($frame->body));
    				} else {
    					$this->component->getStomp()->unsubscribe($frame->headers['destination']);
    					usleep(20000);
    					$this->component->getStomp()->subscribe($frame->headers['destination']);
    				}
    			}
    		} else {
    			$error = $this->component->getStomp()->error();
    			while (strpos($error, 'Unable to connect') !== false) {
    				try {
    					$this->component->reconnect();
    					$this->resubscribe();
    					break;
    				} catch (\Exception $ex) {
    					$msg = $ex->getMessage();
    					print($msg . PHP_EOL);
    					sleep(1);
    				}
    			}
    		}    			

    		foreach ($this->listeners as $destination => $jobs) {
    			foreach ($jobs as &$job) {
    				$job->onIdle();
    			}
    		}
 			
    		usleep(10000);
    	}
    }
    
    /**
     * @return bool
     */
    public function getKill()
    {
    	return $this->kill;
    }
    
    /**
     * @param $kill
     * @return $this
     */
    public function setKill($kill)
    {
    	$this->kill = $kill;
    	return $this;
    }    
            
}