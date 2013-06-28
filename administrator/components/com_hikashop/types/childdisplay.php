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
class hikashopChilddisplayType{
	function load($show_inherit = true){
		$this->values = array();
		$this->values[] = JHTML::_('select.option', 0,JText::_('DIRECT_SUB_ELEMENTS') );
		$this->values[] = JHTML::_('select.option', 1,JText::_('ALL_SUB_ELEMENTS'));
		if($show_inherit && JRequest::getCmd('from_display',false) == false)
			$this->values[] = JHTML::_('select.option', 2,JText::_('HIKA_INHERIT'));
	}
	function display($map,$value,$form=true,$show_inherit=true){
		$this->load($show_inherit);
		$options = 'class="inputbox" size="1" ';
		if(!$form){
			$options .= 'onchange="this.form.submit();"';
		}
		return JHTML::_('select.genericlist',   $this->values, $map, $options, 'value', 'text', (int)$value );
	}
}
