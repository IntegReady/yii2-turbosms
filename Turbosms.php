<?php

namespace integready\turbosms;

use SoapClient;
use integready\turbosms\models\TurboSmsSent;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\ViewContextInterface;
use yii\web\View;

/**
 * Class for TurboSMS
 *
 * @property string $login
 * @property string $password
 * @property string $sender
 * @property bool $debug
 * @property bool $logToDb
 * @property SoapClient $client
 * @property string $wsdl
 * @property string $error
 * @property \yii\base\View|array $_view view instance or its array configuration
 * @property string $_viewPath the directory containing view files for composing mail messages
 * @property string $_message
 * @property string $_to
 *
 * @property float|int balance
 * @property string $viewPath
 * @property string $to
 * @property array|\yii\web\View $view
 * @property array messageStatus
 */
class Turbosms extends Component implements ViewContextInterface
{
    public $login;
    public $password;
    public $sender;
    public $debug   = false;
    public $logToDb = true;

    protected $client;
    protected $wsdl = 'http://turbosms.in.ua/api/wsdl.html';

    private $_error;
    private $_view = [];
    private $_viewPath;
    private $_message;
    private $_to;

    /**
     * @param string|null $view
     * @param array $params
     *
     * @return $this
     */
    public function compose($view = null, array $params = [])
    {
        $this->_message = $this->render($view, $params);

        return $this;
    }

    /**
     * Renders the specified view with optional parameters and layout.
     * The view will be rendered using the [[view]] component.
     *
     * @param string $view the view name or the path alias of the view file.
     * @param array $params the parameters (name-value pairs) that will be extracted and made available in the view file.
     * @param string|boolean $layout layout view name or path alias. If false, no layout will be applied.
     *
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
     * @param array|View $view view instance or its array configuration that will be used to
     * render message bodies.
     *
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
     * Creates view instance from given configuration.
     *
     * @param array $config view configuration.
     *
     * @return View|object view instance.
     */
    protected function createView(array $config)
    {
        if (!array_key_exists('class', $config)) {
            $config['class'] = View::className();
        }

        return Yii::createObject($config);
    }

    /**
     * @param string $mobile
     *
     * @return $this
     */
    public function setTo($mobile)
    {
        $this->_to = $mobile;

        return $this;
    }

    /**
     * Send sms
     *
     * @return boolean
     * @throws InvalidConfigException
     */
    public function send()
    {
        $phone = $this->_to;
        $text  = $this->_message;
        if (!$this->debug || !$this->client) {
            $this->connect();
        }

        if (!$this->debug) {
            $result = $this->client->SendSMS([
                'sender'      => $this->sender,
                'destination' => $phone,
                'text'        => $text,
            ]);

            $message = $this->_error = $result->SendSMSResult->ResultArray;
            $result  = (is_array($message)) ? ($message[0] == 'Сообщения успешно отправлены') : ($message == 'Сообщения успешно отправлены');
        } else {
            $result  = true;
            $message = 'Сообщения успешно отправлено';
        }

        if ($this->logToDb) {
            $this->saveToDb($text, $phone, $message);
        }

        return $result;
    }

    /**
     * @return SoapClient
     * @throws InvalidConfigException
     */
    protected function connect()
    {
        if ($this->client) {
            return $this->client;
        }

        $client = new SoapClient($this->wsdl);

        if (!$this->login || !$this->password) {
            throw new InvalidConfigException('Enter login and password from Turbosms');
        }

        $result = $client->Auth([
            'login'    => $this->login,
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
     * @param string $text
     * @param string $phone
     * @param string $message
     */
    public function saveToDb($text, $phone, $message)
    {
        $model         = new TurboSmsSent();
        $model->text   = $text;
        $model->phone  = $phone;
        $model->status = json_encode($message, JSON_PRETTY_PRINT + JSON_UNESCAPED_UNICODE) . ($this->debug ? ' (debug mode)' : '');
        $model->save();
    }

    /**
     * Get balance
     *
     * @return int
     */
    public function getBalance()
    {
        if (!$this->debug || !$this->client) {
            $this->connect();
        }
        $result = $this->client->GetCreditBalance();

        return intval($result->GetCreditBalanceResult);
    }

    /**
     * @param $messageId
     *
     * @return mixed
     */
    public function getMessageStatus($messageId)
    {
        if (!$this->debug || !$this->client) {
            $this->connect();
        }
        $result = $this->client->GetMessageStatus(['MessageId' => $messageId]);

        return $result->GetMessageStatusResult;
    }

    /**
     * Get last error
     *
     * @return string
     */
    public function getError()
    {
        return $this->_error;
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
}
