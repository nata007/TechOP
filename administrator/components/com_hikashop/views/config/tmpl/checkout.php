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
echo $this->leftmenu(
	'checkout',
	array(
		'#checkout_checkout' => JText::_('CHECKOUT'),
		'#checkout_shipping' => JText::_('SHIPPING_PAYMENT'),
		'#checkout_login' => JText::_('LOGIN_REGISTRATION')
	)
);
?>
<div id="page-checkout" class="rightconfig-container <?php if(HIKASHOP_BACK_RESPONSIVE) echo 'rightconfig-container-j30';?>">
<table style="width:100%;">
	<tr>
		<td valign="top" width="50%">
			<div id="checkout_checkout">
				<fieldset class="adminform">
					<legend><?php echo JText::_( 'CHECKOUT' ); ?></legend>
					<table class="admintable table" cellspacing="1" style="width:100%;">
						<tr>
							<td class="key"><?php echo JText::_('CHECKOUT_FLOW'); ?></td>
							<td>
								<textarea class="inputbox" name="config[checkout]" id="TextCheckoutWorkFlow" cols="30" rows="5"><?php echo $this->config->get('checkout'); ?></textarea>
<?php
	if($this->config->get('checkout_workflow_edition',1)) {
		$checkoutlist = array('login','address','shipping','payment','coupon','cart','status','fields','terms');
		hikashop_loadJsLib('jquery');
?>
	<div class="checkout_workflow_zone" style="width:100%">
		<ul id="checkout_delete" class="checkout_trash">
		</ul>
		<ul class="checkout_items">
<?php
	foreach($checkoutlist as $c) {
		echo '<li class="checkoutElem" rel="'.$c.'">'.JText::_('HIKASHOP_CHECKOUT_'.strtoupper($c)).'</li>';
	}
?>
		</ul>
		<div style="clear:both">
<?php
	$workflow = explode(',', $this->config->get('checkout'));
	$checkoutRel = 0;
	if(!empty($workflow)) {
		foreach($workflow as $flow) {
			if( $flow == 'end')
				continue;

			echo '<ul class="checkout_step" rel="'.$checkoutRel.'" id="hikashop_checkout_workflow_step_'.$checkoutRel.'">';
			$checkoutRel++;
			$flow = explode('_', $flow);
			foreach($flow as $f) {
				if( in_array($f, $checkoutlist) ) {
					echo '<li class="checkoutElem" rel="'.$f.'">'. JText::_('HIKASHOP_CHECKOUT_'.strtoupper($f)).'</li>';
				}
			}
			echo '</ul>';
		}
	}
	echo '<ul class="checkout_step" rel="'.$checkoutRel.'" id="hikashop_checkout_workflow_step_'.$checkoutRel.'"></ul>';
?>
		</div>
	<div style="clear:both"></div>
</div>
<script type="text/javascript">
var checkoutWorkflowHelper = {
	maxRel: <?php echo $checkoutRel; ?>,
	init: function() {
		var t = this;
		jQuery("ul.checkout_trash").droppable({
			accept: "ul.checkout_step li",
			hoverClass: "drophover",
			drop: function(event, ui) { ui.draggable.remove(); }
		});
		jQuery("ul.checkout_items li").draggable({
			dropOnEmpty: true,
			connectToSortable: "ul.checkout_step",
			helper: "clone",
			revert: "invalid"
		});
		jQuery("ul.checkout_step").sortable({
			revert: true,
			dropOnEmpty: true,
			connectWith: "ul.checkout_step, ul.checkout_trash",
			update: function(event, ui) { t.serialize(); }
		});
		jQuery("ul.checkout_items").disableSelection();
		jQuery('#TextCheckoutWorkFlow').hide();
		jQuery('#CheckoutWorkflow').show();
	},
	serialize: function() {
		var max = 0, data = '';
		jQuery("ul.checkout_step li").each(function(index, el) {
			var p = parseInt(jQuery(el).parent().attr("rel"), r = jQuery(el).attr("rel"));
			if(p > max) {
				max = p;
				if( data != '')
					data += ',';
			} else if( data != '') {
				data += '_';
			}
			data += r;
		});
		data += '_confirm,end';
		jQuery('#TextCheckoutWorkFlow').val(data);

		if(max == this.maxRel) {
			this.maxRel++;
			var t = this;
			jQuery('<ul class="checkout_step" rel="' + this.maxRel + '" id="hikashop_checkout_workflow_step_' + this.maxRel + '"></ul>').insertAfter('#hikashop_checkout_workflow_step_' + (this.maxRel-1) ).sortable({
				revert: true,
				dropOnEmpty: true,
				connectWith: "ul.checkout_step, ul.checkout_trash",
				update: function(event, ui) { t.serialize(); }
			});
			jQuery("ul.checkout_step").sortable('refresh');
		}
		if(max < (this.maxRel - 1)) {
			for(var i = this.maxRel; i > (max+1); i--) {
				jQuery('#hikashop_checkout_workflow_step_' + i).sortable("destroy").remove();
				jQuery("ul.checkout_step").sortable('refresh');
			}
			this.maxRel = max + 1;
		}
	}
};
jQuery(document).ready(function($) { checkoutWorkflowHelper.init(); });
</script>
<?php } ?>
							</td>
						</tr>
						<tr>
							<td class="key">
								<?php echo JText::_('CHECKOUT_WORKFLOW_EDITION'); ?>
							</td>
							<td>
								<?php echo JHTML::_('hikaselect.booleanlist', "config[checkout_workflow_edition]",'onchange="task = document.getElementById(\'config_form_task\');if(task) task.value=\'apply\'; this.form.submit();"',$this->config->get('checkout_workflow_edition',1)); ?>
							</td>
						</tr>
						<tr>
							<td class="key">
								<?php echo JText::_('CHECKOUT_FORCE_SSL'); ?>
							</td>
							<td>
								<?php
								$values = array();
								$values[] = JHTML::_('select.option', 'url',JText::_('SHARED_SSL'));
								$values[] = JHTML::_('select.option', 1,JText::_('HIKASHOP_YES'));
								$values[] = JHTML::_('select.option', 0,JText::_('HIKASHOP_NO'));
								echo JHTML::_('hikaselect.radiolist',  $values, 'config[force_ssl]', 'onchange="displaySslField()"', 'value', 'text', $this->config->get('force_ssl',0) );
								if($this->config->get('force_ssl',0)=='url'){ $hidden=''; }
								else{ $hidden="display:none"; }?>
								<input class="inputbox" id="force_ssl_url" name="config[force_ssl_url]" type="text" size="20" value="<?php echo $this->config->get('force_ssl_url'); ?>" style="<?php echo $hidden; ?>">
							</td>
						</tr>
						<tr>
							<td class="key">
								<?php echo JText::_('DISPLAY_CHECKOUT_BAR'); ?>
							</td>
							<td>
								<?php echo $this->checkout->display('config[display_checkout_bar]',$this->config->get('display_checkout_bar')); ?>
							</td>
						</tr>
						<tr>
							<td class="key">
								<?php echo JText::_('CHECKOUT_SHOW_CART_DELETE'); ?>
							</td>
							<td>
								<?php echo JHTML::_('hikaselect.booleanlist', 'config[checkout_cart_delete]','',$this->config->get('checkout_cart_delete'));?>
							</td>
						</tr>
						<tr>
							<td class="key">
								<?php echo JText::_('FORCE_MENU_ON_CHECKOUT'); ?>
							</td>
							<td>
								<?php echo $this->elements->hikashop_menu;?>
							</td>
						</tr>
						<tr>
							<td class="key" >
								<?php echo JText::_('HIKASHOP_CHECKOUT_TERMS'); ?>
							</td>
							<td>
								<input class="inputbox" id="checkout_terms" name="config[checkout_terms]" type="text" size="20" value="<?php echo $this->config->get('checkout_terms'); ?>">
								<?php
								if(!HIKASHOP_J16){
									$link = 'index.php?option=com_content&amp;task=element&amp;tmpl=component&amp;object=checkout';
									$js = "
									function jSelectArticle(id, title, object) {
										document.getElementById(object+'_terms').value = id;
										window.top.hikashop.closeBox();
									}";
									if (!HIKASHOP_PHP5) {
										$doc =& JFactory::getDocument();
									}else{
										$doc = JFactory::getDocument();
									}
									$doc->addScriptDeclaration($js);
								}else{
									$link = 'index.php?option=com_content&amp;view=articles&amp;layout=modal&amp;tmpl=component&amp;object=content&amp;function=jSelectArticle_checkout';
									$js = "
									function jSelectArticle_checkout(id, title, catid, object) {
										document.getElementById('checkout_terms').value = id;
										hikashop.closeBox();
									}";
									if (!HIKASHOP_PHP5) {
										$doc =& JFactory::getDocument();
									}else{
										$doc = JFactory::getDocument();
									}
									$doc->addScriptDeclaration($js);
								}
								echo $this->popup->display(
									JText::_('Select'),
									'Select one article which will be displayed for the Terms & Conditions',
									$link,
									'checkout_terms_link',
									760, 480, '', '', 'button'
								);
								?>
							</td>
						</tr>
						<tr>
							<td class="key">
								<?php echo JText::_('CONTINUE_SHOPPING_BUTTON_URL');?>
							</td>
							<td>
								<input name="config[continue_shopping]" type="text" value="<?php echo $this->config->get('continue_shopping');?>" />
							</td>
						</tr>
						<tr>
							<td class="key">
								<?php echo JText::_('SHOW_IMAGE'); ?>
							</td>
							<td>
								<?php 	echo JHTML::_('hikaselect.booleanlist', 'config[show_cart_image]','',$this->config->get('show_cart_image')); ?>
							</td>
						</tr>
						<tr>
							<td class="key"><?php echo JText::_('BUSINESS_HOURS'); ?></td>
							<td><?php
								if(hikashop_level(1)){
									$hours = array();
									for($i=0;$i<24;$i++) $hours[]=JHTML::_('select.option', $i,$i);
									$minutes = array();
									for($i=0;$i<60;$i++) $minutes[]=JHTML::_('select.option', $i,$i);
									echo '<fieldset class="adminform"><legend>'.JText::_('OPENS_AT').'</legend>'.JHTML::_('select.genericlist',   $hours, "config[store_open_hour]", 'class="inputbox" size="1"', 'value', 'text', $this->config->get('store_open_hour',0) ); ?><?php echo JText::_('HOURS');
									echo JHTML::_('select.genericlist',   $minutes, "config[store_open_minute]", 'class="inputbox" size="1"', 'value', 'text', $this->config->get('store_open_minute',0) ); ?><?php echo JText::_('HIKA_MINUTES').'</fieldset>';
									echo '<fieldset class="adminform"><legend>'.JText::_('CLOSES_AT').'</legend>'.JHTML::_('select.genericlist',   $hours, "config[store_close_hour]", 'class="inputbox" size="1"', 'value', 'text', $this->config->get('store_close_hour',0) ); ?><?php echo JText::_('HOURS');
									echo JHTML::_('select.genericlist',   $minutes, "config[store_close_minute]", 'class="inputbox" size="1"', 'value', 'text', $this->config->get('store_close_minute',0) ); ?><?php echo JText::_('HIKA_MINUTES').'</fieldset>';
								}else{
									echo '<small style="color:red">'.JText::_('ONLY_COMMERCIAL').'</small>';
								}
							?></td>
						</tr>
					</table>
				</fieldset>
				</div>
																			<!-- SHIPPING & PAYMENT -->
				<div id="checkout_shipping">
				<fieldset class="adminform">
					<legend><?php echo JText::_( 'SHIPPING_PAYMENT' ); ?></legend>
					<table class="admintable table" cellspacing="1">
						<tr>
							<td class="key">
								<?php echo JText::_('AUTO_SELECT_DEFAULT_SHIPPING_AND_PAYMENT'); ?>
							</td>
							<td>
								<?php echo $this->auto_select->display('config[auto_select_default]',$this->config->get('auto_select_default',2)); ?>
							</td>
						</tr>
						<tr>
							<td class="key">
								<?php echo JText::_('AUTO_SUBMIT_SHIPPING_AND_PAYMENT_SELECTION'); ?>
							</td>
							<td>
								<?php 	echo JHTML::_('hikaselect.booleanlist', 'config[auto_submit_methods]','',$this->config->get('auto_submit_methods',1)); ?>
							</td>
						</tr>
						<tr>
							<td class="key">
								<?php echo JText::_('FORCE_SHIPPING_REGARDLESS_OF_WEIGHT'); ?>
							</td>
							<td>
								<?php 	echo JHTML::_('hikaselect.booleanlist', 'config[force_shipping]','',$this->config->get('force_shipping',0)); ?>
							</td>
						</tr>
					</table>
				</fieldset>
				</div>
																			<!-- LOGIN & REGISTRATION -->
				<div id="checkout_login">
				<fieldset class="adminform">
					<legend><?php echo JText::_( 'LOGIN_REGISTRATION' ); ?></legend>
					<table class="admintable table" cellspacing="1">
						<tr>
							<td class="key">
								<?php echo JText::_('HIKA_LOGIN'); ?>
							</td>
							<td>
								<?php 	echo JHTML::_('hikaselect.booleanlist', 'config[display_login]','',$this->config->get('display_login',1)); ?>
							</td>
						</tr>
						<tr>
							<td class="key">
								<?php echo JText::_('HIKA_REGISTRATION'); ?>
							</td>
							<td>
								<?php
								if(hikashop_level(1)){
									echo $this->registration->display( 'config[simplified_registration]',$this->config->get('simplified_registration'));
								}else{
									echo '<small style="color:red">'.JText::_('ONLY_COMMERCIAL').'</small>';
								}
								?>
							</td>
						</tr>
						<tr>
							<td class="key">
								<?php echo JText::_('DISPLAY_EMAIL_CONFIRMATION_FIELD'); ?>
							</td>
							<td>
								<?php 	echo JHTML::_('hikaselect.booleanlist', 'config[show_email_confirmation_field]','',$this->config->get('show_email_confirmation_field',0)); ?>
							</td>
						</tr>
					</table>
				</fieldset>
				</div>
			</td>
		</tr>
	</table>
</div>
