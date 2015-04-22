<?php
/**
 * @link https://github.com/sergshner/yii2-stomp
 * @copyright Copyright (c) 2015 sergshner
 * @license https://github.com/sergshner/yii2-stomp/blob/master/LICENSE.md
 */

namespace sergshner\stomp\components;

use yii\base\Component;
use yii\base\Exception;
use yii\helpers\Inflector;
use yii\helpers\Json;

use FuseSource\Stomp\Stomp as StompClient;

use sergshner\stomp\controllers\StompController;

/**
 * Stomp wrapper.
 *
 * @property Stomp $stomp Stomp connection instance.
 * @author Sergei Shnerson <serg.flashtech@gmail.com>
 * @since 2.0
 */
class Stomp extends Component
{
	/**
	 * @var string
	 */
	public $connect_uri = 'tcp://localhost:61613';
	
	/**
	 * 
	 * @var array
	 */
	public $jobs = [];
	
    /**
     * @var Stomp
     */
    protected $stomp;
    
    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if (empty($this->stomp)) {
        	try {
            	$this->stomp = new \Stomp($this->connect_uri);
            	$this->stomp->setReadTimeout(0, 10000);
            	//$this->stomp->connect();
        	} catch (Exception $ex) {
        		throw $ex;
        	}
        }
    }

    /**
     * Returns Stomp instance.
     *
     * @return Stomp
     */
    public function getStomp()
    {
        return $this->stomp;
    }
    
    public function setStomp($stomp) { 
    	$this->stomp = $stomp; 
    }
   
    /**
     * Sends message to the MQ broker.
     *
     * @param string $exchange
     * @param string|array $message
     * @return void
     */
    public function send($destination, $message)
    {        
        $this->getStomp()->send($destination, Json::encode($message), array('persistent' => 'true'));
    }    
    
    public function reconnect() {    	
    	unset($this->stomp);
    	$this->stomp = new \Stomp($this->connect_uri);    	
    	$this->stomp->setReadTimeout(0, 10000);
    	//$this->stomp->connect();
    }
    
    public function checkConnection() {   	
    	try {
    		/*
    		$tmpStomp = new StompClient($this->connect_uri);
    		$tmpStomp->setReadTimeout(0, 10000);
    		$tmpStomp->connect();
    		$tmpStomp->disconnect();
    		unset($tmpStomp);
    		*/
    		return true;
    	} catch (Exception $ex) {
    		return false;
    	}
    } 
}
