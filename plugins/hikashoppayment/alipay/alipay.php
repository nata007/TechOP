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
class plgHikashoppaymentAlipay extends JPlugin
{
	var $accepted_currencies = array( 'CNY', 'USD', 'EUR', 'JPY', 'GBP', 'CAD', 'AUD', 'SGD', 'CHF', 'SEK', 'DKK', 'NOK', 'HKD', );

	function onPaymentDisplay(&$order,&$methods,&$usable_methods) {
		if(!empty($methods)){
			foreach($methods as $method){
				if($method->payment_type!='alipay' || !$method->enabled){
					continue;
				}
				if(!empty($method->payment_zone_namekey)){
					$zoneClass=hikashop::get('class.zone');
					$zones = $zoneClass->getOrderZones($order);
					if(!in_array($method->payment_zone_namekey,$zones)){
						return true;
					}
				}
				$currencyClass = hikashop::get('class.currency');
				$null=null;
				if(!empty($order->total)){
					$currency_id = intval(@$order->total->prices[0]->price_currency_id);
					$currency = $currencyClass->getCurrencies($currency_id,$null);
					if (!empty($currency) && !in_array(@$currency[$currency_id] -> currency_code, $this -> accepted_currencies)) {
						return true;
					}
				}
				$usable_methods[$method->ordering] = $method;
			}
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
	function onAfterOrderConfirm(&$order,&$methods,$method_id) {
		$method =& $methods[$method_id];
		$currencyClass = hikashop::get('class.currency');
		$currencies = null;
		$currencies = $currencyClass->getCurrencies($order->order_currency_id,$currencies);
		$currency = $currencies[$order->order_currency_id];
		hikashop::loadUser(true,true);
		$user = hikashop::loadUser(true);
		$lang = &JFactory::getLanguage();
		$locale = strtolower(substr($lang->get('tag'),0,2));
		$address_type = 'billing_address';
		$app =& JFactory::getApplication();
		$address=$app->getUserState( HIKASHOP_COMPONENT.'.'.$address_type);
		if(!empty($address)){
			$cart = hikashop_get('class.cart');
			$cart->loadAddress($order->cart,$address,'object',$address_type);
			$firstname=@$order->cart->$address_type->address_firstname;
			$lastname=@$order->cart->$address_type->address_lastname;
			$address1 = '';
			if(!empty($order->cart->$address_type->address_street)){
					$address1 = substr($order->cart->$address_type->address_street,0,200);
		}
		$zip=@$order->cart->$address_type->address_post_code;
		$city=@$order->cart->$address_type->address_city;
		$state=@$order->cart->$address_type->address_state->zone_code_3;
		$country=@$order->cart->$address_type->address_country->zone_code_2;
		$email=$user->user_email;
		$phone=@$order->cart->$address_type->address_telephone;
		}
		global $Itemid;
		$url_itemid='';
		if(!empty($Itemid)){
			$url_itemid='&Itemid='.$Itemid;
		}
		$notify_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment=alipay&tmpl=component&lang='.$locale.$url_itemid;
		$return_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=after_end&order_id='.$order->order_id.$url_itemid;
		$out_trade_no = $order->order_id;
		if ($method->payment_params->Mode == "Partner")
		{
			$order_params = array(
				"seller_email" => $method->payment_params->email,
				"service" => "create_partner_trade_by_buyer",
				"partner" => $method->payment_params->Partner_ID,
				"return_url" => $return_url,
				"notify_url" => $notify_url,
				"_input_charset" => "utf-8",
				"subject" => 'order number : '.$out_trade_no,
				"body" => '',
				"out_trade_no" => $out_trade_no,
				"payment_type"=> "1",
				"price" => $order->order_full_price,
				"quantity" => "1",
				"logistics_type"=>"EXPRESS",
				"logistics_fee"=> "0.00",
				"logistics_payment"=>"BUYER_PAY",
				'receive_name' => $lastname.' '.$firstname,
				'receive_address' => $address1,
				'receive_zip' => $zip,
				'receive_phone' =>$phone
			);
		}
		else {
			$order_params = array(
				"seller_email" => $method->payment_params->email,
				"service" => "create_direct_pay_by_user",
				"partner" => $method->payment_params->Partner_ID,
				"return_url" => $return_url,
				"notify_url" => $notify_url,
				"_input_charset" => "utf-8",
				"subject" => 'order number : '.$out_trade_no,
				"body" => '',
				"out_trade_no" => $out_trade_no,
				"payment_type"=> "1",
				"total_fee" => $order->order_full_price
			);
		}
		$alipay = new alipay();
		$alipay->set_order_params($order_params);
		$alipay->set_transport($method->payment_params->Transport);
		$alipay->set_security_code($method->payment_params->Security_code);
		$alipay->set_sign_type($method->payment_params->Sign_type);
		$sign = $alipay->_sign($alipay->_order_params);
		$alipay_link = $alipay->create_payment_link();

		if(!HIKASHOP_J30)
			JHTML::_('behavior.mootools');
		else
			JHTML::_('behavior.framework');
		$app =& JFactory::getApplication();
		$name = $method->payment_type.'_end.php';
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
	function onPaymentNotification(&$statuses){
		$pluginsClass = hikashop::get('class.plugins');
		$elements = $pluginsClass->getMethods('payment','alipay');
		if(empty($elements)) return false;
		$element = reset($elements);
		$vars = array();
		$data = array();
		$filter = JFilterInput::getInstance();
		foreach($_REQUEST as $key => $value){
			$key = $filter->clean($key);
			if(preg_match("#^[0-9a-z_-]{1,30}$#i",$key)&&!preg_match("#^cmd$#i",$key)){
				$value = JRequest::getString($key);
				$vars[$key]=$value;
				$data[]=$key.'='.urlencode($value);
			}
		}
		$data = implode('&',$data).'&cmd=_notify-validate';
		if($element->payment_params->debug){
			echo print_r($vars,true)."\n\n\n";
		}
		$orderClass = hikashop_get('class.order');
		$dbOrder = $orderClass->get((int)@$vars['out_trade_no']);
		if(empty($dbOrder)){
			echo "Could not load any order for your notification ".@$vars['out_trade_no'];
			return false;
		}
		$order = new stdClass();
		$order->order_id = $dbOrder->order_id;
		$order->old_status->order_status=$dbOrder->order_status;
		$url = HIKASHOP_LIVE.'administrator/index.php?option=com_hikashop&ctrl=order&task=edit&order_id='.$order->order_id;
		$order_text = "\r\n".JText::sprintf('NOTIFICATION_OF_ORDER_ON_WEBSITE',$dbOrder->order_number,HIKASHOP_LIVE);
		$order_text .= "\r\n".str_replace('<br/>',"\r\n",JText::sprintf('ACCESS_ORDER_WITH_LINK',$url));
		if($element->payment_params->debug){
			echo print_r($dbOrder,true)."\n\n\n";
		}
		$mailer = JFactory::getMailer();
		$config =& hikashop_config();
		$sender = array(
				$config->get('from_email'),
				$config->get('from_name') );
		$mailer->setSender($sender);
		$mailer->addRecipient(explode(',',$config->get('payment_notification_email')));
		$alipay = new alipay();
		$alipay->set_transport($element->payment_params->Transport);
		$alipay->set_security_code($element->payment_params->Security_code);
		$alipay->set_sign_type($element->payment_params->Sign_type);
		$alipay->set_partner_id($element->payment_params->Partner_ID);
		if($alipay->_transport == "https") {
			$notify_url = $alipay->_notify_gateway . "service=notify_verify" ."&partner=" .$alipay->_partner_id . "&notify_id=".$_POST["notify_id"];
		} else {
			$notify_url = $alipay->_notify_gateway . "partner=" . $alipay->_partner_id . "&notify_id=".$_POST["notify_id"];
		}
		$url_array  = parse_url($notify_url);
		$errno='';
		$errstr='';
		$notify = array();
		$response = array();
		if($url_array['scheme'] == 'https') {
			$transport = 'ssl://';
			$url_array['port'] = '443';
		} else {
			$transport = 'tcp://';
			$url_array['port'] = '80';
		}
		if($element->payment_params->debug){
			echo print_r($url_array,true)."\n\n\n";
		}
		$fp = @fsockopen($transport . $url_array['host'], $url_array['port'], $errno, $errstr, 60);
		if(!$fp) {
			$mailer->setSubject(JText::sprintf('NOTIFICATION_REFUSED_FOR_THE_ORDER','Alipay').' '.JText::sprintf('PAYPAL_CONNECTION_FAILED',$dbOrder->order_number));
			$body = str_replace('<br/>',"\r\n",JText::sprintf('NOTIFICATION_REFUSED_NO_CONNECTION','Alipay'))."\r\n\r\n".$order_text;
			$mailer->setBody($body);
			$mailer->Send();
			JError::raiseError( 403, JText::_( 'Access Forbidden' ));
			return false;
		} else {
			fputs($fp, "POST " . $url_array['path'] . " HTTP/1.1\r\n");
			fputs($fp, "HOST: " . $url_array['host'] . "\r\n");
			fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
			fputs($fp, "Content-length: " . strlen($url_array['query']) . "\r\n");
			fputs($fp, "Connection: close\r\n\r\n");
			fputs($fp, $url_array['query'] . "\r\n\r\n");
			while(!feof($fp)) {
				$notify[] = @fgets($fp, 1024);
			}
			fclose($fp);
			if($element->payment_params->debug){
				echo print_r($notify,true)."\n\n\n";
			}
			$response=implode(',', $notify);
		}
		if(is_array($_POST)) {
			$tmp_array = array();
			foreach($_POST as $key=>$value) {
				if($value != '' && $key != 'sign' && $key != 'sign_type') {
					$tmp_array[$key] = $value;
				}
			}
			ksort($tmp_array);
			reset($tmp_array);
			$params = $tmp_array;
		} else {
			return false;
		}
		$sign = $alipay->_sign($params);
		if((preg_match('/true$/i', $response) && $sign == $_POST['sign']) || ($_POST['trade_status'] == 'TRADE_FINISHED' || $_POST['trade_status'] == 'TRADE_SUCCESS' || $_POST['trade_status'] == 'WAIT_SELLER_SEND_GOODS' || $_POST['trade_status']== 'WAIT_BUYER_PAY')) {
			$currencyClass = hikashop_get('class.currency');
			$currencies=null;
			$currencies = $currencyClass->getCurrencies($dbOrder->order_currency_id,$currencies);
			$currency=$currencies[$dbOrder->order_currency_id];
			$price_check = round($dbOrder->order_full_price, (int)$currency->currency_locale['int_frac_digits'] );

			$order->history->history_reason=JText::sprintf('AUTOMATIC_PAYMENT_NOTIFICATION');
			$order->history->history_notified=0;
			$order->history->history_amount=$price_check;
			$order->history->history_payment_id = $element->payment_id;
			$order->history->history_payment_method =$element->payment_type;
			$order->history->history_data = ob_get_clean();
			$order->history->history_type = 'payment';
			$order->order_status = $element->payment_params->verified_status;
			$order->history->history_notified=1;
			if($dbOrder->order_status == $order->order_status) return true;
			$order->mail_status=$statuses[$order->order_status];
			$mailer->setSubject(JText::sprintf('PAYMENT_NOTIFICATION_FOR_ORDER','Alipay',$_POST['trade_status'],$dbOrder->order_number));
			$body = str_replace('<br/>',"\r\n",JText::sprintf('PAYMENT_NOTIFICATION_STATUS','Alipay',$_POST['trade_status'])).' '.JText::sprintf('ORDER_STATUS_CHANGED',$order->mail_status)."\r\n\r\n".$order_text;
			$mailer->setBody($body);
			$mailer->Send();
			$orderClass->save($order);
			return true;
		} else {
				$mailer->setSubject(JText::sprintf('NOTIFICATION_REFUSED_FOR_THE_ORDER','Alipay').'invalid response');
				$body = JText::sprintf("Hello,\r\n An Alipay notification was refused because the response from the Alipay server was invalid")."\r\n\r\n".$order_text;
				$mailer->setBody($body);
				$mailer->Send();
				if($element->payment_params->debug){
					echo 'invalid response'."\n\n\n";
				}
				return false;
		}
	}

	function onPaymentConfiguration(&$element){
		$this->alipay = JRequest::getCmd('name','alipay');
		if(empty($element)){
			$element = new stdClass();
			$element->payment_name='Alipay';
			$element->payment_description='You can pay by credit card using this payment method';
			$element->payment_images='MasterCard,VISA';
			$element->payment_type=$this->alipay;
			$element->payment_params=new stdClass();
			$element->payment_params->login='';
			$element->payment_params->password='';
			$element->payment_params->ask_ccv = true;
			$element->payment_params->security = false;
			$element->payment_params->pending_status='created';
			$element->payment_params->verified_status='confirmed';
			$element = array($element);
		}
		$this->toolbar = array(
			'save',
			'apply',
			'cancel',
			'|',
			array('name' => 'pophelp', 'target' =>'payment-alipay-form')
		);
		hikashop::setTitle('ALIPAY','plugin','plugins&plugin_type=payment&task=edit&name='.$this->alipay);
		$app = JFactory::getApplication();
		$app->setUserState( HIKASHOP_COMPONENT.'.payment_plugin_type', $this->alipay);
		$this->address = hikashop::get('type.address');
		$this->category = hikashop::get('type.categorysub');
		$this->category->type = 'status';

	}
	function onPaymentConfigurationSave(&$element){
		if( isset($element->payment_params->security) && $element->payment_params->security && isset($element->payment_params->security_cvv) && $element->payment_params->security_cvv ) {
			$element->payment_params->ask_ccv = true;
		}
		return true;
	}

}


class alipay {
	var $_order_params;
	var $_security_code;
	var $_sign_type;
	var $_partner_id;
	var $_transport;
	var $_gateway;
	var $_notify_gateway;

	function set_order_params($order_params) {
		if(is_array($order_params)) {
			$tmp_array = array();
			foreach($order_params as $key=>$value) {
				if($value != '' && $key != 'sign' && $key != 'sign_type') {
					$tmp_array[$key] = $value;
				}
			}
			ksort($tmp_array);
			reset($tmp_array);
			$this->_order_params = $tmp_array;
		} else {
			return false;
		}
	}

	function set_security_code($security_code) {
		$this->_security_code = $security_code;
	}

	function set_sign_type($sign_type) {
		$this->_sign_type = strtoupper($sign_type);
	}

	function set_partner_id($partner_id) {
		$this->_partner_id = $partner_id;
	}

	function set_transport($transport) {
		$this->_transport = strtolower($transport);
		if($this->_transport == 'https') {
			$this->_gateway = 'http://www.alipay.com/cooperate/gateway.do?';
			$this->_notify_gateway = $this->_gateway;
		} elseif($this->_transport == 'http') {
			$this->_gateway = 'http://www.alipay.com/cooperate/gateway.do?';
			$this->_notify_gateway = 'http://notify.alipay.com/trade/notify_query.do?';
		}
	}

	function _sign($params) {
		$params_str = '';
		foreach($params as $key => $value) {
			if($params_str == '') {
				$params_str = "$key=$value";
			} else {
				$params_str .= "&$key=$value";
			}
		}
		if($this->_sign_type == 'MD5') {
			return md5($params_str . $this->_security_code);
		}
	}

	function create_payment_link() {
		$params_str = '';
		foreach($this->_order_params as $key => $value) {
			$params_str .= "$key=" . urlencode($value) . "&";
		}
		return $this->_gateway . $params_str . 'sign=' . $this->_sign($this->_order_params) . '&sign_type=' . $this->_sign_type;
	}
}
?>
