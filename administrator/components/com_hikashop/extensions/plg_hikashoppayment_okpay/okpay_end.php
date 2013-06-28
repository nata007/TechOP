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
defined('_JEXEC') or die('Restricted access');
$site	= JUri::base(false);
?>
<style type="text/css">
#hikashop_okpay_button{
	background:url(<?=$site?>plugins/hikashoppayment/<?=$method->payment_type?>/assets/images/button.png) left top no-repeat;
	width:186px;
	height:54px;
	border:none;
	cursor:pointer;
}
</style>
<div class="hikashop_okpay_end" id="hikashop_okpay_end">
	<form id="hikashop_okpay_form" name="hikashop_okpay_form" action="<?php echo $method->payment_params->url;?>" method="post">
		<div id="hikashop_okpay_end_image" class="hikashop_okpay_end_image">
			<input id="hikashop_okpay_button" type="submit" class="btn btn-primary" value="" name="" alt="" />
		</div>
		<?php
			foreach( $vars as $name => $value ) {
				echo '<input type="hidden" name="'.$name.'" value="'.htmlspecialchars((string)$value).'" />';
			}
			JRequest::setVar('noform',1); ?>
		</form>
		<?php ?>
</div>
