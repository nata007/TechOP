<?php
/**
 * @package	HikaShop for Joomla!
 * @version	2.1.3
 * @author	hikashop.com
 * @copyright	(C) 2010-2013 HIKARI SOFTWARE. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');
?><tr>
	<td class="key">
		<label for="data[payment][payment_params][return_url]">
			<?php echo JText::_( 'RETURN_URL' ); ?>
		</label>
	</td>
	<td colspan="2">
	<?php
	echo HIKASHOP_LIVE.'index.php?option=com_hikashop&amp;ctrl=checkout&amp;task=notify&amp;notif_payment=postfinance&amp;tmpl=component';
	?>
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][shop_ID]">
			<?php echo JText::_( 'ATOS_MERCHANT_ID' ); ?>
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][shop_ID]" value="<?php echo @$this->element->payment_params->shop_ID; ?>" />
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][sha_in_phrase]">
			<?php echo JText::_( 'SHA-IN_Pass_phrase' ); ?>
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][sha_in_phrase]" value="<?php echo @$this->element->payment_params->sha_in_phrase; ?>" />
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][sha_out_phrase]">
			<?php echo JText::_( 'SHA-OUT_Pass_phrase' ); ?>
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][sha_out_phrase]" value="<?php echo @$this->element->payment_params->sha_out_phrase; ?>" />
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
		<label for="data[payment][payment_params][address_type]">
			<?php echo JText::_( 'PAYPAL_ADDRESS_TYPE' ); ?>
		</label>
	</td>
	<td>
		<?php echo $this->data['address']->display('data[payment][payment_params][address_type]',@$this->element->payment_params->address_type); ?>
	</td>
</tr>
<tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][url]">
			<?php echo JText::_( 'URL' ); ?>
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][url]" value="<?php echo @$this->element->payment_params->url; ?>" />
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][invalid_status]">
			<?php echo JText::_( 'INVALID_STATUS' ); ?>
		</label>
	</td>
	<td>
		<?php echo $this->data['category']->display("data[payment][payment_params][invalid_status]",@$this->element->payment_params->invalid_status); ?>
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][pending_status]">
			<?php echo JText::_( 'PENDING_STATUS' ); ?>
		</label>
	</td>
	<td>
		<?php echo $this->data['category']->display("data[payment][payment_params][pending_status]",@$this->element->payment_params->pending_status); ?>
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
<!--
<tr>
	<td class="key">
		<label for="data[payment][payment_params][return_url]">
			<?php echo JText::_( 'RETURN_URL' ); ?>
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][return_url]" value="<?php echo @$this->element->payment_params->return_url; ?>" />
	</td>
</tr>
-->
