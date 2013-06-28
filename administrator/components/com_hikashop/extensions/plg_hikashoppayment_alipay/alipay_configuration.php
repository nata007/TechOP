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
?>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][return_url]">
			<?php echo JText::_( 'RETURN_URL' ); ?>
		</label>
	</td>
	<td>
	<?php
		$httpsHikashop = str_replace('http://','https://', HIKASHOP_LIVE);
		echo $httpsHikashop.'index.php?option=com_hikashop&amp;ctrl=checkout&amp;task=notify&amp;notif_payment=alipay&amp;tmpl=component';
	?>
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][email]">
			<?php echo JText::_( 'HIKA_EMAIL' ); ?>
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][email]" value="<?php echo @$this->element->payment_params->email; ?>" />
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][Partner_ID]">
			Partner ID
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][Partner_ID]" value="<?php echo @$this->element->payment_params->Partner_ID; ?>" />
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][Security_code]">
			Security code
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][Security_code]" value="<?php echo @$this->element->payment_params->Security_code; ?>" />
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][Mode]">
			Payment mode
		</label>
	</td>
	<td>
		<?php
		$values = array();
		$values[] = JHTML::_('select.option', 'Partner', JText::_('Partner'));
		$values[] = JHTML::_('select.option', 'Direct', JText::_('Direct'));
		echo JHTML::_('select.genericlist', $values, "data[payment][payment_params][Mode]", 'class="inputbox" size="1"', 'value', 'text', @$this->element->payment_params->Mode ); ?>
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][Transport]">
			Transport
		</label>
	</td>
	<td>
		<?php
		$values = array();
		$values[] = JHTML::_('select.option', 'http', JText::_('http'));
		$values[] = JHTML::_('select.option', 'https', JText::_('https'));
		echo JHTML::_('select.genericlist', $values, "data[payment][payment_params][Transport]", 'class="inputbox" size="1"', 'value', 'text', @$this->element->payment_params->Transport ); ?>
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][Sign_type]">
			Signature type
		</label>
	</td>
	<td>
		<?php
		$values = array();
		$values[] = JHTML::_('select.option', 'MD5', JText::_('MD5'));
		echo JHTML::_('select.genericlist', $values, "data[payment][payment_params][Sign_type]", 'class="inputbox" size="1"', 'value', 'text', @$this->element->payment_params->Sign_type ); ?>
	</td>
</tr>

<tr>
	<td class="key">
		<label for="data[payment][payment_params][debug]">
			<?php echo JText::_( 'DEBUG' ); ?>
		</label>
	</td>
	<td>
		<?php echo JHTML::_('hikaselect.booleanlist', "data[payment][payment_params][debug]" , '',@$this->element->payment_params->debug	); ?>
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][verified_status]">
			<?php echo JText::_( 'VERIFIED_STATUS' ); ?>
		</label>
	</td>
	<td>
		<?php echo $this->data['category']->display("data[payment][payment_params][verified_status]",@$this->element->payment_params->verified_status); ?>
	</td>
</tr>
