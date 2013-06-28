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
class hikashopPaymentClass extends hikashopClass{
	var $tables = array('payment');
	var $pkeys = array('payment_id');
	var $toggle = array('payment_published'=>'payment_id');

	function getMethods(&$order,$currency=''){
		$pluginClass = hikashop_get('class.plugins');
		$shipping = '';
		if(!empty($order->shipping))$shipping = $order->shipping->shipping_type.'_'.$order->shipping->shipping_id;
		$rates = $pluginClass->getMethods('payment','',$shipping,$currency);

		if(bccomp($order->total->prices[0]->price_value,0,5) && !empty($rates)){
			$currencyClass = hikashop_get('class.currency');
			$currencyClass->convertPayments($rates);
		}
		return $rates;
	}

	function getPayments(&$order){
		static $usable_methods = null;

		if(is_null($usable_methods)){
			JPluginHelper::importPlugin( 'hikashoppayment' );
			$dispatcher = JDispatcher::getInstance();
			$payment = '';
			if(!empty($order->payment->payment_type) && !empty($order->payment->payment_id)){
				$payment = $order->payment->payment_type.'_'.$order->payment->payment_id;
			}
			$pluginClass = hikashop_get('class.plugins');
			$currency = @$order->total->prices[0]->price_currency_id;
			if(empty($currency)) $currency = hikashop_getCurrency();
			$methods = $this->getMethods($order,$currency);
			$max = 0;
			if(empty($methods)){
				$app = JFactory::getApplication();
				$app->enqueueMessage(JText::_('CONFIGURE_YOUR_PAYMENT_METHODS'));
			}else{
				$already = array();
				$price_all = @$order->full_total->prices[0]->price_value_with_tax;
				if(isset($order->full_total->prices[0]->price_value_without_payment_with_tax)){
					$price_all = @$order->full_total->prices[0]->price_value_without_payment_with_tax;
				}

				foreach($methods as $k => $method){
					$methods[$k]->payment_price = ($price_all * (float)@$method->payment_params->payment_percentage / 100) + @$method->payment_price;
					if(!empty($method->ordering) && $max<$method->ordering){
						$max=$method->ordering;
					}
				}
				foreach($methods as $k => $method){
					if(empty($method->ordering)){
						$max++;
						$methods[$k]->ordering=$max;
					}
					while(isset($already[$methods[$k]->ordering])){
						$max++;
						$methods[$k]->ordering=$max;
					}
					$already[$methods[$k]->ordering]=true;
				}
				$dispatcher->trigger( 'onPaymentDisplay', array( & $order,&$methods,&$usable_methods ) );

				$cartClass = hikashop_get('class.cart');
				$paymentType = $cartClass->checkSubscription($order);
				if(is_array($usable_methods)){
					foreach($usable_methods as $k => $usable_method){
						if($paymentType == 'noRecurring' && isset($usable_method->recurring) && $usable_method->recurring == 1){
							unset($usable_methods[$k]);
						}
						else if($paymentType == 'recurring' && (!isset($usable_method->recurring) || $usable_method->recurring != 1)){
							unset($usable_methods[$k]);
						}
					}
				}
			}
			if(!empty($usable_methods)){
				ksort($usable_methods);
			}else{
				$app = JFactory::getApplication();
				$app->enqueueMessage(JText::_('NO_PAYMENT_METHODS_FOUND'));
				$usable_methods=false;
			}
		}
		return $usable_methods;
	}
	function save(&$element){

		if(isset($element->payment_shipping_methods) && is_array($element->payment_shipping_methods)){
			$element->payment_shipping_methods = implode("\n",$element->payment_shipping_methods);
		}
		if(isset($element->payment_currency) && is_array($element->payment_currency)){
			$element->payment_currency = implode(",",$element->payment_currency);
			if(!empty($element->payment_currency)) $element->payment_currency = ','.$element->payment_currency.',';
		}

		$status = parent::save($element);
		return $status;
	}

	function readCC() {
		$app = JFactory::getApplication();

		$payment = $app->getUserState( HIKASHOP_COMPONENT.'.payment_method');
		$payment_id = $app->getUserState( HIKASHOP_COMPONENT.'.payment_id');
		$payment_data = $app->getUserState( HIKASHOP_COMPONENT.'.payment_data');
		$ret = false;

		if(!empty($payment_data->ask_cc)){
			$cc_number = $app->getUserState( HIKASHOP_COMPONENT.'.cc_number');
			$cc_month = $app->getUserState( HIKASHOP_COMPONENT.'.cc_month');
			$cc_year = $app->getUserState( HIKASHOP_COMPONENT.'.cc_year');
			$cc_CCV = $app->getUserState( HIKASHOP_COMPONENT.'.cc_CCV');
			$cc_type = $app->getUserState( HIKASHOP_COMPONENT.'.cc_type');
			$cc_owner = $app->getUserState( HIKASHOP_COMPONENT.'.cc_owner');
			if(empty($cc_number) || empty($cc_month) || empty($cc_year) || (empty($cc_CCV)&&!empty($payment_data->ask_ccv)) || (empty($cc_owner)&&!empty($payment_data->ask_owner))){
				$cc_numbers = JRequest::getVar( 'hikashop_credit_card_number', array(), '', 'array' );
				$cc_number='';
				if(!empty($cc_numbers[$payment.'_'.$payment_id])){
					$cc_number=preg_replace('#[^0-9]#','',$cc_numbers[$payment.'_'.$payment_id]);
				}
				$cc_months = JRequest::getVar( 'hikashop_credit_card_month', array(), '', 'array' );
				$cc_month='';
				if(!empty($cc_months[$payment.'_'.$payment_id])){
					$cc_month=substr(preg_replace('#[^0-9]#','',$cc_months[$payment.'_'.$payment_id]),0,2);
					if(strlen($cc_month)==1){
						$cc_month='0'.$cc_month;
					}
				}
				$cc_years = JRequest::getVar( 'hikashop_credit_card_year', array(), '', 'array' );
				$cc_year='';
				if(!empty($cc_years[$payment.'_'.$payment_id])){
					$cc_year=substr(preg_replace('#[^0-9]#','',$cc_years[$payment.'_'.$payment_id]),0,2);
					if(strlen($cc_year)==1){
						$cc_year='0'.$cc_year;
					}
				}
				$cc_CCVs = JRequest::getVar( 'hikashop_credit_card_CCV', array(), '', 'array' );
				$cc_CCV='';
				if(!empty($cc_CCVs[$payment.'_'.$payment_id])){
					$cc_CCV=substr(preg_replace('#[^0-9]#','',$cc_CCVs[$payment.'_'.$payment_id]),0,4);
					if(strlen($cc_CCV)<3){
						$cc_CCV='';
					}
				}
				$cc_types = JRequest::getVar( 'hikashop_credit_card_type', array(), '', 'array' );
				$cc_type='';
				if(!empty($cc_types[$payment.'_'.$payment_id])){
					$cc_type=$cc_types[$payment.'_'.$payment_id];
				}
				$cc_owners = JRequest::getVar( 'hikashop_credit_card_owner', array(), '', 'array' );
				$cc_owner='';
				if(!empty($cc_owners[$payment.'_'.$payment_id])){
					$cc_owner=strip_tags($cc_owners[$payment.'_'.$payment_id]);
				}
				$new_cc_valid = !(empty($cc_number) || empty($cc_month) || empty($cc_year) || (empty($cc_CCV)&&!empty($payment_data->ask_ccv)) || (empty($cc_owner)&&!empty($payment_data->ask_owner)));
				if($new_cc_valid){
					$app->setUserState( HIKASHOP_COMPONENT.'.cc_number',base64_encode($cc_number));
					$app->setUserState( HIKASHOP_COMPONENT.'.cc_month',base64_encode($cc_month));
					$app->setUserState( HIKASHOP_COMPONENT.'.cc_year',base64_encode($cc_year));
					$app->setUserState( HIKASHOP_COMPONENT.'.cc_CCV',base64_encode($cc_CCV));
					$app->setUserState( HIKASHOP_COMPONENT.'.cc_type',base64_encode($cc_type));
					$app->setUserState( HIKASHOP_COMPONENT.'.cc_owner',base64_encode($cc_owner));

					$ret = true;
				}
			}
		}
		return $ret;
	}
}
