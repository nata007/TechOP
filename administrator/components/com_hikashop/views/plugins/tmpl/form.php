<?php
/**
 * @package	HikaShop for Joomla!
 * @version	2.1.3
 * @author	hikashop.com
 * @copyright	(C) 2010-2013 HIKARI SOFTWARE. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');
?><div class="iframedoc" id="iframedoc"></div>
<div>
	<form action="index.php?option=<?php echo HIKASHOP_COMPONENT ?>&amp;ctrl=plugins" method="post"  name="adminForm" id="adminForm" enctype="multipart/form-data">
		<?php
		if(empty($this->noForm)){
			$type=$this->plugin_type;
			$upType=strtoupper($type);
			$plugin_name = $type.'_name';
			$plugin_name_input =$plugin_name.'_input';
			$plugin_images = $type.'_images';

			?>
<?php if(!HIKASHOP_BACK_RESPONSIVE) { ?>
<div id="page-plugins">
	<table style="width:100%">
		<tr>
			<td valign="top" width="70%">
<?php } else { ?>
<div id="page-plugins" class="row-fluid">
	<div class="span6">
<?php } ?>
						<fieldset class="adminform" id="htmlfieldset">
							<legend><?php echo JText::_( 'MAIN_INFORMATION' ); ?></legend>
							<?php
								$this->$plugin_name_input = "data[$type][$plugin_name]";
								if($this->translation){
									$this->setLayout('translation');
									echo $this->loadTemplate();
								}else{
									$this->setLayout('normal');
									echo $this->loadTemplate();
								}
							?>
						</fieldset>
<?php if(!HIKASHOP_BACK_RESPONSIVE) { ?>
			</td>
			<td valign="top" width="30%">
<?php } else { ?>
	</div>
	<div class="span6 hikaspanleft">
<?php } ?>
						<table class="admintable table">

							<tr>
								<td class="key">
										<?php echo JText::_( 'HIKA_IMAGES' ); ?>
								</td>
								<td>
									<input type="text" id="plugin_images" name="data[<?php echo $type;?>][<?php echo $type;?>_images]" value="<?php echo @$this->element->$plugin_images; ?>" />
									<?php
										echo $this->popup->display(
											'<img src="'. HIKASHOP_IMAGES.'edit.png" alt="'.JText::_('HIKA_EDIT').'"/>',
											'HIKA_IMAGES',
											'\''.hikashop_completeLink('plugins&task=selectimages&type='.$type,true).'&values=\'+document.getElementById(\'plugin_images\').value',
											'plugin_images_link',
											760, 480, '', '', 'link',true
										);
									?>
								</td>
							</tr>
							<?php if($this->plugin_type=='payment'){ ?>
							<tr>
								<td class="key">
									<?php
										echo JText::_( 'PRICE' );
									?>
								</td>
								<td>
									<input type="text" name="data[payment][payment_price]" value="<?php echo @$this->element->payment_price; ?>" /><?php echo $this->currencies->display('data[payment][payment_params][payment_currency]',@$this->element->payment_params->payment_currency); ?>
								</td>
							</tr>
							<tr>
								<td class="key">
									<?php
										echo JText::_( 'DISCOUNT_PERCENT_AMOUNT' );
									?>
								</td>
								<td>
									<input type="text" name="data[payment][payment_params][payment_percentage]" value="<?php echo (float)@$this->element->payment_params->payment_percentage; ?>" />%
								</td>
							</tr>
							<?php } ?>
							<?php echo $this->content;?>
						</table>
						<fieldset class="adminform">
							<?php
							if(!empty($this->element->zone_id) || (!empty($this->element->payment_shipping_methods_id) && is_array($this->element->payment_shipping_methods_id) && count($this->element->payment_shipping_methods_id)) || (!empty($this->element->payment_currency) && is_array($this->element->payment_currency) && (count($this->element->payment_currency)>2 || count($this->element->payment_currency)==1 && reset($this->element->payment_currency)!=''))){
								$field_style='';
								$checked='checked';
							}else{
								$field_style='style="display:none;"';
								$checked='';
							} ?>
							<legend><input type="checkbox" id="restrictions_checkbox" name="restrictions_checkbox" onchange="var display_fieldset ='none'; if(this.checked){ display_fieldset = 'block'; } document.getElementById('restrictions').style.display=display_fieldset;" <?php echo $checked;?> /><label style="cursor:pointer;" for="restrictions_checkbox"><?php echo JText::_('HIKA_RESTRICTIONS'); ?></label></legend>
							<div id="restrictions" <?php echo $field_style; ?>>
								<table class="admintable table">
									<tr>
										<td class="key">
											<?php echo JText::_( 'ZONE' ); ?>
										</td>
										<td>
											<span id="zone_id" >
												<?php echo @$this->element->zone_id.' '.@$this->element->zone_name_english;
												$plugin_zone_namekey = $type.'_zone_namekey';
												?>
												<input type="hidden" name="data[<?php echo $type;?>][<?php echo $type;?>_zone_namekey]" value="<?php echo @$this->element->$plugin_zone_namekey; ?>" />
											</span>
											<?php
												echo $this->popup->display(
													'<img src="'. HIKASHOP_IMAGES.'edit.png" alt="'.JText::_('HIKA_EDIT').'"/>',
													'ZONE',
													 hikashop_completeLink("zone&task=selectchildlisting&type=".$type,true ),
													'zone_id_link',
													760, 480, '', '', 'link'
												);
											?>
											<a href="#" onclick="document.getElementById('zone_id').innerHTML='<input type=\'hidden\' name=\'data[<?php echo $type;?>][<?php echo $type;?>_zone_namekey]\' value=\'\' />';return false;" >
												<img src="<?php echo HIKASHOP_IMAGES; ?>delete.png" alt="delete"/>
											</a>
										</td>
									</tr>
									<?php if($this->plugin_type=='payment'){ ?>
									<tr>
										<td class="key">
												<?php echo JText::_( 'HIKASHOP_SHIPPING_METHOD' ); ?>
										</td>
										<td>
											<?php echo $this->shippingMethods->display('data[payment][payment_shipping_methods][]',@$this->element->payment_shipping_methods_type,@$this->element->payment_shipping_methods_id,true,'multiple="multiple" size="3"'); ?>
										</td>
									</tr>
									<tr>
										<td class="key">
												<?php echo JText::_( 'CURRENCY' ); ?>
										</td>
										<td>
											<?php echo $this->currencies->display('data[payment][payment_currency][]',@$this->element->payment_currency,'multiple="multiple" size="3"'); ?>
										</td>
									</tr>
									<?php } ?>
								</table>
							</div>
						</fieldset>

						<fieldset class="adminform">
							<legend><?php echo JText::_('ACCESS_LEVEL'); ?></legend>
							<?php
							if(hikashop_level(2)){
								$acltype = hikashop_get('type.acl');
								$access = $type.'_access';
								echo $acltype->display($access,@$this->element->$access,$type);
							}else{
								echo '<small style="color:red">'.JText::_('ONLY_FROM_BUSINESS').'</small>';
							} ?>
						</fieldset>
<?php if(!HIKASHOP_BACK_RESPONSIVE) { ?>
			</td>
		</tr>
	</table>
</div>
<?php } else { ?>
	</div>
</div>
<?php } ?>
			<input type="hidden" name="data[<?php echo $type;?>][<?php echo $type;?>_id]" value="<?php echo $this->id;?>"/>
			<input type="hidden" name="data[<?php echo $type;?>][<?php echo $type;?>_type]" value="<?php echo $this->plugin;?>"/>
		<?php
		}else{
			echo $this->content;
		}

		?>
		<input type="hidden" name="task" value="save"/>
		<input type="hidden" name="name" value="<?php echo $this->plugin;?>"/>
		<input type="hidden" name="ctrl" value="plugins" />
		<input type="hidden" name="plugin_type" value="<?php echo $this->plugin_type;?>" />
		<input type="hidden" name="option" value="<?php echo HIKASHOP_COMPONENT; ?>" />
		<?php echo JHTML::_( 'form.token' ); ?>
	</form>
</div>
