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
class hikashopCartHelper{
	function hikashopCartHelper(){
		static $done = false;
		static $override = false;
		if(!$done){
			$done = true;
			$app = JFactory::getApplication();
			$chromePath = JPATH_THEMES.DS.$app->getTemplate().DS.'html'.DS.'hikashop_button.php';
			if (file_exists($chromePath)){
				require_once ($chromePath);
				$override = true;
			}
		}
		$this->override = $override;
	}

	function displayButton($name,$map,&$params,$url='',$ajax="",$options="",$max_quantity=0,$min_quantity=1,$classname='',$inc=true){
		$config =& hikashop_config();

		$button = $config->get('button_style','normal');
		static $i=0;
		if($inc)
			$i++;
		if(!empty($ajax)){
			$ajax = 'onclick="var field=document.getElementById(\'hikashop_product_quantity_field_'.$i.'\');'.$ajax.'" ';
		}
		if(!empty($classname) && substr($classname, 0, 1) != ' ')
			$classname = ' '.$classname;
		if($this->override && function_exists('hikashop_button_render')){
			$html = hikashop_button_render($map,$name,$ajax,$options,$url,$classname);
		}else{
			switch($button){
				case 'rounded': //deprecated
					$params->set('main_div_name', 'hikashop_button_'.$i);
					$moduleHelper = hikashop_get('helper.module');
					$moduleHelper->setCSS($params);
					$url = 'href="'.$url.'" ';
					$html='
					<div id="'.$params->get('main_div_name').'">
					<div class="hikashop_container">
					<div class="hikashop_subcontainer">
					<a class="hikashop_cart_rounded_button'.$classname.'" '.$url.$ajax.$options.'>'.$name.'</a>
					</div>
					</div>
					</div>
					';
					break;
				case 'css':
					$url = 'href="'.$url.'" ';
					$html= '<a class="hikashop_cart_button'.$classname.'" '.$options.' '.$url.$ajax.'>'.$name.'</a>';
					break;
				case 'normal':
				default:
					$html= '<input type="submit" class="btn button hikashop_cart_input_button'.$classname.'" name="'.$map.'" value="'.$name.'" '.$ajax.$options.'/>';
					break;
			}
		}

		if($map=='add'){

			$show_quantity_field=$config->get('show_quantity_field',0);
			if($params->get('show_quantity_field',0)=='-1')$params->set('show_quantity_field',$show_quantity_field);

			if($params->get('show_quantity_field',0)==1){
				$max_quantity=(int)$max_quantity;
				$min_quantity=(int)$min_quantity;

				static $first = false;
				if(!$first && $map=='add'){
					$first=true;
					$js = '
					function hikashopQuantityChange(field,plus,max,min){
						var fieldEl=document.getElementById(field);
						var current = fieldEl.value;
						current = parseInt(current);
						if(plus){
							if(max==0 || current<max){
								fieldEl.value=parseInt(fieldEl.value)+1;
							}else if(max && current==max){
								alert(\''.JText::_('NOT_ENOUGH_STOCK',true).'\');
							}
						}else{
							if(current>1 && current>min){
								fieldEl.value=current-1;
							}
						}
						return false;
					}
					function hikashopCheckQuantityChange(field,max,min){
						var fieldEl=document.getElementById(field);
						var current = fieldEl.value;
						current = parseInt(current);
						if(max && current>max){
							fieldEl.value=max;
							alert(\''.JText::_('NOT_ENOUGH_STOCK',true).'\');
						}else if(current<min){
							fieldEl.value=min;
						}
						return false;
					}
					';
					$setJS=$params->get('js');
					if(!$setJS){
						if (!HIKASHOP_PHP5) {
							$doc =& JFactory::getDocument();
						}else{
							$doc = JFactory::getDocument();
						}
						$doc->addScriptDeclaration("<!--\n".$js."\n//-->\n");
					}else{
						echo '<script type="text/javascript">'."<!--\n".$js."\n//-->\n".'</script>';
					}
				}
				if($this->override && function_exists('hikashop_quantity_render')){
					$html = hikashop_quantity_render($html,$i,$max_quantity,$min_quantity);
				}else{
					$html ='
					<table>
						<tr>
							<td rowspan="2">
								<input id="hikashop_product_quantity_field_'.$i.'" type="text" value="'.JRequest::getInt('quantity',$min_quantity).'" class="hikashop_product_quantity_field" name="quantity" onchange="hikashopCheckQuantityChange(\'hikashop_product_quantity_field_'.$i.'\','.$max_quantity.','.$min_quantity.');" />
							</td>
							<td>
								<a id="hikashop_product_quantity_field_change_plus_'.$i.'" class="hikashop_product_quantity_field_change_plus hikashop_product_quantity_field_change" href="#" onclick="return hikashopQuantityChange(\'hikashop_product_quantity_field_'.$i.'\',1,'.$max_quantity.','.$min_quantity.');">+</a>
							</td>
							<td rowspan="2">
								'.$html.'
							</td>
						</tr>
						<tr>
							<td>
								<a id="hikashop_product_quantity_field_change_minus_'.$i.'" class="hikashop_product_quantity_field_change_minus hikashop_product_quantity_field_change" href="#" onclick="return hikashopQuantityChange(\'hikashop_product_quantity_field_'.$i.'\',0,'.$max_quantity.','.$min_quantity.');">&ndash;</a>
							</td>
						</tr>
					</table>
					';
				}
			}elseif($params->get('show_quantity_field',0)==0){
				$html.='<input id="hikashop_product_quantity_field_'.$i.'" type="hidden" value="'.$min_quantity.'" class="hikashop_product_quantity_field" name="quantity" />';
			}elseif($params->get('show_quantity_field',0)==-1){
				static $second = false;
				if(!$second){
					$second=true;
					$js = '

					function hikashopQuantityChange(field,plus,max,min){
						var fieldEl=document.getElementById(field);
						var current = fieldEl.value;
						current = parseInt(current);
						if(plus){
							if(max==0 || current<max){
								fieldEl.value=parseInt(fieldEl.value)+1;
							}else if(max && current==max){
								alert(\''.JText::_('NOT_ENOUGH_STOCK',true).'\');
							}
						}else{
							if(current>1 && current>min){
								fieldEl.value=current-1;
							}
						}
						return false;
					}

					';
					$setJS=$params->get('js');
					if(!$setJS){
					$doc =& JFactory::getDocument();
					$doc->addScriptDeclaration("\n<!--\n".$js."\n//-->\n");
					}else{
						echo '<script type="text/javascript">'."<!--\n".$js."\n//-->\n".'</script>';
					}
				}
				$html = '<input id="hikashop_product_quantity_field_'.$i.'" type="text" value="'.JRequest::getInt('quantity',$min_quantity).'" class="hikashop_product_quantity_field" name="quantity" onchange="hikashopCheckQuantityChange(\'hikashop_product_quantity_field_'.$i.'\','.$max_quantity.','.$min_quantity.');" />'.$html;
			}elseif($params->get('show_quantity_field',0)==2){
			}
		}
		return $html;
	}

	function cartCount($add=false){
		static $carts = 0;
		if($add){
			$carts=$carts+1;
		}
		return $carts;
	}

	function getJS($url,$needNotice=true){
		static $first = true;
		if($first){
			$config =& hikashop_config();
			$redirect = $config->get('redirect_url_after_add_cart','stay_if_cart');
			global $Itemid;
			$url_itemid='';
			if(!empty($Itemid)){
				$url_itemid='&Itemid='.$Itemid;
			}
			$baseUrl = hikashop_completeLink('product&task=updatecart',true,true);
			if(strpos($baseUrl,'?')!==false){
				$baseUrl.='&';
			}else{
				$baseUrl.='?';
			}
			if($redirect=='ask_user'){
				JHTML::_('behavior.modal');
				if($needNotice && JRequest::getVar('tmpl','')!='component'){
					if($this->override && function_exists('hikashop_popup_render')){
						echo hikashop_popup_render();
					}else{
						echo '<div style="display:none;">'.
							'<a rel="{handler: \'iframe\',size: {x: 480, y: 140}}" id="hikashop_notice_box_trigger_link" href="'.hikashop_completeLink('checkout&task=notice'.$url_itemid,true).'"></a>'.
							'<a rel="{handler: \'iframe\',size: {x: 480, y: 140}}" id="hikashop_notice_wishlist_box_trigger_link" href="'.hikashop_completeLink('checkout&task=notice&cart_type=wishlist'.$url_itemid,true).'"></a>'.
							'</div>';
					}
				}
				if($this->override && function_exists('hikashop_popup_js_render')){
						$js = hikashop_popup_js_render($url);
				}else{
					$addTo = JRequest::getString('add_to','');
					if(!empty($addTo))
						$addTo = '&addTo='.$addTo;
					$js = '
	function hikashopModifyQuantity(id,obj,add,form,type,moduleid){
		var d = document, cart_type="cart", addStr="", qty=1, e = null;
		if(type) cart_type = type;
		if(add) addStr = "&add=1";

		if(moduleid === undefined) moduleid = 0;

		if(obj){
			qty = parseInt(obj.value);
		}else if(document.getElementById("hikashop_product_quantity_field_"+id).value){
			qty = document.getElementById("hikashop_product_quantity_field_"+id).value;
		}
		if(form && document[form]){
			var varform = document[form];
			e = d.getElementById("hikashop_cart_type_"+id+"_"+moduleid);

			if(!e)
				e = d.getElementById("hikashop_cart_type_"+id);
			if(cart_type == "wishlist"){
				if(e) e.value = "wishlist";
				if(varform.cid) varform.cid.value = id;
				f = d.getElementById("type");
				if(f) f.value = "wishlist";
			}else{
				if(e) e.value = "cart";
				if(varform.cid) varform.cid.value = id;
			}
			if(varform.task) {
				varform.task.value = "updatecart";
			}
			varform.submit();
		}else{
			if(qty){
				if(cart_type == "wishlist"){
					SqueezeBox.fromElement("hikashop_notice_wishlist_box_trigger_link",{parse: "rel"});
				} else {
					SqueezeBox.fromElement("hikashop_notice_box_trigger_link",{parse: "rel"});
				}
			}
			var url = "'.$baseUrl.'from=module&product_id="+id+"&cart_type="+cart_type+"&quantity="+qty+addStr+"'.$url_itemid.$addTo.'&return_url='.urlencode(base64_encode(urldecode($url))).'";
			var completeFct = function(result) {
				if(cart_type != "wishlist") {
					var hikaModule = window.document.getElementById("hikashop_cart_module");
					if(hikaModule) hikaModule.innerHTML = result;
				}
			};
			try{
				new Ajax(url, {method: "get", onComplete: completeFct}).request();
			}catch(err){
				new Request({url: url, method: "get", onComplete: completeFct}).send();
			}
		}
		return false;
	}
';
		}
	}else{
		if($this->override && function_exists('hikashop_cart_js_render')){
			$js = hikashop_cart_js_render($url);
		}else{
			$js='';
			if($this->cartCount()!=1 && !empty($url)){
				$js = 'window.location = \''.urldecode($url).'\';';
			}
			$addTo = JRequest::getString('add_to','');
			if(!empty($addTo))
				$addTo = '&addTo='.$addTo;
			$js = '
	function hikashopModifyQuantity(id,obj,add,form,type,moduleid){
		var d = document, cart_type="cart", addStr="", qty=1, e = null;
		if(type) cart_type = type;
		if(add) addStr = "&add=1";

		if(moduleid === undefined) moduleid = 0;

		if(obj){
			qty = parseInt(obj.value);
		}else if(document.getElementById("hikashop_product_quantity_field_"+id).value){
			qty = document.getElementById("hikashop_product_quantity_field_"+id).value;
		}
		if(form && document[form]){
			var varform = document[form];
			e = d.getElementById("hikashop_cart_type_"+id+"_"+moduleid);

			if(!e)
				e = d.getElementById("hikashop_cart_type_"+id);
			if(cart_type == "wishlist"){
				if(e) e.value = "wishlist";
				f = d.getElementById("type");
				if(f) f.value = "wishlist";
			}else{
				if(e) e.value = "cart";
			}
			if(varform.task) {
				varform.task.value = "updatecart";
			}
			varform.submit();
		}else{
			var url = "'.$baseUrl.'from=module&product_id="+id+"&cart_type="+cart_type+"&quantity="+qty+addStr+"'.$url_itemid.$addTo.'&return_url='.urlencode(base64_encode(urldecode($url))).'";
			var completeFct = function(result) {
				var hikaModule = false;
				if(cart_type != "wishlist") {
					hikaModule = window.document.getElementById("hikashop_cart_module");
					if(hikaModule) hikaModule.innerHTML = result;
				}
				if(!hikaModule) {
					'.$js.'
				}
			};
			try{
				new Ajax(url, {method: "get", onComplete: completeFct}).request();
			}catch(err){
				new Request({url: url, method: "get", onComplete: completeFct}).send();
			}
		}
		return false;
	}
';
				}
				if(!HIKASHOP_J30)
					JHTML::_('behavior.mootools');
				else
					JHTML::_('behavior.framework');
			}
			if (!HIKASHOP_PHP5) {
				$doc =& JFactory::getDocument();
			}else{
				$doc = JFactory::getDocument();
			}
			$doc->addScriptDeclaration("\n<!--\n".$js."\n//-->\n");
			$first = !$needNotice;
			return $js;
		}
	}
}
