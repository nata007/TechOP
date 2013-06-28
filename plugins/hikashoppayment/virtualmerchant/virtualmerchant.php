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
class plgHikashoppaymentVirtualmerchant extends JPlugin
{
	function onPaymentDisplay(&$order,&$methods,&$usable_methods) {
		if(!empty($methods)){
			foreach($methods as $method){
				if($method->payment_type!='virtualmerchant' || !$method->enabled){
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
					if(!empty($currency) && !empty($method->payment_params->currency) && @$currency[$currency_id]->currency_code != $method->payment_params->currency) {
						return true;
					}
				}
				$method->ask_cc = true;
				if( $method->payment_params->ask_ccv ) {
					$method->ask_ccv = true;
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

	function onBeforeOrderCreate(&$order, &$do) {
		$app =& JFactory::getApplication();
		if($app->isAdmin()) {
			return true;
		}
		if(empty($order->order_payment_method) || $order->order_payment_method != 'virtualmerchant') {
			return true;
		}
		if(!function_exists('curl_init')){
			$app->enqueueMessage('The Virtual Merchant payment plugin needs the CURL library installed but it seems that it is not available on your server. Please contact your web hosting to set it up.','error');
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
		if( $method->payment_params->ask_ccv ) {
			$this->cc_CCV = $app->getUserState( HIKASHOP_COMPONENT.'.cc_CCV');
			if(!empty($this->cc_CCV)){
				$this->cc_CCV = base64_decode($this->cc_CCV);
			}
		} else {
			$this->cc_CCV = '';
		}

		$address = $app->getUserState( HIKASHOP_COMPONENT.'.billing_address');
		$address_type = 'billing_address';
		$cart = hikashop_get('class.cart');
		$cart->loadAddress($order->cart,$address,'object','billing');

		$amount = number_format($order->cart->full_total->prices[0]->price_value_with_tax,2,'.','');

		$vars = '<txn>'.
			'<ssl_merchant_ID>'.$method->payment_params->merchant_id.'</ssl_merchant_ID>'.
			'<ssl_user_id>'.$method->payment_params->user_id.'</ssl_user_id>'.
			'<ssl_pin>'.$method->payment_params->pin.'</ssl_pin>'.
			'<ssl_test_mode>'.(($method->payment_params->debug)?'True':'False').'</ssl_test_mode>'.
			'<ssl_transaction_type>CCSALE</ssl_transaction_type>'.
			'<ssl_show_form >False</ssl_show_form >'.
			'<ssl_card_number>'.str_replace(array('<','>'),array('&lt;','&gt;'),$this->cc_number).'</ssl_card_number>'.
			'<ssl_exp_date>'.$this->cc_month.$this->cc_year.'</ssl_exp_date>'.
			'<ssl_amount>'.$amount.'</ssl_amount>'.
			'<ssl_salestax>0.00</ssl_salestax>'.
			'<ssl_cvv2cvc2_indicator>'.(($method->payment_params->ask_ccv)?'1':'0').'</ssl_cvv2cvc2_indicator>'.
			'<ssl_cvv2cvc2>'.str_replace(array('<','>'),array('&lt;','&gt;'),$this->cc_CCV).'</ssl_cvv2cvc2>'.
			'<ssl_customer_code>'.$user->user_id.'</ssl_customer_code>'.
			'<ssl_first_name>'.str_replace(array('<','>'),array('&lt;','&gt;'),$order->cart->$address_type->address_firstname).'</ssl_first_name>'.
			'<ssl_last_name>'.str_replace(array('<','>'),array('&lt;','&gt;'),$order->cart->$address_type->address_lastname).'</ssl_last_name>';

		if($method->payment_params->use_avs) {
			$addr1 = @$order->cart->$address_type->address_street;
			if(strlen(urlencode($addr1)) > 20) {
				$vars .= '<ssl_avs_address>'.urlencode(substr($addr1,0,20)).'</ssl_avs_address>'.
					'<ssl_address2>'.urlencode(substr($addr1,20,30)).'</ssl_address2>';
			} else {
				$vars .= '<ssl_avs_address>'.urlencode($addr1).'</ssl_avs_address>';
			}
			$vars .= '<ssl_city>'.urlencode(@$order->cart->$address_type->address_city).'</ssl_city>'.
				'<ssl_state>'.urlencode(@$order->cart->$address_type->address_state->zone_name).'</ssl_state>'.
				'<ssl_avs_zip>'.urlencode(@$order->cart->$address_type->address_post_code).'</ssl_avs_zip>'.
				'<ssl_country>'.urlencode(@$order->cart->$address_type->address_country->zone_name_english).'</ssl_country>';
		}

		$vars .= '<ssl_email>'.str_replace(array('<','>'),array('&lt;','&gt;'),$user->user_email).'</ssl_email>'.
		'</txn>';

		if( $method->payment_params->debug ) {
			echo str_replace(
					array($this->cc_number, $this->cc_CCV),
					array('**************', '***'),
				$vars) . "\n\n\n";
		}

		$session = curl_init();
		curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($session, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($session, CURLOPT_POST,           1);
		curl_setopt($session, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($session, CURLOPT_VERBOSE,        1);
		curl_setopt($session, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($session, CURLOPT_FAILONERROR,    true);

		$httpsHikashop = str_replace('http://','https://', HIKASHOP_LIVE);
		if($method->payment_params->debug) {
			$url = 'demo.myvirtualmerchant.com/VirtualMerchantDemo/processxml.do';
		} else {
			$url = 'www.myvirtualmerchant.com/VirtualMerchant/processxml.do';
		}

		curl_setopt($session, CURLOPT_URL, 'https://' . $url);
		curl_setopt($session, CURLOPT_REFERER, $httpsHikashop);
		curl_setopt($session, CURLOPT_POSTFIELDS, 'xmldata=' . urlencode($vars) );

		$ret = curl_exec($session);
		$error = curl_errno($session);

		curl_close($session);

		if( !$error ) {


			$p0 = strpos($ret,'<txn>');
			if($p0 !== false ) { $ret = substr($ret, $p0); }
			$data = str_replace(array('<txn>','</txn>'), '', trim($ret));
			$ret = array();
			while ($data) {
				$p0 = strpos($data, '<');
				$p1 = strpos($data, '>');
				if($p0 === false || $p1 === false) {
					break;
				}
				$key = substr($data, $p0+1, $p1-1);
				$data = substr($data, $p0+1);
				if(substr($key,-1) == '/') {
					$ret[$key] = '';
				} else {
					$l = strlen($key);
					$p1 = strpos($data, '</'.$key.'>');
					if($p1 !== false) {
						$ret[$key] = substr($data, $l+1, $p1-$l-1);
						$data = substr($data, $p1+$l+3);
					}
				}
			}

			if( $method->payment_params->debug ) {
				echo print_r($ret, true)."\n\n\n";
			}

			if( isset($ret['ssl_result']) ) {

				if( $ret['ssl_result'] == '0' ) {

					$dbg .= ob_get_clean();
					if( !empty($dbg) ) $dbg .= "\r\n";
					ob_start();

					$order->history->history_reason = JText::sprintf('AUTOMATIC_PAYMENT_NOTIFICATION');
					$order->history->history_notified = 0;
					$order->history->history_amount = $amount . $method->payment_params->currency;
					$order->history->history_payment_id = $method->payment_id;
					$order->history->history_payment_method = $method->payment_type;
					$order->history->history_data = $dbg . 'Authorization Code: ' . $ret['ssl_approval_code'] . "\r\n" . 'Transaction ID: ' . $ret['ssl_txn_id'];
					$order->history->history_type = 'payment';
					$order->order_status = $method->payment_params->verified_status;

					$mailer = JFactory::getMailer();
					$config =& hikashop_config();
					$sender = array(
						$config->get('from_email'),
						$config->get('from_name')
					);
					$mailer->setSender($sender);
					$mailer->addRecipient(explode(',',$config->get('payment_notification_email')));
					$url = HIKASHOP_LIVE.'administrator/index.php?option=com_hikashop&ctrl=order&task=listing';
					$order_text = "\r\n".JText::sprintf('NOTIFICATION_OF_ORDER_ON_WEBSITE','',HIKASHOP_LIVE);
					$order_text .= "\r\n".str_replace('<br/>',"\r\n",JText::sprintf('ACCESS_ORDER_WITH_LINK',$url));
					$mailer->setSubject(JText::sprintf('PAYMENT_NOTIFICATION','VirtualMerchant','Accepted'));
					$body = str_replace('<br/>',"\r\n",JText::sprintf('PAYMENT_NOTIFICATION_STATUS','VirtualMerchant','Accepted')).' '.JText::sprintf('ORDER_STATUS_CHANGED',$order->order_status)."\r\n\r\n".$order_text;
					$mailer->setBody($body);
					$mailer->Send();

				} else {
					$app->enqueueMessage('Error Code #' . $ret['errorCode'] . ': ' . $ret['errorMessage']);
					$do = false;
				}
			} else {
				$app->enqueueMessage('An error occurred.');
				$do = false;
			}
		} else {
			$app->enqueueMessage('An error occurred.');
			$do = false;
		}

		$app->setUserState( HIKASHOP_COMPONENT.'.cc_number','');
		$app->setUserState( HIKASHOP_COMPONENT.'.cc_month','');
		$app->setUserState( HIKASHOP_COMPONENT.'.cc_year','');
		$app->setUserState( HIKASHOP_COMPONENT.'.cc_CCV','');
		$app->setUserState( HIKASHOP_COMPONENT.'.cc_valid',0);

		return true;
	}

	function onAfterOrderConfirm(&$order,&$methods,$method_id){
		$method =& $methods[$method_id];
		$this->removeCart = true;
		if(!HIKASHOP_J30)
			JHTML::_('behavior.mootools');
		else
			JHTML::_('behavior.framework');
		$app =& JFactory::getApplication();
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

	function writeToLog($data) {
		if( $data === null ) {
			$dbg .= ob_get_clean();
		} else {
			$dbg = $data;
		}
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
		if( $data === null ) {
			ob_start();
		}
	}

	function onPaymentConfiguration(&$element){
		$this->virtualmerchant = JRequest::getCmd('name','virtualmerchant');
		if(empty($element)){
			$element = new stdClass();
			$element->payment_name='VirtualMerchant';
			$element->payment_description='You can pay by credit card using this payment method';
			$element->payment_images='MasterCard,VISA,Credit_card,American_Express';
			$element->payment_type=$this->virtualmerchant;
			$element->payment_params=new stdClass();
			$element->payment_params->invalid_status='cancelled';
			$element->payment_params->pending_status='created';
			$element->payment_params->verified_status='confirmed';
			$element = array($element);
		}
		$this->toolbar = array(
				'save',
				'apply',
				'cancel',
				'|',
				array('name' => 'pophelp', 'target' =>'payment-virtualmerchant-form')
			);
		hikashop_setTitle('VirtualMerchant','plugin','plugins&plugin_type=payment&task=edit&name='.$this->virtualmerchant);
		$app = JFactory::getApplication();
		$app->setUserState( HIKASHOP_COMPONENT.'.payment_plugin_type', $this->virtualmerchant);
		$this->address = hikashop_get('type.address');
		$this->category = hikashop_get('type.categorysub');
		$this->category->type = 'status';
	}

	function onPaymentConfigurationSave(&$element){
		return true;
	}
}
