<?php
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_'))
    exit;

class ExpressPay extends PaymentModule
{

    private $_qr_code;
    private $_postErrors = array();

    public function __construct()
    {
        $this->name = 'expresspay';
        $this->tab = 'payments_gateways';
        $this->author = 'ООО "ТриИнком"';
        $this->version = '1.7';
        $this->controllers = array('redirect');
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);

        $this->currencies      = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
  
        parent::__construct();

        $this->page = basename(__FILE__, '.php');

        $this->displayName      = $this->l('ExpressPay');
        $this->description      = $this->l('This module allows you to accepts ERIP payments');
        $this->confirmUninstall = $this->l('Are you sure you want to remove module ?');
    }

    // Установка модуля
    public function install()
    {
        if (Shop::isFeatureActive())
            Shop::setContext(Shop::CONTEXT_ALL);

        return parent::install() &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('paymentReturn') &&
            Configuration::updateValue('EXPRESSPAY_MODULE_NAME', 'EXPRESSPAY') &&
            Configuration::updateValue('EXPRESSPAY_TOKEN', '')&&
            Configuration::updateValue('EXPRESSPAY_NOTIFICATION_URL', $this->context->link->getModuleLink($this->name,'notification',[]))&&
            Configuration::updateValue('EXPRESSPAY_SHOW_QR_CODE', false)&&
            Configuration::updateValue('EXPRESSPAY_USE_DIGITAL_SIGN_SEND', false)&&
            Configuration::updateValue('EXPRESSPAY_SEND_SECRET_WORD', '')&&
            Configuration::updateValue('EXPRESSPAY_USE_DIGITAL_SIGN_RECEIVE', false)&&
            Configuration::updateValue('EXPRESSPAY_RECEIVE_SECRET_WORD', '')&&
            Configuration::updateValue('EXPRESSPAY_ALLOW_CHANGE_NAME', false)&&
            Configuration::updateValue('EXPRESSPAY_ALLOW_CHANGE_ADDRESS', false)&&
            Configuration::updateValue('EXPRESSPAY_ALLOW_CHANGE_AMOUNT', false)&&
            Configuration::updateValue('EXPRESSPAY_TESTING_MODE', true)&&
            Configuration::updateValue('EXPRESSPAY_API_URL', "https://api.express-pay.by/v1/")&&
            Configuration::updateValue('EXPRESSPAY_TEST_API_URL', "https://sandbox-api.express-pay.by/v1/")&&
            Configuration::updateValue('EXPRESSPAY_SUCCESS_PAYMENT_TEXT', "Ваш номер заказа ##order_id##. Сумма к оплате: ##total_amount##.");
            Configuration::updateValue('EXPRESSPAY_ERIP_PATH', "Интернет-магазины\Сервисы->Первая буква доменного имени интернет-магазина->Доменное имя интернет-магазина->Оплата заказа"); 
    }

    // Удаление модуля
    public function uninstall()
    {
        return parent::uninstall() &&
            Configuration::deleteByName('EXPRESSPAY_MODULE_NAME') &&
            Configuration::deleteByName('EXPRESSPAY_TOKEN')&&
            Configuration::deleteByName('EXPRESSPAY_NOTIFICATION_URL')&&
            Configuration::deleteByName('EXPRESSPAY_SHOW_QR_CODE')&&
            Configuration::deleteByName('EXPRESSPAY_USE_DIGITAL_SIGN_SEND')&&
            Configuration::deleteByName('EXPRESSPAY_SEND_SECRET_WORD')&&
            Configuration::deleteByName('EXPRESSPAY_USE_DIGITAL_SIGN_RECEIVE')&&
            Configuration::deleteByName('EXPRESSPAY_RECEIVE_SECRET_WORD')&&
            Configuration::deleteByName('EXPRESSPAY_ALLOW_CHANGE_NAME')&&
            Configuration::deleteByName('EXPRESSPAY_ALLOW_CHANGE_ADDRESS')&&
            Configuration::deleteByName('EXPRESSPAY_ALLOW_CHANGE_AMOUNT')&&
            Configuration::deleteByName('EXPRESSPAY_TESTING_MODE')&&
            Configuration::deleteByName('EXPRESSPAY_API_URL')&&
            Configuration::deleteByName('EXPRESSPAY_TEST_API_URL')&&
            Configuration::deleteByName('EXPRESSPAY_SUCCESS_PAYMENT_TEXT')&&
            Configuration::deleteByName('EXPRESSPAY_ERIP_PATH');  
    }

    // Сохранение значений из конфигурации
    public function getContent()
    {
        $this->log_info('getContent','start');

        $output = null;
        $check = true;

        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->log_info('getContent','!count($this->_postErrors)');

                $output .= $this->_postProcess();

            }else{
                $this->log_error('getContent','Post Errors; Errors - '.implode($this->_postErrors));

                foreach ($this->_postErrors as $err) {
                    $output .= $this->displayError($err);
                }
            }
        }
        $this->log_info('getContent',' Output- '.$output);
        return $output . $this->displayForm();
    }

    protected function _postValidation()
    {
        if (!Tools::getValue('EXPRESSPAY_TOKEN')) {
            $this->_postErrors[] = $this->trans('Token is empty', array(), 'Modules.ExpressPay.Admin');
        } elseif (!Tools::getValue('EXPRESSPAY_SUCCESS_PAYMENT_TEXT')) {
            $this->_postErrors[] = $this->trans('payment text is empty.', array(), "Modules.ExpressPay.Admin");
        }
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('EXPRESSPAY_TOKEN', Tools::getValue('EXPRESSPAY_TOKEN'));
            Configuration::updateValue('EXPRESSPAY_NOTIFICATION_URL', Tools::getValue('EXPRESSPAY_NOTIFICATION_URL'));
            Configuration::updateValue('EXPRESSPAY_SHOW_QR_CODE', Tools::getValue('EXPRESSPAY_SHOW_QR_CODE'));
            Configuration::updateValue('EXPRESSPAY_USE_DIGITAL_SIGN_SEND', Tools::getValue('EXPRESSPAY_USE_DIGITAL_SIGN_SEND'));
            Configuration::updateValue('EXPRESSPAY_SEND_SECRET_WORD', Tools::getValue('EXPRESSPAY_SEND_SECRET_WORD'));
            Configuration::updateValue('EXPRESSPAY_USE_DIGITAL_SIGN_RECEIVE', Tools::getValue('EXPRESSPAY_USE_DIGITAL_SIGN_RECEIVE'));
            Configuration::updateValue('EXPRESSPAY_RECEIVE_SECRET_WORD', Tools::getValue('EXPRESSPAY_RECEIVE_SECRET_WORD'));
            Configuration::updateValue('EXPRESSPAY_ALLOW_CHANGE_NAME', Tools::getValue('EXPRESSPAY_ALLOW_CHANGE_NAME'));
            Configuration::updateValue('EXPRESSPAY_ALLOW_CHANGE_ADDRESS', Tools::getValue('EXPRESSPAY_ALLOW_CHANGE_ADDRESS'));
            Configuration::updateValue('EXPRESSPAY_ALLOW_CHANGE_AMOUNT', Tools::getValue('EXPRESSPAY_ALLOW_CHANGE_AMOUNT'));
            Configuration::updateValue('EXPRESSPAY_TESTING_MODE', Tools::getValue('EXPRESSPAY_TESTING_MODE'));
            Configuration::updateValue('EXPRESSPAY_API_URL', Tools::getValue('EXPRESSPAY_API_URL'));
            Configuration::updateValue('EXPRESSPAY_TEST_API_URL', Tools::getValue('EXPRESSPAY_TEST_API_URL'));
            Configuration::updateValue('EXPRESSPAY_SUCCESS_PAYMENT_TEXT', Tools::getValue('EXPRESSPAY_SUCCESS_PAYMENT_TEXT'));
            Configuration::updateValue('EXPRESSPAY_ERIP_PATH', Tools::getValue('EXPRESSPAY_ERIP_PATH'));
        }
        return $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Global'));
    }

    // Форма страницы конфигурации
    public function displayForm()
    {
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $fields_form[0]['form'] = array(

            'legend' => array(
                'title' => 'ExpressPay Settings',
                'icon' => 'icon-envelope'
            ),
            'input' =>[
                [
                    'type' => 'text',
                    'label' => $this->l('Token'),
                    'name' => 'EXPRESSPAY_TOKEN',
                    'desc' => $this->l('Your token from express-pay.by website.'),
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Notification URL'),
                    'name' => 'EXPRESSPAY_NOTIFICATION_URL',
                    'desc' => $this->l('Copy this URL to \"URL for notification\" field on express-pay.by.'),
                    'readonly' => true,
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Show Qr Code'),
                    'name' => 'EXPRESSPAY_SHOW_QR_CODE',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => true,
                            'label' => $this->l('Yes')
                        ],
                        [
                            'id' => 'active_off',
                            'value' => false,
                            'label' => $this->l('No')
                        ]
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Digital signature for API'),
                    'name' => 'EXPRESSPAY_USE_DIGITAL_SIGN_SEND',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => true,
                            'label' => $this->l('Yes')
                        ],
                        [
                            'id' => 'active_off',
                            'value' => false,
                            'label' => $this->l('No')
                        ]
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Secret word for bills signing'),
                    'name' => 'EXPRESSPAY_SEND_SECRET_WORD'
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Digital signature for notifications'),
                    'name' => 'EXPRESSPAY_USE_DIGITAL_SIGN_RECEIVE',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => true,
                            'label' => $this->l('Yes')
                        ],
                        [
                            'id' => 'active_off',
                            'value' => false,
                            'label' => $this->l('No')
                        ]
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Secret word for notifications'),
                    'name' => 'EXPRESSPAY_RECEIVE_SECRET_WORD'
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Allow to change payer name'),
                    'name' => 'EXPRESSPAY_ALLOW_CHANGE_NAME',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => true,
                            'label' => $this->l('Yes')
                        ],
                        [
                            'id' => 'active_off',
                            'value' => false,
                            'label' => $this->l('No')
                        ]
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Allow to change payer address'),
                    'name' => 'EXPRESSPAY_ALLOW_CHANGE_ADDRESS',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => true,
                            'label' => $this->l('Yes')
                        ],
                        [
                            'id' => 'active_off',
                            'value' => false,
                            'label' => $this->l('No')
                        ]
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Allow to change pay amount'),
                    'name' => 'EXPRESSPAY_ALLOW_CHANGE_AMOUNT',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => true,
                            'label' => $this->l('Yes')
                        ],
                        [
                            'id' => 'active_off',
                            'value' => false,
                            'label' => $this->l('No')
                        ]
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Use test mode'),
                    'name' => 'EXPRESSPAY_TESTING_MODE',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => true,
                            'label' => $this->l('Yes')
                        ],
                        [
                            'id' => 'active_off',
                            'value' => false,
                            'label' => $this->l('No')
                        ]
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('API URL'),
                    'name' => 'EXPRESSPAY_API_URL'
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Test API URL'),
                    'name' => 'EXPRESSPAY_TEST_API_URL'
                ],
                [
                    'type' => 'textarea',
                    'label' => $this->l('Erip path'),
                    'desc' => $this->l('A message that contains a path along the ERIP branch'),
                    'name' => 'EXPRESSPAY_ERIP_PATH',
                    'required' => true
                ],
                [
                    'type' => 'textarea',
                    'label' => $this->l('Success payment message'),
                    'desc' => $this->l('This message will be showed to payer after payment.'),
                    'name' => 'EXPRESSPAY_SUCCESS_PAYMENT_TEXT',
                    'required' => true
                ]/*,
                [
                    'type' => 'label',
                    'label' => '<h3>' . $this->l('Plugin settings') . '</h3>',
                    'name' => '_unused_lable'
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Order Status for successful transactions'),
                    'name' => 'success_status'
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Order Status for pending transactions'),
                    'name' => 'pending_status'
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Order Status for failed transactions'),
                    'name' => 'failed_status'
                ]*/
            ],

            'submit' => array(
                'title' => $this->l('Сохранить'),
                'class' => 'button'));

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;//AdminController::$currentIndex . '&configure=' . $this->name;

        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;

        $helper->title = $this->displayName;
        $helper->show_toolbar = false;
        $helper->toolbar_scroll = false;
        $helper->submit_action = 'btnSubmit';

        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->l('Сохранить'),
                    'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                        '&token=' . Tools::getAdminTokenLite('AdminModules')),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Назад к списку')));

        $helper->fields_value['EXPRESSPAY_TOKEN']                    = Configuration::get('EXPRESSPAY_TOKEN');
        $helper->fields_value['EXPRESSPAY_NOTIFICATION_URL']         = Configuration::get('EXPRESSPAY_NOTIFICATION_URL');
        $helper->fields_value['EXPRESSPAY_USE_DIGITAL_SIGN_SEND']    = Configuration::get('EXPRESSPAY_USE_DIGITAL_SIGN_SEND');
        $helper->fields_value['EXPRESSPAY_SHOW_QR_CODE']             = Configuration::get('EXPRESSPAY_SHOW_QR_CODE');
        $helper->fields_value['EXPRESSPAY_SEND_SECRET_WORD']         = Configuration::get('EXPRESSPAY_SEND_SECRET_WORD');
        $helper->fields_value['EXPRESSPAY_USE_DIGITAL_SIGN_RECEIVE'] = Configuration::get('EXPRESSPAY_USE_DIGITAL_SIGN_RECEIVE');
        $helper->fields_value['EXPRESSPAY_RECEIVE_SECRET_WORD']      = Configuration::get('EXPRESSPAY_RECEIVE_SECRET_WORD');
        $helper->fields_value['EXPRESSPAY_ALLOW_CHANGE_NAME']        = Configuration::get('EXPRESSPAY_ALLOW_CHANGE_NAME');
        $helper->fields_value['EXPRESSPAY_ALLOW_CHANGE_ADDRESS']     = Configuration::get('EXPRESSPAY_ALLOW_CHANGE_ADDRESS');
        $helper->fields_value['EXPRESSPAY_ALLOW_CHANGE_AMOUNT']      = Configuration::get('EXPRESSPAY_ALLOW_CHANGE_AMOUNT');
        $helper->fields_value['EXPRESSPAY_TESTING_MODE']             = Configuration::get('EXPRESSPAY_TESTING_MODE');
        $helper->fields_value['EXPRESSPAY_API_URL']                  = Configuration::get('EXPRESSPAY_API_URL');
        $helper->fields_value['EXPRESSPAY_TEST_API_URL']             = Configuration::get('EXPRESSPAY_TEST_API_URL');
        $helper->fields_value['EXPRESSPAY_SUCCESS_PAYMENT_TEXT']     = Configuration::get('EXPRESSPAY_SUCCESS_PAYMENT_TEXT');
        $helper->fields_value['EXPRESSPAY_ERIP_PATH']                = Configuration::get('EXPRESSPAY_ERIP_PATH');

        $html = $this->_displayInfo();
        $html .= $helper->generateForm($fields_form);
        return $html;
    }

    private function _displayInfo()
    {
        return $this->display(__FILE__, 'infos.tpl');
    }

   /* public function getQrCode($data){
       $this->_qr_code .='<img src="data:image/jpeg;base64,' . $data . '';
       $this->log('QrCodeInModel', "INFO" , $this->_qr_code);
    }*/

    function sendRequestGET($url){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;	
	}

    // Хук оплаты
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        $newOption = new PaymentOption();
        $newOption->setCallToActionText($this->l('ExpressPay'))
            ->setAction($this->context->link->getModuleLink($this->name, 'redirect', array(), true));
        $payment_options = [
            $newOption,
        ];

        return $payment_options;
    }
    
    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }
        $state = $params['order']->getCurrentState();

        $config = Configuration::get("EXPRESSPAY_SUCCESS_PAYMENT_TEXT");
        $eripPath = Configuration::get("EXPRESSPAY_ERIP_PATH");
        $eripPath = nl2br($eripPath);
        $orderId = $params['order']->id;
        $successMessage = str_replace('##order_id##', $params['order']->id, $config);
        $successMessage = str_replace('##total_amount##', Tools::displayPrice($params['order']->total_paid), $successMessage);
        $successMessage = nl2br($successMessage);

        if(Configuration::get('EXPRESSPAY_SHOW_QR_CODE')){
            $response_qr = $this->sendRequestGET('https://api.express-pay.by/v1/qrcode/getqrcode/?token='.Tools::safeOutput(Configuration::get('EXPRESSPAY_TOKEN')) .'&InvoiceId='.$_REQUEST['ExpressPayInvoiceNo'] .'&viewtype=base64' );
            $response_qr = json_decode($response_qr);
            $qr_code = $response_qr->QrCodeBody;
            $qr_description = 'Отсканируйте QR-код для оплаты';
            $this->log_info('initContent','Qr_Code_BODY - '.$qr_code);
        }

        if($state == _PS_OS_PREPARATION_){
            $this->smarty->assign(array(
                'status' => 'fail'
            ));
        }
        else
        {
            $this->smarty->assign(array(
                'erip_path' => $eripPath,
                'order_id' => $orderId,
                'qr_code' => $qr_code,
                'qr_description' => $qr_description,
                'success_message' => $successMessage,
                'status' => 'ok'
            ));
        }

        return $this->display(__FILE__, 'payment_return.tpl');
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;

    }

    public function log_error_exception($name, $message, $e) {
        $this->log($name, "ERROR" , $message . '; EXCEPTION MESSAGE - ' . $e->getMessage() . '; EXCEPTION TRACE - ' . $e->getTraceAsString());
    }

    public function log_error($name, $message) {
        $this->log($name, "ERROR" , $message);
    }

    public function log_info($name, $message) {
        $this->log($name, "INFO" , $message);
    }

    public function log($name, $type, $message) {
        $log_url = dirname(__FILE__) . '/Log';

        if(!file_exists($log_url)) {
            $is_created = mkdir($log_url, 0777);

            if(!$is_created)
                return;
        }

        $log_url .= '/express-pay-' . date('Y.m.d') . '.log';

        file_put_contents($log_url, $type . " - IP - " . $_SERVER['REMOTE_ADDR'] . "; DATETIME - ".date('c')."; USER AGENT - " . $_SERVER['HTTP_USER_AGENT'] . "; FUNCTION - " . $name . "; MESSAGE - " . $message . ';' . PHP_EOL, FILE_APPEND);
    
    }
}
