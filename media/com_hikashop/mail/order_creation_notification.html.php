<?php
/**
 * @package	HikaShop for Joomla!
 * @version	2.1.3
 * @author	hikashop.com
 * @copyright	(C) 2010-2013 HIKARI SOFTWARE. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');
?><div style="background-color: #ffffff; font-family: Verdana, Arial, Helvetica, sans-serif;font-size:12px; color: #000000; width: 100%;">
	<table style="margin: auto;font-family: Verdana, Arial, Helvetica, sans-serif;font-size:12px;" border="0" cellspacing="0" cellpadding="0">
		<tbody>
			<tr>
				<td height="10">
				</td>
			</tr>
			<tr>
				<td>
					<?php echo JText::sprintf('HI_CUSTOMER',@$data->customer->name);?>
					<br/>
					<br/>
					<?php
					$colspan = 4;
					$url = $data->order_number;
					$config =& hikashop_config();
					if($config->get('simplified_registration',0)!=2){
						$url = '<a href="'.$data->order_url.'">'. $url.'</a>';
					}
					echo JText::sprintf('ORDER_CREATION_SUCCESS_ON_WEBSITE_AT_DATE',$url,HIKASHOP_LIVE, hikashop_getDate(time(),'%d %B %Y'), hikashop_getDate(time(),'%H:%M'));?>
				</td>
			</tr>
			<tr>
				<td height="10">
				</td>
			</tr>
			<tr>
				<td>
					<h1 style="background-color:#DDDDDD;font-size:14px;width:100%;padding:5px;"><?php echo JText::_('SUMMARY_OF_YOUR_ORDER');?></h1>
					<br/>
					<table width="100%" style="font-family: Verdana, Arial, Helvetica, sans-serif;font-size:12px;">
						<tr>
							<td style="font-weight:bold;"><?php
								echo JText::_('CART_PRODUCT_NAME');
							?></td>
							<?php if ($config->get('show_code')) { $colspan++; ?>
								<td style="font-weight:bold;"><?php echo JText::_('CART_PRODUCT_CODE'); ?></td>
							<?php } ?>
							<td style="font-weight: bold;"><?php
								echo JText::_('CART_PRODUCT_UNIT_PRICE');
							?></td>
							<td style="font-weight: bold;"><?php
								echo JText::_('CART_PRODUCT_QUANTITY');
							?></td>
							<td style="font-weight: bold; text-align: right;"><?php echo JText::_('HIKASHOP_TOTAL'); ?>
							</td>
						</tr>
						<?php
							if(hikashop_level(2)){
								$fieldsClass = hikashop_get('class.field');
								$null = null;
								$itemFields = $fieldsClass->getFields('frontcomp',$null,'item');
							}
							$group = $config->get('group_options',0);
							foreach($data->cart->products as $item){
								if($group && $item->cart_product_option_parent_id) continue;
								?>
								<tr>
									<td>
										<p>
											<?php echo $item->order_product_name;
											if($group){
												$display_item_price=false;
												foreach($data->cart->products as $j => $optionElement){
													if($optionElement->cart_product_option_parent_id != $item->cart_product_id) continue;
													if($optionElement->order_product_price>0){
														$display_item_price = true;
													}

												}
												if($display_item_price){
													if($config->get('price_with_tax')){
														echo ' '.$currencyHelper->format($item->order_product_price+$item->order_product_tax,$data->order_currency_id);
													}else{
														echo ' '.$currencyHelper->format($item->order_product_price,$data->order_currency_id);
													}
												}
											}
											?>
										</p><?php
										if(!empty($itemFields)){
											foreach($itemFields as $field){
												$namekey = $field->field_namekey;
												if(empty($item->$namekey)) continue;
												echo '<p>'.$fieldsClass->getFieldName($field).': '.$fieldsClass->show($field,$item->$namekey).'</p>';
											}
										}
										$optionsPrices = new stdClass();
										$optionsPrices->order_product_price=0;
										$optionsPrices->order_product_tax=0;
										$optionsPrices->order_product_total_price=0;
										$optionsPrices->order_product_total_price_no_vat=0;
										if($group){
											foreach($data->cart->products as $j => $optionElement){
												if($optionElement->cart_product_option_parent_id == 0 || $optionElement->cart_product_option_parent_id != $item->cart_product_id) continue;

												$optionsPrices->order_product_price +=$optionElement->order_product_price;
												$optionsPrices->order_product_tax +=$optionElement->order_product_tax;
												$optionsPrices->order_product_total_price+=$optionElement->order_product_total_price;
												$optionsPrices->order_product_total_price_no_vat+=$optionElement->order_product_total_price_no_vat;

												 ?>
													<p class="hikashop_order_option_name">
														<?php
															echo $optionElement->order_product_name;
															if($optionElement->order_product_price>0){
																if($config->get('price_with_tax')){
																	echo ' ( + '.$currencyHelper->format($optionElement->order_product_price+$optionElement->order_product_tax,$data->order_currency_id).' )';
																}else{
																	echo ' ( + '.$currencyHelper->format($optionElement->order_product_price,$data->order_currency_id).' )';
																}
															}
														?>
													</p>
											<?php
											}
										}
										$statusDownload = explode(',',$config->get('order_status_for_download','confirmed,shipped'));
										if(in_array($data->order_status,$statusDownload)){
											if(!empty($item->files)){
												global $Itemid;
												$url_itemid = '';
												if(!empty($Itemid)){
													$url_itemid='&Itemid='.$Itemid;
												}
												echo '<p>';
												foreach($item->files as $file){
													$fileName = empty($file->file_name) ? $file->file_path : $file->file_name;

													echo '<a href="'.hikashop_frontendLink('index.php?option=com_hikashop&ctrl=order&task=download&file_id='.$file->file_id.'&order_id='.$data->order_id.$url_itemid).'">'.$fileName.'</a><br/>';
												}
												echo '</p>';
											}
										} ?>
									</td>
									<?php if ($config->get('show_code')) { ?>
										<td><p class="hikashop_product_code_mail"><?php echo $item->order_product_code; ?></p></td>
									<?php } ?>
									<td>
									<?php
										if($config->get('price_with_tax')){
											echo $currencyHelper->format($item->order_product_price+$optionsPrices->order_product_price+$item->order_product_tax+$optionsPrices->order_product_tax,$data->order_currency_id);
										}else{
											echo $currencyHelper->format($item->order_product_price+$optionsPrices->order_product_price,$data->order_currency_id);
										} ?>
									</td>
									<td><?php echo $item->order_product_quantity; ?></td>
									<td style="text-align: right">
										<?php
										if($config->get('price_with_tax')){
											echo $currencyHelper->format($item->order_product_total_price+$optionsPrices->order_product_total_price,$data->order_currency_id);
										}else{
											echo $currencyHelper->format($item->order_product_total_price_no_vat+$optionsPrices->order_product_total_price_no_vat,$data->order_currency_id);
										} ?>
									</td>
								</tr>
								<?php
							}
							if(bccomp($data->order_discount_price,0,5)){
								echo '<tr><td colspan="'.$colspan.'" style="text-align:right">'.JText::_('HIKASHOP_COUPON').' : ';
								if($config->get('price_with_tax')){
									echo $currencyHelper->format($data->order_discount_price*-1,$data->order_currency_id);
								}else{
									echo $currencyHelper->format(($data->order_discount_price-@$data->order_discount_tax)*-1,$data->order_currency_id);
								}
								echo '</td></tr>';
							}
							if(bccomp($data->order_shipping_price,0,5)){
								echo '<tr><td colspan="'.$colspan.'" style="text-align:right">'.JText::_('HIKASHOP_SHIPPING_METHOD').' : ';
								if($config->get('price_with_tax')){
									echo $currencyHelper->format($data->order_shipping_price,$data->order_currency_id);
								}else{
									echo $currencyHelper->format($data->order_shipping_price-@$data->order_shipping_tax,$data->order_currency_id);
								}
								echo '</td></tr>';
							}
							if(bccomp($data->order_payment_price,0,5)){
								echo '<tr><td colspan="'.$colspan.'" style="text-align:right">'.JText::_('HIKASHOP_PAYMENT_METHOD').' : '.$currencyHelper->format($data->order_payment_price,$data->order_currency_id).'</td></tr>';
							}
							if(!empty($data->cart->additional)) {
								$exclude_additionnal = explode(',', $config->get('order_additional_hide', ''));
								foreach($data->cart->additional as $additional) {
									if(in_array($additional->name, $exclude_additionnal)) continue;
									echo '<tr><td colspan="'.$colspan.'" style="text-align:right">'. JText::_($additional->name).' : ';
									if(!empty($additional->price_value) || empty($additional->value)) {
										if($config->get('price_with_tax')){
											echo $currencyHelper->format($additional->price_value_with_tax, $data->order_currency_id);
										}else{
											echo $currencyHelper->format($additional->price_value, $data->order_currency_id);
										}
									} else {
										echo $additional->value;
									}
									echo '</td></tr>';
								}
							}
							if($data->cart->full_total->prices[0]->price_value!=$data->cart->full_total->prices[0]->price_value_with_tax){
								if($config->get('detailed_tax_display') && !empty($data->order_tax_info)){
									foreach($data->order_tax_info as $tax){
										echo '<tr><td colspan="'.$colspan.'" style="text-align:right">'.$tax->tax_namekey. ' : '.$currencyHelper->format($tax->tax_amount,$data->order_currency_id).'</td></tr>';
									}
								}else{
									echo '<tr><td colspan="'.$colspan.'" style="text-align:right">'.JText::sprintf('TOTAL_WITHOUT_VAT',$currencyHelper->format($data->cart->full_total->prices[0]->price_value,$data->order_currency_id)).'</td></tr>';
								}
								$text=JText::sprintf('TOTAL_WITH_VAT',$currencyHelper->format($data->cart->full_total->prices[0]->price_value_with_tax,$data->order_currency_id));
							}else{
								$text=JText::_('HIKASHOP_FINAL_TOTAL'). ' : '.$currencyHelper->format($data->cart->full_total->prices[0]->price_value_with_tax,$data->order_currency_id);
							}
							echo '<tr><td colspan="'.$colspan.'" style="text-align:right;font-weight:bold;">'.$text.'</td></tr>';

							?>
					</table>
				</td>
			</tr>
			<tr>
				<td height="10">
				</td>
			</tr>
			<tr>
				<td>
					<table width="100%" style="border: 1px solid #DDDDDD;font-family: Verdana, Arial, Helvetica, sans-serif;font-size:12px;">
					<?php
					$params = null;
					$js = '';
					$fieldsClass = hikashop_get('class.field');
					$app = JFactory::getApplication();
					if($app->isAdmin()){
						$view = 'order';
					}else{
						$view = 'address';
					}
					$template = trim(hikashop_getLayout($view,'address_template',$params,$js));
					if(!empty($data->cart->billing_address) && !empty($data->order_addresses_fields)){
						$billing = $template;
						foreach($data->order_addresses_fields as $field){
							$fieldname = $field->field_namekey;
							$address =& $data->order_addresses[$data->cart->billing_address->address_id];
							$billing=str_replace('{'.$fieldname.'}',$fieldsClass->show($field,$address->$fieldname),$billing);
						}

						echo '<tr><td style="font-weight:bold;background-color:#DDDDDD">'.JText::_('HIKASHOP_BILLING_ADDRESS').'</td></tr><tr><td>';
						echo str_replace(array("\r\n","\r","\n"),'<br/>',preg_replace('#{(?:(?!}).)*}#i','',$billing)).'<br/></td></tr>';
					}
					if(!empty($data->order_shipping_method)) {
						$currentShipping = hikashop_import('hikashopshipping',$data->order_shipping_method);
						if(method_exists($currentShipping, 'getShippingAddress')) {
							$override = $currentShipping->getShippingAddress($data->order_shipping_id);
							if($override !== false) {
								$data->override_shipping_address = $override;
							}
						}
					}
					if(!empty($data->override_shipping_address) || (!empty($data->cart->has_shipping) && !empty($data->cart->shipping_address))) echo '<tr><td style="font-weight:bold;background-color:#DDDDDD">'.JText::_('HIKASHOP_SHIPPING_ADDRESS').'</td></tr><tr><td>';
					if(!empty($data->override_shipping_address)) {
						echo $data->override_shipping_address.'<br/></td></tr>';
					} else if(!empty($data->cart->has_shipping) && !empty($data->cart->shipping_address)){
						$shipping = $template;
						foreach($data->order_addresses_fields as $field){
							$fieldname = $field->field_namekey;
							$address =& $data->order_addresses[$data->cart->shipping_address->address_id];
							$shipping=str_replace('{'.$fieldname.'}',$fieldsClass->show($field,$address->$fieldname),$shipping);
						}
						echo str_replace(array("\r\n","\r","\n"),'<br/>',preg_replace('#{(?:(?!}).)*}#i','',$shipping)).'<br/></td></tr>';
					}?>
					</table>
				</td>
			</tr>
			<tr>
				<td><?php
				$fields = $fieldsClass->getFields('frontcomp',$data,'order','');
				foreach($fields as $fieldName => $oneExtraField) {
					if(empty($data->$fieldName)) continue;
					echo "<br/>".$fieldsClass->trans($oneExtraField->field_realname).' : '.$fieldsClass->show($oneExtraField,$data->$fieldName);
				} ?></td>
			</tr>
			<tr>
				<td height="10">
				</td>
			</tr>
<?php
	JPluginHelper::importPlugin('hikashop');
	$dispatcher = JDispatcher::getInstance();
	$dispatcher->trigger('onAfterOrderProductsListingDisplay', array(&$data->cart, 'email_notification_html'));
?>
			<tr>
				<td>
					<?php

					if(!$app->isAdmin()){
						$confirmed = $config->get('order_confirmed_status','confirmed');
						if($data->order_status!=$confirmed && $data->order_payment_method!='collectondelivery'){
							if($data->cart->full_total->prices[0]->price_value_with_tax>0) echo JText::_('ORDER_VALID_AFTER_PAYMENT');

							$config =& hikashop_config();
							if($data->cart->full_total->prices[0]->price_value_with_tax>0 && hikashop_level(1) && $config->get('allow_payment_button',1)){
								global $Itemid;
								$url = '';
								if(!empty($Itemid)){
									$url='&Itemid='.$Itemid;
								}
								$pay_url = hikashop_frontendLink('index.php?option=com_hikashop&ctrl=order&task=pay&order_id='.$data->order_id.$url);
								if($config->get('force_ssl',0) && strpos('https://',$pay_url) === false) {
									$pay_url = str_replace('http://','https://',$pay_url);
								} ?>
								<a href="<?php echo $pay_url; ?>"><?php JText::_('PAY_NOW'); ?></a>
							<?php }
						} ?>
						<br/>
						<br/>
						<?php echo JText::sprintf('THANK_YOU_FOR_YOUR_ORDER',HIKASHOP_LIVE);
					}?>
					<br/>
					<br/>
					<?php echo JText::sprintf('BEST_REGARDS_CUSTOMER',$mail->from_name);?>
				</td>
			</tr>
			<tr>
				<td height="10">
				</td>
			</tr>
			<tr>
				<td height="10">
				</td>
			</tr>
		</tbody>
	</table>
</div>
