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
defined('_JEXEC') or die('Restricted access');
?>
<?php
class plgHikashoppaymentOkpay extends JPlugin{
	var $accepted_currencies = array(
									'AUD','CAD','EUR','GBP','JPY','USD','NZD','CHF','SGD',
									'DKK','PLN','NOK','CZK','MXN','MYR','PHP','TWD','ILS',
									'RUB','CNY','NGN'
									);
	var $debugData = array();

	function onPaymentDisplay(&$order,&$methods,&$usable_methods){
		if(!empty($methods)){
			foreach($methods as $method){
				if($method->payment_type!='okpay' || !$method->enabled){
					continue;
				}

				if(!empty($method->payment_zone_namekey)){
					$zoneClass	= hikashop_get('class.zone');
					$zones		= $zoneClass->getOrderZones($order);

					if(!in_array($method->payment_zone_namekey,$zones)){
						return true;
					}
				}

				$currencyClass	= hikashop_get('class.currency');
				$null			= null;

				if(!empty($order->total)){
					$currency_id	= intval(@$order->total->prices[0]->price_currency_id);
					$currency		= $currencyClass->getCurrencies($currency_id,$null);

					if(!empty($currency) && !in_array(@$currency[$currency_id]->currency_code,$this->accepted_currencies)){
						return true;
					}
				}

				$usable_methods[$method->ordering]=$method;
			}
		}
		return true;
	}

	function onPaymentSave(&$cart,&$rates,&$payment_id){
		$usable		= array();
		$this->onPaymentDisplay($cart,$rates,$usable);
		$payment_id	= (int) $payment_id;

		foreach($usable as $usable_method){
			if($usable_method->payment_id==$payment_id){
				return $usable_method;
			}
		}
		return false;
	}

	function onAfterOrderConfirm(&$order,&$methods,$method_id){
		$method			=& $methods[$method_id];
		$tax_total		= '';
		$discount_total	= '';
		$currencyClass	= hikashop_get('class.currency');
		$currencies		= null;
		$currencies		= $currencyClass->getCurrencies($order->order_currency_id,$currencies);
		$currency		= $currencies[$order->order_currency_id];

		hikashop_loadUser(true,true); //reset user data in case the emails were changed in the email code

		$user			= hikashop_loadUser(true);
		$lang			= &JFactory::getLanguage();
		$locale			= strtolower(substr($lang->get('tag'),0,2));
		global $Itemid;

		$url_itemid		= '';

		if(!empty($Itemid)){
			$url_itemid='&Itemid='.$Itemid;
		}

		$notify_url 	= HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment=okpay&tmpl=component&&invoice='.$order->order_id.'lang='.$locale.$url_itemid;
		$return_url		= HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=after_end&order_id='.$order->order_id.$url_itemid;
		$cancel_url		= HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=order&task=cancel_order&order_id='.$order->order_id.$url_itemid;

		$vars = array(
			"ok_receiver"			=> $method->payment_params->walletid,
			"ok_currency"			=> $currency->currency_code,
			"invoice"				=> $order->order_id,
			"ok_return_success"		=> $return_url,
			"ok_ipn"				=> $notify_url,
			"ok_return_fail"		=> $cancel_url
		);

		$i = 1;

		foreach($order->cart->products as $product){
			$item_price							= round($product->order_product_price,(int)$currency->currency_locale['int_frac_digits']) + round($product->order_product_tax,(int)$currency->currency_locale['int_frac_digits'])*$product->order_product_quantity;
			$vars["ok_item_".$i."_name"]		= substr(strip_tags($product->order_product_name),0,127);
			$vars["ok_item_".$i."_quantity"]	= $product->order_product_quantity;
			$vars["ok_item_".$i."_price"]		= $item_price;
			$i++;
		}

		if(!HIKASHOP_J30)
			JHTML::_('behavior.mootools');
		else
			JHTML::_('behavior.framework');
		$app	=& JFactory::getApplication();
		$name	= $method->payment_type.'_end.php';
		$path	= JPATH_THEMES.DS.$app->getTemplate().DS.'hikashoppayment'.DS.$name;
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
		$pluginsClass	= hikashop_get('class.plugins');
		$elements		= $pluginsClass->getMethods('payment','okpay');

		$vars	= array();
		$data	= array();
		$filter	=& JFilterInput::getInstance();

		foreach($_REQUEST as $key => $val){
			$$key = $val;
		}

		$orderClass	= hikashop_get('class.order');
		$dbOrder	= $orderClass->get((int)@$invoice);

		if(empty($dbOrder)){
			echo "Could not load any order for your notification ".@$invoice;
			return false;
		}

		$order								= new stdClass();
		$order->order_id					= $dbOrder->order_id;
		$order->old_status->order_status	= $dbOrder->order_status;

		$url			= HIKASHOP_LIVE.'administrator/index.php?option=com_hikashop&ctrl=order&task=edit&order_id='.$order->order_id;
		$order_text		= "\r\n".JText::sprintf('NOTIFICATION_OF_ORDER_ON_WEBSITE',$dbOrder->order_number,HIKASHOP_LIVE);
		$order_text		.= "\r\n".str_replace('<br/>',"\r\n",JText::sprintf('ACCESS_ORDER_WITH_LINK',$url));

		$mailer = JFactory::getMailer();
		$config =& hikashop_config();
		$sender = array(
		$config->get('from_email'),
		$config->get('from_name') );
		$mailer->setSender($sender);
		$receipients	= explode(',',$config->get('payment_notification_email'));

		$mailer->addRecipient($receipients);

		$response = $ok_txn_status;

		$verified = preg_match( "#completed#i", $response);

		$req = 'ok_verify=true';

		foreach ($_POST as $key => $value) {
			$value = urlencode(stripslashes($value));
			$req .= "&$key=$value";
		}

		$header .= "POST /ipn-verify.html HTTP/1.0\r\n";
		$header .= "Host: www.okpay.com\r\n";
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header .= "Content-Length: " . strlen($req) . "\r\n\r\n";
		$fp = fsockopen ('www.okpay.com', 80, $errno, $errstr, 30);

		if (!$fp)
		{
		} else
		{
			fputs ($fp, $header . $req);
			while (!feof($fp))
			{
			$res = fgets ($fp, 1024);
			if (strcmp ($res, "VERIFIED") == 0)
			{
				$ok_response = $res;
			}
			else if (strcmp ($res, "INVALID") == 0)
			{
				$ok_response = $res;
			}
			else if (strcmp ($res, "TEST")== 0)
			{
				$ok_response = $res;
			}
			}
			fclose ($fp);
		}

		$ok_verified = preg_match('/verified/i', @$ok_response);

		if(!$verified && !$ok_verified){
			$mailer->setSubject(JText::sprintf('NOTIFICATION_REFUSED_FOR_THE_ORDER','OKPay').'invalid transaction');
			$body = JText::sprintf("Hello,\r\n A okpay notification was refused because it could not be verified by the okpay server")."\r\n\r\n".JText::sprintf('CHECK_DOCUMENTATION',HIKASHOP_HELPURL.'payment-okpay-error#invalidtnx').$order_text;
			$mailer->setBody($body);
			$mailer->Send();
			return false;
		} else {
			$body = str_replace('<br/>',"\r\n",JText::sprintf('PAYMENT_NOTIFICATION_STATUS','OKPay',$response)).' '.JText::_('STATUS_NOT_CHANGED')."\r\n\r\n".JText::sprintf('CHECK_DOCUMENTATION',HIKASHOP_HELPURL.'payment-okpay-error#status').$order_text;
			$mailer->setSubject(JText::sprintf('PAYMENT_NOTIFICATION_FOR_ORDER','OKPay',$response,$dbOrder->order_number));
			$mailer->setBody($body);
			$mailer->Send();
		}

		$order->history->history_reason=JText::sprintf('AUTOMATIC_PAYMENT_NOTIFICATION');
		$order->history->history_notified=0;
		$order->history->history_amount=@$ok_txn_gross.@$ok_txn_currency;
		$order->history->history_payment_id = $element->payment_id;
		$order->history->history_payment_method =$element->payment_type;
		$order->history->history_data = ob_get_clean();
		$order->history->history_type = 'payment';
		$currencyClass = hikashop_get('class.currency');
		$currencies=null;
		$currencies = $currencyClass->getCurrencies($dbOrder->order_currency_id,$currencies);
		$currency=$currencies[$dbOrder->order_currency_id];
		$price_check = round($dbOrder->order_full_price, (int)$currency->currency_locale['int_frac_digits'] );

		if($price_check != @$ok_txn_gross || $currency->currency_code != @$ok_txn_currency){
			$order->order_status = $element->payment_params->invalid_status;
			$orderClass->save($order);
			$mailer->setSubject(JText::sprintf('NOTIFICATION_REFUSED_FOR_THE_ORDER','OKPay').JText::_('INVALID_AMOUNT'));
			$body = str_replace('<br/>',"\r\n",JText::sprintf('AMOUNT_RECEIVED_DIFFERENT_FROM_ORDER','OKPay',$order->history->history_amount,$price_check.$currency->currency_code))."\r\n\r\n".JText::sprintf('CHECK_DOCUMENTATION',HIKASHOP_HELPURL.'payment-okpay-error#amount').$order_text;
			$mailer->setBody($body);
			$mailer->Send();
			return false;
		}

		$order->order_status = $element->payment_params->verified_status;
		$order->history->history_notified=1;

		if($dbOrder->order_status == $order->order_status) return true;
		$order->mail_status=$statuses[$order->order_status];
		$mailer->setSubject(JText::sprintf('PAYMENT_NOTIFICATION_FOR_ORDER','OKPay',$ok_txn_status,$dbOrder->order_number));
		$body = str_replace('<br/>',"\r\n",JText::sprintf('PAYMENT_NOTIFICATION_STATUS','OKPay',$ok_txn_status)).' '.JText::sprintf('ORDER_STATUS_CHANGED',$order->mail_status)."\r\n\r\n".$order_text;
		$mailer->setBody($body);
		$mailer->Send();
		$orderClass->save($order);
		return true;
	}

	function onPaymentConfiguration(&$element){
		$subtask = JRequest::getCmd('subtask','');
		if($subtask=='ips'){
			$ips = null;
		echo implode(',',$this->_getIPList($ips));
		exit;
		}else{
			$this->okpay = JRequest::getCmd('name','okpay');
		if(empty($element)){
			$element = new stdClass();
				$element->payment_name='OKPay';
				$element->payment_description='You can pay by OKPay using this payment method';
				$element->payment_images='';
				$element->payment_type=$this->okpay;
				$element->payment_params= new stdClass();
				$element->payment_params->url='https://www.okpay.com/process.html';
				$element->payment_params->notification=1;
				$list=null;
				$element->payment_params->ips=$this->_getIPList($list);
				$element->payment_params->details=0;
				$element->payment_params->invalid_status='cancelled';
				$element->payment_params->pending_status='created';
				$element->payment_params->verified_status='confirmed';
				$element->payment_params->address_override=1;
				$element = array($element);
			}
			$obj = reset($element);
		if(empty($obj->payment_params->walletid)){
			$app = JFactory::getApplication();
			$enqueueMessage	= 'You need to enter you OKPay Wallet ID. If you don\'t have yet an OKPay Account, You can <a href="https://www.okpay.com/en/account/signup.html" style="text-decoration: underline; font-size: 1.2em;" target="_blank">create a new account by clicking here</a>';
			$app->enqueueMessage($enqueueMessage);
		}
		$this->toolbar = array(
				'save',
				'apply',
				'cancel',
				'|',
				array('name' => 'pophelp', 'target' =>'payment-okpay-form')
			);

		hikashop_setTitle('OKPay','plugin','plugins&plugin_type=payment&task=edit&name='.$this->okpay);
		$app = JFactory::getApplication();
		$app->setUserState( HIKASHOP_COMPONENT.'.payment_plugin_type', $this->okpay);
		$this->address = hikashop_get('type.address');
		$this->category = hikashop_get('type.categorysub');
		$this->category->type = 'status';
		}
	}

	function onPaymentConfigurationSave(&$element){
		if(!empty($element->payment_params->ips)){
			$element->payment_params->ips=explode(',',$element->payment_params->ips);
		}
	return true;
	}

	function _getIPList(&$ipList){
		$okpay1 = gethostbynamel('www.okpay.com');
		$okpay2 = gethostbynamel('notify.okpay.com');
		$okpay3 = gethostbynamel('ipn.sandbox.okpay.com');
		$ipList = array();
		if(!empty($okpay1)){
			$ipList = $okpay1;
		}
		if(!empty($okpay2)){
			$ipList = array_merge($ipList,$okpay2);
		}
		if(!empty($okpay3)){
			$ipList = array_merge($ipList,$okpay3);
		}
		if(!empty($ipList)){
			$newList = array();
			foreach($ipList as $k => $ip){
				$ipParts = explode('.',$ip);
				if(count($ipParts)==4){
					array_pop($ipParts);
					$ip = implode('.',$ipParts).'.*';
				}
				if(!in_array($ip,$newList)){
					$newList[]=$ip;
				}
			}
			$ipList = $newList;
		}
		return $ipList;
	}
}
