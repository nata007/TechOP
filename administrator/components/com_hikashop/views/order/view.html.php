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

class OrderViewOrder extends hikashopView{
	var $ctrl= 'order';
	var $nameListing = 'ORDERS';
	var $nameForm = 'HIKASHOP_ORDER';
	var $icon = 'order';
	var $displayCompleted = false;
	var $triggerView = true;

	function display($tpl = null){
		$this->paramBase = HIKASHOP_COMPONENT.'.'.$this->getName();
		$function = $this->getLayout();
		if(method_exists($this,$function)) $this->$function();
		if(empty($this->displayCompleted)) parent::display($tpl);
	}

	function listing(){
		$app = JFactory::getApplication();
		$fieldsClass = hikashop_get('class.field');
		$fields = $fieldsClass->getData('backend_listing','order',false);
		$popup = (JRequest::getString('tmpl') === 'component');
		$pageInfo = new stdClass();
		$pageInfo->filter = new stdClass();
		$pageInfo->filter->order = new stdClass();
		$pageInfo->limit = new stdClass();
		$pageInfo->elements = new stdClass();
		$pageInfo->filter->order->value = $app->getUserStateFromRequest( $this->paramBase.".filter_order", 'filter_order',	'b.order_id','cmd' );
		$pageInfo->filter->order->dir	= $app->getUserStateFromRequest( $this->paramBase.".filter_order_Dir", 'filter_order_Dir',	'desc',	'word' );
		$pageInfo->limit->value = $app->getUserStateFromRequest( $this->paramBase.'.list_limit', 'limit', $app->getCfg('list_limit'), 'int' );
		if(empty($pageInfo->limit->value)) $pageInfo->limit->value = 500;
		if(JRequest::getVar('search')!=$app->getUserState($this->paramBase.".search")){
			$app->setUserState( $this->paramBase.'.limitstart',0);
			$pageInfo->limit->start = 0;
		}else{
			$pageInfo->limit->start = $app->getUserStateFromRequest( $this->paramBase.'.limitstart', 'limitstart', 0, 'int' );
		}
		$pageInfo->search = $app->getUserStateFromRequest( $this->paramBase.".search", 'search', '', 'string' );
		$pageInfo->filter->filter_status = $app->getUserStateFromRequest( $this->paramBase.".filter_status",'filter_status','','string');
		$pageInfo->filter->filter_payment = $app->getUserStateFromRequest( $this->paramBase.".filter_payment",'filter_payment','','string');
		$pageInfo->filter->filter_partner = $app->getUserStateFromRequest( $this->paramBase.".filter_partner",'filter_partner','','int');
		$pageInfo->filter->filter_end = $app->getUserStateFromRequest( $this->paramBase.".filter_end",'filter_end','','string');
		$pageInfo->filter->filter_start = $app->getUserStateFromRequest( $this->paramBase.".filter_start",'filter_start','','string');
		$pageInfo->filter->filter_product = JRequest::getInt('filter_product',0);
		$database	= JFactory::getDBO();
		$tables = '';
		$filters = array('b.order_type=\'sale\'');

		switch($pageInfo->filter->filter_status){
			case '':
				break;
			default:
				$filters[]='b.order_status = '.$database->Quote($pageInfo->filter->filter_status);
				break;
		}
		switch($pageInfo->filter->filter_start){
			case '':
				switch($pageInfo->filter->filter_end){
					case '':
						break;
					default:
						$filter_end=explode('-',$pageInfo->filter->filter_end);
						$noHourDay=explode(' ',$filter_end[2]);
						$filter_end[2]=$noHourDay[0];
						$filter_end= mktime(23, 59, 59, $filter_end[1], $filter_end[2], $filter_end[0]);
						$filters[]='b.order_created < '.$filter_end;
						break;
				}
				break;
			default:
				$filter_start=explode('-',$pageInfo->filter->filter_start);
				$noHourDay=explode(' ',$filter_start[2]);
				$filter_start[2]=$noHourDay[0];
				$filter_start= mktime(0, 0, 0, $filter_start[1], $filter_start[2], $filter_start[0]);
				switch($pageInfo->filter->filter_end){
					case '':
						$filters[]='b.order_created > '.$filter_start;
						break;
					default:
						$filter_end=explode('-',$pageInfo->filter->filter_end);
						$noHourDay=explode(' ',$filter_end[2]);
						$filter_end[2]=$noHourDay[0];
						$filter_end= mktime(23, 59, 59, $filter_end[1], $filter_end[2], $filter_end[0]);
						$filters[]='b.order_created > '.$filter_start. ' AND b.order_created < '.$filter_end;
						break;
				}
				break;
		}
		switch($pageInfo->filter->filter_payment){
			case '':
				break;
			default:
				$filters[]='b.order_payment_method = '.$database->Quote($pageInfo->filter->filter_payment);
				break;
		}
		$searchMap = array('c.id','c.username','c.name','a.user_email','b.order_user_id','b.order_number','b.order_id','b.order_full_price');
		foreach($fields as $field){
			$searchMap[]='b.'.$field->field_namekey;
		}

		$extrafilters = array();
		JPluginHelper::importPlugin('hikashop');
		if(hikashop_level(2))
			JPluginHelper::getPlugin('system', 'hikashopaffiliate');
		$dispatcher = JDispatcher::getInstance();
		$dispatcher->trigger('onBeforeOrderListing', array($this->paramBase, &$extrafilters, &$pageInfo, &$filters));
		$this->assignRef('extrafilters',$extrafilters);

		if(!empty($pageInfo->search)){
			$searchVal = '\'%'.hikashop_getEscaped(JString::strtolower( $pageInfo->search ),true).'%\'';
			$filter = implode(" LIKE $searchVal OR ",$searchMap)." LIKE $searchVal";
			$filters[] =  $filter;
		}
		if(!empty($pageInfo->filter->filter_product)){
			$tables = ' INNER JOIN '.hikashop_table('order_product').' AS d ON b.order_id = d.order_id INNER JOIN '.hikashop_table('product').' AS e ON (e.product_id = d.product_id OR (e.product_parent_id > 0 AND e.product_parent_id = d.product_id))';
			$filters[] = 'e.product_id = '.(int)$pageInfo->filter->filter_product.' OR e.product_parent_id = '.(int)$pageInfo->filter->filter_product;
		}
		$order = '';
		if(!empty($pageInfo->filter->order->value)){
			$order = ' ORDER BY '.$pageInfo->filter->order->value.' '.$pageInfo->filter->order->dir;
		}
		if(!empty($filters)){
			$filters = ' WHERE ('. implode(') AND (',$filters).')';
		}else{
			$filters = '';
		}
		$query = ' FROM '.hikashop_table('order').' AS b LEFT JOIN '.hikashop_table('user').' AS a ON b.order_user_id=a.user_id LEFT JOIN '.hikashop_table('users',false).' AS c ON a.user_cms_id=c.id '.$tables.$filters.$order;
		$database->setQuery('SELECT a.*,b.*,c.*'.$query,(int)$pageInfo->limit->start,(int)$pageInfo->limit->value);
		$rows = $database->loadObjectList();

		if(!empty($pageInfo->search)){
			$rows = hikashop_search($pageInfo->search,$rows,'order_id');
		}
		$database->setQuery('SELECT COUNT(*)'.$query);
		$pageInfo->elements = new stdClass();
		$pageInfo->elements->total = $database->loadResult();
		$pageInfo->elements->page = count($rows);
		if($pageInfo->limit->value == 500) $pageInfo->limit->value = 100;

		$pagination = hikashop_get('helper.pagination',$pageInfo->elements->total, $pageInfo->limit->start, $pageInfo->limit->value );
		hikashop_setTitle(JText::_($this->nameListing),$this->icon,$this->ctrl);

		$config =& hikashop_config();
		$manage = hikashop_isAllowed($config->get('acl_order_manage','all'));
		$this->assignRef('manage',$manage);
		$exportIcon = 'archive';
		if(HIKASHOP_J30) {
			$exportIcon = 'export';
		}
		$this->toolbar = array(
			array('name' => 'custom', 'icon' => $exportIcon, 'alt' => JText::_('HIKA_EXPORT'), 'task' => 'export', 'check' => false),
			array('name' => 'link', 'icon' => 'new', 'alt' => JText::_('HIKA_NEW'),'url' => hikashop_completeLink('order&task=neworder'),'display' => $manage),
			array('name'=> 'editList', 'display' => $manage),
			array('name'=> 'deleteList', 'display' => hikashop_isAllowed($config->get('acl_order_delete','all'))),
			'|',
			array('name' => 'pophelp', 'target' => $this->ctrl.'-listing'),
			'dashboard'
		);

		$toggleClass = hikashop_get('helper.toggle');
		$this->assignRef('toggleClass',$toggleClass);

		$this->assignRef('fields',$fields);
		$this->assignRef('fieldsClass',$fieldsClass);
		$fieldsClass->handleZoneListing($fields,$rows);
		$pluginClass = hikashop_get('class.plugins');
		$payments = $pluginClass->getMethods('payment');
		$newPayments = array();
		foreach($payments as $payment){
			$newPayments[$payment->payment_type] = $payment;
		}

		$this->assignRef('payments',$newPayments);
		$this->assignRef('rows',$rows);
		$this->assignRef('pageInfo',$pageInfo);
		$this->assignRef('pagination',$pagination);
		$currencyClass = hikashop_get('class.currency');
		$this->assignRef('currencyHelper',$currencyClass);
		$category = hikashop_get('type.categorysub');
		$category->type = 'status';
		$this->assignRef('category',$category);
		$payment = hikashop_get('type.payment');
		$this->assignRef('payment',$payment);
		$this->assignRef('popup',$popup);
		$popupHelper = hikashop_get('helper.popup');
		$this->assignRef('popupHelper',$popupHelper);
		$extrafields = array();
		$dispatcher->trigger('onAfterOrderListing', array(&$this->rows, &$extrafields, $pageInfo));
		$this->assignRef('extrafields',$extrafields);

	}

	function form(){
		$order_id = hikashop_getCID('order_id');
		$fieldsClass = hikashop_get('class.field');
		$fields = null;
		if(!empty($order_id)){
			$class = hikashop_get('class.order');
			$order = $class->loadFullOrder($order_id,true);
			if(hikashop_level(2)){
				$fields['order'] = $fieldsClass->getFields('backend',$order,'order');
				$null = null;
				$fields['entry'] = $fieldsClass->getFields('backend_listing',$null,'entry');
				$fields['item'] = $fieldsClass->getFields('backend_listing',$null,'item');
			}
			$task='edit';
		}
		if(empty($order)){
			$app = JFactory::getApplication();
			$app->redirect(hikashop_completeLink('order&task=listing',false,true));
		}
		$config =& hikashop_config();
		$order_status_for_download = $config->get('order_status_for_download','confirmed,shipped');
		$download_time_limit = $config->get('download_time_limit',0);
		$download_number_limit = $config->get('download_number_limit',0);
		$this->assignRef('order_status_for_download',$order_status_for_download);
		$this->assignRef('download_time_limit',$download_time_limit);
		$this->assignRef('download_number_limit',$download_number_limit);
		$this->assignRef('config',$config);

		$category = hikashop_get('type.categorysub');
		$category->type = 'status';
		$category->load(true);
		$this->assignRef('category',$category);

		$pluginsPayment = hikashop_get('type.plugins');
		$pluginsPayment->type='payment';
		$this->assignRef('payment',$pluginsPayment);

		$pluginsShipping = hikashop_get('type.plugins');
		$pluginsShipping->type='shipping';
		$this->assignRef('shipping',$pluginsShipping);

		$currencyClass = hikashop_get('class.currency');
		$this->assignRef('currencyHelper',$currencyClass);

		JPluginHelper::importPlugin( 'hikashop' );
		JPluginHelper::importPlugin( 'hikashoppayment' );
		JPluginHelper::importPlugin( 'hikashopshipping' );
		$dispatcher = JDispatcher::getInstance();
		$dispatcher->trigger( 'onHistoryDisplay', array( & $order->history) );

		$this->assignRef('order',$order);
		$this->assignRef('fields',$fields);
		$this->assignRef('fieldsClass',$fieldsClass);

		$user_id = JRequest::getInt('user_id',0);
		if(!empty($user_id)){
			$user_info='&user_id='.$user_id;
			$url = hikashop_completeLink('user&task=edit&user_id='.$user_id);
		}else{
			$user_info='';
			$cancel_url = JRequest::getVar('cancel_redirect');
			if(!empty($cancel_url)){
				$url = base64_decode($cancel_url);
			}else{
				$url = hikashop_completeLink('order');
			}
		}

		if(version_compare(JVERSION,'1.6','<')){
			$url_email = hikashop_completeLink('order&task=mail&order_id='.$order_id,true);
			$url_invoice = hikashop_completeLink('order&task=invoice&type=full&order_id='.$order_id,true);
			$url_shipping = hikashop_completeLink('order&task=invoice&type=shipping&order_id='.$order_id,true);
		} else {
			$url_email = 'index.php?option=com_hikashop&ctrl=order&task=mail&tmpl=component&order_id='.$order_id;
			$url_invoice = 'index.php?option=com_hikashop&ctrl=order&task=invoice&tmpl=component&type=full&order_id='.$order_id;
			$url_shipping = 'index.php?option=com_hikashop&ctrl=order&task=invoice&tmpl=component&type=shipping&order_id='.$order_id;
		}

		$this->toolbar = array(
			array('name' => 'Popup', 'icon' => 'send', 'id' => 'send', 'alt' => JText::_('HIKA_EMAIL'), 'url' => $url_email, 'width' => 720),
			array('name' => 'Popup', 'icon' => 'invoice', 'id' => 'invoice', 'alt' => JText::_('INVOICE'), 'url' => $url_invoice),
			array('name' => 'Popup', 'icon' => 'shipping', 'id' => 'shipping', 'alt' => JText::_('SHIPPING_INVOICE'), 'url' => $url_shipping),
			array('name' => 'Link', 'icon' => 'cancel', 'alt' => JText::_('HIKA_BACK'), 'url' => $url),
			'|',
			array('name' => 'pophelp', 'target' => $this->ctrl.'-form')
		);
		$popupHelper = hikashop_get('helper.popup');
		$this->assignRef('popup',$popupHelper);
		hikashop_setTitle(JText::_($this->nameForm),$this->icon,$this->ctrl.'&task='.$task.'&order_id='.$order_id.$user_info);
	}

	function changestatus(){
		$order_id = hikashop_getCID('order_id');
		$new_status = JRequest::getVar('status','');
		if(!empty($order_id)){
			$class = hikashop_get('class.order');
			$order = $class->get($order_id,$new_status);
			$order->order_old_status = $order->order_status;
			$order->order_status = $new_status;
			$class->loadOrderNotification($order);
		}else{
			$order = new stdClass();
		}

		$order->order_status = $new_status;

		$this->assignRef('element',$order);
		$editor = hikashop_get('helper.editor');
		$editor->name = 'hikashop_mail_body';
		$editor->content = $order->mail->body;
		$this->assignRef('editor',$editor);
	}

	function partner(){
		$order_id = hikashop_getCID('order_id');
		if(!empty($order_id)){
			$class = hikashop_get('class.order');
			$order = $class->loadNotification($order_id);
		}else{
			$order = new stdClass();
		}
		$this->assignRef('element',$order);
		$editor = hikashop_get('helper.editor');
		$editor->name = 'hikashop_mail_body';
		$editor->content = '';
		$order->mail->altbody='';
		$this->assignRef('editor',$editor);
		$partners = hikashop_get('type.partners');
		$this->assignRef('partners',$partners);
		$currencyType=hikashop_get('type.currency');
		$this->assignRef('currencyType',$currencyType);
	}

	function discount(){
		$order_id = hikashop_getCID('order_id');
		if(!empty($order_id)){
			$class = hikashop_get('class.order');
			$order = $class->loadNotification($order_id);
		}else{
			$order = new stdClass();
		}
		if(!empty($order->order_tax_info)){
			foreach($order->order_tax_info as $tax){
				if(isset($tax->tax_amount_for_coupon)){
					$order->order_discount_tax_namekey=$tax->tax_namekey;
					break;
				}
			}
		}
		$this->assignRef('element',$order);
		$editor = hikashop_get('helper.editor');
		$editor->name = 'hikashop_mail_body';
		$editor->content = '';
		$order->mail->altbody='';
		$this->assignRef('editor',$editor);
		$ratesType = hikashop_get('type.rates');
		$this->assignRef('ratesType',$ratesType);
	}

	function fields(){
		$order_id = hikashop_getCID('order_id');
		$fieldsClass = hikashop_get('class.field');
		$fields = null;
		if(!empty($order_id)){
			$class = hikashop_get('class.order');
			$order = $class->loadNotification($order_id);
			if(hikashop_level(2)){
				$fields['order'] = $fieldsClass->getFields('backend',$order,'order');
			}
		}else{
			$order = new stdClass();
		}
		$this->assignRef('element',$order);
		$this->assignRef('fields',$fields);
		$this->assignRef('fieldsClass',$fieldsClass);
		$editor = hikashop_get('helper.editor');
		$editor->name = 'hikashop_mail_body';
		$editor->content = '';
		$order->mail->altbody='';
		$this->assignRef('editor',$editor);
	}

	function changeplugin(){
		$order_id = hikashop_getCID('order_id');
		$new_status = JRequest::getVar('status','');
		if(!empty($order_id)){
			$class = hikashop_get('class.order');
			$order = $class->loadNotification($order_id);
		}else{
			$order = new stdClass();
		}

		if(!empty($order->order_tax_info)){
			foreach($order->order_tax_info as $tax){
				if(isset($tax->tax_amount_for_shipping)){
					$order->order_shipping_tax_namekey=$tax->tax_namekey;
					break;
				}
			}
		}
		$this->assignRef('element',$order);
		$editor = hikashop_get('helper.editor');
		$editor->name = 'hikashop_mail_body';
		$editor->content = '';
		$order->mail->altbody='';
		$this->assignRef('editor',$editor);
		$pluginsPayment = hikashop_get('type.plugins');
		$pluginsPayment->type=JRequest::getWord('type');
		$this->assignRef($pluginsPayment->type,$pluginsPayment);
		$this->assignRef('type',$pluginsPayment->type);
		$full_id = JRequest::getCmd('plugin');
		$this->assignRef('full_id',$full_id);
		$parts = explode('_',$full_id);
		$id = array_pop($parts);
		$this->assignRef('id',$id);
		$method = implode('_',$parts);
		$this->assignRef('method',$method);
		$ratesType = hikashop_get('type.rates');
		$this->assignRef('ratesType',$ratesType);
	}

	function mail(){
		$element = new stdClass();
		$element->order_id = JRequest::getInt('order_id',0);

		if(empty($element->order_id)){
			$user_id = JRequest::getInt('user_id',0);
			$userClass = hikashop_get('class.user');
			$element->customer = $userClass->get($user_id);
			$mailClass = hikashop_get('class.mail');
			$element->mail = new stdClass();
			$element->mail->body='';
			$element->mail->altbody='';
			$element->mail->html=1;
			$mailClass->loadInfos($element->mail, 'user_notification');
			$element->mail->dst_email =& $element->customer->user_email;
			if(!empty($element->customer->name)){
				$element->mail->dst_name =& $element->customer->name;
			}else{
				$element->mail->dst_name = '';
			}
		}else{
			$orderClass = hikashop_get('class.order');
			$orderClass->loadMail($element);
		}
		$editor = hikashop_get('helper.editor');
		$editor->name = 'hikashop_mail_body';
		$editor->content = $element->mail->body;
		$this->assignRef('editor',$editor);
		$this->assignRef('element',$element);
	}

	function export(){
		$ids = JRequest::getVar( 'cid', array(), '', 'array' );
		$fieldsClass = hikashop_get('class.field');
		$fields = $fieldsClass->getData('all','order',false);
		$database	= JFactory::getDBO();
		$filters = array('b.order_type=\'sale\'');

		if(empty($ids)){
			$app = JFactory::getApplication();
			$search = $app->getUserStateFromRequest( $this->paramBase.".search", 'search', '', 'string' );
			$filter_status = $app->getUserStateFromRequest( $this->paramBase.".filter_status",'filter_status','','string');
			$filter_payment = $app->getUserStateFromRequest( $this->paramBase.".filter_payment",'filter_payment','','string');
			$filter_partner = $app->getUserStateFromRequest( $this->paramBase.".filter_partner",'filter_partner','','int');

			if(!empty($filter_partner)){
				if($filter_partner==1){
					$filters[]='b.order_partner_id != 0';
				}else{
					$filters[]='b.order_partner_id = 0';
				}
			}
			switch($filter_status){
				case '':
					break;
				default:
					$filters[]='b.order_status = '.$database->Quote($filter_status);
					break;
			}
			switch($filter_payment){
				case '':
					break;
				default:
					$filters[]='b.order_payment_method = '.$database->Quote($filter_payment);
					break;
			}
			$searchMap = array('c.id','c.username','c.name','a.user_email','b.order_user_id','b.order_id','b.order_full_price');
			foreach($fields as $field){
				$searchMap[]='b.'.$field->field_namekey;
			}
			if(!empty($pageInfo->search)){
				$searchVal = '\'%'.hikashop_getEscaped(JString::strtolower( $pageInfo->search ),true).'%\'';
				$id = hikashop_decode($pageInfo->search);
				$filter = implode(" LIKE $searchVal OR ",$searchMap)." LIKE $searchVal";
				if(!empty($id)){
					$filter .= " OR b.order_id LIKE '%".hikashop_getEscaped($id,true).'%\'';
				}
				$filters[] =  $filter;
			}
		}else{
			JArrayHelper::toInteger($ids,0);
			$filters[] =  'b.order_id IN ('.implode(',',$ids).')';
		}
		$filters = implode(' AND ', $filters);

		$query = ' FROM '.hikashop_table('order').' AS b LEFT JOIN '.hikashop_table('user').' AS a ON b.order_user_id=a.user_id LEFT JOIN '.hikashop_table('users',false).' AS c ON a.user_cms_id=c.id WHERE '.$filters;
		$database->setQuery('SELECT a.*,b.*,c.*'.$query);

		$rows = $database->loadObjectList('order_id');
		if(!empty($rows)){
			$addressIds = array();
			foreach($rows as $k => $row){
				$rows[$k]->products = array();
				$addressIds[$row->order_shipping_address_id]=$row->order_shipping_address_id;
				$addressIds[$row->order_billing_address_id]=$row->order_billing_address_id;
			}
			if(!empty($addressIds)){
				$database->setQuery('SELECT * FROM '.hikashop_table('address').' WHERE address_id IN ('.implode(',',$addressIds).')');
				$addresses = $database->loadObjectList('address_id');
				if(!empty($addresses)){
					$zoneNamekeys = array();
					foreach($addresses as $address){
						$zoneNamekeys[$address->address_country]=$database->Quote($address->address_country);
						$zoneNamekeys[$address->address_state]=$database->Quote($address->address_state);
					}
					if(!empty($zoneNamekeys)){
						$database->setQuery('SELECT zone_namekey,zone_name FROM '.hikashop_table('zone').' WHERE zone_namekey IN ('.implode(',',$zoneNamekeys).')');
						$zones = $database->loadObjectList('zone_namekey');
						if(!empty($zones)){
							foreach($addresses as $i => $address){
								if(!empty($zones[$address->address_country])){
									$addresses[$i]->address_country = $zones[$address->address_country]->zone_name;
								}
								if(!empty($zones[$address->address_state])){
									$addresses[$i]->address_state = $zones[$address->address_state]->zone_name;
								}
							}
						}
					}
					$fields = array_keys(get_object_vars(reset($addresses)));
					foreach($rows as $k => $row){
						if(!empty($addresses[$row->order_shipping_address_id])){
							foreach($addresses[$row->order_shipping_address_id] as $key => $val){
								$key = 'shipping_'.$key;
								$rows[$k]->$key = $val;
							}
						}else{
							foreach($fields as $field){
								$key = 'shipping_'.$field;
								$rows[$k]->$key = '';
							}
						}
						if(!empty($addresses[$row->order_billing_address_id])){
							foreach($addresses[$row->order_billing_address_id] as $key => $val){
								$key = 'billing_'.$key;
								$rows[$k]->$key = $val;
							}
						}else{
							foreach($fields as $field){
								$key = 'billing_'.$field;
								$rows[$k]->$key = '';
							}
						}
					}
				}
			}
			$orderIds = array_keys($rows);
			$database->setQuery('SELECT * FROM '.hikashop_table('order_product').' WHERE order_id IN ('.implode(',',$orderIds).')');
			$products = $database->loadObjectList();

			foreach($products as $product){
				$order =& $rows[$product->order_id];
				$order->products[] = $product;
				if(!isset($order->order_full_tax)){
					$order->order_full_tax=0;
				}
				$order->order_full_tax+=round($product->order_product_quantity*$product->order_product_tax,2);
			}
			foreach($rows as $k => $row){
				$rows[$k]->order_full_tax+=$row->order_shipping_tax-$row->order_discount_tax;
			}
		}
		JPluginHelper::importPlugin( 'hikashop' );
		$dispatcher = JDispatcher::getInstance();
		$dispatcher->trigger( 'onBeforeOrderExport', array( & $rows, &$this) );
		$this->assignRef('orders',$rows);
	}

	function invoice(){
		$order_id = hikashop_getCID('order_id');
		$fieldsClass = hikashop_get('class.field');
		$fields = array();
		if(!empty($order_id)){
			$class = hikashop_get('class.order');
			$order = $class->loadFullOrder($order_id);
			$null = null;
			$fields['item'] = $fieldsClass->getFields('backend_listing',$null,'item');
			$task='edit';
		}else{
			$order = new stdClass();
			$task='add';
		}
		$config =& hikashop_config();
		$store = str_replace(array("\r\n","\n","\r"),array('<br/>','<br/>','<br/>'),$config->get('store_address',''));
		$this->assignRef('store_address',$store);
		$this->assignRef('element',$order);
		$this->assignRef('order',$order);
		$this->assignRef('fields',$fields);

		if(!empty($order->order_payment_id)){
			$pluginsPayment = hikashop_get('type.plugins');
			$pluginsPayment->type='payment';
			$this->assignRef('payment',$pluginsPayment);
		}
		if(!empty($order->order_shipping_id)){
			$pluginsShipping = hikashop_get('type.plugins');
			$pluginsShipping->type='shipping';
			$this->assignRef('shipping',$pluginsShipping);
		}

		$type = JRequest::getWord('type');
		$this->assignRef('invoice_type',$type);
		$nobutton = true;
		$this->assignRef('nobutton',$nobutton);
		$display_type = 'frontcomp';
		$this->assignRef('display_type',$display_type);
		$currencyClass = hikashop_get('class.currency');
		$this->assignRef('currencyHelper',$currencyClass);
		$config =& hikashop_config();
		$this->assignRef('config',$config);
		$this->assignRef('fieldsClass',$fieldsClass);
	}

	function product(){
		$product_id = hikashop_getCID('product_id');
		$orderClass = hikashop_get('class.order');
		if(!empty($product_id)){
			$class = hikashop_get('class.order_product');
			$product = $class->get($product_id);
		}else{
			$product = new stdClass();
			$product->order_id = JRequest::getInt('order_id');
			$product->mail = new stdClass();
			$product->mail->body = '';
		}

		$orderClass->loadMail($product);

		if(!empty($product->order_product_tax_info)){
			$tax = reset($product->order_product_tax_info);
			$product->tax_namekey = $tax->tax_namekey;
		}

		$this->assignRef('element',$product);

		$editor = hikashop_get('helper.editor');
		$editor->name = 'hikashop_mail_body';
		$editor->content = $product->mail->body;
		$this->assignRef('editor',$editor);
		$extraFields=array();
		$fieldsClass = hikashop_get('class.field');
		$this->assignRef('fieldsClass',$fieldsClass);
		$null=null;
		$extraFields['item'] = $fieldsClass->getFields('backend',$null,'item','user&task=state');
		$this->assignRef('extraFields',$extraFields);
		$ratesType = hikashop_get('type.rates');
		$this->assignRef('ratesType',$ratesType);
	}
	function user(){
		$element = new stdClass();
		$element->order_id = JRequest::getInt('order_id');
		$element->mail = new stdClass();
		$element->mail->body = '';
		$orderClass = hikashop_get('class.order');
		$orderClass->loadMail($element);
		$this->assignRef('element',$element);
		$editor = hikashop_get('helper.editor');
		$editor->name = 'hikashop_mail_body';
		$editor->content = $element->mail->body;
		$this->assignRef('editor',$editor);
	}

	function product_delete(){
		$product_id = hikashop_getCID('product_id');
		$orderClass = hikashop_get('class.order');
		if(!empty($product_id)){
			$class = hikashop_get('class.order_product');
			$product = $class->get($product_id);
			$orderClass->loadMail($product);
			$this->assignRef('element',$product);
			$editor = hikashop_get('helper.editor');
			$editor->name = 'hikashop_mail_body';
			$editor->content = $product->mail->body;
			$this->assignRef('editor',$editor);
		}
	}

	function address(){
		$address_id = hikashop_getCID('address_id');
		$address_type = JRequest::getCmd('type');
		$fieldsClass = hikashop_get('class.field');
		$orderClass = hikashop_get('class.order');
		$order = new stdClass();
		$order->order_id = JRequest::getInt('order_id');
		$addressClass=hikashop_get('class.address');
		$name = $address_type.'_address';
		if(!empty($address_id)){
			$order->$name=$addressClass->get($address_id);
		}
		$fieldClass = hikashop_get('class.field');
		$order->fields = $fieldClass->getData('backend','address');
		$orderClass->loadMail($order);
		$name = $address_type.'_address';
		$fieldsClass->prepareFields($order->fields,$order->$name,'address','field&task=state');

		$this->assignRef('fieldsClass',$fieldsClass);

		$this->assignRef('element',$order);
		$this->assignRef('type',$address_type);
		$this->assignRef('id',$address_id);
		$editor = hikashop_get('helper.editor');
		$editor->name = 'hikashop_mail_body';
		$editor->content = $order->mail->body;
		$this->assignRef('editor',$editor);
	}
	function product_select(){
		$app = JFactory::getApplication();
		$config =& hikashop_config();
		$this->assignRef('config',$config);
		$this->paramBase.="_product_select";
		$element = new stdClass();
		$element->order_id = JRequest::getInt('order_id');
		$this->assignRef('element',$element);
		$this->paramBase.="_product_select";
		$pageInfo = new stdClass();
		$pageInfo->filter = new stdClass();
		$pageInfo->filter->order = new stdClass();
		$pageInfo->limit = new stdClass();
		$pageInfo->filter->order->value = $app->getUserStateFromRequest( $this->paramBase.".filter_order", 'filter_order',	'a.ordering','cmd' );
		$pageInfo->filter->order->dir	= $app->getUserStateFromRequest( $this->paramBase.".filter_order_Dir", 'filter_order_Dir',	'asc',	'word' );
		if(JRequest::getVar('search')!=$app->getUserState($this->paramBase.".search")){
			$app->setUserState( $this->paramBase.'.limitstart',0);
			$pageInfo->limit->start = 0;
		}else{
			$pageInfo->limit->start = $app->getUserStateFromRequest( $this->paramBase.'.limitstart', 'limitstart', 0, 'int' );
		}
		$pageInfo->search = $app->getUserStateFromRequest( $this->paramBase.".search", 'search', '', 'string' );
		$pageInfo->search = JString::strtolower( $pageInfo->search );
		$pageInfo->limit->value = $app->getUserStateFromRequest( $this->paramBase.'.list_limit', 'limit', $app->getCfg('list_limit'), 'int' );
		if(empty($pageInfo->limit->value)) $pageInfo->limit->value = 500;
		$selectedType = $app->getUserStateFromRequest( $this->paramBase.".filter_type",'filter_type',0,'int');
		$pageInfo->filter->filter_id = $app->getUserStateFromRequest( $this->paramBase.".filter_id",'filter_id',0,'string');
		$pageInfo->filter->filter_product_type = $app->getUserStateFromRequest( $this->paramBase.".filter_product_type",'filter_product_type','main','word');
		$database = JFactory::getDBO();
		$filters = array();
		$searchMap = array('b.product_name','b.product_description','b.product_id','b.product_code');
		if(empty($pageInfo->filter->filter_id)|| !is_numeric($pageInfo->filter->filter_id)){
			$pageInfo->filter->filter_id='product';
			$class = hikashop_get('class.category');
			$class->getMainElement($pageInfo->filter->filter_id);
		}
		if(!empty($pageInfo->search)){
			$searchVal = '\'%'.hikashop_getEscaped($pageInfo->search,true).'%\'';
			$filters[] = implode(" LIKE $searchVal OR ",$searchMap)." LIKE $searchVal";
		}
		$order = '';
		if(!$selectedType){
			$filters[]='a.category_id='.(int)$pageInfo->filter->filter_id;
			$select='SELECT a.ordering, b.*';
		}else{
			$categoryClass = hikashop_get('class.category');
			$categoryClass->parentObject =& $this;
			$childs = $categoryClass->getChilds((int)$pageInfo->filter->filter_id,true,array(),'',0,0);
			$filter = 'a.category_id IN (';
			foreach($childs as $child){
				$filter .= $child->category_id.',';
			}
			$filters[]=$filter.(int)$pageInfo->filter->filter_id.')';
			$select='SELECT DISTINCT b.*';
		}
		if($pageInfo->filter->filter_product_type=='all'){
			if(!empty($pageInfo->filter->order->value)){
				$select.=','.$pageInfo->filter->order->value.' as sorting_column';
				$order = ' ORDER BY sorting_column '.$pageInfo->filter->order->dir;
			}
		}else{
			if(!empty($pageInfo->filter->order->value)){
				$order = ' ORDER BY '.$pageInfo->filter->order->value.' '.$pageInfo->filter->order->dir;
			}
		}
		JPluginHelper::importPlugin( 'hikashop' );
		$dispatcher = JDispatcher::getInstance();
		$dispatcher->trigger( 'onBeforeProductListingLoad', array( & $filters, & $order, &$this, & $select, & $select2, & $a, & $b, & $on) );
		if($pageInfo->filter->filter_product_type=='all'){
			$query = '( '.$select.' FROM '.hikashop_table('product_category').' AS a LEFT JOIN '.hikashop_table('product').' AS b ON a.product_id=b.product_id WHERE '.implode(' AND ',$filters).' AND b.product_id IS NOT NULL )
			UNION
						( '.$select.' FROM '.hikashop_table('product_category').' AS a LEFT JOIN '.hikashop_table('product').' AS b ON a.product_id=b.product_parent_id WHERE '.implode(' AND ',$filters).' AND b.product_parent_id IS NOT NULL ) ';
			$database->setQuery($query.$order,(int)$pageInfo->limit->start,(int)$pageInfo->limit->value);
		}else{
			$filters[]='b.product_type = '.$database->Quote($pageInfo->filter->filter_product_type);
			if($pageInfo->filter->filter_product_type!='variant'){
				$lf = 'a.product_id=b.product_id';
			}else{
				$lf = 'a.product_id=b.product_parent_id';
			}
			$query = ' FROM '.hikashop_table('product_category').' AS a LEFT JOIN '.hikashop_table('product').' AS b ON '.$lf.' WHERE '.implode(' AND ',$filters);
			$database->setQuery($select.$query.$order,(int)$pageInfo->limit->start,(int)$pageInfo->limit->value);
		}
		$rows = $database->loadObjectList();
		if(!empty($pageInfo->search)){
			$rows = hikashop_search($pageInfo->search,$rows,'product_id');
		}
		if($pageInfo->filter->filter_product_type=='all'){
			$database->setQuery('SELECT COUNT(*) FROM ('.$query.') as u');
		}else{
			$database->setQuery('SELECT COUNT(DISTINCT(b.product_id))'.$query);
		}
		$pageInfo->elements = new stdClass();
		$pageInfo->elements->total = $database->loadResult();
		$pageInfo->elements->page = count($rows);
		if($pageInfo->elements->page){
			$this->_loadPrices($rows);
		}

		if($pageInfo->limit->value == 500) $pageInfo->limit->value = 100;

		$pagination = hikashop_get('helper.pagination',$pageInfo->elements->total, $pageInfo->limit->start, $pageInfo->limit->value );
		$this->assignRef('pagination',$pagination);
		$toggleClass = hikashop_get('helper.toggle');
		$this->assignRef('toggleClass',$toggleClass);
		$childClass = hikashop_get('type.childdisplay');
		$childDisplay = $childClass->display('filter_type',$selectedType,false);
		$this->assignRef('childDisplay',$childDisplay);
		$productClass = hikashop_get('type.product');
		$this->assignRef('productType',$productClass);
		$breadcrumbClass = hikashop_get('type.breadcrumb');
		$breadCrumb = $breadcrumbClass->display('filter_id',$pageInfo->filter->filter_id,'product');
		$this->assignRef('breadCrumb',$breadCrumb);
		$this->assignRef('rows',$rows);
		$this->assignRef('pageInfo',$pageInfo);
		$currencyClass = hikashop_get('class.currency');
		$this->assignRef('currencyHelper',$currencyClass);
		$doOrdering = !$selectedType;
		if($doOrdering && !(empty($pageInfo->filter->filter_product_type) || $pageInfo->filter->filter_product_type=='main')){
			$doOrdering=false;
		}
		$this->assignRef('doOrdering',$doOrdering);
		if($doOrdering){
			$order = new stdClass();
			$order->ordering = false;
			$order->orderUp = 'orderup';
			$order->orderDown = 'orderdown';
			$order->reverse = false;
			if($pageInfo->filter->order->value == 'a.ordering'){
				$order->ordering = true;
				if($pageInfo->filter->order->dir == 'desc'){
					$order->orderUp = 'orderdown';
					$order->orderDown = 'orderup';
					$order->reverse = true;
				}
			}
			$this->assignRef('order',$order);
		}
	}
	function _loadPrices(&$rows){
		$ids = array();
		foreach($rows as $row){
			$ids[]=(int)$row->product_id;
		}
		$query = 'SELECT * FROM '.hikashop_table('price').' WHERE price_product_id IN ('.implode(',',$ids).')';
		$database = JFactory::getDBO();
		$database->setQuery($query);
		$prices = $database->loadObjectList();
		if(!empty($prices)){
			foreach($prices as $price){
				foreach($rows as $k => $row){
					if($price->price_product_id==$row->product_id){
						if(!isset($row->prices)) $row->prices=array();
						$rows[$k]->prices[]=$price;
						break;
					}
				}
			}
		}
	}

}
