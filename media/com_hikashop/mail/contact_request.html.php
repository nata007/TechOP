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
echo JText::_('FROM_ADDRESS').' : '.$data->element->email."<br />";
echo JText::_('FROM_NAME').' : '.$data->element->name."<br/><br/>";

echo JText::_('PRODUCT_NAME').' : '.$data->product->product_name."<br />";
echo JText::_('PRODUCT_CODE').' : '.$data->product->product_code."<br /><br />";

echo $data->element->altbody;

global $Itemid;
$url_itemid='';
if(!empty($Itemid)){
	$url_itemid='&Itemid='.$Itemid;
}
$url = JRoute::_('administrator/index.php?option=com_hikashop&ctrl=product&task=edit&cid[]='.$data->product->product_id.$url_itemid,false,true);
?>
<br /><br />
<a href="<?php echo $url;?>"><?php echo JText::_('LINK_TO_PRODUCT_PAGE') ?></a>
