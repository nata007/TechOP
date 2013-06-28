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
class plgHikashoppaymentEpay extends JPlugin
{
	var $accepted_currencies = array
	(
		'AUD',
		'CAD',
		'EUR',
		'GBP',
		'JPY',
		'USD',
		'NZD',
		'CHF',
		'HKD',
		'SGD',
		'SEK',
		'DKK',
		'PLN',
		'NOK',
		'HUF',
		'CZK',
		'MXN',
		'BRL',
		'MYR',
		'PHP',
		'TWD',
		'THB',
		'ILS',
		'TRY'
	);

	var $debugData = array();

	function onPaymentDisplay(&$order, &$methods, &$usable_methods)
	{
		if(!empty($methods))
		{
			foreach($methods as $method)
			{
				if($method->payment_type != 'epay' || !$method->enabled)
				{
					continue;
				}
				if(!empty($method->payment_zone_namekey))
				{
					$zoneClass = hikashop_get('class.zone');
					$zones = $zoneClass->getOrderZones($order);
					if(!in_array($method->payment_zone_namekey, $zones))
					{
						return true;
					}
				}
				$currencyClass = hikashop_get('class.currency');
				$null = null;
				if(!empty($order->total))
				{
					$currency_id = intval(@$order->total->prices[0]->price_currency_id);
					$currency = $currencyClass->getCurrencies($currency_id, $null);
					if(!empty($currency) && !in_array(@$currency[$currency_id]->currency_code, $this->accepted_currencies))
					{
						return true;
					}
				}
				$usable_methods[$method->ordering] = $method;
			}
		}
		return true;
	}

	function onPaymentSave(&$cart, &$rates, &$payment_id)
	{
		$usable = array();
		$this->onPaymentDisplay($cart, $rates, $usable);
		$payment_id = (int)$payment_id;
		foreach($usable as $usable_method)
		{
			if($usable_method->payment_id == $payment_id)
			{
				return $usable_method;
			}
		}
		return false;
	}

	function getVars($order, $methods, $method_id)
	{
		global $Itemid;

		$method =  &$methods[$method_id];
		$currencyClass = hikashop_get('class.currency');
		$currencies = null;
		$currencies = $currencyClass->getCurrencies($order->order_currency_id, $currencies);
		$currency = $currencies[$order->order_currency_id];
		$user = hikashop_loadUser(true);
		$lang = JFactory::getLanguage();
		$locale = strtolower(substr($lang->get('tag'), 0, 2));

		$url_itemid = '';

		if(!empty($Itemid))
		{
			$url_itemid = '&Itemid=' . $Itemid;
		}

		$callback_url = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment=epay&tmpl=component&lang=' . $locale . $url_itemid;
		$accept_url = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=checkout&task=after_end&order_id=' . $order->order_id . $url_itemid;
		$decline_url = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=order&task=cancel_order&order_id=' . $order->order_id . $url_itemid;

		$vars = array
		(
			"merchantnumber" => $method->payment_params->merchantnumber,
			"orderid" => $order->order_id,
			"amount" => intval($order->order_full_price * 100),
			"currency" => $this->get_iso_code($currency->currency_code),
			"windowstate" => $method->payment_params->windowstate,
			"windowid" => $method->payment_params->windowid,
			"accepturl" => $accept_url,
			"cancelurl" => $decline_url,
			"callbackurl" => $callback_url,
			"smsreceipt" => $method->payment_params->authsms,
			"mailreceipt" => $method->payment_params->authmail,
			"instantcapture" => $method->payment_params->instantcapture,
			"group" => $method->payment_params->group,
			"ownreceipt" => $method->payment_params->ownreceipt,
			"instantcallback" => 1,
			"language" => 0,
			"cms" => "hikashop"
		);

		$vars["hash"] = md5(implode("", array_values($vars)) . $method->payment_params->md5key);

		return $vars;
	}

	function onAfterOrderConfirm(&$order, &$methods, $method_id)
	{
		$method =  & $methods[$method_id];
		$tax_total = '';
		$discount_total = '';

		$vars = $this->getVars($order, $methods, $method_id);

		if(!HIKASHOP_J30)
			JHTML::_('behavior.mootools');
		else
			JHTML::_('behavior.framework');

		$app = JFactory::getApplication();
		$name = $method->payment_type . '_end.php';
		$path = JPATH_THEMES . DS . $app->getTemplate() . DS . 'hikashoppayment' . DS . $name;

		if(!file_exists($path))
		{
			if(version_compare(JVERSION, '1.6', '<'))
			{
				$path = JPATH_PLUGINS . DS . 'hikashoppayment' . DS . $name;
			}
			else
			{
				$path = JPATH_PLUGINS . DS . 'hikashoppayment' . DS . $method->payment_type . DS . $name;
			}
			if(!file_exists($path))
			{
				return true;
			}
		}

		require($path);
		return true;
	}

	function onPaymentNotification(&$statuses)
	{

		$pluginsClass = hikashop_get('class.plugins');
		$elements = $pluginsClass->getMethods('payment', 'epay');

		if(empty($elements))
			return false;

		$element = reset($elements);

		$orderClass = hikashop_get('class.order');
		$dbOrder = $orderClass->get((int)@$_GET['orderid']);

		if(empty($dbOrder))
		{
			echo "Could not load any order for your notification " . @$_GET['orderid'];
			return false;
		}

		$order = new stdClass();
		$order->order_id = $dbOrder->order_id;
		$order->old_status->order_status = $dbOrder->order_status;
		$url = HIKASHOP_LIVE . 'administrator/index.php?option=com_hikashop&ctrl=order&task=edit&order_id=' . $order->order_id;
		$order_text = "\r\n" . JText::sprintf('NOTIFICATION_OF_ORDER_ON_WEBSITE', $dbOrder->order_number, HIKASHOP_LIVE);
		$order_text .= "\r\n" . str_replace('<br/>', "\r\n", JText::sprintf('ACCESS_ORDER_WITH_LINK', $url));

		if($element->payment_params->debug)
		{
			echo print_r($dbOrder, true) . "\n\n\n";
		}

		$mailer = JFactory::getMailer();
		$config =  & hikashop_config();
		$sender = array($config->get('from_email'), $config->get('from_name'));

		$mailer->setSender($sender);
		$mailer->addRecipient(explode(',', $config->get('payment_notification_email')));

		if($element->payment_params->debug)
		{
			echo print_r($_GET, true) . "\n\n\n";
		}

		if(strlen($element->payment_params->md5key) > 0)
		{
			$var = "";
			$params = $_GET;


			foreach($params as $key => $value)
			{
				if($key != "hash")
				{
					$var .= $value;
				}
				else
					break;
			}

			$genstamp = md5($var . $element->payment_params->md5key);

			if($genstamp != $_GET["hash"])
			{
				$order->order_status = 'cancelled';
				$order->history->history_reason = JText::_('PAYMENT_MD5_ERROR');
				$order->history->history_notified = 0;
				$order->history->history_payment_id = $element->payment_id;
				$order->history->history_payment_method = $element->payment_type;
				$order->history->history_data = "Payment by ePay - Invalid MD5 - ePay transaction ID: " . $_GET["tid"];
				$order->history->history_type = 'payment';
				$mailer->setSubject(JText::sprintf('NOTIFICATION_REFUSED_FOR_THE_ORDER', 'ePay') . 'invalid response');
				$body = JText::sprintf("Hello,\r\n An ePay notification was refused because the notification from the ePay server was invalid") . "\r\n\r\n" . $order_text;
				$mailer->setBody($body);
				$mailer->Send();
				$orderClass->save($order);
				return false;
			}
		}

		$order->order_status = 'confirmed';
		if($dbOrder->order_status == $order->order_status)
			return true;

		$order->history->history_reason = JText::_('PAYMENT_ORDER_CONFIRMED');
		$order->history->history_notified = 1;
		$order->history->history_payment_id = $element->payment_id;
		$order->history->history_payment_method = $element->payment_type;
		$order->history->history_data = "Payment by ePay - ePay transaction ID: " . $_GET["tid"];
		$order->history->history_type = 'payment';
		$order->mail_status = $statuses[$order->order_status];
		$mailer->setSubject(JText::sprintf('PAYMENT_NOTIFICATION_FOR_ORDER', 'ePay', $order->mail_status, $dbOrder->order_number));
		$body = str_replace('<br/>', "\r\n", JText::sprintf('PAYMENT_NOTIFICATION_STATUS', 'ePay', $order->mail_status)) . ' ' . JText::sprintf('ORDER_STATUS_CHANGED', $order->mail_status) . "\r\n\r\n" . $order_text;
		$mailer->setBody($body);
		$mailer->Send();
		$orderClass->save($order);
		return true;
	}

	function onPaymentConfiguration(&$element)
	{
		$this->epay = JRequest::getCmd('name', 'epay');

		if(empty($element))
		{
			$element = new stdClass();
			$element->payment_name = 'ePay';
			$element->payment_description = 'You can pay by credit card or epay using this payment method';
			$element->payment_type = $this->epay;
			$element->payment_params = new stdClass();
			$element->payment_params->notification = 1;
			$element->payment_params->windowstate = 1;
			$element->payment_params->windowid = 1;
			$element->payment_params->instantcapture = 0;
			$element->payment_params->ownreceipt = 0;
			$element->payment_params->invalid_status = 'cancelled';
			$element->payment_params->pending_status = 'created';
			$element->payment_params->verified_status = 'confirmed';
			$element = array($element);
		}

		$this->toolbar = array
		(
			'save',
			'apply',
			'cancel',
			'|',
			array('name' => 'pophelp', 'target' => 'payment-epay-form')
		);

		hikashop_setTitle('ePay', 'plugin', 'plugins&plugin_type=payment&task=edit&name=' . $this->epay);
		$app = JFactory::getApplication();
		$app->setUserState(HIKASHOP_COMPONENT . '.payment_plugin_type', $this->epay);
		$this->address = hikashop_get('type.address');
		$this->category = hikashop_get('type.categorysub');
		$this->category->type = 'status';
	}

	function get_iso_code($code)
	{
		switch(strtoupper($code))
		{
			case 'ADP':
				return '020';
			case 'AED':
				return '784';
			case 'AFA':
				return '004';
			case 'ALL':
				return '008';
			case 'AMD':
				return '051';
			case 'ANG':
				return '532';
			case 'AOA':
				return '973';
			case 'ARS':
				return '032';
			case 'AUD':
				return '036';
			case 'AWG':
				return '533';
			case 'AZM':
				return '031';
			case 'BAM':
				return '977';
			case 'BBD':
				return '052';
			case 'BDT':
				return '050';
			case 'BGL':
				return '100';
			case 'BGN':
				return '975';
			case 'BHD':
				return '048';
			case 'BIF':
				return '108';
			case 'BMD':
				return '060';
			case 'BND':
				return '096';
			case 'BOB':
				return '068';
			case 'BOV':
				return '984';
			case 'BRL':
				return '986';
			case 'BSD':
				return '044';
			case 'BTN':
				return '064';
			case 'BWP':
				return '072';
			case 'BYR':
				return '974';
			case 'BZD':
				return '084';
			case 'CAD':
				return '124';
			case 'CDF':
				return '976';
			case 'CHF':
				return '756';
			case 'CLF':
				return '990';
			case 'CLP':
				return '152';
			case 'CNY':
				return '156';
			case 'COP':
				return '170';
			case 'CRC':
				return '188';
			case 'CUP':
				return '192';
			case 'CVE':
				return '132';
			case 'CYP':
				return '196';
			case 'CZK':
				return '203';
			case 'DJF':
				return '262';
			case 'DKK':
				return '208';
			case 'DOP':
				return '214';
			case 'DZD':
				return '012';
			case 'ECS':
				return '218';
			case 'ECV':
				return '983';
			case 'EEK':
				return '233';
			case 'EGP':
				return '818';
			case 'ERN':
				return '232';
			case 'ETB':
				return '230';
			case 'EUR':
				return '978';
			case 'FJD':
				return '242';
			case 'FKP':
				return '238';
			case 'GBP':
				return '826';
			case 'GEL':
				return '981';
			case 'GHC':
				return '288';
			case 'GIP':
				return '292';
			case 'GMD':
				return '270';
			case 'GNF':
				return '324';
			case 'GTQ':
				return '320';
			case 'GWP':
				return '624';
			case 'GYD':
				return '328';
			case 'HKD':
				return '344';
			case 'HNL':
				return '340';
			case 'HRK':
				return '191';
			case 'HTG':
				return '332';
			case 'HUF':
				return '348';
			case 'IDR':
				return '360';
			case 'ILS':
				return '376';
			case 'INR':
				return '356';
			case 'IQD':
				return '368';
			case 'IRR':
				return '364';
			case 'ISK':
				return '352';
			case 'JMD':
				return '388';
			case 'JOD':
				return '400';
			case 'JPY':
				return '392';
			case 'KES':
				return '404';
			case 'KGS':
				return '417';
			case 'KHR':
				return '116';
			case 'KMF':
				return '174';
			case 'KPW':
				return '408';
			case 'KRW':
				return '410';
			case 'KWD':
				return '414';
			case 'KYD':
				return '136';
			case 'KZT':
				return '398';
			case 'LAK':
				return '418';
			case 'LBP':
				return '422';
			case 'LKR':
				return '144';
			case 'LRD':
				return '430';
			case 'LSL':
				return '426';
			case 'LTL':
				return '440';
			case 'LVL':
				return '428';
			case 'LYD':
				return '434';
			case 'MAD':
				return '504';
			case 'MDL':
				return '498';
			case 'MGF':
				return '450';
			case 'MKD':
				return '807';
			case 'MMK':
				return '104';
			case 'MNT':
				return '496';
			case 'MOP':
				return '446';
			case 'MRO':
				return '478';
			case 'MTL':
				return '470';
			case 'MUR':
				return '480';
			case 'MVR':
				return '462';
			case 'MWK':
				return '454';
			case 'MXN':
				return '484';
			case 'MXV':
				return '979';
			case 'MYR':
				return '458';
			case 'MZM':
				return '508';
			case 'NAD':
				return '516';
			case 'NGN':
				return '566';
			case 'NIO':
				return '558';
			case 'NOK':
				return '578';
			case 'NPR':
				return '524';
			case 'NZD':
				return '554';
			case 'OMR':
				return '512';
			case 'PAB':
				return '590';
			case 'PEN':
				return '604';
			case 'PGK':
				return '598';
			case 'PHP':
				return '608';
			case 'PKR':
				return '586';
			case 'PLN':
				return '985';
			case 'PYG':
				return '600';
			case 'QAR':
				return '634';
			case 'ROL':
				return '642';
			case 'RUB':
				return '643';
			case 'RUR':
				return '810';
			case 'RWF':
				return '646';
			case 'SAR':
				return '682';
			case 'SBD':
				return '090';
			case 'SCR':
				return '690';
			case 'SDD':
				return '736';
			case 'SEK':
				return '752';
			case 'SGD':
				return '702';
			case 'SHP':
				return '654';
			case 'SIT':
				return '705';
			case 'SKK':
				return '703';
			case 'SLL':
				return '694';
			case 'SOS':
				return '706';
			case 'SRG':
				return '740';
			case 'STD':
				return '678';
			case 'SVC':
				return '222';
			case 'SYP':
				return '760';
			case 'SZL':
				return '748';
			case 'THB':
				return '764';
			case 'TJS':
				return '972';
			case 'TMM':
				return '795';
			case 'TND':
				return '788';
			case 'TOP':
				return '776';
			case 'TPE':
				return '626';
			case 'TRL':
				return '792';
			case 'TRY':
				return '949';
			case 'TTD':
				return '780';
			case 'TWD':
				return '901';
			case 'TZS':
				return '834';
			case 'UAH':
				return '980';
			case 'UGX':
				return '800';
			case 'USD':
				return '840';
			case 'UYU':
				return '858';
			case 'UZS':
				return '860';
			case 'VEB':
				return '862';
			case 'VND':
				return '704';
			case 'VUV':
				return '548';
			case 'XAF':
				return '950';
			case 'XCD':
				return '951';
			case 'XOF':
				return '952';
			case 'XPF':
				return '953';
			case 'YER':
				return '886';
			case 'YUM':
				return '891';
			case 'ZAR':
				return '710';
			case 'ZMK':
				return '894';
			case 'ZWD':
				return '716';
		}
		return '208';
	}
}
