<?php
/**
 * @package	HikaShop for Joomla!
 * @version	2.1.3
 * @author	hikashop.com
 * @copyright	(C) 2010-2013 HIKARI SOFTWARE. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');
?><?php
class plgHikashoppaymentIpaydna extends JPlugin
{
	var $accepted_currencies = array( 'USD', 'EUR', 'GBP', 'JPY', 'MYR', 'AUD', 'CAD', 'SGD', 'DKK', 'SEK', 'NOK', 'HKD', 'KRW' );

	function onPaymentDisplay(&$order,&$methods,&$usable_methods) {
		if(!empty($methods)){
			foreach($methods as $method){
				if($method->payment_type!='ipaydna' || !$method->enabled){
					continue;
				}
				if(!empty($method->payment_zone_namekey)){
					$zoneClass=hikashop_get('class.zone');
					$zones = $zoneClass->getOrderZones($order);
					if(!in_array($method->payment_zone_namekey,$zones)){
						return true;
					}
				}
				$currencyClass = hikashop_get('class.currency');
				$null=null;
				if(!empty($order->total)){
					$currency_id = intval(@$order->total->prices[0]->price_currency_id);
					$currency = $currencyClass->getCurrencies($currency_id,$null);
					if(!empty($currency) && !in_array(@$currency[$currency_id]->currency_code,$this->accepted_currencies)) {
						return true;
					}
				}
				$this->needCC($method);
				$usable_methods[$method->ordering] = $method;
			}
		}
		return true;
	}

	function needCC(&$method) {
		$method->ask_cc = true;
		$method->ask_owner = true;
		$method->ask_cctype = array('VISA' => 'VISA', 'MASTERCARD' => 'MasterCard', 'AMEX' => 'American Express', 'DISCOVER' => 'Discover', 'JCB' => 'JCB', 'AQUARIUS' => 'Aquarius' );

		if( $method->payment_params->ask_ccv ) {
			$method->ask_ccv = true;
		}
		return true;
	}

	function onPaymentSave(&$cart,&$rates,&$payment_id) {
		$usable = array();
		$this->onPaymentDisplay($cart,$rates,$usable);
		$payment_id = (int) $payment_id;
		foreach($usable as $usable_method){
			if($usable_method->payment_id==$payment_id){
				return $usable_method;
			}
		}
		return false;
	}

	function onBeforeOrderCreate(&$order, &$do) {
		$app =& JFactory::getApplication();
		if($app->isAdmin()) {
			return true;
		}
		if(empty($order->order_payment_method) || $order->order_payment_method != 'ipaydna') {
			return true;
		}
		if(!function_exists('curl_init')){
			$app->enqueueMessage('The iPayDNA payment plugin needs the CURL library installed but it seems that it is not available on your server. Please contact your web hosting to set it up.','error');
			return false;
		}
		$db = JFactory::getDBO();
		$query = 'SELECT * FROM '.hikashop_table('payment').' WHERE payment_type='.$db->Quote($order->order_payment_method);
		$db->setQuery($query);
		$paymentData = $db->loadObjectList('payment_id');
		$pluginsClass = hikashop_get('class.plugins');
		$pluginsClass->params($paymentData,'payment');
		$method =& $paymentData[$order->order_payment_id];

		$currencyClass = hikashop_get('class.currency');
		$currencies = null;
		$currencies = $currencyClass->getCurrencies($order->order_currency_id,$currencies);
		$currency = $currencies[$order->order_currency_id];

		if( !in_array($currency->currency_code, $this->accepted_currencies) ) {
			$app->enqueueMessage('The iPayDNA payment plugin doest not support your currency: &quot;'.htmlentities($currency->currency_code).'&quot;','error');
			return false;
		}

		$user = hikashop_loadUser(true);

		$this->cc_number = $app->getUserState( HIKASHOP_COMPONENT.'.cc_number');
		if(!empty($this->cc_number)){
			$this->cc_number = base64_decode($this->cc_number);
		}
		$this->cc_month = $app->getUserState( HIKASHOP_COMPONENT.'.cc_month');
		if(!empty($this->cc_month)){
			$this->cc_month = base64_decode($this->cc_month);
		}
		$this->cc_year = $app->getUserState( HIKASHOP_COMPONENT.'.cc_year');
		if(!empty($this->cc_year)){
			$this->cc_year = base64_decode($this->cc_year);
		}
		$this->cc_owner = $app->getUserState( HIKASHOP_COMPONENT.'.cc_owner');
		if(!empty($this->cc_owner)){
			$this->cc_owner = base64_decode($this->cc_owner);
		}
		$this->cc_type = $app->getUserState( HIKASHOP_COMPONENT.'.cc_type');
		if(!empty($this->cc_type)){
			$this->cc_type = base64_decode($this->cc_type);
		}
		if( $method->payment_params->ask_ccv ) {
			$this->cc_CCV = $app->getUserState( HIKASHOP_COMPONENT.'.cc_CCV');
			if(!empty($this->cc_CCV)){
				$this->cc_CCV = base64_decode($this->cc_CCV);
			}
		} else {
			$this->cc_CCV = '';
		}

		ob_start();
		$dbg = '';

		$address = $app->getUserState( HIKASHOP_COMPONENT.'.billing_address');
		$cart = hikashop_get('class.cart');
		$cart->loadAddress($order->cart,$address,'object','billing');
		$address = $app->getUserState( HIKASHOP_COMPONENT.'.shipping_address');
		$cart->loadAddress($order->cart,$address,'object','shipping');

		$amount = number_format($order->cart->full_total->prices[0]->price_value_with_tax,2,'.','');
		if( !empty($method->payment_params->currency) ) {
			$db = JFactory::getDBO();
			$db->setQuery("SELECT currency_id as `id` FROM #__hikashop_currency WHERE currency_code='".$method->payment_params->currency."';");
			$dstCurrency = $db->loadObjectList();

			if( isset($dstCurrency) && @$dstCurrency[0]->id > 0 ) {
				if( $dstCurrency[0]->id != $order->order_currency_id ) {
					$price = $currencyClass->convertUniquePrice($order->cart->full_total->prices[0]->price_value_with_tax, $order->order_currency_id, $dstCurrency[0]->id);
					$dstCurrencies = null;
					$dstCurrencies = $currencyClass->getCurrencies($dstCurrency[0]->id,$dstCurrencies);
					$tmpCurrency = $dstCurrencies[$dstCurrency[0]->id];
					$amount = number_format($price,2,'.','');
					$currency = $tmpCurrency;
				} else {
					$amount = number_format($order->cart->full_total->prices[0]->price_value_with_tax,2,'.','');
				}
			}
		}

		$vars = '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' . "\r\n" .
			'<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" '.
			'xmlns:ns1="http://acquirer.process.training.aquarius" xmlns:xsd="http://www.w3.org/2001/XMLSchema" '.
			'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" '.
			'SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">';

		if( isset($order->order_id) )
			$uuid = $order->order_id;
		else
			$uuid = uniqid('');

		$vars .='<SOAP-ENV:Body><ns1:payment>'.
			'<customerpaymentpagetext xsi:type="xsd:string">'. $method->payment_params->tid . '</customerpaymentpagetext>'.
			'<orderdescription xsi:type="xsd:string">'. $uuid . '</orderdescription>'.
			'<orderDetail xsi:type="xsd:string">HikaShop order ' . $user->user_id . '</orderDetail>'.
			'<currencytext xsi:type="xsd:string">'.$currency->currency_code.'</currencytext>'.
			'<purchaseamount xsi:type="xsd:string">'. $amount . '</purchaseamount>'.
			'<taxamount xsi:type="xsd:string">0.00</taxamount>'.
			'<shippingamount xsi:type="xsd:string">0.00</shippingamount>'.
			'<dutyamount xsi:type="xsd:string">0.00</dutyamount>'.
			'<cardholdername xsi:type="xsd:string">'. $this->cc_owner . '</cardholdername>'.
			'<cardno xsi:type="xsd:string">'. $this->cc_number . '</cardno>'.
			'<cardtypetext xsi:type="xsd:string">'. $this->cc_type . '</cardtypetext>'.
			'<securitycode xsi:type="xsd:string">'. $this->cc_CCV . '</securitycode>'.
			'<cardexpiremonth xsi:type="xsd:string">'. $this->cc_month . '</cardexpiremonth>'.
			'<cardexpireyear xsi:type="xsd:string">20'. $this->cc_year . '</cardexpireyear>'.
			'<cardissuemonth xsi:type="xsd:string">0</cardissuemonth>'.
			'<cardissueyear xsi:type="xsd:string">0</cardissueyear>'.
			'<issuername xsi:type="xsd:string"></issuername>'.
			'<firstname xsi:type="xsd:string">'. substr( @$order->cart->billing_address->address_firstname, 0, 100) . '</firstname>'.
			'<lastname xsi:type="xsd:string">'. substr( @$order->cart->billing_address->address_lastname, 0, 100) . '</lastname>'.
			'<company xsi:type="xsd:string"></company>'.
			'<address xsi:type="xsd:string">'. substr($order->cart->billing_address->address_street,0,250) . '</address>'.
			'<city xsi:type="xsd:string">'. substr(@$order->cart->billing_address->address_city, 0, 50) . '</city>'.
			'<state xsi:type="xsd:string">'. $state . '</state>'.
			'<zip xsi:type="xsd:string">'. substr(@$order->cart->billing_address->address_post_code, 0, 50) . '</zip>'.
			'<country xsi:type="xsd:string">'. @$order->cart->billing_address->address_country->zone_code_2 . '</country>'.
			'<email xsi:type="xsd:string">'. substr($user->user_email, 0, 250) . '</email>'.
			'<phone xsi:type="xsd:string">0</phone>'.
			'<shipfirstname xsi:type="xsd:string">'. substr( @$order->cart->shipping_address->address_firstname, 0, 100) . '</shipfirstname>'.
			'<shiplastname xsi:type="xsd:string">'. substr( @$order->cart->shipping_address->address_lastname, 0, 100) . '</shiplastname>'.
			'<shipaddress xsi:type="xsd:string">'. substr($order->cart->shipping_address->address_street,0,250) . '</shipaddress>'.
			'<shipcity xsi:type="xsd:string">'. substr(@$order->cart->shipping_address->address_city, 0, 50) . '</shipcity>'.
			'<shipstate xsi:type="xsd:string">'. $state2 . '</shipstate>'.
			'<shipzip xsi:type="xsd:string">'. substr(@$order->cart->shipping_address->address_post_code, 0, 50) . '</shipzip>'.
			'<shipcountry xsi:type="xsd:string">'. @$order->cart->shipping_address->address_country->zone_code_2 . '</shipcountry>'.
			'<cardHolderIP xsi:type="xsd:string">127.0.0.1</cardHolderIP>'.
			'</ns1:payment></SOAP-ENV:Body></SOAP-ENV:Envelope>';

		$url = $method->payment_params->url;

		$header = array(
			'Content-type: text/xml; charset=utf-8',
			'Accept: text/xml',
			'Cache-Control: no-cache',
			'Pragma: no-cache',
			'SOAPAction: ""',
			'Content-length: '.strlen($vars),
		);

		$session = curl_init('https://' . $url);
		curl_setopt($session, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($session, CURLOPT_VERBOSE, 1);
		curl_setopt($session, CURLOPT_POST, 1);
		curl_setopt($session, CURLOPT_HTTPHEADER, $header);
		curl_setopt($session, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($session, CURLOPT_POSTFIELDS, $vars);
		curl_setopt($session, CURLOPT_RETURNTRANSFER, 1);

		$ret = curl_exec($session);
		$error = curl_errno($session);
		$err_msg = curl_error($session);

		curl_close($session);

		if( !empty($ret) ) {

			if( $method->payment_params->debug ) {
				echo print_r($ret, true) . "\n\n\n";
			}

			$result = array();
			if( strpos($ret, 'TRANSACTIONSTATUSTEXT') !== false ) {
				if( preg_match_all('#&lt;var name=\'(.+)\'&gt;&lt;[a-zA-Z]+&gt;(.*)&lt;/[a-zA-Z]+&gt;&lt;/var&gt;#iU', $ret, $res, PREG_SET_ORDER) ) {
					foreach($res as $r) {
						$result[ $r[1] ] = $r[2];
					}
				}
			}

			if( isset($result['TRANSACTIONSTATUSTEXT']) && $result['TRANSACTIONSTATUSTEXT'] == 'SUCCESSFUL' ) {
				$do = true;

				$dbg .= ob_get_clean();
				if( !empty($dbg) ) $dbg .= "\r\n";
				ob_start();

				$order->history->history_reason = JText::sprintf('AUTOMATIC_PAYMENT_NOTIFICATION');
				$order->history->history_notified = 0;
				$order->history->history_amount = $amount . $this->accepted_currencies[0];
				$order->history->history_payment_id = $method->payment_id;
				$order->history->history_payment_method = $method->payment_type;
				$order->history->history_data = $dbg . 'Authorization Code: ' . @$result['AUTHORIZATIONCODE'] . "\r\n" . 'Order Reference: ' . @$result['ORDERREFERENCE'] . "\r\n" . 'Unique ID: ' . $uuid;
				$order->history->history_type = 'payment';
				$order->order_status = $method->payment_params->verified_status;

				$mailer = JFactory::getMailer();
				$config =& hikashop_config();
				$sender = array(
					$config->get('from_email'),
					$config->get('from_name') );
				$mailer->setSender($sender);
				$mailer->addRecipient(explode(',',$config->get('payment_notification_email')));
				$url = HIKASHOP_LIVE.'administrator/index.php?option=com_hikashop&ctrl=order&task=listing';
				$order_text = "\r\n".JText::sprintf('NOTIFICATION_OF_ORDER_ON_WEBSITE','',HIKASHOP_LIVE);
				$order_text .= "\r\n".str_replace('<br/>',"\r\n",JText::sprintf('ACCESS_ORDER_WITH_LINK',$url));
				$mailer->setSubject(JText::sprintf('PAYMENT_NOTIFICATION','iPayDNA','Accepted'));
				$body = str_replace('<br/>',"\r\n",JText::sprintf('PAYMENT_NOTIFICATION_STATUS','iPayDNA','Accepted')).' '.JText::sprintf('ORDER_STATUS_CHANGED',$order->order_status)."\r\n\r\n".$order_text;
				$mailer->setBody($body);
				$mailer->Send();
			} else {
				$errMsg = 'An error occurred.';
				if( !empty($result['ERRORMESSAGE']) ) {
					$errMsg = 'An error occurred: [' . @$result['ERRORCODE'] . '] ' . $result['ERRORMESSAGE'];
				}
				$app->enqueueMessage($errMsg);
				$do = false;
			}
		} else {
			$do = false;
		}

		if( $error != 0 ) {
			$app->enqueueMessage('There was an error during the connection with the iPayDNA payment gateway');
			if( $method->payment_params->debug ) {
				echo 'Curl Err [' . $error . '] : ' . $err_msg . "\n\n\n";
			}
		}

		$dbg .= ob_get_clean();
		if(!empty($dbg)){
			$dbg = '-- ' . date('m.d.y H:i:s') . ' --' . "\r\n" . $dbg;

			$config =& hikashop::config();
			jimport('joomla.filesystem.file');
			$file = $config->get('payment_log_file','');
			$file = rtrim(JPath::clean(html_entity_decode($file)),DS.' ');
			if(!preg_match('#^([A-Z]:)?/.*#',$file)){
				if(!$file[0]=='/' || !file_exists($file)){
					$file = JPath::clean(HIKASHOP_ROOT.DS.trim($file,DS.' '));
				}
			}
			if(!empty($file) && defined('FILE_APPEND')){
				if (!file_exists(dirname($file))) {
					jimport('joomla.filesystem.folder');
					JFolder::create(dirname($file));
				}
				file_put_contents($file,$dbg,FILE_APPEND);
			}
		}

		if( $error != 0 ) {
			return true;
		}

		$app->setUserState( HIKASHOP_COMPONENT.'.cc_number','');
		$app->setUserState( HIKASHOP_COMPONENT.'.cc_month','');
		$app->setUserState( HIKASHOP_COMPONENT.'.cc_year','');
		$app->setUserState( HIKASHOP_COMPONENT.'.cc_CCV','');
		$app->setUserState( HIKASHOP_COMPONENT.'.cc_type','');
		$app->setUserState( HIKASHOP_COMPONENT.'.cc_owner','');
		$app->setUserState( HIKASHOP_COMPONENT.'.cc_valid',0);

		return true;
	}

	function onAfterOrderConfirm(&$order,&$methods,$method_id){
		$method =& $methods[$method_id];

		if(!HIKASHOP_J30)
			JHTML::_('behavior.mootools');
		else
			JHTML::_('behavior.framework');
		$app =& JFactory::getApplication();
		$this->removeCart = true;
		$name = $method->payment_type.'_thanks.php';
		$path = JPATH_THEMES.DS.$app->getTemplate().DS.'hikashoppayment'.DS.$name;
		if(!file_exists($path)){
			if(version_compare(JVERSION,'1.6','<')){
				$path = JPATH_PLUGINS .DS.'hikashoppayment'.DS.$name;
			}else{
				$path = JPATH_PLUGINS .DS.'hikashoppayment'.DS.$method->payment_type.DS.$name;
			}
			if(!file_exists($path)){
				return true;
			}
		}
		require($path);
		return true;
	}

	function onPaymentConfiguration(&$element){
		$this->ipaydna = JRequest::getCmd('name','ipaydna');
		if(empty($element)){
			$element = new stdClass();
			$element->payment_name='IPAYDNA';
			$element->payment_description='You can pay by credit card using this payment method';
			$element->payment_images='MasterCard,VISA,Credit_card,American_Express';
			$element->payment_type=$this->ipaydna;
			$element->payment_params= new stdClass();
			$element->payment_params->login='';
			$element->payment_params->password='';
			$element->payment_params->ask_ccv = true;
			$element->payment_params->cert = false;
			$element->payment_params->pending_status='created';
			$element->payment_params->verified_status='confirmed';
			$element = array($element);
		}
		$this->toolbar = array(
			'save',
			'apply',
			'cancel',
			'|',
			array('name' => 'pophelp', 'target' =>'payment-ipaydna-form')
		);

		hikashop_setTitle('IPAYDNA','plugin','plugins&plugin_type=payment&task=edit&name='.$this->ipaydna);
		$app = JFactory::getApplication();
		$app->setUserState( HIKASHOP_COMPONENT.'.payment_plugin_type', $this->ipaydna);
		$this->address = hikashop_get('type.address');
		$this->category = hikashop_get('type.categorysub');
		$this->category->type = 'status';
	}

	function onPaymentConfigurationSave(&$element){
		if( isset($element->payment_params->url) ) {
			$element->payment_params->url = str_replace(array('http://','https://'),'', $element->payment_params->url);
		}
		return true;
	}
}
