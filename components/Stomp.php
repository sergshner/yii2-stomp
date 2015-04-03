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
     * @var Stomp
     */
    protected static $stomp;

    /**
     * @var string
     */
    public $connect_uri = 'tcp://localhost:61613';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if (empty(self::$ampqConnection)) {
            self::$stomp = new Stomp($this->connect_uri);
        }
    }

    /**
     * Returns Stomp instance.
     *
     * @return Stomp
     */
    public function getStomp()
    {
        return self::$stomp;
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
        $this->getStomp()->send($destination, $message);
    }    
}
