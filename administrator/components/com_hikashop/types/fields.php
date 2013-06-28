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

class hikashopFieldsType{
	var $allValues;
	var $externalValues;

	function __construct() {
		$this->externalValues = null;
	}

	function load($type=''){
		$this->allValues = array();
		$this->allValues["text"] = JText::_('FIELD_TEXT');
		$this->allValues["link"] = JText::_('LINK');
		$this->allValues["textarea"] = JText::_('FIELD_TEXTAREA');
		$this->allValues["radio"] = JText::_('FIELD_RADIO');
		$this->allValues["checkbox"] = JText::_('FIELD_CHECKBOX');
		$this->allValues["singledropdown"] = JText::_('FIELD_SINGLEDROPDOWN');
		$this->allValues["multipledropdown"] = JText::_('FIELD_MULTIPLEDROPDOWN');
		$this->allValues["date"] = JText::_('FIELD_DATE');
		$this->allValues["zone"] = JText::_('FIELD_ZONE');
		if(hikashop_level(2)){
			if($type=='entry'|| empty($type)){
				$this->allValues["coupon"] = JText::_('HIKASHOP_COUPON');
			}
			$this->allValues["file"] = JText::_('HIKA_FILE');
			$this->allValues["image"] = JText::_('HIKA_IMAGE');
		}
		$this->allValues["customtext"] = JText::_('CUSTOM_TEXT');

		if($this->externalValues == null) {
			$this->externalValues = array();
			JPluginHelper::importPlugin('hikashop');
			$dispatcher = JDispatcher::getInstance();
			$dispatcher->trigger('onFieldsLoad', array( &$this->externalValues ) );

			if(!empty($this->externalValues)) {
				foreach($this->externalValues as $value) {
					if(substr($value->name,0,4) != 'plg.')
						$value->name = 'plg.'.$value->name;
					$this->allValues[$value->name] = $value->text;
				}
			}
		}
	}
	function addJS(){
		$externalJS = '';
		if(!empty($this->externalValues)){
			foreach($this->externalValues as $value) {
				$externalJS .= "\r\n\t\t\t".$value->js;
			}
		}
		$js = "function updateFieldType(){
			newType = document.getElementById('fieldtype').value;
			hiddenAll = new Array('multivalues','cols','rows','size','required','format','zone','coupon','default','customtext','columnname','filtering','maxlength','allow','readonly');
			allTypes = new Array();
			allTypes['text'] = new Array('size','required','default','columnname','filtering','maxlength','readonly');
			allTypes['link'] = new Array('size','required','default','columnname','filtering','maxlength','readonly');
			allTypes['textarea'] = new Array('cols','rows','required','default','columnname','filtering','readonly','maxlength');
			allTypes['radio'] = new Array('multivalues','required','default','columnname');
			allTypes['checkbox'] = new Array('multivalues','required','default','columnname');
			allTypes['singledropdown'] = new Array('multivalues','required','default','columnname');
			allTypes['multipledropdown'] = new Array('multivalues','size','default','columnname');
			allTypes['date'] = new Array('required','format','size','default','columnname','allow');
			allTypes['zone'] = new Array('required','zone','default','columnname');
			allTypes['file'] = new Array('required','default','columnname');
			allTypes['image'] = new Array('required','default','columnname');
			allTypes['coupon'] = new Array('size','required','default','columnname');
			allTypes['customtext'] = new Array('customtext');".$externalJS."
			for (var i=0; i < hiddenAll.length; i++){
				$$('tr[class='+hiddenAll[i]+']').each(function(el) {
					el.style.display = 'none';
				});
			}
			for (var i=0; i < allTypes[newType].length; i++){
				$$('tr[class='+allTypes[newType][i]+']').each(function(el) {
					el.style.display = '';
				});
			}
		}
		window.addEvent('domready', function(){ updateFieldType(); });";
		$doc = JFactory::getDocument();
		$doc->addScriptDeclaration( $js );
	}

	function display($map,$value,$type){
		$this->load($type);
		$this->addJS();

		$this->values = array();
		foreach($this->allValues as $oneType => $oneVal){
			$this->values[] = JHTML::_('select.option', $oneType,$oneVal);
		}

		return JHTML::_('select.genericlist', $this->values, $map , 'size="1" onchange="updateFieldType();"', 'value', 'text', (string) $value,'fieldtype');
	}
}
