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
class plgHikashopshippingManual extends JPlugin{

	var $multiple_entries = true;

	function onShippingDisplay(&$order,&$dbrates,&$usable_rates,&$messages){
		$config =& hikashop_config();
		if(!$config->get('force_shipping') && bccomp(@$order->weight,0,5)<=0) return true;

		if(empty($dbrates)) {
			$messages['no_rates'] = JText::_('NO_SHIPPING_METHOD_FOUND');
			return true;
		}

		$rates = array();
		foreach($dbrates as $k => $rate){
			if($rate->shipping_type=='manual' && !empty($rate->shipping_published)){
				$rates[]=$rate;
			}
		}
		if(empty($rates)) {
			$messages['no_rates'] = JText::_('NO_SHIPPING_METHOD_FOUND');
			return true;
		}

		$products = array();
		if(!isset($rate->shipping_params->shipping_price_use_tax))$rate->shipping_params->shipping_price_use_tax=1;
		if($rate->shipping_params->shipping_price_use_tax){
			$price_all = $order->total->prices[0]->price_value_with_tax;
			if(isset($order->full_total->prices[0]->price_value_without_shipping_with_tax)){
				$price_all = $order->full_total->prices[0]->price_value_without_shipping_with_tax;
			}
		}else{
			$price_all = $order->total->prices[0]->price_value;
			if(isset($order->full_total->prices[0]->price_value_without_shipping)){
				$price_all = $order->full_total->prices[0]->price_value_without_shipping;
			}
		}
		$price_realproducts = 0.0;
		if(!empty($order->products)){
			$copy = new stdClass();
			$copy->products = array();
			foreach($order->products as $k => $row){
				if(empty($products[$row->product_id])) $products[$row->product_id] = 0;
				$products[$row->product_id] += $row->cart_product_quantity;

				if(!empty($row->product_parent_id)) {
					if(!isset($products[$row->product_parent_id])) $products[$row->product_parent_id] = 0;
					$products[$row->product_parent_id] += $row->cart_product_quantity;
				}

				if($row->product_weight > 0){
					$copy->products[] = $row;
				}
			}
			$currencyClass = hikashop_get('class.currency');
			$currencyClass->calculateTotal($copy->products, $copy->total,hikashop_getCurrency());
			if($rate->shipping_params->shipping_price_use_tax){
				$price_realproducts = $copy->total->prices[0]->price_value_with_tax;
			}else{
				$price_realproducts = $copy->total->prices[0]->price_value;
			}
		}

		$notUsable = array();
		foreach($rates as $k => $rate){
			if(!isset($rate->shipping_params->shipping_virtual_included) || $rate->shipping_params->shipping_virtual_included){
				$price = $price_all;
			} else {
				$price = $price_realproducts;
			}

			if(@$rate->shipping_params->shipping_min_price > $price){
				$notUsable[]=$k;
				continue;
			}
			if(bccomp($price,0,5)) {
				if(!empty($rate->shipping_params->shipping_max_price) && bccomp($rate->shipping_params->shipping_max_price,0,5) && @$rate->shipping_params->shipping_max_price<$price){
					$notUsable[]=$k;
					continue;
				}
				if(isset($rate->shipping_params->shipping_percentage) && bccomp($rate->shipping_params->shipping_percentage,0,3)){
					$currencyClass = hikashop_get('class.currency');
					$rates[$k]->shipping_price = round($rate->shipping_price + $price*$rate->shipping_params->shipping_percentage/100,$currencyClass->getRounding($rate->shipping_currency_id));
				}
			}
		}
		foreach($notUsable as $item){
			unset($rates[$item]);
		}

		if(empty($rates)) {
			$messages['order_total_too_low'] = JText::_('ORDER_TOTAL_TOO_LOW_FOR_SHIPPING_METHODS');
			return true;
		}

		$notUsable = array();
		$zoneClass = hikashop_get('class.zone');
		$zones = $zoneClass->getOrderZones($order);
		foreach($rates as $k => $rate){
			if(!empty($rate->shipping_zone_namekey)){
				if(!in_array($rate->shipping_zone_namekey,$zones)){
					$notUsable[]=$k;
					continue;
				}
			}
			if(!empty($rate->shipping_params->shipping_zip_prefix) || !empty($rate->shipping_params->shipping_min_zip) || !empty($rate->shipping_params->shipping_max_zip) || !empty($rate->shipping_params->shipping_zip_suffix)){
				$checkDone = false;
				if(!empty($order->shipping_address) && !empty($order->shipping_address->address_post_code)){
					if(preg_match('#([a-z]*)([0-9]+)(.*)#i',preg_replace('#[^a-z0-9]#i','',$order->shipping_address->address_post_code),$match)){
						$checkDone = true;
						$prefix = $match[1];
						$main = $match[2];
						$suffix = $match[3];
						if(!empty($rate->shipping_params->shipping_zip_prefix) && $rate->shipping_params->shipping_zip_prefix!=$prefix){
							$notUsable[]=$k;
							continue;
						}
						if(!empty($rate->shipping_params->shipping_min_zip) && $rate->shipping_params->shipping_min_zip>$main){
							$notUsable[]=$k;
							continue;
						}
						if(!empty($rate->shipping_params->shipping_max_zip) && $rate->shipping_params->shipping_max_zip<$main){
							$notUsable[]=$k;
						}
						if(!empty($rate->shipping_params->shipping_zip_suffix) && $rate->shipping_params->shipping_zip_suffix!=$suffix){
							$notUsable[]=$k;
							continue;
						}
					}
				}
				if(!$checkDone){
					$notUsable[]=$k;
					continue;
				}
			}
		}
		foreach($notUsable as $item){
			unset($rates[$item]);
		}
		if(empty($rates)){
			if(hikashop_loadUser())	$messages['no_shipping_to_your_zone'] = JText::_('NO_SHIPPING_TO_YOUR_ZONE');
			return true;
		}

		$volumeClass=hikashop_get('helper.volume');
		$notUsable = array();
		foreach($rates as $k => $rate){
			if(bccomp($rate->shipping_params->shipping_max_volume,0,3)){
				$rates[$k]->shipping_params->shipping_max_volume_orig = $rates[$k]->shipping_params->shipping_max_volume;
				$rates[$k]->shipping_params->shipping_max_volume=$volumeClass->convert($rate->shipping_params->shipping_max_volume,@$rate->shipping_params->shipping_size_unit);
				if($rates[$k]->shipping_params->shipping_max_volume<$order->volume){
					$notUsable[]=$k;
				}
			}
			if(bccomp((float)@$rate->shipping_params->shipping_min_volume,0,3)){
				$rates[$k]->shipping_params->shipping_min_volume_orig = $rates[$k]->shipping_params->shipping_min_volume;
				$rates[$k]->shipping_params->shipping_min_volume=$volumeClass->convert($rate->shipping_params->shipping_min_volume,@$rate->shipping_params->shipping_size_unit);
				if($rates[$k]->shipping_params->shipping_min_volume>$order->volume){
					$notUsable[]=$k;
				}
			}
		}
		foreach($notUsable as $item){
			unset($rates[$item]);
		}

		if(empty($rates)){
			$messages['items_volume_over_limit'] = JText::_('ITEMS_VOLUME_TOO_BIG_FOR_SHIPPING_METHODS');
			return true;
		}

		if(isset($order->weight)){
			$notUsable = array();
			$weightClass=hikashop_get('helper.weight');
			foreach($rates as $k => $rate){
				if(!empty($rate->shipping_params->shipping_max_weight) && bccomp($rate->shipping_params->shipping_max_weight,0,3)){
					$rates[$k]->shipping_params->shipping_max_weight_orig = $rates[$k]->shipping_params->shipping_max_weight;
					$rates[$k]->shipping_params->shipping_max_weight=$weightClass->convert($rate->shipping_params->shipping_max_weight,@$rate->shipping_params->shipping_weight_unit);
					if($rates[$k]->shipping_params->shipping_max_weight<$order->weight){
						$notUsable[]=$k;
					}
				}
				if(!empty($rate->shipping_params->shipping_min_weight) && bccomp((float)@$rate->shipping_params->shipping_min_weight,0,3)){
					$rates[$k]->shipping_params->shipping_min_weight_orig = $rates[$k]->shipping_params->shipping_min_weight;
					$rates[$k]->shipping_params->shipping_min_weight=$weightClass->convert($rate->shipping_params->shipping_min_weight,@$rate->shipping_params->shipping_weight_unit);
					if($rates[$k]->shipping_params->shipping_min_weight>$order->weight){
						$notUsable[]=$k;
					}
				}
			}

			foreach($notUsable as $item){
				unset($rates[$item]);
			}

			$ships_per_product = array();
			foreach($rates as $k => $rate){
				if(isset($rate->shipping_params->shipping_per_product) && $rate->shipping_params->shipping_per_product) {
					$ships_per_product[$rate->shipping_id] = array(
						'price' => (float)$rate->shipping_params->shipping_price_per_product,
						'products' => array()
					);
				}
			}

			if(!empty($ships_per_product)) {
				$query = 'SELECT a.shipping_id, a.shipping_price_ref_id as `ref_id`, a.shipping_price_min_quantity as `min_quantity`, a.shipping_price_value as `price`, a.shipping_fee_value as `fee` FROM ' . hikashop_table('shipping_price') . ' AS a '.
					'WHERE a.shipping_id IN (' . implode(',', array_keys($ships_per_product)) . ') AND a.shipping_price_ref_id IN (' . implode(',', array_keys($products)) . ') AND a.shipping_price_ref_type = \'product\' '.
					'ORDER BY a.shipping_id, a.shipping_price_ref_id, a.shipping_price_min_quantity';
				$db = JFactory::getDBO();
				$db->setQuery($query);
				$ret = $db->loadObjectList();
				if(!empty($ret)) {
					foreach($ret as $ship) {
						if($ship->min_quantity <= $products[$ship->ref_id]) {
							$ships_per_product[$ship->shipping_id]['products'][$ship->ref_id] = ($ship->price * $products[$ship->ref_id]) + $ship->fee;
						}
					}
				}
			}

			if(empty($rates)){
				$messages['items_weight_over_limit'] = JText::_('ITEMS_WEIGHT_TOO_BIG_FOR_SHIPPING_METHODS');
			}else{
				foreach($rates as $rate){
					$usable = true;
					if(isset($ships_per_product[$rate->shipping_id]) && !empty($order->products)){
						foreach($order->products as $k => $row) {
							$price = 0;
							if(isset($ships_per_product[$rate->shipping_id]['products'][$row->product_id])) {
								$price = $ships_per_product[$rate->shipping_id]['products'][$row->product_id];
								$ships_per_product[$rate->shipping_id]['products'][$row->product_id]=0;
							} elseif(isset($ships_per_product[$rate->shipping_id]['products'][$row->product_parent_id])) {
								$price = $ships_per_product[$rate->shipping_id]['products'][$row->product_parent_id];
								$ships_per_product[$rate->shipping_id]['products'][$row->product_parent_id]=0;
							} else {
								$price = $ships_per_product[$rate->shipping_id]['price'] * $row->cart_product_quantity;
							}
							if($price > 0) {
								$rate->shipping_price = round($rate->shipping_price + $price, $currencyClass->getRounding($rate->shipping_currency_id));
							}
							if($price < 0) {
								$usable = false;
							}
						}
					}

					if($usable)
						$usable_rates[]=$rate;
				}
				if(empty($rates)){
					$messages['product_exclusion'] = JText::_('SOME_PRODUCTS_ARE_NOT_ALLOWED_FOR_YOUR_COUNTRY');
					return true;
				}
			}
		}

		return true;
	}

	function onShippingConfiguration(&$elements){
		$this->manual = JRequest::getCmd('name','manual');
		$subtask = JRequest::getCmd('subtask','');
		if($subtask=='shipping_edit'){
			$this->view = 'edit';
			$this->currency = hikashop_get('type.currency');
			$this->weight = hikashop_get('type.weight');
			$this->volume = hikashop_get('type.volume');
			$this->categoryType = hikashop_get('type.categorysub');
			$this->categoryType->type = 'tax';
			$this->categoryType->field = 'category_id';
			$this->toolbar = array(
				'save',
				'apply',
				array('name' => 'link', 'icon'=> 'cancel', 'alt' => JText::_('HIKA_CANCEL'),'url'=>hikashop_completeLink('plugins&plugin_type=shipping&task=edit&name='.$this->manual)),
				'|',
				array('name' => 'pophelp', 'target' =>'shipping-manual-form')
			);

			hikashop_setTitle(JText::_('HIKASHOP_SHIPPING_METHOD'),'plugin','plugins&plugin_type=shipping&task=edit&name='.$this->manual.'&subtask=shipping_edit&shipping_id='.JRequest::getInt('shipping_id',0));
		}else{

			if($subtask=='copy'){
				$task = JRequest::getVar('task');
				if(!in_array($task,array('orderup','orderdown','saveorder'))){
					$shippings = JRequest::getVar( 'cid', array(), '', 'array' );
					JArrayHelper::toInteger($shippings);
					$result = true;
					if(!empty($shippings)){
						$db = JFactory::getDBO();
						$db->setQuery('SELECT * FROM '.hikashop_table('shipping').' WHERE shipping_id IN ('.implode(',',$shippings).')');
						$elements = $db->loadObjectList();
						$helper = hikashop_get('class.shipping');
						foreach($elements as $element){
							unset($element->shipping_id);
							if(!$helper->save($element)){
								$result=false;
							}
						}
					}
					if($result){
						$app = JFactory::getApplication();
						$app->enqueueMessage(JText::_( 'HIKASHOP_SUCC_SAVED' ), 'success');
						$app->redirect(hikashop_completeLink('plugins&plugin_type=shipping&task=edit&name='.$this->manual,false,true));
					}
				}
			}
			$this->dbrates =& $elements;
			$this->noForm=true;
			if(!empty($this->dbrates)){
				$db = JFactory::getDBO();
				$zones = array();
				foreach($this->dbrates as $rate){
					if(!empty($rate->shipping_zone_namekey)){
						$zones[$rate->shipping_zone_namekey]=$db->Quote($rate->shipping_zone_namekey);
					}
				}
				if(!empty($zones)){
					$query = 'SELECT * FROM '.hikashop_table('zone').' WHERE zone_namekey IN ('.implode(',',$zones).');';
					$db->setQuery($query);
					$zones = $db->loadObjectList();
					if(!empty($zones)){
						foreach($this->dbrates as $k => $rate){
							if(!empty($rate->shipping_zone_namekey)){
								foreach($zones as $zone){
									if($zone->zone_namekey==$rate->shipping_zone_namekey){
										foreach(get_object_vars($zone) as $key => $val){
											$this->dbrates[$k]->$key=$val;
										}
										break;
									}
								}
							}
						}
					}
				}
			}
			$this->toolbar = array(
				array('name' => 'copy', 'icon'=> 'copy', 'alt' => JText::_('HIKA_COPY'),'task'=>'edit'),
				array('name' => 'link', 'icon'=> 'new', 'alt' => JText::_('HIKA_NEW'),'url'=>hikashop_completeLink('plugins&plugin_type=shipping&task=edit&name='.$this->manual.'&subtask=shipping_edit')),
				'cancel',
				'|',
				array('name' => 'pophelp', 'target' =>'shipping-manual-listing')
			);

			hikashop_setTitle(JText::_('HIKASHOP_SHIPPING_METHOD'),'plugin','plugins&plugin_type=shipping&task=edit&name='.$this->manual);
			$this->toggleClass = hikashop_get('helper.toggle');
			$this->currencyHelper = hikashop_get('class.currency');
			$this->pagination = hikashop_get('helper.pagination',count($this->dbrates), 0, false );
			$this->order = new stdClass();
			$this->order->ordering = true;
			$this->order->orderUp = 'orderup';
			$this->order->orderDown = 'orderdown';
			$this->order->reverse = false;
			$app = JFactory::getApplication();
			$app->setUserState( HIKASHOP_COMPONENT.'.shipping_plugin_type', $this->manual);
		}
	}

	function onShippingConfigurationSave(&$elements){
		if(!empty($elements->shipping_params->shipping_min_price)){
			$elements->shipping_params->shipping_min_price = preg_replace('#[^0-9\.,]#','',$elements->shipping_params->shipping_min_price);
		}
		if(!empty($elements->shipping_params->shipping_max_price)){
			$elements->shipping_params->shipping_max_price = preg_replace('#[^0-9\.,]#','',$elements->shipping_params->shipping_max_price);
		}
		if(!empty($elements->shipping_params->shipping_min_weight)){
			$elements->shipping_params->shipping_min_weight = preg_replace('#[^0-9\.,]#','',$elements->shipping_params->shipping_min_weight);
		}
		if(!empty($elements->shipping_params->shipping_max_weight)){
			$elements->shipping_params->shipping_max_weight = preg_replace('#[^0-9\.,]#','',$elements->shipping_params->shipping_max_weight);
		}
		if(!empty($elements->shipping_params->shipping_min_volume)){
			$elements->shipping_params->shipping_min_volume = preg_replace('#[^0-9\.,]#','',$elements->shipping_params->shipping_min_volume);
		}
		if(!empty($elements->shipping_params->shipping_max_volume)){
			$elements->shipping_params->shipping_max_volume = preg_replace('#[^0-9\.,]#','',$elements->shipping_params->shipping_max_volume);
		}
		return true;
	}

	function onShippingSave(&$cart,&$methods,&$shipping_id){
		$usable_methods = array();
		$errors = array();
		$this->onShippingDisplay($cart,$methods,$usable_methods,$errors);
		$shipping_id = (int) $shipping_id;

		foreach($usable_methods as $k => $usable_method){
			if($usable_method->shipping_id==$shipping_id){
				return $usable_method;
			}
		}

		return false;
	}

	function onAfterOrderConfirm(&$order,&$methods,$method_id){
		return true;
	}

	function getShippingAddress($id = 0) {
		$app = JFactory::getApplication();
		if($id == 0)
			$id = $app->getUserState( HIKASHOP_COMPONENT.'.shipping_id','');
		$class = hikashop_get('class.shipping');
		$shipping = $class->get($id);
		$params = unserialize($shipping->shipping_params);
		$override = 0;
		if(isset($params->shipping_override_address)) {
			$override = (int)$params->shipping_override_address;
		}

		switch($override) {
			case 4:
				if(!empty($params->shipping_override_address_text)) {
					return $params->shipping_override_address_text;
				}
				break;
			case 3:
				if(!empty($params->shipping_override_address_text)) {
					return str_replace(array("\r\n","\n","\r"),"<br/>", htmlentities($params->shipping_override_address_text) );
				}
				break;
			case 2:
				return '';
			case 1:
				$config =& hikashop_config();
				return str_replace(array("\r\n","\n","\r"),"<br/>", $config->get('store_address'));
			case 0:
			default:
				return false;
		}
		return false;
	}
}
