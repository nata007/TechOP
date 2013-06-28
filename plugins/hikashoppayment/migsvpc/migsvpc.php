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
class plgHikashoppaymentMigsvpc extends JPlugin
{
	var $accepted_currencies = array('AUD');

	function onPaymentDisplay(&$order,&$methods,&$usable_methods) {
		if(!empty($methods)){
			foreach($methods as $method){
				if($method->payment_type != 'migsvpc' || !$method->enabled){
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

				if(!empty($method->payment_params->currency))
					$this->accepted_currencies = array( strtoupper($method->payment_params->currency) );

				if(!empty($order->total)){
					$currency_id = intval(@$order->total->prices[0]->price_currency_id);
					$currency = $currencyClass->getCurrencies($currency_id,$null);
					if(!empty($currency) && !in_array(@$currency[$currency_id]->currency_code,$this->accepted_currencies)) {
						return true;
					}
				}

				if(empty($method->payment_params->vpc_mode) || $method->payment_params->vpc_mode == 'dps') {
					$method->ask_cc = true;
					$method->ask_owner = false;

					if( $method->payment_params->ask_ccv || (!empty($method->payment_params->security) && !empty($method->payment_params->security_cvv)) ) {
						$method->ask_ccv = true;
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

	function onBeforeOrderCreate(&$order, &$do) {
		$app = JFactory::getApplication();
		if($app->isAdmin()) {
			return true;
		}
		if(empty($order->order_payment_method) || $order->order_payment_method != 'migsvpc') {
			return true;
		}
		if(!function_exists('curl_init')){
			$app->enqueueMessage('The MIGS payment plugin needs the CURL library installed but it seems that it is not available on your server. Please contact your web hosting to set it up.','error');
			return false;
		}
		$db = JFactory::getDBO();
		$query = 'SELECT * FROM '.hikashop_table('payment').' WHERE payment_type='.$db->Quote($order->order_payment_method);
		$db->setQuery($query);
		$paymentData = $db->loadObjectList('payment_id');
		$pluginsClass = hikashop_get('class.plugins');
		$pluginsClass->params($paymentData,'payment');
		$method =& $paymentData[$order->order_payment_id];

		if(!empty($method->payment_params->vpc_mode) && $method->payment_params->vpc_mode != 'dps')
			return true;

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

		if(!empty($method->payment_params->currency))
			$this->accepted_currencies = array( strtoupper($method->payment_params->currency) );

		ob_start();
		$dbg = '';

		$amount = round($order->cart->full_total->prices[0]->price_value_with_tax * 100);
		$order_id = uniqid('');
		$uuid = $order_id.'-1';

		$vars = array(
			'vpc_Version' => '1',
			'vpc_Command' => 'pay',
			'vpc_AccessCode' => $method->payment_params->access_code,
			'vpc_MerchTxnRef' => $uuid,
			'vpc_Merchant' => $method->payment_params->merchant_id,
			'vpc_OrderInfo' => $order_id,
			'vpc_Amount' => $amount,
			'vpc_CardNum' => $this->cc_number,
			'vpc_CardExp' => $this->cc_year.$this->cc_month
		);
		if($method->payment_params->ask_ccv) {
			$vars['vpc_CardSecurityCode'] = $this->cc_CCV;
		}

		$postdata = array();
		foreach($vars as $k => $v) {
			$postdata[] = urlencode($k).'='.urlencode($v);
		}
		$postdata = implode('&', $postdata);

		$httpsHikashop = str_replace('http://','https://', HIKASHOP_LIVE);

		$url = 'https://migs.mastercard.com.au/vpcdps';

		if(!empty($method->payment_params->url)){
			$url = rtrim($method->payment_params->url, '/');
			if(strpos($url,'http')!==false){
				$url='https://'.$url;
			}
		}

		$session = curl_init($url);
		curl_setopt($session, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($session, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($session, CURLOPT_VERBOSE, 1);
		curl_setopt($session, CURLOPT_POST, 1);
		curl_setopt($session, CURLOPT_POSTFIELDS, $postdata);
		curl_setopt($session, CURLOPT_RETURNTRANSFER, 1);

		$ret = curl_exec($session);
		$error = curl_errno($session);
		$err_msg = curl_error($session);;

		curl_close($session);

		if( !empty($ret) ) {

			if( $method->payment_params->debug ) {
				echo print_r($ret, true) . "\n\n\n";
			}

			$result = 0;
			if( strpos($ret, '&') !== false ) {
				$res = explode('&', $ret);
				$ret = array();
				foreach($res as $r) {
					list($k,$v) = explode('=',$r,2);
					$ret[urldecode($k)] = urldecode($v);
				}

				$result = 1;
				$errorMsg = '';
				if( $ret['vpc_TxnResponseCode'] == 0 || $ret['vpc_TxnResponseCode'] == '0' ) {
					$result = 2;
				} else {
					$errorMsg = $this->getResponseMessage($ret['vpc_TxnResponseCode']);
				}
				$transactionId = $ret['vpc_TransactionNo'];
				$approvalCode = $ret['vpc_AuthorizeId'];
				$responseMsg = $ret['vpc_Message'];
			}

			if( $result > 0 ) {

				if( $result == 2 ) {

					$do = true;

					$dbg .= ob_get_clean();
					if( !empty($dbg) ) $dbg .= "\r\n";
					ob_start();

					$order->history->history_reason = JText::sprintf('AUTOMATIC_PAYMENT_NOTIFICATION');
					$order->history->history_notified = 0;
					$order->history->history_amount = number_format($order->cart->full_total->prices[0]->price_value_with_tax,2,'.','') . $this->accepted_currencies[0];
					$order->history->history_payment_id = $method->payment_id;
					$order->history->history_payment_method = $method->payment_type;
					$order->history->history_data = $dbg . 'Authorization Code: ' . @$approvalCode . "\r\n" . 'Transaction ID: ' . @$transactionId;
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
					$mailer->setSubject(JText::sprintf('PAYMENT_NOTIFICATION','MIGS','Accepted'));
					$body = str_replace('<br/>',"\r\n",JText::sprintf('PAYMENT_NOTIFICATION_STATUS','MIGS','Accepted')).' '.JText::sprintf('ORDER_STATUS_CHANGED',$order->order_status)."\r\n\r\n".$order_text;
					$mailer->setBody($body);
					$mailer->Send();

				} else {
					if( !empty($responseMsg) ) {
						$app->enqueueMessage($responseMsg);
					} else {
						$app->enqueueMessage('Error');
					}
					if( !empty($errorMsg) ) {
						$app->enqueueMessage($errorMsg);
					}
					$do = false;
				}
			} else {
				$app->enqueueMessage('An error occurred.');
				$do = false;
			}
		} else {
			$do = false;
		}

		if( $error != 0 ) {
			$app->enqueueMessage('There was an error during the connection with the MIGS payment gateway');
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
		$app->setUserState( HIKASHOP_COMPONENT.'.cc_valid',0);

		return true;
	}

	function onAfterOrderConfirm(&$order,&$methods,$method_id){
		$method =& $methods[$method_id];

		if(!empty($method->payment_params->vpc_mode) && $method->payment_params->vpc_mode == 'pay') {
			return $this->onAfterOrderConfirm_VPCPAY($order, $methods, $method_id);
		}

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

	function onAfterOrderConfirm_VPCPAY(&$order,&$methods,$method_id){
		$method =& $methods[$method_id];

		$amount = round($order->cart->full_total->prices[0]->price_value_with_tax * 100);
		$uuid = $order->order_id.'-'.uniqid('');

		$lang = JFactory::getLanguage();
		$locale = strtolower(substr($lang->get('tag'), 0, 2));
		global $Itemid;
		$url_itemid = '';
		if(!empty($Itemid))
			$url_itemid='&Itemid='.$Itemid;
		$return_url = HIKASHOP_LIVE.'migsvpc_return.php';

		if(empty($method->payment_params->locale))
			$method->payment_params->locale = 'en';

		$this->vars = array(
			'vpc_Version' => '1',
			'vpc_Command' => 'pay',
			'vpc_MerchTxnRef' => $uuid,
			'vpc_AccessCode' => $method->payment_params->access_code,
			'vpc_Merchant' => $method->payment_params->merchant_id,
			'vpc_OrderInfo' => $order->order_id,
			'vpc_Locale' => $method->payment_params->locale,
			'vpc_Amount' => $amount,
			'vpc_ReturnURL' => $return_url,
		);

		ksort($this->vars);
		$this->vars['vpc_SecureHash'] = md5($method->payment_params->secure_secret . implode('', $this->vars));

		foreach($this->vars as $key => &$var) {
			$var = $key . '=' . urlencode($var);
		}
		unset($var);

		if(empty($method->payment_params->url))
			$method->payment_params->url = 'https://migs.mastercard.com.au/vpcpay';

		$app = JFactory::getApplication();
		$app->redirect($method->payment_params->url . '?' . implode('&', $this->vars));
	}

	function onPaymentNotification(&$statuses){
		$pluginsClass = hikashop_get('class.plugins');
		$elements = $pluginsClass->getMethods('payment','migsvpc');
		if(empty($elements)) return false;

		$method = reset($elements);

		if(empty($method->payment_params->vpc_mode) || $method->payment_params->vpc_mode != 'pay') {
			return false;
		}

		$app = JFactory::getApplication();

		$vars = array();
		$filter = JFilterInput::getInstance();
		foreach($_REQUEST as $key => $value) {
			$key = $filter->clean($key);
			if(substr($key, 0, 4) == 'vpc_') {
				$value = JRequest::getString($key);
				$vars[$key] = $value;
			}
		}

		$return_hash = $vars['vpc_SecureHash'];
		unset($vars['vpc_SecureHash']);

		ksort($vars);
		$hash = $method->payment_params->secure_secret;
		foreach($vars as $var) {
			$hash .= $var;
		}

		if(strtolower($return_hash) != strtolower(md5($hash))) {
			echo 'Invalid hash';
			return false;
		}

		$orderId = (int)$vars['vpc_OrderInfo'];
		$orderClass = hikashop_get('class.order');
		$dbOrder = $orderClass->get($orderId);
		if(empty($dbOrder)) {
			echo 'Could not load any order for your notification '.$orderId;
			return false;
		}

		$order = new stdClass();
		$order->order_id = $dbOrder->order_id;
		$order->old_status = new stdClass();
		$order->old_status->order_status=$dbOrder->order_status;

		$return_url = hikashop_completeLink('checkout&task=after_end&order_id='.$order->order_id.$url_itemid);
		$cancel_url = hikashop_completeLink('order&task=cancel_order&order_id='.$order->order_id.$url_itemid);

		$url = HIKASHOP_LIVE.'administrator/index.php?option=com_hikashop&ctrl=order&task=edit&order_id='.$order->order_id.$url_itemid;
		$order_text = "\r\n".JText::sprintf('NOTIFICATION_OF_ORDER_ON_WEBSITE',$dbOrder->order_number,HIKASHOP_LIVE);
		$order_text .= "\r\n".str_replace('<br/>',"\r\n",JText::sprintf('ACCESS_ORDER_WITH_LINK',$url));

		$order->history->history_reason=JText::sprintf('AUTOMATIC_PAYMENT_NOTIFICATION');
		$order->history->history_notified = 0;
		$order->history->history_amount = $vars['vpc_Amount'];
		$order->history->history_payment_id = $method->payment_id;
		$order->history->history_payment_method = $method->payment_type;
		$order->history->history_data = $vars['vpc_TransactionNo'] . "\r\n\r\n" . ob_get_clean();
		$order->history->history_type = 'payment';

		$mailer = JFactory::getMailer();
		$config =& hikashop_config();
		$sender = array(
			$config->get('from_email'),
			$config->get('from_name')
		);
		$mailer->setSender($sender);
		$mailer->addRecipient(explode(',',$config->get('payment_notification_email')));

		$currencyClass = hikashop_get('class.currency');
		$currencies = null;
		$currencies = $currencyClass->getCurrencies($dbOrder->order_currency_id, $currencies);
		$currency = $currencies[$dbOrder->order_currency_id];

		$orderPrice = round($dbOrder->order_full_price * 100);

		if($orderPrice != $vars['vpc_Amount']) {
			$order->order_status = $element->payment_params->invalid_status;
			$orderClass->save($order);

			$mailer->setSubject(JText::sprintf('NOTIFICATION_REFUSED_FOR_THE_ORDER','MIGS').JText::_('INVALID_AMOUNT'));
			$body = str_replace('<br/>',"\r\n",JText::sprintf('AMOUNT_RECEIVED_DIFFERENT_FROM_ORDER','MIGS', $order->history->history_amount, $orderPrice . $currency->currency_code))."\r\n\r\n".$order_text;
			$mailer->setBody($body);
			$mailer->Send();

			$app->enqueueMessage(JText::sprintf('NOTIFICATION_REFUSED_FOR_THE_ORDER','MIGS').JText::_('INVALID_AMOUNT'));
			$app->redirect($cancel_url);
		}

		$completed = ($vars['vpc_TxnResponseCode'] == '0');

		$redirect_to = $return_url;
		if($completed) {
			$order->order_status = $method->payment_params->verified_status;
			$order->history->history_notified = 1;
			$payment_status = 'confirmed';
		} else {
			$order->order_status = $method->payment_params->invalid_status;
			$payment_status = 'cancelled';

			$app->enqueueMessage(JText::sprintf('NOTIFICATION_REFUSED_FOR_THE_ORDER','MIGS'));
			$redirect_to = $cancel_url;
		}
		$order->mail_status = $statuses[$order->order_status];
		$mailer->setSubject(JText::sprintf('PAYMENT_NOTIFICATION_FOR_ORDER', 'MIGS', $payment_status, $dbOrder->order_number));
		$body = str_replace('<br/>',"\r\n",JText::sprintf('PAYMENT_NOTIFICATION_STATUS','MIGS',$payment_status)).' '.JText::sprintf('ORDER_STATUS_CHANGED',$order->mail_status)."\r\n\r\n".$order_text;
		$mailer->setBody($body);
		$mailer->Send();
		$orderClass->save($order);

		if(@$method->payment_params->ticket_info){
			$key = 'TICKET_INFO';
			if(JText::_($key) != $key) {
				$text = JText::sprintf($key, $vars['vpc_AuthorizeId'], $currencyClass->format($dbOrder->order_full_price,$dbOrder->order_currency_id));
			}else{
				$text = sprintf('Your authorization number is %s for the payment of %s.', $vars['vpc_AuthorizeId'], $currencyClass->format($dbOrder->order_full_price,$dbOrder->order_currency_id));
			}
			$app->enqueueMessage($text);

		}

		$app->redirect($redirect_to);
	}

	function onPaymentConfiguration(&$element){
		$this->migsvpc = JRequest::getCmd('name','migsvpc');
		if(empty($element)){
			$element = new stdClass();
			$element->payment_name='MIGSVPC';
			$element->payment_description='You can pay by credit card using this payment method';
			$element->payment_images='MasterCard,VISA,Credit_card,American_Express';
			$element->payment_type = $this->migsvpc;
			$element->payment_params= new stdClass();
			$element->payment_params->merchant_id = '';
			$element->payment_params->access_code = '';
			$element->payment_params->ask_ccv = true;
			$element->payment_params->pending_status='created';
			$element->payment_params->verified_status='confirmed';
			$element = array($element);
		}
		$this->toolbar = array(
			'save',
			'apply',
			'cancel',
			'|',
			array('name' => 'pophelp', 'target' =>'payment-migsvpc-form')
		);

		hikashop_setTitle('MIGS','plugin','plugins&plugin_type=payment&task=edit&name='.$this->migsvpc);
		$app = JFactory::getApplication();
		$app->setUserState( HIKASHOP_COMPONENT.'.payment_plugin_type', $this->migsvpc);
		$this->address = hikashop_get('type.address');
		$this->category = hikashop_get('type.categorysub');
		$this->category->type = 'status';
	}

	function onPaymentConfigurationSave(&$element){
		$app = JFactory::getApplication();
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.path');
		$lang = JFactory::getLanguage();
		$locale = strtolower(substr($lang->get('tag'),0,2));

		$migsvpc_file='<?php
	$_GET[\'option\']=\'com_hikashop\';
	$_GET[\'tmpl\']=\'component\';
	$_GET[\'ctrl\']=\'checkout\';
	$_GET[\'task\']=\'notify\';
	$_GET[\'notif_payment\']=\'migsvpc\';
	$_GET[\'format\']=\'html\';
	$_GET[\'lang\']=\''.$locale.'\';
	$_REQUEST[\'option\']=\'com_hikashop\';
	$_REQUEST[\'tmpl\']=\'component\';
	$_REQUEST[\'ctrl\']=\'checkout\';
	$_REQUEST[\'task\']=\'notify\';
	$_REQUEST[\'notif_payment\']=\'migsvpc\';
	$_REQUEST[\'format\']=\'html\';
	$_REQUEST[\'lang\']=\''.$locale.'\';
	include(\'index.php\');
';
		JFile::write(JPATH_ROOT.DS.'migsvpc_return.php', $migsvpc_file);

		return true;
	}

	function getResponseMessage($code) {
		switch ($code) {
			case '0': return 'Transaction Successful';
			case '?': return 'Transaction status is unknown';
			case '1': return 'Unknown Error';
			case '2': return 'Bank Declined Transaction';
			case '3': return 'No Reply from Bank';
			case '4': return 'Expired Card';
			case '5': return 'Insufficient funds';
			case '6': return 'Error Communicating with Bank';
			case '7': return 'Payment Server System Error';
			case '8': return 'Transaction Type Not Supported';
			case '9': return 'Bank declined transaction (Do not contact Bank)';
			case 'A': return 'Transaction Aborted';
			case 'C': return 'Transaction Cancelled';
			case 'D': return 'Deferred transaction has been received and is awaiting processing';
			case 'F': return '3D Secure Authentication failed';
			case 'I': return 'Card Security Code verification failed';
			case 'L': return 'Shopping Transaction Locked (Please try the transaction again later)';
			case 'N': return 'Cardholder is not enrolled in Authentication scheme';
			case 'P': return 'Transaction has been received by the Payment Adaptor and is being processed';
			case 'R': return 'Transaction was not processed - Reached limit of retry attempts allowed';
			case 'S': return 'Duplicate SessionID (OrderInfo)';
			case 'T': return 'Address Verification Failed';
			case 'U': return 'Card Security Code Failed';
			case 'V': return 'Address Verification and Card Security Code Failed';
		}
		return 'Unable to be determined';
	}
}
