<?php

class ExpressPayRedirectModuleFrontController extends ModuleFrontController
{
	public function initContent()
	{
		parent::initContent();

		$token      = Tools::safeOutput(Configuration::get('EXPRESSPAY_TOKEN'));
		$url        = Tools::safeOutput(Configuration::get('EXPRESSPAY_TESTING_MODE'))  ? Tools::safeOutput(Configuration::get('EXPRESSPAY_TEST_API_URL')) 
																						: Tools::safeOutput(Configuration::get('EXPRESSPAY_API_URL'));
											
		//$url    .= 'invoices?token='.$token;																			
		$id_cart  = $this->context->cart->id;

		$cart       = new Cart($id_cart);// Объект корзины
		$expressPay = Module::getInstanceByName('expresspay');//Объект ExpressPay
		$amount     = $cart->getOrderTotal(true, Cart::BOTH);//Сумма заказа

		$amount = str_replace('.',',',$amount);

		$expressPay->log_info('initContent','CART:' . json_encode($cart));

		$currency      = new Currency((int)($cart->id_currency));
		$currency_code = trim($currency->iso_code) == 'BYN' ? 933 : trim($currency->iso_code);
		//$currency   = Tools::safeOutput( Currency::getDefaultCurrency()->iso_code_num);//код валюты

		$required_currency = (date('y') > 16 || (date('y') >= 16 && date('n') >= 7)) ? '933' : '974';//требуемый код валюты
	
		$customer = new Customer((int)$this->context->cart->id_customer);//Покупатель

		$address = new Address((int)$this->context->cart->id_address_delivery);//Адрес покупателя

		if($currency_code != $required_currency)//проверка соответствия кода валюты
		{
			$expressPay->validateOrder($cart->id, Configuration::get('PS_OS_PREPARATION'),$cart->getOrderTotal(true, Cart::BOTH), $expressPay->displayName);//Создание заказа с статусом ожидаем оплату

			$expressPay->log_error('initContent','currency error; CURRENCY - '. json_encode($currency));

			Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);

			return;
		}

		$expressPay->validateOrder($cart->id, Configuration::get('PS_OS_CHEQUE'),$cart->getOrderTotal(true, Cart::BOTH), $expressPay->displayName);//Создание заказа с статусом ожидаем оплату

		$accountNo = $id_order = Order::getOrderByCartId($id_cart);

		//$accountNo = Order::getOrderByCartId($accountNo);
		$expiration = ''; //Дата истечения срока действия выставлена счета на оплату. Формат - yyyyMMdd
		$info = 'Оплата заказа номер '.$accountNo.' в интернет-магазине '.Tools::safeOutput(Configuration::get('PS_SHOP_DOMAIN'));//Назначение платежа

		$surname = $customer->lastname;
		$firstName = $customer->firstname;
		$patronymic = '';

		$city = $address->city;
		$street = '';
		$house = '';
		$building = '';
		$apartment = '';

		$isNameEditable = Configuration::get('EXPRESSPAY_ALLOW_CHANGE_NAME') == false ? 0 : 1;
		$isAddressEditable = Configuration::get('EXPRESSPAY_ALLOW_CHANGE_ADDRESS') == false ? 0 : 1;
		$isAmountEditable = Configuration::get('EXPRESSPAY_ALLOW_CHANGE_AMOUNT') == false ? 0 : 1;

		$emailNotification = $customer->email;
		$smsPhone = $address->phone;

		$requestParams = array(
			"accountno"  		=> $accountNo,
			"amount"     		=> $amount,
			"currency"   		=> $currency_code,
			"expiration" 	 	=> $expiration,
			"info"              => $info,
			"surname"           => $surname,
			"firstname"         => $firstName,
			"patronymic"        => $patronymic,
			"city"              => $city,
			"street"            => $street,
			"house"             => $house,
			"building"          => $building,
			"apartment"         => $apartment,
			"isnameeditable"    => $isNameEditable,
			"isaddresseditable" => $isAddressEditable,
			"isamounteditable"  => $isAmountEditable,
			"emailnotification" => $emailNotification,
			"smsphone"          => $smsPhone
		);

		$expressPay->log_info('initContent','requestParams: ' . json_encode($requestParams));

		foreach($requestParams as $param){
			$param = (isset($param) ? $param : '');
		}

		if(Configuration::get('EXPRESSPAY_USE_DIGITAL_SIGN_SEND'))
		{
			$expressPay->log_info('initContent','computeSignature');

			$signature = $this->computeSignature($requestParams,Configuration::get('EXPRESSPAY_SEND_SECRET_WORD'),'add-invoice', $expressPay, $token);

			$url    .= 'invoices?token='.$token;

			$url .= '&signature='.$signature;
		}


		$expressPay->log_info('initContent','url - '.$url);

		$response = $this->sendRequestPOST($url. 'invoices?token='.$token, $requestParams);
		
		//$expressPay->log_info('initContent','response - '.$response);

		$response = json_decode($response,true);

		if(isset($response['InvoiceNo']))
		{
			$expressPay->log_info('initContent','InvoiceNo - '.$response['InvoiceNo']);
		}
		else if(isset($response['Message']))
		{
			$this->errors[] = $this->trans($response['Message'], $response, 'Modules.ExpressPay.Shop');

			$expressPay->log_error('initContent','ERROR MESSAGE - '.$response['Message']);
		}

		
	   Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key.'&ExpressPayInvoiceNo='.$response['InvoiceNo']);
	}

	function sendRequestPOST($url, $params) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
	}


	function computeSignature($signatureParams, $secretWord, $method, $expressPay, $token) 
	{
		$normalizedParams = array_change_key_case($signatureParams, CASE_LOWER);
		
		$expressPay->log_info('computeSignature','normalizedParams - '.json_encode($normalizedParams).'; sectret word - '.$secretWord);

        $mapping = array(
            "add-invoice" => array(
                                    //"token",
                                    "accountno",
                                    "amount",
                                    "currency",
                                    "expiration",
                                    "info",
                                    "surname",
                                    "firstname",
                                    "patronymic",
                                    "city",
                                    "street",
                                    "house",
                                    "building",
                                    "apartment",
                                    "isnameeditable",
                                    "isaddresseditable",
                                    "isamounteditable",
									"emailnotification",
								),
            "get-details-invoice" => array(
                                    "token",
                                    "id"),
            "cancel-invoice" => array(
                                    "token",
                                    "id"),
            "status-invoice" => array(
                                    "token",
                                    "id"),
            "get-list-invoices" => array(
                                    "token",
                                    "from",
                                    "to",
                                    "accountno",
                                    "status"),
            "get-list-payments" => array(
                                    "token",
                                    "from",
                                    "to",
                                    "accountno"),
            "get-details-payment" => array(
                                    "token",
                                    "id"),
            "add-card-invoice"  =>  array(
                                    "token",
                                    "accountno",                 
                                    "expiration",             
                                    "amount",                  
                                    "currency",
                                    "info",      
                                    "returnurl",
                                    "failurl",
                                    "language",
                                    "sessiontimeoutsecs",
                                    "expirationdate"),
           "card-invoice-form"  =>  array(
                                    "token",
                                    "cardinvoiceno"),
            "status-card-invoice" => array(
                                    "token",
                                    "cardinvoiceno",
                                    "language"),
            "reverse-card-invoice" => array(
                                    "token",
                                    "cardinvoiceno")
		);
		
        $apiMethod = $mapping[$method];
        $result = $token;

		$expressPay->log_info('computeSignature','result string; RESULT - '.$result);
        foreach ($apiMethod as $item){
            $result .= $normalizedParams[$item];
		}

		$expressPay->log_info('computeSignature','result string; RESULT - '.$result);

		$hash = strtoupper(hash_hmac('sha1', $result, $secretWord, false));
		
		$expressPay->log_info('computeSignature','result hash; HASH - '.$hash);

        return $hash;
    }
}
