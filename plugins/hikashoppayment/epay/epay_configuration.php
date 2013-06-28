<?php
/**
 * @package	HikaShop for Joomla!
 * @version	2.1.3
 * @author	hikashop.com
 * @copyright	(C) 2010-2013 HIKARI SOFTWARE. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');
?>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][merchantnumber]">
			<?php echo JText::_( 'MERCHANT_NUMBER' ); ?>
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][merchantnumber]" value="<?php echo @$this->element->payment_params->merchantnumber; ?>" />
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][windowstate]">
			<?php echo JText::_( 'WINDOW_STATE' ); ?>
		</label>
	</td>
	<td>
		<select name="data[payment][payment_params][windowstate]">
			<option <?php if($this->element->payment_params->windowstate == 1) echo "selected=\"selected\""; ?> value="1"><?php echo JText::_( 'Overlay' ); ?></option>
			<option <?php if($this->element->payment_params->windowstate == 3) echo "selected=\"selected\""; ?> value="3"><?php echo JText::_( 'Fullscreen' ); ?></option>
		</select>
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][windowid]">
			<?php echo JText::_( 'WINDOW_ID' ); ?>
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][windowid]" value="<?php echo @$this->element->payment_params->windowid; ?>" />
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][md5key]">
			<?php echo JText::_( 'MD5KEY' ); ?>
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][md5key]" value="<?php echo @$this->element->payment_params->md5key; ?>" />
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][group]">
			<?php echo JText::_( 'GROUP' ); ?>
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][group]" value="<?php echo @$this->element->payment_params->group; ?>" />
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][authsms]">
			<?php echo JText::_( 'AUTHSMS' ); ?>
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][authsms]" value="<?php echo @$this->element->payment_params->authsms; ?>" />
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][authmail]">
			<?php echo JText::_( 'AUTHEMAIL' ); ?>
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][authmail]" value="<?php echo @$this->element->payment_params->authmail; ?>" />
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][instantcapture]">
			<?php echo JText::_( 'INSTANTCAPTURE' ); ?>
		</label>
	</td>
	<td>
		<select name="data[payment][payment_params][instantcapture]">
			<option <?php if($this->element->payment_params->instantcapture == 0) echo "selected=\"selected\""; ?> value="0"><?php echo JText::_( 'HIKASHOP_NO' ); ?></option>
			<option <?php if($this->element->payment_params->instantcapture == 1) echo "selected=\"selected\""; ?> value="1"><?php echo JText::_( 'HIKASHOP_YES' ); ?></option>
		</select>
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][ownreceipt]">
			<?php echo JText::_( 'OWN_RECEIPT' ); ?>
		</label>
	</td>
	<td>
		<select name="data[payment][payment_params][ownreceipt]">
			<option <?php if($this->element->payment_params->ownreceipt == 0) echo "selected=\"selected\""; ?> value="0"><?php echo JText::_( 'HIKASHOP_NO' ); ?></option>
			<option <?php if($this->element->payment_params->ownreceipt == 1) echo "selected=\"selected\""; ?> value="1"><?php echo JText::_( 'HIKASHOP_YES' ); ?></option>
		</select>
	</td>
</tr>
