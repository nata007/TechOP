<?php
/**
 * @package	HikaShop for Joomla!
 * @version	2.1.3
 * @author	hikashop.com
 * @copyright	(C) 2010-2013 HIKARI SOFTWARE. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');
?><?php $i = (int)(count($this->buttons)/2); if(count($this->buttons)%2)$i++;?>
<?php if(!HIKASHOP_BACK_RESPONSIVE) { ?>
	<table style="width:100%">
		<tr>
			<td valign="top" width="50%">
<?php } else { ?>
<div class="row-fluid">
	<div class="span6">
<?php } ?>
			<div class="hikashopcpanel">
				<?php
					foreach($this->buttons as $k => $oneButton){
						if($k == $i){
							if(HIKASHOP_BACK_RESPONSIVE) {
								echo '</div></div><div class="span6"><div class="hikashopcpanel">';
							}else{
								echo '</div></td><td valign="top"><div class="hikashopcpanel">';
							}
						}
						echo $oneButton;
					}
					?>
			</div>
<?php if(!HIKASHOP_BACK_RESPONSIVE) { ?>
			</td>
		</tr>
	</table>
<?php } else { ?>
	</div>
</div>
<?php } ?>
