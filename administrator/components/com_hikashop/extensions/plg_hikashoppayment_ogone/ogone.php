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
class plgHikashoppaymentOgone extends JPlugin
{
	var $debugData = array();
	function onPaymentDisplay(&$order,&$methods,&$usable_methods){
		if(!empty($methods)){
			foreach($methods as $method){
			if($method->payment_type!='ogone' || !$method->enabled){
				continue;
			}
			if(!empty($method->payment_zone_namekey)){
				$zoneClass=hikashop_get('class.zone');
					$zones = $zoneClass->getOrderZones($order);
				if(!in_array($method->payment_zone_namekey,$zones)){
					return true;
				}
			}
			$usable_methods[$method->ordering]=$method;
			}
		}
		return true;
	}
	function onPaymentSave(&$cart,&$rates,&$payment_id){
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
	function onAfterOrderConfirm(&$order,&$methods,$method_id){
		$method =& $methods[$method_id];
		$tax_total = '';
		$discount_total = '';
		$currencyClass = hikashop_get('class.currency');
		$currencies=null;
		$currencies = $currencyClass->getCurrencies($order->order_currency_id,$currencies);
		$currency=$currencies[$order->order_currency_id];
		hikashop_loadUser(true,true); //reset user data in case the emails were changed in the email code
		$user = hikashop_loadUser(true);
		$lang = JFactory::getLanguage();
		$locale=strtolower(substr($lang->get('tag'),0,2));
		global $Itemid;
		$url_itemid='';
		if(!empty($Itemid)){
			$url_itemid='&Itemid='.$Itemid;
		}
		$notify_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment=paypal&tmpl=component&lang='.$locale.$url_itemid;
		$return_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=after_end&order_id='.$order->order_id.$url_itemid;
		$cancel_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=order&task=cancel_order&order_id='.$order->order_id.$url_itemid;

		$language = str_replace('-','_',$lang->get('tag'));
		$language_codes = array(
			'ar_AR',
			'cs_CZ',
			'zh_CN',
			'da_DK',
			'nl_BE',
			'nl_NL',
			'en_GB',
			'en_US',
			'fr_FR',
			'de_DE',
			'el_GR',
			'hu_HU',
			'it_IT',
			'ja_JP',
			'no_NO',
			'pl_PL',
			'pt_PT',
			'ru_RU',
			'sk_SK',
			'es_ES',
			'se_SE',
			'tr_TR',
		);

		if(!in_array($language,$language_codes)){
			$language = 'en_US';
		}
		$vars = array(
		"PSPID" => $method->payment_params->pspid,
		"orderID" => @$order->order_id,
		"amount" => 100 * round(@$order->cart->full_total->prices[0]->price_value_with_tax,2),
		"currency" => $currency->currency_code,
		"language" => $language,
		"EMAIL" => $user->user_email,
		"accepturl"=>$return_url,
		"declineurl"=>$cancel_url,
		"exceptionurl"=>$cancel_url,
		"cancelurl"=>$cancel_url,
		);

		$app = JFactory::getApplication();
		$address=$app->getUserState( HIKASHOP_COMPONENT.'.billing_address');
		if(!empty($address)){
			$cart = hikashop_get('class.cart');
			$cart->loadAddress($order->cart,$address,'object','billing');
			$vars["owneraddress"]=@$order->cart->billing_address->address_street;
			$vars["ownerZIP"]=substr(@$order->cart->billing_address->address_post_code,0,10);
			$vars["ownertown"]=@$order->cart->billing_address->address_city;
			$vars["ownercty"]=@$order->cart->billing_address->address_country->zone_code_2;
			$vars["CN"]=@$order->cart->billing_address->address_firstname." ".@$order->cart->billing_address->address_lastname;
			$vars["ownertelno"]=@$order->cart->billing_address->address_telephone;
		}

		$vars["SHASign"]=$this->generateHash($vars,$method->payment_params->shain_passphrase,$method->payment_params->hash_method);

		if($method->payment_params->environnement=='test'){
			$method->payment_params->url='https://secure.ogone.com/ncol/test/orderstandard_utf8.asp';
		}else{
			$method->payment_params->url='https://secure.ogone.com/ncol/prod/orderstandard_utf8.asp';
		}


		if(!HIKASHOP_J30)
			JHTML::_('behavior.mootools');
		else
			JHTML::_('behavior.framework');
		$app = JFactory::getApplication();
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

	function generateHash($vars,$passphrase,$hash_method,$type='in'){
		uksort($vars, 'strnatcasecmp');
		$key = '';
		foreach($vars as $k => $v){
			if(strlen($v) && !in_array(strtoupper($k),array('SHASIGN','OPTION','CTRL','TASK','NOTIF_PAYMENT','TMPL','ITEMID','HIKASHOP_FRONT_END_MAIN','VIEW','LANG'))){
				if($type=='out' && strtoupper($k)=='LANGUAGE') continue;
				$key.=strtoupper($k).'='.$v.$passphrase;
			}
		}
		return strtoupper(hash($hash_method,$key));
	}

	function onPaymentNotification(&$statuses){
		$pluginsClass = hikashop_get('class.plugins');
		$elements = $pluginsClass->getMethods('payment','ogone');
		if(empty($elements)) return false;
		$element = reset($elements);

		$_REQUEST['GENERATEDHASH'] = $this->generateHash($_REQUEST,$element->payment_params->shaout_passphrase,$element->payment_params->hash_method,'out');
		$vars = array();
		foreach($_REQUEST as $k => $v){
			$vars[strtoupper($k)]=$v;
		}

		if($element->payment_params->debug){
			echo print_r($vars,true)."\n\n\n";
		}
		global $Itemid;
		$url_itemid='';
		if(!empty($Itemid)){
			$url_itemid='&Itemid='.$Itemid;
		}

		$app = JFactory::getApplication();

		$orderClass = hikashop_get('class.order');
		$dbOrder = $orderClass->get((int)@$vars['ORDERID']);
		if(empty($dbOrder)){
			echo "Could not load any order for your notification ".@$vars['ORDERID'];
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

		$invalid = false;
		$waiting = false;
		switch(substr($_REQUEST['STATUS'],0,1)){
			case '0':
			case '1':
			case '2':
			case '4':
			case '6':
			case '7':
			case '8':
				$invalid = true;
				break;
			case '5':
			case '9':
				if(in_array($_REQUEST['STATUS'],array('52','92','93'))){
					$invalid = true;
				}
				if(in_array($_REQUEST['STATUS'],array('51','55','59','99','91'))){
					$waiting = true;
				}
				break;
		}

		if($invalid || $_REQUEST['GENERATEDHASH']!=$_REQUEST['SHASIGN'] || empty($_REQUEST['SHASIGN'])){
			$mailer->setSubject(JText::sprintf('NOTIFICATION_REFUSED_FOR_THE_ORDER','Ogone').'invalid transaction');
			if($_REQUEST['GENERATEDHASH']!=$_REQUEST['SHASIGN']){
				$order_text=' The Hashs didn\'t match. Received: '.$_REQUEST['SHASIGN']. ' and generated: '.$_REQUEST['GENERATEDHASH']."\n\n\n"."\n\n\n".ob_get_clean()."\n\n\n"."\n\n\n".$order_text;
				ob_start();
			}
			$body = JText::sprintf("Hello,\r\n An Ogone payment notification was not validated. The status code was :".$_REQUEST['STATUS']).$order_text;
			$mailer->setBody($body);
			$mailer->Send();
			if($element->payment_params->debug){
				echo 'invalid transaction'."\n\n\n";
			}

			$cancel_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=order&task=cancel_order&order_id='.$order->order_id.$url_itemid;
			$app->redirect($cancel_url);
			return true;
		}

		$order->history->history_reason=JText::sprintf('AUTOMATIC_PAYMENT_NOTIFICATION');
		$order->history->history_notified=0;
		$order->history->history_payment_id = $element->payment_id;
		$order->history->history_payment_method =$element->payment_type;
		$order->history->history_data = ob_get_clean();
		$order->history->history_type = 'payment';

	 	if(!$waiting){
	 		$order->order_status = $element->payment_params->verified_status;
	 	}else{
	 		$order->order_status = $element->payment_params->pending_status;
	 	}
	 	$config =& hikashop_config();
		if($config->get('order_confirmed_status','confirmed')==$order->order_status){
			$order->history->history_notified=1;
		}
	 	$order->mail_status=$statuses[$order->order_status];
	 	$mailer->setSubject(JText::sprintf('PAYMENT_NOTIFICATION_FOR_ORDER','Ogone',$_REQUEST['STATUS'],$dbOrder->order_number));
		$body = str_replace('<br/>',"\r\n",JText::sprintf('PAYMENT_NOTIFICATION_STATUS','Ogone',$_REQUEST['STATUS'])).' '.JText::sprintf('ORDER_STATUS_CHANGED',$order->mail_status)."\r\n\r\n".$order_text;
		$mailer->setBody($body);
		$mailer->Send();
	 	$orderClass->save($order);
		$return_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=after_end&order_id='.$order->order_id.$url_itemid;
		$app->redirect($return_url);
		return true;
	}

	function onPaymentConfiguration(&$element){
		$this->ogone = JRequest::getCmd('name','ogone');
		if(empty($element)){
			$element = new stdClass();
			$element->payment_name='Ogone';
			$element->payment_description='You can pay by credit card using this payment method';
			$element->payment_images='MasterCard,VISA,American_Express';
			$element->payment_type='ogone';
			$element->payment_params= new stdClass();
			$element->payment_params->notification=1;
			$element->payment_params->details=0;
			$element->payment_params->invalid_status='created';
			$element->payment_params->pending_status='created';
			$element->payment_params->verified_status='confirmed';
			$element->payment_params->address_override=1;
			$element = array($element);
		}
		$obj = reset($element);
		$lang = JFactory::getLanguage();
		$locale=strtolower(substr($lang->get('tag'),0,2));

		if(empty($obj->payment_params->pspid)){
			$app = JFactory::getApplication();
			$app->enqueueMessage(JText::sprintf('ENTER_INFO_REGISTER_IF_NEEDED','Ogone','PSPID','Ogone','http://www.ogone.com/en/sitecore/Content/COM/Web/Solutions/Payment%20Processing/eCommerce.aspx'));
		}
		$this->toolbar = array(
			'save',
			'apply',
			'cancel',
			'|',
			array('name' => 'pophelp', 'target' =>'payment-ogone-form')
		);

		$obj->payment_params->status_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment=ogone&tmpl=component&lang='.strtolower($locale);

		hikashop_setTitle('Ogone','plugin','plugins&plugin_type=payment&task=edit&name='.$this->ogone);
		$app = JFactory::getApplication();
		$app->setUserState( HIKASHOP_COMPONENT.'.payment_plugin_type', $this->ogone);
		$this->address = hikashop_get('type.address');
		$this->category = hikashop_get('type.categorysub');
		$this->category->type = 'status';
	}

	function onPaymentConfigurationSave(&$element){
		return true;
	}
}
