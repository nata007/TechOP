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

if(!defined('DS'))
	define('DS', DIRECTORY_SEPARATOR);
if(!@include_once(rtrim(JPATH_ADMINISTRATOR,DS).DS.'components'.DS.'com_hikashop'.DS.'helpers'.DS.'helper.php')) return true;

class plgButtonHikashopproduct extends JPlugin
{
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}

	function onDisplay($name, $asset='', $author=''){
		$extension = JRequest::getCmd('option');
		$ctrl = JRequest::getString('ctrl');
		$task = JRequest::getString('task');
		if ($extension=='com_hikashop'&&$ctrl=='product'&&$task=='show') return;
		if(version_compare(JVERSION,'1.6.0','<')){
			global $mainframe;
			$params = JComponentHelper::getParams('com_media');
			$acl = JFactory::getACL();
			switch ($params->get('allowed_media_usergroup'))
			{
				case '1':
					$acl->addACL( 'com_media', 'upload', 'users', 'publisher' );
					break;
				case '2':
					$acl->addACL( 'com_media', 'upload', 'users', 'publisher' );
					$acl->addACL( 'com_media', 'upload', 'users', 'editor' );
					break;
				case '3':
					$acl->addACL( 'com_media', 'upload', 'users', 'publisher' );
					$acl->addACL( 'com_media', 'upload', 'users', 'editor' );
					$acl->addACL( 'com_media', 'upload', 'users', 'author' );
					break;
				case '4':
					$acl->addACL( 'com_media', 'upload', 'users', 'publisher' );
					$acl->addACL( 'com_media', 'upload', 'users', 'editor' );
					$acl->addACL( 'com_media', 'upload', 'users', 'author' );
					$acl->addACL( 'com_media', 'upload', 'users', 'registered' );
					break;
			}

			$user = JFactory::getUser();
			if (!$user->authorize( 'com_media', 'popup' )) {
				return;
			}
			$doc 		= JFactory::getDocument();
			$template 	= $mainframe->getTemplate();

			$pluginsClass = hikashop_get('class.plugins');
			$plugin = $pluginsClass->getByName('editors-xtd','hikashopproduct');
			$link = 'index.php?option=com_hikashop&amp;ctrl=plugins&amp;task=trigger&amp;function=productDisplay&amp;tmpl=component&amp;cid='.$plugin->id.'&amp;'.hikashop_getFormToken().'=1';
			JHtml::_('behavior.modal');
			$button = new JObject;
			$button->set('modal', true);
			$button->set('link', $link);
			$button->set('text', JText::_('PRODUCT'));
			$button->set('name', 'hikashopproduct');
			$button->set('options', "{handler: 'iframe', size: {x: 800, y: 450}}");
			$doc = JFactory::getDocument();

			if(!HIKASHOP_J30)
				JHTML::_('behavior.mootools');
			else
				JHTML::_('behavior.framework');
			$name = 'hikashopproduct.png';
			$path = '../plugins/editors-xtd/'.$name;
			$doc->addStyleDeclaration('.button2-left .hikashopproduct {background: url('.$path.') 100% 0 no-repeat; }');

			return $button;
		}
		else{
			$app = JFactory::getApplication();
			$params = JComponentHelper::getParams('com_media');
			$user = JFactory::getUser();




			if ($asset == ''){
				$asset = $extension;
			}
			if (	$user->authorise('core.edit', $asset)
				||	$user->authorise('core.create', $asset)
				||	(count($user->getAuthorisedCategories($asset, 'core.create')) > 0)
				||	($user->authorise('core.edit.own', $asset) && $author == $user->id)
				||	(count($user->getAuthorisedCategories($extension, 'core.edit')) > 0)
				||	(count($user->getAuthorisedCategories($extension, 'core.edit.own')) > 0 && $author == $user->id)
			){
				$pluginsClass = hikashop_get('class.plugins');
				$plugin = $pluginsClass->getByName('editors-xtd','hikashopproduct');

				$link = 'index.php?option=com_hikashop&amp;ctrl=plugins&amp;task=trigger&amp;function=productDisplay&amp;tmpl=component&amp;cid='.$plugin->extension_id.'&amp;'.hikashop_getFormToken().'=1';
				JHtml::_('behavior.modal');
				$button = new JObject;
				$button->set('modal', true);
				$button->set('link', $link);
				$button->set('text', JText::_('PRODUCT'));
				$button->set('name', 'hikashopproduct');
				$button->set('options', "{handler: 'iframe', size: {x: 800, y: 450}}");
				$doc = JFactory::getDocument();

				if(!HIKASHOP_J30)
					JHTML::_('behavior.mootools');
				else
					JHTML::_('behavior.framework');
				$name = 'hikashopproduct.png';
				$path = '../plugins/editors-xtd/hikashopproduct/'.$name;
				$doc->addStyleDeclaration('.button2-left .hikashopproduct {background: url('.$path.') 100% 0 no-repeat; }');

				return $button;
			}
			else{
				return false;
			}
		}
	}
	function productDisplay(){
		jimport('joomla.html.pagination');
		$app = JFactory::getApplication();
		$db = JFactory::getDBO();

		$pageInfo = new stdClass();
		$pageInfo->filter = new stdClass();
		$pageInfo->filter->order = new stdClass();
		$pageInfo->limit = new stdClass();
		$pageInfo->limit->value = $app->getUserStateFromRequest( 'global.list.limit', 'limit', $app->getCfg('list_limit'), 'int' );
		$pageInfo->limit->start = $app->getUserStateFromRequest( 'limitstart', 'limitstart', 0, 'int' );

		$db->setQuery('SELECT * FROM '. hikashop_table('product') .' WHERE product_type=\'main\' AND product_access=\'all\' AND product_published=1 ORDER BY product_id ASC',(int)$pageInfo->limit->start,(int)$pageInfo->limit->value);
		$products = $db->loadObjectList();
		$db->setQuery('SELECT COUNT(product_id) FROM '. hikashop_table('product').' WHERE product_type=\'main\' AND product_access=\'all\' AND product_published=1' );
		$nbrow = $db->loadResult();
		$db->setQuery('SELECT * FROM '. hikashop_table('price') .' ORDER BY price_product_id ASC');
		$prices = $db->loadObjectList();
		$pagination = new JPagination( $nbrow , $pageInfo->limit->start, $pageInfo->limit->value);

		$scriptV1 = "function insertTag(tag){ window.parent.jInsertEditorText(tag,'text'); return true;}";
		$scriptV2 = "function insertTag(tag){ window.parent.jInsertEditorText(tag,'jform_articletext'); return true;}";
		if (!HIKASHOP_PHP5) {
			$doc =& JFactory::getDocument();
		}else{
			$doc = JFactory::getDocument();
		}
		if(version_compare(JVERSION,'1.6.0','<')) $doc->addScriptDeclaration( $scriptV1 );
		else $doc->addScriptDeclaration( $scriptV2 );

		$config =& hikashop_config();
		$pricetaxType = hikashop_get('type.pricetax');
		$discountDisplayType = hikashop_get('type.discount_display');
?>
	<script language="JavaScript" type="text/javascript">
		function divhidder(){
			if (document.getElementById('price').checked) {
				document.getElementById('Priceopt').style.visibility = 'visible';
			}
			else {
				document.getElementById('Priceopt').style.visibility = 'hidden';
			}
		}
		function checkSelect(){
			form = document.getElementById('adminForm');
			inputs = form.getElementsByTagName('input');
			nbbox = 0;
			nbboxOk = 0;
			nbboxProd = 0;

			for(i=0 ; i<inputs.length ; i++){
				if(inputs[i].type=='checkbox' && inputs[i].checked==true){
					nbbox++;
				}
			}
			for(i=0 ; i<inputs.length ; i++){
				if(inputs[i].type=='checkbox' && inputs[i].checked==true){
					nbboxOk++;
					if(inputs[i].id=='product'){
					<!-- Tag construction -->
						if (nbboxProd ==0 )form.getElementById('product').value='{product}';
						nbboxProd++;
							form.getElementById('product').value =  form.getElementById('product').value +  inputs[i].name;
						if(nbbox>nbboxOk){
							form.getElementById('product').value =  form.getElementById('product').value + '|';
						}
					}
				}
			}
			<!-- if a product is checked -->
			if( nbboxProd > 0 )
			{
				if(form.getElementById('name').checked==true){
					form.getElementById('product').value =form.getElementById('product').value +  '|name';
				}
				if(form.getElementById('cart').checked==true){
					form.getElementById('product').value =form.getElementById('product').value +  '|cart';
				}
				if(form.getElementById('quantityfield').checked==true){
					form.getElementById('product').value =form.getElementById('product').value +  '|quantityfield';
				}
				if(form.getElementById('description').checked==true){
					form.getElementById('product').value =form.getElementById('product').value +  '|description';
				}
				if(form.getElementById('picture').checked==true){
					form.getElementById('product').value =form.getElementById('product').value +  '|picture';
				}
				if(form.getElementById('link').checked==true){
					form.getElementById('product').value =form.getElementById('product').value +  '|link';
				}
				if(form.getElementById('border').checked==true){
					form.getElementById('product').value =form.getElementById('product').value +  '|border';
				}
				if(form.getElementById('pricedisc').value==1 && form.getElementById('price').checked==true){
				form.getElementById('product').value =form.getElementById('product').value +  '|pricedis1';
				}
				if(form.getElementById('pricedisc').value==2 && form.getElementById('price').checked==true){
					form.getElementById('product').value =form.getElementById('product').value +  '|pricedis2';
				}
				if(form.getElementById('pricedisc').value==3 && form.getElementById('price').checked==true){
					form.getElementById('product').value =form.getElementById('product').value +  '|pricedis3';
				}
				if(form.getElementById('pricetax').value==1 && form.getElementById('price').checked==true){
					form.getElementById('product').value =form.getElementById('product').value +  '|pricetax1';
				}
				if(form.getElementById('pricetax').value==2 && form.getElementById('price').checked==true){
					form.getElementById('product').value =form.getElementById('product').value +  '|pricetax2';
				}
				if(form.getElementById('pricedisc').value==0 && form.getElementById('pricetax').value==0 && form.getElementById('price').checked==true){
				form.getElementById('product').value =form.getElementById('product').value +  '|price';
				}
				form.getElementById('product').value=form.getElementById('product').value + '{/product}';
			}
			<!-- if we don't have any option -->
			if(form.getElementById('name').checked==false
			&& form.getElementById('price').checked==false
			&& form.getElementById('cart').checked==false
			&& form.getElementById('description').checked==false
			&& form.getElementById('picture').checked==false){
				form.getElementById('product').value='';
			}
		}
</script>

		<form action="<?php echo hikashop_currentURL();?>" method="post" name="adminForm" id="adminForm">
			<fieldset>
				<legend>OPTION</legend>
				<input type="checkbox" name="name" id="name" value="1" checked/><?php echo JText::_( 'HIKA_NAME' );?>
				<input type="checkbox" name="description" id="description" value="1" checked/><?php echo JText::_( 'PRODUCT_DESCRIPTION' );?>
				<input type="checkbox" name="cart" id="cart" value="1" <?php if(!empty($_REQUEST['cart'])) echo 'checked'; ?> /><?php echo JText::_( 'HIKASHOP_CHECKOUT_CART' );?>
				<input type="checkbox" name="quantity" id="quantityfield" value="1" <?php if(!empty($_REQUEST['quantityfield'])) echo 'checked'; ?> /><?php echo JText::_( 'HIKA_QUANTITY_FIELD' );?>
				<input type="checkbox" name="picture" id="picture" value="1" <?php if(!empty($_REQUEST['picture'])) echo 'checked'; ?>/><?php echo JText::_( 'HIKA_IMAGE' );?>
				<input type="checkbox" name="link" id="link" value="1" <?php if(!empty($_REQUEST['link'])) echo 'checked'; ?>/><?php echo JText::_( 'LINK_TO_PRODUCT_PAGE' );?>
				<input type="checkbox" name="border" id="border" value="1" <?php if(!empty($_REQUEST['border'])) echo 'checked'; ?> /><?php echo JText::_( 'ITEM_BOX_BORDER' );?>
				<input type="checkbox" name="pricetax" id="pricetax" value="<?php echo $config->get('price_with_tax');?>" hidden/>
				<br/>
				<input type="checkbox" name="price" id="price" value="1" checked onclick="divhidder()"/><?php echo JText::_('DISPLAY_PRICE');?>
				<br/>
				<div id="Priceopt">
				<tr id="show_discount_line">
					<td class="key" valign="top">
						<?php echo JText::_('SHOW_DISCOUNTED_PRICE');?>
					</td>
					<td>
						<?php
						$default_params = $config->get('default_params');
						echo $discountDisplayType->display( 'pricedisc' ,3); ?>
					</td>
				</tr>
				<div>
			</fieldset>

			<fieldset>
				<table class="adminlist table table-striped" cellpadding="1">
					<thead>
						<tr>
							<th class="title titlenum">
								<?php echo JText::_( 'HIKA_NUM' );?>
							</th>
							<th class="title titlebox">
								<input type="checkbox" name="toggle" value="" DISABLED/>
							</th>
							<th class="title">
								<?php echo JText::_( 'HIKA_NAME' ); ?>
							</th>
							<th class="title">
								<?php echo JText::_('PRODUCT_PRICE'); ?>
							</th>
							<th class="title">
								<?php echo JText::_('PRODUCT_QUANTITY'); ?>
							</th>
							<th class="title">
								<?php echo'Id'; ?>
							</th>
						</tr>
					</thead>
					<tbody>
						<?php
							$i = 0;
							$row ='';
							$currencyClass = hikashop_get('class.currency');
							$currencies=new stdClass();
							$currency_symbol='';
							foreach($products as $product){
								$i++;
								$row.= '<tr><th class="title titlenum">';
								$row.= $i;
								$row.='</th><th class="title titlebox"><input type="checkbox" id="product" name="'.$product->product_id;
								$row.='" value=""/></th><th class="center">';
								$row.=$product->product_name;
								$row.='</th><th class="center">';
								foreach($prices as $price){
									if($price->price_product_id==$product->product_id){
										$row.= $price->price_value;
										$currency = $currencyClass->getCurrencies($price->price_currency_id,$currencies);
										foreach($currency as $currrencie){
											if($price->price_currency_id == $currrencie->currency_id){
												$currency_symbol = $currrencie->currency_symbol;
											}
										}
										$row.=' ' .$currency_symbol;
									}
								}
								$row.='</th><th class="center">';
								if($product->product_quantity > -1) $row.=$product->product_quantity;
								else $row.= JText::_('UNLIMITED');
								$row.='</th><th class="center">';
								$row.=$product->product_id;
								$row.='</th></tr>';
							}
							echo $row;
						?>
					</tbody>
					<tfoot>
						<tr>
							<td colspan="7">
								<?php echo $pagination->getListFooter(); ?>
							</td>
						</tr>
					</tfoot>
				</table>
			</fieldset>
			<input type="hidden" name="product" id="product" value="" />
			<button class="btn" onclick="checkSelect(); insertTag(document.getElementById('product').value); window.parent.SqueezeBox.close();"><?php echo JText::_( 'HIKA_INSERT' ); ?></button>
			<?php echo JHTML::_( 'form.token' );
	}
}
?>
