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
class hikashopOrderType{
	function load($type,$value=''){
		$filter=false;
		if($type=='product_filter'){
			$type='product';
			$filter=true;
		}
		$query = 'SELECT * FROM '.hikashop_table($type).' LIMIT 1';
		$database = JFactory::getDBO();
		$database->setQuery($query);
		$arr = $database->loadAssoc();
		$object = new stdClass();
		if(!empty($arr)){
			if(!is_array($value)&& !isset($arr[$value])){
				$arr[$value]=$value;
			}
			ksort($arr);
			foreach($arr as $key => $value){
				if(!empty($key)) $object->$key = $value;
			}
		}

		$this->values = array();
		if($type=='product'){
			if(!$filter){
				$this->values[] = JHTML::_('select.option', 'ordering',JText::_('ORDERING'));
			}else{
				$this->values[] = JHTML::_('select.option', 'all','all');
			}
		}
		if(!empty($object)){
			foreach(get_object_vars($object) as $key => $val){
				$this->values[] = JHTML::_('select.option', $key,$key);
			}
			if(JRequest::getCmd('from_display',false) == false)
				$this->values[] = JHTML::_('select.option', 'inherit',JText::_('HIKA_INHERIT'));
		}
	}
	function display($map,$value,$type,$options='class="inputbox" size="1"'){
		$this->load($type,$value);
		return JHTML::_('select.genericlist',   $this->values, $map, $options, 'value', 'text', $value );
	}
}
