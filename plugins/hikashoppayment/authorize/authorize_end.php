<?php
/**
 * @package	HikaShop for Joomla!
 * @version	2.1.3
 * @author	hikashop.com
 * @copyright	(C) 2010-2013 HIKARI SOFTWARE. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');
?><?php if ($method->payment_params->api == 'dpm' && @$method->payment_params->iframe){
		$url=urlencode(base64_encode(serialize($vars)));
	?>
	<iframe name="frame" scrolling="auto" height="1000" width="660" Frameborder="no" src="<?php echo $vars["x_relay_url"].'&iframe='.$url;?>"></iframe>
<?php return;
} ?>
<div class="hikashop_authorize_end" id="hikashop_authorize_end">
	<?php if ($method->payment_params->api == 'sim') {?>
		<span id="hikashop_authorize_end_message" class="hikashop_authorize_end_message">
			<?php echo JText::sprintf('PLEASE_WAIT_BEFORE_REDIRECTION_TO_X',$method->payment_name).'<br/>'. JText::_('CLICK_ON_BUTTON_IF_NOT_REDIRECTED');?>
		</span>
		<span id="hikashop_authorize_end_spinner" class="hikashop_authorize_end_spinner">
			<img src="<?php echo HIKASHOP_IMAGES.'spinner.gif';?>" />
		</span>
		<br/>
		<?php } ?>
		<form id="hikashop_authorize_form" name="hikashop_authorize_form" action="<?php echo $method->payment_params->url;?>" method="post">
			<?php
			foreach ($vars as $name => $value) {
				if (is_array($value)) {
					foreach ($value as $v) {
						echo '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars($v) . '" />';
					}
				} else {
					echo '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars($value) . '" />';
				}
			}
			$doc =& JFactory::getDocument();
			$doc->addScriptDeclaration("window.addEvent('domready', function() {document.getElementById('hikashop_authorize_form').submit();});");
			JRequest::setVar('noform',1);
		?>
		<div id="hikashop_authorize_end_image" class="hikashop_authorize_end_image">
			<input id="hikashop_authorize_button" type="submit" class="btn btn-primary" value="<?php echo JText::_('PAY_NOW');?>" name="" alt="<?php echo JText::_('PAY_NOW');?>" />
		</div>
	</form>
</div>
