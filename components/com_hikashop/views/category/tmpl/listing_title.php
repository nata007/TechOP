<?php
/**
 * @package	HikaShop for Joomla!
 * @version	2.1.3
 * @author	hikashop.com
 * @copyright	(C) 2010-2013 HIKARI SOFTWARE. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');
?><?php $link = $this->getLink($this->row->category_id,$this->row->alias);?>
<span class="hikashop_category_name">
	<a href="<?php echo $link;?>">
		<?php

		echo $this->row->category_name;
		if($this->params->get('number_of_products',0)){
			echo ' ('.$this->row->number_of_products.')';
		}
		?>
	</a>
</span>
