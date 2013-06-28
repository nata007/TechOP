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
class plgHikashoppaymentEselect extends JPlugin {
	var $accepted_currencies = array( 'CAD' );

	function onPaymentDisplay(&$order,&$methods,&$usable_methods) {
		if(!empty($methods)){
			foreach($methods as $method){
				if($method->payment_type!='eselect' || !$method->enabled){
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
				$method->ask_cc = true;
				$method->ask_owner = true;

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
		$app = JFactory::getApplication();
		if($app->isAdmin()) {
			return true;
		}
		if($order->order_payment_method != 'eselect') {
			return true;
		}
		if(!function_exists('curl_init')){
			$app->enqueueMessage('The eSelect payment plugin needs the CURL library installed but it seems that it is not available on your server. Please contact your web hosting to set it up.', 'error');
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
		if(!empty($this->cc_number)){ $this->cc_number = base64_decode($this->cc_number); }

		$this->cc_month = $app->getUserState( HIKASHOP_COMPONENT.'.cc_month');
		if(!empty($this->cc_month)){ $this->cc_month = base64_decode($this->cc_month); }

		$this->cc_year = $app->getUserState( HIKASHOP_COMPONENT.'.cc_year');
		if(!empty($this->cc_year)){ $this->cc_year = base64_decode($this->cc_year); }

		$this->cc_owner = $app->getUserState( HIKASHOP_COMPONENT.'.cc_owner');
		if(!empty($this->cc_owner)){ $this->cc_owner = base64_decode($this->cc_owner); }

		$this->cc_CCV = '';
		if( $method->payment_params->ask_ccv ) {
			$this->cc_CCV = $app->getUserState( HIKASHOP_COMPONENT.'.cc_CCV');
			if(!empty($this->cc_CCV)){ $this->cc_CCV = base64_decode($this->cc_CCV); }
		}

		ob_start();
		$dbg = '';

		$address = $app->getUserState( HIKASHOP_COMPONENT.'.billing_address');
		$address_type = 'billing_address';
		$cart = hikashop_get('class.cart');
		$cart->loadAddress($order->cart,$address,'object','billing');

		$amount = number_format($order->cart->full_total->prices[0]->price_value_with_tax,2,'.','');

		require_once dirname(__FILE__) . DS . 'eselect_lib.php';

		$txnArray = array(
			'type' => 'purchase',
			'order_id' => uniqid(),
			'cust_id' => $user->user_id,
			'amount' => $amount,
			'pan' => $this->cc_number,
			'expdate' => $this->cc_month . $this->cc_year,
			'crypt_type' => '7', // SSL enabled merchant
			'dynamic_descriptor' => ''
		);

		$mpgTxn = new mpgTransaction($txnArray); 

		if($method->payment_params->ask_ccv) {
			$cvdTemplate = array(
				'cvd_indicator' => 1, 
				'cvd_value' => $this->cc_CCV
			); 
			$mpgCvdInfo = new mpgCvdInfo($cvdTemplate);

			$mpgTxn->setCvdInfo($mpgCvdInfo); 
		}

		$mpgRequest = new mpgRequest($mpgTxn);
		$mpgHttpPost = new mpgHttpsPost($method->payment_params->store_id, $method->payment_params->api_token, $mpgRequest, (int)$method->payment_params->debug != 0);
		$mpgResponse = $mpgHttpPost->getMpgResponse();

		$ret = $mpgResponse->getResponseCode();

		if($ret !== null && $ret != 'null') {
			$ret = (int)$ret;
			if( $ret < 50 && $mpgResponse->getComplete() == 'true') {

				ob_get_clean();
				ob_start();

				$order->history->history_reason = JText::sprintf('AUTOMATIC_PAYMENT_NOTIFICATION');
				$order->history->history_notified = 0;
				$order->history->history_amount = $amount . $this->accepted_currencies[0];
				$order->history->history_payment_id = $method->payment_id;
				$order->history->history_payment_method = $method->payment_type;
				$order->history->history_data = $dbg . 'Authorization Code: ' . $mpgResponse->getAuthCode() . "\r\n" . 'Transaction ID: ' . $mpgResponse->getReferenceNum();
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
				$mailer->setSubject(JText::sprintf('PAYMENT_NOTIFICATION','eSelect','Accepted'));
				$body = str_replace('<br/>',"\r\n",JText::sprintf('PAYMENT_NOTIFICATION_STATUS','eSelect','Accepted')).' '.JText::sprintf('ORDER_STATUS_CHANGED',$order->order_status)."\r\n\r\n".$order_text;
				$mailer->setBody($body);
				$mailer->Send();
			} else {
				$responseMsg = $mpgResponse->getMessage();
				if( isset($responseMsg) ) {
					$app->enqueueMessage($responseMsg);
				} else {
					$app->enqueueMessage('Error');
				}
				$do = false;
			}
		} else {
			$do = false;
		}

		if( $do == false ) {
			return true;
		}

		$app->setUserState( HIKASHOP_COMPONENT.'.cc_number','');
		$app->setUserState( HIKASHOP_COMPONENT.'.cc_month','');
		$app->setUserState( HIKASHOP_COMPONENT.'.cc_year','');
		$app->setUserState( HIKASHOP_COMPONENT.'.cc_CCV','');
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
		$app = JFactory::getApplication();
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
		$this->eselect = JRequest::getCmd('name','eselect');
		if(empty($element)){
			$element = new stdClass();
			$element->payment_name='ESELECT';
			$element->payment_description='You can pay by credit card using this payment method';
			$element->payment_images='MasterCard,VISA,Credit_card,American_Express';
			$element->payment_type = $this->eselect;
			$element->payment_params = new stdClass();
			$element->payment_params->store_id='';
			$element->payment_params->api_token='';
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
			array('name' => 'pophelp', 'target' =>'payment-eselect-form')
		);

		hikashop_setTitle('ESELECT','plugin','plugins&plugin_type=payment&task=edit&name='.$this->eselect);
		$app = JFactory::getApplication();
		$app->setUserState( HIKASHOP_COMPONENT.'.payment_plugin_type', $this->eselect);
		$this->address = hikashop_get('type.address');
		$this->category = hikashop_get('type.categorysub');
		$this->category->type = 'status';
	}

	function onPaymentConfigurationSave(&$element){
		return true;
	}
}
