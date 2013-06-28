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
class DiscountController extends hikashopController{
	var $toggle = array('discount_published'=>'discount_id');
	var $type='discount';

	function __construct($config = array()) {
		parent::__construct($config);
		$this->modify_views[]='select_coupon';
		$this->modify_views[]='add_coupon';
		$this->modify[]='copy';
	}

	function copy(){
		$discounts = JRequest::getVar( 'cid', array(), '', 'array' );
		$result = true;
		if(!empty($discounts)){
			$discountClass = hikashop_get('class.discount');
			foreach($discounts as $discount){
				$data = $discountClass->get($discount);
				if($data){
					unset($data->discount_id);
					$data->discount_code = $data->discount_code.'_copy'.rand();
					if(!$discountClass->save($data)){
						$result=false;
					}
				}
			}
		}
		if($result){
			$app =& JFactory::getApplication();
			$app->enqueueMessage(JText::_( 'HIKASHOP_SUCC_SAVED' ), 'success');
			return $this->listing();
		}
		return $this->form();
	}

	function select_coupon(){
		JRequest::setVar( 'layout', 'select_coupon'  );
		return parent::display();
	}

	function add_coupon(){
		JRequest::setVar( 'layout', 'add_coupon'  );
		return parent::display();
	}
}
