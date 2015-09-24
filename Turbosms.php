<?php

namespace stern87\turbosms;

use Yii;
use SoapClient;
use yii\base\InvalidConfigException;
use yii\base\Component;
use stern87\turbosms\models\TurboSmsSent;
use yii\base\ViewContextInterface;
use yii\web\View;

class Turbosms extends Component implements ViewContextInterface
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
     * @var string
     */
    private $error;

    /**
     * @var \yii\base\View|array view instance or its array configuration.
     */
    private $_view = [];

    /**
     * @var string the directory containing view files for composing mail messages.
     */
    private $_viewPath;

    private $_message;

    private $_to;

    public function compose($view = null, array $params = []) {
        $this->_message = $this->render($view, $params);

        return $this;
    }

    public function setTo($mobile) {
        $this->_to = $mobile;

        return $this;
    }

    /**
     * Send sms
     *
     * @return boolean
     * 
     * @throws InvalidConfigException
     */
    public function send() {
        $phone  = $this->_to;
        $text   = $this->_message;
        if (!$this->debug || !$this->client) {
            $this->connect();
        }

        if (!$this->debug) {
            $result = $this->client->SendSMS([
                'sender' => $this->sender,
                'destination' => $phone,
                'text' => $text
            ]);

            $message = $this->error = $result->SendSMSResult->ResultArray;
            $result = ($message == 'Сообщения успешно отправлены');
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
        $model->status = json_encode($message, JSON_PRETTY_PRINT) . ($this->debug ? ' (debug mode)' : '');
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

    /**
     * Get last error
     *
     * @return string
     */
    public function getError() {
        return $this->error;
    }

    /**
     * Creates view instance from given configuration.
     * @param array $config view configuration.
     * @return View view instance.
     */
    protected function createView(array $config)
    {
        if (!array_key_exists('class', $config)) {
            $config['class'] = View::className();
        }

        return Yii::createObject($config);
    }

    /**
     * @param array|View $view view instance or its array configuration that will be used to
     * render message bodies.
     * @throws InvalidConfigException on invalid argument.
     */
    public function setView($view)
    {
        if (!is_array($view) && !is_object($view)) {
            throw new InvalidConfigException('"' . get_class($this) . '::view" should be either object or configuration array, "' . gettype($view) . '" given.');
        }
        $this->_view = $view;
    }

    /**
     * @return View view instance.
     */
    public function getView()
    {
        if (!is_object($this->_view)) {
            $this->_view = $this->createView($this->_view);
        }

        return $this->_view;
    }

    /**
     * @return string the directory that contains the view files for composing mail messages
     * Defaults to '@app/mail'.
     */
    public function getViewPath()
    {
        if ($this->_viewPath === null) {
            $this->setViewPath('@app/sms');
        }
        return $this->_viewPath;
    }

    /**
     * @param string $path the directory that contains the view files for composing mail messages
     * This can be specified as an absolute path or a path alias.
     */
    public function setViewPath($path)
    {
        $this->_viewPath = Yii::getAlias($path);
    }

    /**
     * Renders the specified view with optional parameters and layout.
     * The view will be rendered using the [[view]] component.
     * @param string $view the view name or the path alias of the view file.
     * @param array $params the parameters (name-value pairs) that will be extracted and made available in the view file.
     * @param string|boolean $layout layout view name or path alias. If false, no layout will be applied.
     * @return string the rendering result.
     */
    public function render($view, $params = [], $layout = false)
    {
        $output = $this->getView()->render($view, $params, $this);
        if ($layout !== false) {
            return $this->getView()->render($layout, ['content' => $output, 'message' => $this->_message], $this);
        } else {
            return $output;
        }
    }

}
