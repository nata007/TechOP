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
echo JText::_('VM_IMPORT_DESC').':<br/>';
$functions = array('TAXATIONS','HIKA_CATEGORIES','PRODUCTS','PRICES','USERS','ORDERS','DOWNLOADS','FILES','IMAGES','DISCOUNTS','COUPONS');
foreach($functions as $k => $v){
	echo '<br/>  - '.JText::_($v);
}
