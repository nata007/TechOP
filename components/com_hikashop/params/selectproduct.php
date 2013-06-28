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
class JElementSelectproduct extends JElement{
	function fetchElement($name, $value, &$node, $control_name)
	{
		if(!include_once(rtrim(JPATH_ADMINISTRATOR,DS).DS.'components'.DS.'com_hikashop'.DS.'helpers'.DS.'helper.php')){
			echo 'HikaShop is required';
			return;
		}

		$class = hikashop_get('class.product');
		$popup = hikashop_get('helper.popup');
		$product = $class->get($value);

		if($product && $product->product_type=='variant'){
			$db = JFactory::getDBO();
			$db->setQuery('SELECT * FROM '.hikashop_table('variant').' AS a LEFT JOIN '.hikashop_table('characteristic') .' AS b ON a.variant_characteristic_id=b.characteristic_id WHERE a.variant_product_id='.(int)$product->product_id.' ORDER BY a.ordering');
			$product->characteristics = $db->loadObjectList();
			$parentProduct = $class->get((int)$product->product_parent_id);
			$class->checkVariant($product,$parentProduct);
		}
		$this->element =& $product;
		static $i = 0;
		$i++;
		return '
				<span id="product_id_'.$i.'" >
					'.(int)@$this->element->product_id.' '.@$this->element->product_name.'
					<input type="hidden" name="'.$control_name.'['.$name.']'.'" value="'.@$this->element->product_id.'" />
				</span>
				'. $popup->display(
							'<img src="'. HIKASHOP_IMAGES.'edit.png" alt="'.JText::_('PRODUCT').'"/>',
							'PRODUCT',
							 hikashop_completeLink("product&task=selectrelated&select_type=menu_".$i."&control=".$control_name.'['.$name.']',true ),
							'product_link',
							760, 480, '', '', 'link')
						.'
				<a href="#" onclick="document.getElementById(\'product_id_'.$i.'\').innerHTML=\'<input type=\\\'hidden\\\' name=\\\''.$control_name.'['.$name.']'.'\\\' value=\\\'0\\\' />\';return false;" >
					<img src="'.HIKASHOP_IMAGES.'delete.png" alt="delete"/>
				</a>
		';

	}
}
