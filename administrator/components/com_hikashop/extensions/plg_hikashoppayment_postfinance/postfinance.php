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
class plgHikashoppaymentPostfinance extends JPlugin {
	var $accepted_currencies = array('CHF', 'EUR', 'GBP', 'USD', 'DZD', 'AUD', 'CAD', 'HRK', 'CZK', 'DKK', 'EGP', 'HKD', 'HUF', 'INR', 'IDR', 'ILS', 'JPY', 'KES', 'LVL', 'LTL', 'MYR', 'MUR', 'MAD', 'NAD', 'NZD', 'NOK', 'PHP', 'PLN', 'RON', 'SGD', 'ZAR', 'LKR', 'SEK', 'TWD', 'THB', 'TND', 'TRY', 'VND', );

	var $debugData = array();

	function onPaymentDisplay(&$order, &$methods, &$usable_methods) {
		if (!empty($methods)) {
			foreach ($methods as $method) {
				if ($method -> payment_type != 'postfinance' || !$method -> enabled) {
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
		$home_url = HIKASHOP_LIVE . 'index.php';
		$notify_url = $home_url . '?option=com_hikashop&ctrl=checkout&task=notify&notif_payment=postfinance&tmpl=component&lang=' . $locale . $url_itemid;
		$return_url = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=checkout&task=after_end&order_id=' . $order -> order_id . $url_itemid;
		if (!isset($method -> payment_params -> no_shipping))
			$method -> payment_params -> no_shipping = 1;
		if (!empty($method -> payment_params -> rm))
			$method -> payment_params -> rm = 2;
		$vars = array("PSPID" => $method -> payment_params -> shop_ID, "LANGUAGE" => 'en_US', "ORDERID" => $order -> order_id, "AMOUNT" => $order -> order_full_price * 100, "CURRENCY" => $currency -> currency_code, "ACCEPTURL" => $return_url, "CANCELURL" => $return_url, "DECLINEURL" => $return_url, "EXCEPTIONURL" => $return_url, "HOMEURL" => $home_url, "CATALOGURL" => $home_url, );
		$billing_address_type = 'billing_address';
		$shipping_address_type = 'shipping_address';
		$app = &JFactory::getApplication();
		$billing_address = $app -> getUserState(HIKASHOP_COMPONENT . '.' . $billing_address_type);
		$shipping_address = $app -> getUserState(HIKASHOP_COMPONENT . '.' . $shipping_address_type);
		if (!empty($billing_address) && $method -> payment_params -> address_type == 'billing') {
			$cart = hikashop_get('class.cart');
			$cart -> loadAddress($order -> cart, $billing_address, 'object', 'billing');
			$billing_address1 = '';
			$billing_address2 = '';
			if (!empty($order -> cart -> $billing_address_type -> address_street2)) {
				$billing_address2 = substr($order -> cart -> $billing_address_type -> address_street2, 0, 99);
			}
			if (!empty($order -> cart -> $billing_address_type -> address_street)) {
				if (strlen($order -> cart -> $billing_address_type -> address_street) > 100) {
					$billing_address1 = substr($order -> cart -> $billing_address_type -> address_street, 0, 99);
					if (empty($billing_address2))
						$billing_address2 = substr($order -> cart -> $billing_address_type -> address_street, 99, 199);
				} else {
					$billing_address1 = $order -> cart -> $billing_address_type -> address_street;
				}
			}
			if (!empty($billing_address1))
				$vars["OWNERADDRESS"] = $billing_address1;
			if (!empty($billing_address2))
				$vars["OWNERADDRESS"] .= $billing_address2;
			if (!empty($order -> cart -> $billing_address_type -> address_post_code))
				$vars["OWNERZIP"] = @$order -> cart -> $billing_address_type -> address_post_code;
			if (!empty($order -> cart -> $billing_address_type -> address_city))
				$vars["OWNERCTY"] = @$order -> cart -> $billing_address_type -> address_city;
			if (!empty($user -> user_email))
				$vars["EMAIL"] = $user -> user_email;
			if (!empty($order -> cart -> $billing_address_type -> address_telephone))
				$vars["OWNERTELNO"] = @$order -> cart -> $billing_address_type -> address_telephone;
		}
		if (!empty($shipping_address) && $method -> payment_params -> address_type == 'shipping') {
			$cart = hikashop_get('class.cart');
			$cart -> loadAddress($order -> cart, $shipping_address, 'object', 'shipping');
			$shipping_address1 = '';
			$shipping_address2 = '';
			if (!empty($order -> cart -> $shipping_address_type -> address_street2)) {
				$shipping_address2 = substr($order -> cart -> $shipping_address_type -> address_street2, 0, 99);
			}
			if (!empty($order -> cart -> $shipping_address_type -> address_street)) {
				if (strlen($order -> cart -> $shipping_address_type -> address_street) > 100) {
					$shipping_address1 = substr($order -> cart -> $shipping_address_type -> address_street, 0, 99);
					if (empty($shipping_address2))
						$shipping_address2 = substr($order -> cart -> $shipping_address_type -> address_street, 99, 199);
				} else {
					$shipping_address1 = $order -> cart -> $shipping_address_type -> address_street;
				}
			}
			if (!empty($shipping_address1))
				$vars["OWNERADDRESS"] = $shipping_address1;
			if (!empty($shipping_address2))
				$vars["OWNERADDRESS"] .= $shipping_address2;
			if (!empty($order -> cart -> $shipping_address_type -> address_post_code))
				$vars["OWNERZIP"] = @$order -> cart -> $shipping_address_type -> address_post_code;
			if (!empty($order -> cart -> $shipping_address_type -> address_city))
				$vars["OWNERCTY"] = @$order -> cart -> $shipping_address_type -> address_city;
			if (!empty($user -> user_email))
				$vars["EMAIL"] = $user -> user_email;
			if (!empty($order -> cart -> $shipping_address_type -> address_telephone))
				$vars["OWNERTELNO"] = @$order -> cart -> $shipping_address_type -> address_telephone;
		}
		ksort($vars);
		$txtSha_tosecure = '';
		foreach ($vars as $key => $var) {
			$txtSha_tosecure .= strtoupper($key) . '=' . $var . $method -> payment_params -> sha_in_phrase;
		}
		$txtSha = strtoupper(sha1($txtSha_tosecure));
		$vars["SHASIGN"] = $txtSha;

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
		$elements = $pluginsClass -> getMethods('payment', 'postfinance');
		if (empty($elements))
			return false;
		$element = reset($elements);
		$vars = array();
		$data = array();
		$filter = JFilterInput::getInstance();
		foreach ($_REQUEST as $key => $value) {
			$key = $filter -> clean($key);
			if (preg_match("#^[0-9a-z_-]{1,30}$#i", $key) && !preg_match("#^cmd$#i", $key)) {
				$value = JRequest::getString($key);
				$vars[$key] = $value;
				$data[] = $key . '=' . urlencode($value);
			}
		}
		$data = implode('&', $data) . '&cmd=_notify-validate';
		if ($element -> payment_params -> debug) {
			echo print_r($vars, true) . "\n\n\n";
		}

		$orderClass = hikashop_get('class.order');
		$dbOrder = $orderClass -> get((int)@$vars['orderID']);
		if (is_array($_POST)) {
			$result = array();
			$acceptedKeys = array('AAVADDRESS', 'AAVCHECK', 'AAVZIP', 'ACCEPTANCE', 'ALIAS', 'AMOUNT', 'BIN', 'BRAND', 'CARDNO', 'CCCTY', 'CN', 'COMPLUS', 'CREATION_STATUS', 'CURRENCY', 'CVCCHECK', 'DCC_COMMPERCENTAGE', 'DCC_CONVAMOUNT', 'DCC_CONVCCY', 'DCC_EXCHRATE', 'DCC_EXCHRATESOURCE', 'DCC_EXCHRATETS', 'DCC_INDICATOR', 'DCC_MARGINPERCENTAGE', 'DCC_VALIDHOURS', 'DIGESTCARDNO', 'ECI', 'ED', 'ENCCARDNO', 'IP', 'IPCTY', 'NBREMAILUSAGE', 'NBRIPUSAGE', 'NBRIPUSAGE_ALLTX', 'NBRUSAGE', 'NCERROR', 'ORDERID', 'PAYID', 'PM', 'SCO_CATEGORY', 'SCORING', 'STATUS', 'SUBBRAND', 'SUBSCRIPTION_ID', 'TRXDATE', 'VC');
			foreach ($_POST as $key => $value) {
				if ($value != '' && in_array(strtoupper($key), $acceptedKeys)) {
					$result[strtoupper($key)] = $value;
				} elseif ($key == 'SHASIGN')
					$shasign = $value;
			}
			if ($element -> payment_params -> debug) {
				echo "---------------------------------------START----------------------------------------<br/>";
				echo '$_POST :' . print_r($_POST, true) . '<br/>';
				echo 'date :' . print_r(getdate(), true) . '<br/>';
			}
			ksort($result);
			$txtSha_tosecure = '';
			foreach ($result as $key => $var) {
				$txtSha_tosecure .= $key . '=' . $var . $element -> payment_params -> sha_out_phrase;
			}
			$txtSha = strtoupper(sha1($txtSha_tosecure));
		} else {
			if ($element -> payment_params -> debug) {
				echo "Could not load any params from the Post finance server";
			}
			return false;
		}
		if (empty($dbOrder)) {
			if ($element -> payment_params -> debug) {
				echo "Could not load any order for your notification " . $vars['orderID'] . "NO ORDER ID <br/>";
			}
			return false;
		}
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
		if ($element -> payment_params -> debug) {
			echo 'result :' . print_r($result, true) . '<br/>';
			echo 'MYSHA : ' . $txtSha . ' THEIRCHA : ' . $shasign . '<br/>';
			echo 'sha_out :' . $element -> payment_params -> sha_out_phrase . '<br/>';
		}
		if (($txtSha == $shasign) && ($result['STATUS'] == 9 || $result['STATUS'] == 91)) {
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
			$order -> history -> history_amount = $result['AMOUNT'];
			$order -> history -> history_payment_id = $element -> payment_id;
			$order -> history -> history_payment_method = $element -> payment_type;
			$order -> history -> history_data = ob_get_clean();
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
			$mailer -> setSubject(JText::sprintf('PAYMENT_NOTIFICATION_FOR_ORDER', 'Postfinance', $result['STATUS'], $dbOrder -> order_number));
			$body = str_replace('<br/>', "\r\n", JText::sprintf('PAYMENT_NOTIFICATION_STATUS', 'Postfinance', $result['STATUS'])) . ' ' . JText::sprintf('ORDER_STATUS_CHANGED', $order -> mail_status) . "\r\n\r\n" . $order_text;
			$mailer -> setBody($body);
			$mailer -> Send();
			$orderClass -> save($order);
			return true;
		} else {
			if ($result['STATUS'] != 5) {
				$order -> history -> history_reason = JText::sprintf('AUTOMATIC_PAYMENT_NOTIFICATION');
				$order -> history -> history_notified = 0;
				$order -> history -> history_amount = $result['AMOUNT'];
				$order -> history -> history_payment_id = $element -> payment_id;
				$order -> history -> history_payment_method = $element -> payment_type;
				$order -> history -> history_data = ob_get_clean();
				$order -> history -> history_type = 'payment';
				$order -> order_status = $element -> payment_params -> invalid_status;
				$order -> history -> history_notified = 1;
				$mailer -> setSubject(JText::sprintf('NOTIFICATION_REFUSED_FOR_THE_ORDER', 'Postfinance') . 'invalid response');
				$body = JText::sprintf("Hello,\r\n A Postfinance notification was refused because the response from the Post finance server was invalid") . "\r\n\r\n" . $order_text;
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
		$this -> postfinance = JRequest::getCmd('name', 'postfinance');
		if (empty($element)) {
			$element = new stdClass();
			$element -> payment_name = 'PostFinance';
			$element -> payment_description = 'You can pay by credit card or Postfinance using this payment method';
			$element -> payment_images = 'MasterCard,VISA,Credit_card,Postfinance';
			$element -> payment_type = $this -> postfinance;
			$element -> payment_params = new stdClass();
			$element -> payment_params -> url = 'https://e-payment.postfinance.ch/ncol/test/orderstandard.asp';
			$element -> payment_params -> notification = 1;
			$list = new stdClass();
			$element -> payment_params -> shop_ID = '';
			$element -> payment_params -> details = 0;
			$element -> payment_params -> invalid_status = 'cancelled';
			$element -> payment_params -> pending_status = 'created';
			$element -> payment_params -> verified_status = 'confirmed';
			$element -> payment_params -> address_override = 1;
			$element = array($element);
		}
		$obj = reset($element);
		$this -> toolbar = array('save', 'apply', 'cancel', '|', array('name' => 'pophelp', 'target' => 'payment-postfinance-form'));
		hikashop_setTitle('Postfinance', 'plugin', 'plugins&plugin_type=payment&task=edit&name=' . $this -> postfinance);
		$app = JFactory::getApplication();
		$app -> setUserState(HIKASHOP_COMPONENT . '.payment_plugin_type', $this -> postfinance);
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

}
