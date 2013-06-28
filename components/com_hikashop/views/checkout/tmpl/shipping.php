<?php
/**
 * @package	HikaShop for Joomla!
 * @version	2.1.3
 * @author	hikashop.com
 * @copyright	(C) 2010-2013 HIKARI SOFTWARE. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');
?><?php if(!empty($this->rates)){ ?>
<div class="hikashop_shipping_methods" id="hikashop_shipping_methods">
	<fieldset>
		<legend><?php echo JText::_('HIKASHOP_SHIPPING_METHOD');?></legend>
<?php
	if(!HIKASHOP_RESPONSIVE) {
?>		<table>
<?php
	} else {
?><div class="controls">
	<div class="hika-radio">
		<table class="hikashop_payment_methods_table table table-striped table-hover">
<?php
	}

	$this->setLayout('listing_price');
	$this->params->set('show_quantity_field', 0);
	$auto_select_default = $this->config->get('auto_select_default',2);
	if($auto_select_default==1 && count($this->rates)>1)
		$auto_select_default=0;
	$done=false;
	$k = 0;
	foreach($this->rates as $rate){
		$checked = '';
		if(($this->shipping_method==$rate->shipping_type && $this->shipping_id==$rate->shipping_id)|| ($auto_select_default && empty($this->shipping_id)&&!$done)){
			$done = true;
			$checked = 'checked="checked"';
		}
		if($this->config->get('auto_submit_methods',1) && empty($checked)){
			$checked.=' onclick="this.form.submit(); return false;"';
		}
		if(empty($rate->shipping_price_with_tax)){
			$rate->shipping_price_with_tax = $rate->shipping_price;
		}
		if(empty($rate->shipping_price)){
			$rate->shipping_price = $rate->shipping_price_with_tax;
		}

		$taxes = round($rate->shipping_price_with_tax-$rate->shipping_price,$this->currencyHelper->getRounding($rate->shipping_currency_id));
		$prices_taxes = 1;
		if(bccomp($taxes,0,5)==0){
			$prices_taxes = 0;
		}

		$price_text = '';
		if(bccomp($rate->shipping_price,0,5)===0){
			$price_text = JText::_('FREE_SHIPPING');
		}else{
			$price_text .= JText::_('PRICE_BEGINNING');
			$price_text .= '<span class="hikashop_checkout_shipping_price">';
			if($prices_taxes){
				$price_text .= $this->currencyHelper->format($rate->shipping_price_with_tax,$rate->shipping_currency_id);
				$price_text .= JText::_('PRICE_BEFORE_TAX');
				$price_text .= $this->currencyHelper->format($rate->shipping_price,$rate->shipping_currency_id);
				$price_text .= JText::_('PRICE_AFTER_TAX');
			}else{
				$price_text .= $this->currencyHelper->format($rate->shipping_price,$rate->shipping_currency_id);
			}

			if($this->params->get('show_original_price') && isset($rate->shipping_price_orig) && bccomp($rate->shipping_price_orig,0,5)){
				$price_text .= JText::_('PRICE_BEFORE_ORIG');
				if($prices_taxes){
					$price_text .= $this->currencyHelper->format($rate->shipping_price_orig_with_tax,$rate->shipping_currency_id_orig);
				}else{
					$price_text .= $this->currencyHelper->format($rate->shipping_price_orig,$rate->shipping_currency_id_orig);
				}
				$price_text .= JText::_('PRICE_AFTER_ORIG');
			}
			$price_text .= '</span> ';
			$price_text .= JText::_('PRICE_END');
		}
?>
			<tr class="row<?php echo $k; ?>">
<?php if(!HIKASHOP_RESPONSIVE) { ?>
				<td>
					<input class="hikashop_checkout_shipping_radio" type="radio" name="hikashop_shipping" id="radio_<?php echo $rate->shipping_type.'_'.$rate->shipping_id;?>" value="<?php echo $rate->shipping_type.'_'.$rate->shipping_id;?>" <?php echo $checked; ?> />
				</td>
				<td><label for="radio_<?php echo $rate->shipping_type.'_'.$rate->shipping_id;?>" style="cursor:pointer;">
					<span class="hikashop_checkout_shipping_image">
<?php } else { ?>
				<td>
					<input class="hikashop_checkout_shipping_radio" type="radio" name="hikashop_shipping" id="radio_<?php echo $rate->shipping_type.'_'.$rate->shipping_id;?>" value="<?php echo $rate->shipping_type.'_'.$rate->shipping_id;?>" <?php echo $checked; ?> />
					<label class="btn btn-radio" for="radio_<?php echo $rate->shipping_type.'_'.$rate->shipping_id;?>"><?php echo $rate->shipping_name;?></label>
					<span class="hikashop_checkout_shipping_price_full"><?php echo $price_text; ?></span>
					<span class="hikashop_checkout_payment_image">
<?php
	}
	if(!empty($rate->shipping_images)){
		$images = explode(',',$rate->shipping_images);
		if(!empty($images)){
			foreach($images as $image){
				if(!empty($this->images_shipping[$image])){
?>
						<img src="<?php echo HIKASHOP_IMAGES .'shipping/'.  $this->images_shipping[$image];?>" alt=""/>
<?php
				}
			}
		}
	}
?>
					</span>
<?php if(!HIKASHOP_RESPONSIVE) { ?>
					</label>
				</td>
				<td><label for="radio_<?php echo $rate->shipping_type.'_'.$rate->shipping_id;?>" style="cursor:pointer;">
					<span class="hikashop_checkout_shipping_name"><?php echo $rate->shipping_name;?></span>
					<span class="hikashop_checkout_shipping_price_full"><?php echo $price_text; ?></span>
					</label>
					<br/>
<?php } ?>
					<div class="hikashop_checkout_shipping_description"><?php echo $rate->shipping_description;?></div>
				</td>
			</tr>
<?php
		$k = 1-$k;
	}

	if(!HIKASHOP_RESPONSIVE) {
?>		</table>
<?php
	} else {
?>		</table>
	</div>
</div>
<script>
(function($){
	jQuery("#hikashop_shipping_methods .hika-radio input[checked=checked]").each(function() {
		jQuery("label[for=" + jQuery(this).attr('id') + "]").addClass('active btn-primary');
	});
	jQuery("#hikashop_shipping_methods .hika-radio input").change(function() {
		jQuery(this).parents('div.hika-radio').find('label.active').removeClass('active btn-primary');
		jQuery("label[for=" + jQuery(this).attr('id') + "]").addClass('active btn-primary');
	});
})(jQuery);
</script>
<?php
	}
?>
	</fieldset>
</div>
<?php } ?>
