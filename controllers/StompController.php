<?php

namespace sergshner\stomp\controllers;

use Yii;
use yii\console\Controller;
use yii\helpers\Console;
use sergshner\stomp\classes\JobInterface;
use yii\helpers\Json;

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
		$this->component = Yii::$app->get($this->stompComponent);
		
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
		}
		
		$this->listeners = [];
		
		$this->component = Yii::$app->get($this->stompComponent);
		foreach($this->component->jobs as $name => $job) {
			$job = Yii::createObject($job);
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
    		    		    	    		
    		if ($this->component->getStomp()->hasFrame()) {    			
    			$frame = $this->component->getStomp()->readFrame();
    			
    			if (isset($this->listeners[$frame->headers['destination']])) {
    				foreach ($this->listeners[$frame->headers['destination']] as &$job) {
    					$ack = $job->ackMessage($frame->headers['destination'], Json::decode($frame->body));
    					if ($ack) {
    						$this->component->getStomp()->ack($frame);
    						$job->onMessage($frame->headers['destination'], Json::decode($frame->body));
    					} else {
    						$this->component->getStomp()->unsubscribe($frame->headers['destination']);
    						usleep(20000);
    						$this->component->getStomp()->subscribe($frame->headers['destination']);
    					}
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