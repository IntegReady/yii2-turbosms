<?php

namespace stern87\turbosms;

use Yii;
use SoapClient;
use yii\base\InvalidConfigException;
use yii\base\Component;
use avator\turbosms\models\TurboSmsSent;

class Turbosms extends Component
{

    /**
     * @var string
     */
    public $login;

    /**
     * @var string
     */
    public $password;

    /**
     * @var string
     */
    public $sender;

    /**
     * @var bool
     */
    public $debug = false;

    /**
     * @var SoapClient
     */
    protected $client;

    /**
     * @var string
     */
    protected $wsdl = 'http://turbosms.in.ua/api/wsdl.html';

    /**
     * Send sms
     *
     * @param $phone
     * @param $text
     * @return boolean
     * 
     * @throws InvalidConfigException
     */
    public function send($phone, $text) {

        if (!$this->debug || !$this->client) {
            $this->connect();
        }

        if (!$this->debug) {
            $result = $this->client->SendSMS([
                'sender' => $this->sender,
                'destination' => $phone,
                'text' => $text
            ]);

            if ($result->SendSMSResult->ResultArray[0] != 'Сообщения успешно отправлены') {
                $message = $result->SendSMSResult->ResultArray[0];
            }
            $result = ($result->SendSMSResult->ResultArray[0] == 'Сообщения успешно отправлены');
        } else {
            $result = true;
            $message = 'Сообщения успешно отправлено';
        }

        $this->saveToDb($text, $phone, $message);
        
        return $result;
    }

    /**
     * @return SoapClient
     * @throws InvalidConfigException
     */
    protected function connect() {

        if ($this->client) {
            return $this->client;
        }

        $client = new SoapClient($this->wsdl);

        if (!$this->login || !$this->password) {
            throw new InvalidConfigException('Enter login and password from Turbosms');
        }

        $result = $client->Auth([
            'login' => $this->login,
            'password' => $this->password,
        ]);

        if ($result->AuthResult . '' != 'Вы успешно авторизировались') {
            throw new InvalidConfigException($result->AuthResult);
        }

        $this->client = $client;

        return $this->client;
    }

    /**
     * Save sms to db
     *
     * @param $text
     * @param $phone
     * @param $message
     */
    public function saveToDb($text, $phone, $message) {
        $model = new TurboSmsSent();
        $model->text = $text;
        $model->phone = $phone;
        $model->status = $message . ($this->debug ? ' (тестовый режим)' : '');
        $model->save();
    }

    /**
     * Get balance
     *
     * @return int
     */
    public function getBalance() {
        $result = $this->client->GetCreditBalance();
        return intval($result->GetCreditBalanceResult);
    }

    /**
     * @param $messageId
     *
     * @return mixed
     */
    public function getMessageStatus($messageId) {
        $result = $this->client->GetMessageStatus(['MessageId' => $messageId]);
        return $result->GetMessageStatusResult;
    }

}
