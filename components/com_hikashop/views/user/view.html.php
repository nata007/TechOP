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
class userViewUser extends HikaShopView {

	var $extraFields=array();
	var $requiredFields = array();
	var	$validMessages = array();

	function display($tpl = null){
		$function = $this->getLayout();
		if(method_exists($this,$function)) $this->$function();
		parent::display($tpl);
	}

	function after_register(){

	}

	function cpanel(){
		$config =& hikashop_config();
		global $Itemid;
		$url_itemid='';
		if(!empty($Itemid)){
			$url_itemid='&Itemid='.$Itemid;
		}
		$buttons = array();
		$buttons[] = array('link'=>hikashop_completeLink('address'.$url_itemid),'level'=>0,'image'=>'user','text'=>JText::_('ADDRESSES'),'description'=>'<ul><li>'.JText::_('MANAGE_ADDRESSES').'</li></ul>');
		$buttons[] = array('link'=>hikashop_completeLink('order'.$url_itemid),'level'=>0,'image'=>'order','text'=>JText::_('ORDERS'),'description'=>'<ul><li>'.JText::_('VIEW_ORDERS').'</li></ul>');
		if(hikashop_level(1)){
			if($config->get('enable_multicart'))
			$buttons[] = array('link'=>hikashop_completeLink('cart&task=showcarts&cart_type=cart'.$url_itemid),'level'=>0,'image'=>'cart','text'=>JText::_('CARTS'),'description'=>'<ul><li>'.JText::_('DISPLAY_THE_CARTS').'</li></ul>');
			if($config->get('enable_wishlist'))
			$buttons[] = array('link'=>hikashop_completeLink('cart&task=showcarts&cart_type=wishlist'.$url_itemid),'level'=>0,'image'=>'wishlist','text'=>JText::_('WISHLISTS'),'description'=>'<ul><li>'.JText::_('DISPLAY_THE_WISHLISTS').'</li></ul>');
		}
		JPluginHelper::importPlugin( 'hikashop' );
		JPluginHelper::importPlugin( 'hikashoppayment' );
		JPluginHelper::importPlugin( 'hikashopshipping' );
		$dispatcher = JDispatcher::getInstance();
		$dispatcher->trigger( 'onUserAccountDisplay', array( & $buttons) );

		$this->assignRef('buttons',$buttons);
		if(!HIKASHOP_PHP5) {
			$app =& JFactory::getApplication();
			$pathway =& $app->getPathway();
		} else {
			$app = JFactory::getApplication();
			$pathway = $app->getPathway();
		}
		$items = $pathway->getPathway();
		if(!count($items))
			$pathway->addItem(JText::_('CUSTOMER_ACCOUNT'),hikashop_completeLink('user'));
	}

	function form(){
		$this->registration();
	}
	function registration(){
		global $Itemid;
		$url_itemid='';
		if(!empty($Itemid)){
			$url_itemid='&Itemid='.$Itemid;
		}
		$mainUser = JFactory::getUser();
		$data = JRequest::getVar('main_user_data',null);
		if(!empty($data)){
			foreach($data as $key => $val){
				$mainUser->$key = $val;
			}
		}

		$this->assignRef('mainUser',$mainUser);
		$lang = JFactory::getLanguage();
		$lang->load('com_user',JPATH_SITE);
		$user_id = hikashop_loadUser();

		JHTML::_('behavior.formvalidation');

		$user = @$_SESSION['hikashop_user_data'];
		$address = @$_SESSION['hikashop_address_data'];
		$fieldsClass = hikashop_get('class.field');
		$this->assignRef('fieldsClass',$fieldsClass);
		$fieldsClass->skipAddressName=true;

		$extraFields['user'] = $fieldsClass->getFields('frontcomp',$user,'user');
		$extraFields['address'] = $fieldsClass->getFields('frontcomp',$address,'address');
		$this->assignRef('extraFields',$extraFields);
		$this->assignRef('user',$user);
		$this->assignRef('address',$address);

		$config =& hikashop_config();
		$simplified_reg = $config->get('simplified_registration',1);
		$this->assignRef('config',$config);
		$this->assignRef('simplified_registration',$simplified_reg);

		$null=array();
		$fieldsClass->addJS($null,$null,$null);
		$fieldsClass->jsToggle($this->extraFields['user'],$user,0);
		$fieldsClass->jsToggle($this->extraFields['address'],$address,0);

		$values = array('address'=>$address,'user'=>$user);
		$fieldsClass->checkFieldsForJS($this->extraFields,$this->requiredFields,$this->validMessages,$values);

		$main = array('name','username','email','password','password2');
		if($simplified_reg && $simplified_reg !=3){
			$main = array('email');
		}
		else if ($simplified_reg == 3) {
			$main = array('email','password','password2');
		}

		if($config->get('show_email_confirmation_field')){
			$main[] = 'email_confirm';
		}

		foreach($main as $field){
			$this->requiredFields['register'][] = $field;
			if($field=='name')$field = 'HIKA_USER_NAME';
			if($field=='username')$field = 'HIKA_USERNAME';
			if($field=='email')$field = 'HIKA_EMAIL';
			if($field=='email_confirm')$field = 'HIKA_EMAIL_CONFIRM';
			if($field=='password')$field = 'HIKA_PASSWORD';
			if($field=='password2')$field = 'HIKA_PASSWORD2';
			$this->validMessages['register'][] = addslashes(JText::sprintf('FIELD_VALID',$fieldsClass->trans($field)));
		}
		$fieldsClass->addJS($this->requiredFields,$this->validMessages,array('register','user','address'));
		jimport('joomla.html.parameter');
		$params=new HikaParameter('');
		$class = hikashop_get('helper.cart');
		$this->assignRef('url_itemid',$url_itemid);
		$this->assignRef('params',$params);
		$this->assignRef('cartClass',$class);

		$affiliate = $config->get( 'affiliate_registration_default',0);
		if($affiliate){
			$affiliate = 'checked="checked"';
		}else{
			$affiliate = '';
		}
		$this->assignRef('affiliate_checked',$affiliate);
	}
}
