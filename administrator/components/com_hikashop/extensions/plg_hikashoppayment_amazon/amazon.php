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
class plgHikashoppaymentAmazon extends JPlugin {
	var $accepted_currencies = array('USD');
	var $debugData = array();

	function onPaymentDisplay(&$order, &$methods, &$usable_methods) {
		if (!empty($methods)) {
			foreach ($methods as $method) {
				if (!function_exists('curl_init')) {
					$app = JFactory::getApplication();
					$app -> enqueueMessage('The AMAZON payment plugin needs the CURL library installed but it seems that it is not available on your server. Please contact your web hosting to set it up.', 'error');
					return false;
				}
				if ($method -> payment_type != 'amazon' || !$method -> enabled) {
					continue;
				}
				if (!empty($method -> payment_zone_namekey)) {
					$zoneClass = hikashop_get('class.zone');
					$zones = $zoneClass -> getOrderZones($order);
					if (!in_array($method -> payment_zone_namekey, $zones)) {
						return true;
					}
				}
				$currencyClass = hikashop_get('class.currency');
				$null = null;
				if (!empty($order -> total)) {
					$currency_id = intval(@$order -> total -> prices[0] -> price_currency_id);
					$currency = $currencyClass -> getCurrencies($currency_id, $null);
					if (!empty($currency) && !in_array(@$currency[$currency_id] -> currency_code, $this -> accepted_currencies)) {
						return true;
					}
				}
				$usable_methods[$method -> ordering] = $method;
			}
		}
		return true;
	}

	function onPaymentSave(&$cart, &$rates, &$payment_id) {
		$usable = array();
		$this -> onPaymentDisplay($cart, $rates, $usable);
		$payment_id = (int)$payment_id;
		foreach ($usable as $usable_method) {
			if ($usable_method -> payment_id == $payment_id) {
				return $usable_method;
			}
		}
		return false;
	}

	function onAfterOrderConfirm(&$order, &$methods, $method_id) {
		$method = &$methods[$method_id];
		$tax_total = '';
		$discount_total = '';
		$currencyClass = hikashop_get('class.currency');
		$currencies = null;
		$currencies = $currencyClass -> getCurrencies($order -> order_currency_id, $currencies);
		$currency = $currencies[$order -> order_currency_id];
		hikashop_loadUser(true, true);
		$user = hikashop_loadUser(true);
		$lang = JFactory::getLanguage();
		$locale = strtolower(substr($lang -> get('tag'), 0, 2));
		global $Itemid;
		$url_itemid = '';
		if (!empty($Itemid)) {
			$url_itemid = '&Itemid=' . $Itemid;
		}
		$notify_url = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment=amazon&tmpl=component&lang=' . $locale . $url_itemid;
		if (!isset($method -> payment_params -> no_shipping))
			$method -> payment_params -> no_shipping = 1;
		if (!empty($method -> payment_params -> rm))
			$method -> payment_params -> rm = 2;

		$host = 'authorize.payments-sandbox.amazon.com';
		$path = '/cobranded-ui/actions/start';

		$vars = array('signatureMethod' => 'HmacSHA256',
		 'signatureVersion' => '2',
		 'currencyCode' => $currency->currency_code,
		 'callerKey' => $method->payment_params->merchant_Key,
		 'callerReference' => $order->order_id,
		 'paymentReason' => 'donation',
		 'pipelineName' => 'SingleUse',
		 'returnUrl' => $notify_url,
		 'transactionAmount' => round($order->order_full_price,3),
		 'version' => '2009-01-09', );
		ksort($vars);
		$vars2 = array_map('rawurlencode', $vars);

		$paramStringArray = array();
		foreach ($vars2 as $key => $value) {
			$paramStringArray[] = $key . '=' . $value;
		}
		$paramString = implode('&', $paramStringArray);
		$string_to_sign = 'POST' . "\n" . $host . "\n" . $path . "\n" . $paramString;

		$signature = base64_encode(hash_hmac('sha256', $string_to_sign, $method->payment_params->secret_Key, true));
		$vars["signature"] = $signature;
		ksort($vars);
		if ($method->payment_params->environnement == 'production'){
			$PayUrl = 'https://authorize.pa yments.amazon.com/cobranded-ui/actions/start';
		}
		if ($method->payment_params->environnement == 'sandbox'){
			$PayUrl = 'https://authorize.payments-sandbox.amazon.com/cobranded-ui/actions/start';
		}
		if (!HIKASHOP_J30)
			JHTML::_('behavior.mootools');
		else
			JHTML::_('behavior.framework');
		$app = JFactory::getApplication();
		$name = $method -> payment_type . '_end.php';
		$path = JPATH_THEMES . DS . $app -> getTemplate() . DS . 'hikashoppayment' . DS . $name;
		if (!file_exists($path)) {
			if (version_compare(JVERSION, '1.6', '<')) {
				$path = JPATH_PLUGINS . DS . 'hikashoppayment' . DS . $name;
			} else {
				$path = JPATH_PLUGINS . DS . 'hikashoppayment' . DS . $method -> payment_type . DS . $name;
			}
			if (!file_exists($path)) {
				return true;
			}
		}
		require ($path);
		return true;
	}

	function onPaymentNotification(&$statuses) {
		$pluginsClass = hikashop_get('class.plugins');
		$elements = $pluginsClass -> getMethods('payment', 'amazon');
		if (empty($elements))
			return false;
		$element = reset($elements);
		$vars = array();
		$data = array();
		$filter = JFilterInput::getInstance();


		foreach ($_REQUEST as $key => $value) {
			$key = $filter -> clean($key);
			if (preg_match("#^[0-9a-z_-]{1,30}$#i", $key) && !preg_match("#^cmd$#i", $key)  && $key != 'task' && $key != 'tmpl' && $key != 'Itemid' && $key != 'notif_payment' && $key != 'ctrl' && $key != 'lang' && $key != 'option' && $key != 'hikashop_front_end_main' && $key != 'view') {
				$value = JRequest::getString($key);
				$vars[$key] = $value;
				$data[] = $key . '=' . urlencode($value);
			}
		}
		if ($element -> payment_params -> debug) {
			echo "<br/>---------------------- REQUEST -------------------------------------";
			foreach ($vars as $key => $value) {
				echo "$key = $value <br/>";
			}
			echo "<br/>------------------ EO REQUEST ----------------------------------------";
		}
		$data = implode('&', $data) . '&cmd=_notify-validate';

        $user = hikashop_loadUser(true);
		$lang = JFactory::getLanguage();
		$locale = strtolower(substr($lang -> get('tag'), 0, 2));
		global $Itemid;
		$url_itemid = '';
		if (!empty($Itemid)) {
			$url_itemid = '&Itemid=' . $Itemid;
		}

		$paramStringArray = array();
		foreach ($vars as $key => $value) {
				$paramStringArray[] = str_replace('%7E', '~', rawurlencode($key)) . '=' . str_replace('%7E', '~', rawurlencode($value));
		}
		$http_param = '';
		$http_param = implode('&', $paramStringArray);
		if ($element->payment_params->environnement == 'production'){
			$curlUrl = 'https://fps.amazonaws.com';
			$parsedUrl = parse_url($curlUrl);
		}
		if ($element->payment_params->environnement == 'sandbox'){
			$curlUrl = 'https://fps.sandbox.amazonaws.com';
			$parsedUrl = parse_url($curlUrl);
		}
		$Timestamp = gmdate("Y-m-d\TH:i:s\Z");
		$urlEndPoint = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment=amazon&tmpl=component&lang=' . $locale . $url_itemid;
        $vars_signVerif= array (
        	"Action"=> "VerifySignature",
        	"UrlEndPoint"=> $urlEndPoint,
        	"HttpParameters"=>$http_param,
        	"AWSAccessKeyId"=> $element->payment_params->merchant_Key,
			"Timestamp" => $Timestamp ,
			"Version" => "2010-08-28" ,
        	"SignatureVersion" => 2 ,
			"SignatureMethod" => "HmacSHA256" ,
		);

		uksort($vars_signVerif, 'strcmp');

		$paramStringArray = array();
		foreach ($vars_signVerif as $key => $value) {
				$paramStringArray[] = $key . '=' . str_replace('%7E', '~', rawurlencode($value));
		}

		$paramString = '';
		$paramString = implode('&', $paramStringArray);
		$string_to_sign = 'GET' . "\n" . $parsedUrl['host'] . "\n" . '/' . "\n" . $paramString;

		$signature = base64_encode(hash_hmac('sha256', $string_to_sign, $element->payment_params->secret_Key, true));
		$paramString.="&Signature=". $signature;
		$vars_signVerif["Signature"] = $signature;
		if ($element -> payment_params -> debug) {
			echo "<br/>---------------------- VARS SIGN VERIF  -------------------------------------";
			foreach ($vars_signVerif as $key => $value) {
				echo "$key = $value <br/>";
			}
			$curlUrl .= '/?' . $paramString;
			echo "<br/> Curl URL : <br/> $curlUrl <br/>";
			echo "<br/>------------------ EO VAR SIGN VERIF -----------------------------------------";
		}
		$session = curl_init();
		curl_setopt($session, CURLOPT_URL, $curlUrl);
		curl_setopt($session, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($session, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($session, CURLOPT_VERBOSE, 1);
		curl_setopt($session, CURLOPT_POST, 0);
		curl_setopt($session, CURLOPT_RETURNTRANSFER, 1);

		$result = curl_exec($session);
		$error = curl_errno($session);
		$err_msg = curl_error($session);;

		curl_close($session);
		$VerificationStatus = $this -> getTagValue($result,'VerificationStatus');
		if ($element -> payment_params -> debug) {
			echo "<br/>---------------------- Curl Result SIGN -------------------------------------<br/>";
			echo"CURL RESULT :<br/>";
			var_dump($result);
			echo "Transaction Status : $VerificationStatus";
			echo "<br/>------------------ EO Curl Result sign -----------------------------------------";
		}

		if($VerificationStatus == 'Success' && ($vars['status'] == 'SA' || $vars['status'] == 'SB' || $vars['status'] == 'SC')){
			$orderClass = hikashop_get('class.order');
			$dbOrder = $orderClass -> get((int)@$vars['callerReference']);
			$currencyClass = hikashop_get('class.currency');
			$currencies = null;
			$currencies = $currencyClass->getCurrencies($dbOrder->order_currency_id,$currencies);
			$currency = $currencies[$dbOrder->order_currency_id];

			if (empty($dbOrder)) {
				if ($element -> payment_params -> debug) {
					echo "Could not load any order for your notification " . $vars['orderID'] . "NO ORDER ID <br/>";
				}
				return false;
			}
			if ($element->payment_params->environnement == 'production'){
				$curlUrl = 'https://fps.amazonaws.com';
				$parsedUrl = parse_url($curlUrl);
			}
			if ($element->payment_params->environnement == 'sandbox'){
				$curlUrl = 'https://fps.sandbox.amazonaws.com';
				$parsedUrl = parse_url($curlUrl);
			}
			$Timestamp = gmdate("Y-m-d\TH:i:s\Z");
			$vars_request= array (
				"Action" => "Pay" ,
				"AWSAccessKeyId"=> $element->payment_params->merchant_Key,
				"CallerDescription"=> "hikashop-amazon",
				"CallerReference"=> $vars['callerReference'],
				"SenderTokenId"=> $vars['tokenID'],
				"SignatureMethod" =>"HmacSHA256" ,
				"SignatureVersion" => 2 ,
				"Timestamp" => $Timestamp ,
				"TransactionAmount.CurrencyCode" => $currency->currency_code ,
				"TransactionAmount.Value" => round($dbOrder->order_full_price,3) ,
				"Version" => "2008-09-17" ,
			);
			ksort($vars_request);
			$vars_sign = array_map('rawurlencode', $vars_request);

			$paramStringArray = array();
			foreach ($vars_sign as $key => $value) {
				$paramStringArray[] = $key . '=' . $value;
			}

			$paramString = implode('&', $paramStringArray);

			$string_to_sign = 'POST' . "\n" . $parsedUrl['host'] . "\n" . '/' . "\n" . $paramString;

			$signature = base64_encode(hash_hmac('sha256', $string_to_sign, $element->payment_params->secret_Key, true));
			$vars_request["Signature"] = $signature;
			ksort($vars_request);
			if ($element -> payment_params -> debug) {
				echo "<br/>---------------------- VARS PAY  -------------------------------------";
				foreach ($vars_request as $key => $value) {
					echo "$key = $value <br/>";
				}
				echo "<br/> $paramString";
				echo "<br/>------------------ EO VARS PAY -----------------------------------------";
			}
			$session = curl_init($curlUrl);
			curl_setopt($session, CURLOPT_URL, $curlUrl);
			curl_setopt($session, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($session, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($session, CURLOPT_VERBOSE, 1);
			curl_setopt($session, CURLOPT_POST, 1);
			curl_setopt($session, CURLOPT_POSTFIELDS, str_replace('+', '%20', http_build_query($vars_request, '', '&')));
			curl_setopt($session, CURLOPT_RETURNTRANSFER, 1);

			$result = curl_exec($session);
			$error = curl_errno($session);
			$err_msg = curl_error($session);;

			curl_close($session);
			if ($element -> payment_params -> debug) {
				echo "<br/>---------------------- Curl Result  -------------------------------------<br/>";
				echo"CURL RESULT : <br/>";
				var_dump($result);
				echo "<br/>------------------ EO Curl Result -----------------------------------------";
			}
			$TransactionId = $this -> getTagValue($result,'TransactionId');
	    	$TransactionStatus = $this -> getTagValue($result,'TransactionStatus');

			$order = new stdClass();
			$order -> history = new stdClass();
			$order -> order_id = $dbOrder -> order_id;
			$order -> old_status -> order_status = $dbOrder -> order_status;
			$url = HIKASHOP_LIVE . 'administrator/index.php?option=com_hikashop&ctrl=order&task=edit&order_id=' . $order -> order_id;
			$order_text = "\r\n" . JText::sprintf('NOTIFICATION_OF_ORDER_ON_WEBSITE', $dbOrder -> order_number, HIKASHOP_LIVE);
			$order_text .= "\r\n" . str_replace('<br/>', "\r\n", JText::sprintf('ACCESS_ORDER_WITH_LINK', $url));
			if ($element -> payment_params -> debug) {
				echo print_r($dbOrder, true) . "\n\n\n";
			}
			$mailer = JFactory::getMailer();
			$config = &hikashop_config();
			$sender = array($config -> get('from_email'), $config -> get('from_name'));
			$mailer -> setSender($sender);
			$mailer -> addRecipient(explode(',', $config -> get('payment_notification_email')));
			if ($TransactionStatus == 'Success' || $TransactionStatus == 'Pending') {
				if ($element -> payment_params -> debug) {
					echo "---------------------------------------NOTIFY OK----------------------------------------<br/>";
				}
				$currencyClass = hikashop_get('class.currency');
				$currencies = null;
				$currencies = $currencyClass -> getCurrencies($dbOrder -> order_currency_id, $currencies);
				$currency = $currencies[$dbOrder -> order_currency_id];
				$price_check = round($dbOrder -> order_full_price, (int)$currency -> currency_locale['int_frac_digits']);
				$order -> history -> history_reason = JText::sprintf('AUTOMATIC_PAYMENT_NOTIFICATION');
				$order -> history -> history_notified = 0;
				$order -> history -> history_amount = $dbOrder->order_full_price;
				$order -> history -> history_payment_id = $element -> payment_id;
				$order -> history -> history_payment_method = $element -> payment_type;
				$order -> history -> history_data = ob_get_clean().'/r/n'.$vars['tokenID'];
				$order -> history -> history_type = 'payment';
				$order -> order_status = $element -> payment_params -> verified_status;
				$order -> history -> history_notified = 1;
				if ($element -> payment_params -> debug) {
					echo "---------------------------------------SUCCESS----------------------------------------<br/>";
					echo 'ORDER :' . var_dump($order) . '<br/>';
				}
				if ($dbOrder -> order_status == $order -> order_status)
					return true;
				$order -> mail_status = $order -> order_status;
				$mailer -> setSubject(JText::sprintf('PAYMENT_NOTIFICATION_FOR_ORDER', 'Amazon', $TransactionStatus, $dbOrder -> order_number));
				$body = str_replace('<br/>', "\r\n", JText::sprintf('PAYMENT_NOTIFICATION_STATUS', 'Amazon', $TransactionStatus)) . ' ' . JText::sprintf('ORDER_STATUS_CHANGED', $order -> mail_status) . "\r\n\r\n" . $order_text;
				$mailer -> setBody($body);
				$mailer -> Send();
				$orderClass -> save($order);
				return true;
			} else {
				$order -> history -> history_reason = JText::sprintf('AUTOMATIC_PAYMENT_NOTIFICATION');
				$order -> history -> history_notified = 0;
				$order -> history -> history_amount = $dbOrder->order_full_price;
				$order -> history -> history_payment_id = $element -> payment_id;
				$order -> history -> history_payment_method = $element -> payment_type;
				$order -> history -> history_data = ob_get_clean().'/r/n'.$vars['tokenID'];
				$order -> history -> history_type = 'payment';
				$order -> order_status = $element -> payment_params -> invalid_status;
				$order -> history -> history_notified = 1;
				$mailer -> setSubject(JText::sprintf('NOTIFICATION_REFUSED_FOR_THE_ORDER', 'Amazon') . 'invalid response');
				$body = JText::sprintf("Hello,\r\n A Amazon notification was refused because the response from the Post finance server was invalid") . "\r\n\r\n" . $order_text;
				$mailer -> setBody($body);
				$mailer -> Send();
				$orderClass -> save($order);
				if ($element -> payment_params -> debug) {
					echo 'invalid response' . "\n\n\n";
				}
				return false;
			}
		}
	}

	function onPaymentConfiguration(&$element) {
		$this -> amazon = JRequest::getCmd('name', 'amazon');
		if (empty($element)) {
			$element = new stdClass();
			$element -> payment_name = 'Amazon';
			$element -> payment_description = 'You can pay by credit card or Amazon using this payment method';
			$element -> payment_images = 'MasterCard,VISA,Credit_card,Amazon';
			$element -> payment_type = $this -> amazon;
			$element -> payment_params = new stdClass();
			$element -> payment_params -> notification = 1;
			$list = new stdClass();
			$element -> payment_params -> merchant_Key = '';
			$element -> payment_params -> merchant_Token = '';
			$element -> payment_params -> details = 0;
			$element -> payment_params -> address_override = 1;
			$element = array($element);
		}
		$obj = reset($element);
		$this -> toolbar = array('save', 'apply', 'cancel', '|', array('name' => 'pophelp', 'target' => 'payment-amazon-form'));
		hikashop_setTitle('Amazon', 'plugin', 'plugins&plugin_type=payment&task=edit&name=' . $this -> amazon);
		$app = JFactory::getApplication();
		$app -> setUserState(HIKASHOP_COMPONENT . '.payment_plugin_type', $this -> amazon);
		$this -> address = hikashop_get('type.address');
		$this -> category = hikashop_get('type.categorysub');
		$this -> category -> type = 'status';

	}

	function onPaymentConfigurationSave(&$element) {
		$element -> payment_params -> invalid_status = 'cancelled';
		$element -> payment_params -> pending_status = 'created';
		$element -> payment_params -> verified_status = 'confirmed';
		return true;
	}
	function getTagValue($string, $tagname) {
	    $pattern = "#<$tagname>(.*)</$tagname>#";
	    preg_match($pattern, $string, $matches);
	    if(isset($matches[1])){
	    	return $matches[1];
	    }else{
	    	return 'Failed';
	    }
	}
}
