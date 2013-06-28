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
class hikashopPopupHelper {

	function __construct() {
	}

	function display($text, $title, $url, $id, $width, $height, $attr = '', $icon = '', $type = 'button', $dynamicUrl = false) {
		$html = '';
		$popupMode = 'mootools';
		if(HIKASHOP_J30) {
			$config = hikashop_config();
			$popupMode = $config->get('popup_mode', 'bootstrap');
			if(empty($popupMode) || $popupMode == 'inherit')
				$popupMode = 'bootstrap';
		}

		switch($popupMode) {
			case 'bootstrap':
				{
					if($text !== null) {
						if(!empty($id)) {
							if($type == 'button') {
								if($dynamicUrl)
									$html = '<button '.$this->getAttr($attr,'btn btn-small').' id="'.$id.'" onclick="window.hikashop.openBox(this,'.$url.',true); return false;">';
								else
									$html = '<button '.$this->getAttr($attr,'btn btn-small').' id="'.$id.'" onclick="window.hikashop.openBox(this,\''.$url.'\',true); return false;">';
							} else
								$html = '<a '.$attr.' href="#" id="'.$id.'" onclick="window.hikashop.openBox(this,null,true); return false;">';
						} else {
							if($type == 'button')
								$html = '<button '.$this->getAttr($attr,'btn btn-small').' onclick="window.hikashop.openBox(this,null,true); return false;">';
							else
								$html = '<a '.$attr.' href="'.$url.'" onclick="window.hikashop.openBox(this,this.href,true); return false;">';
						}

						if(!empty($icon)) {
							$html .= '<i class="icon-16-'.$icon.'"></i> ';
						}
						$html .= $text . (($type == 'button')?'</button>':'</a>');
					} else {
						$html = '<a style="display:none;" href="#" id="'.$id.'" onclick="window.hikashop.openBox(this,null,true); return false;"></a>';
					}

					$params = array(
						'title' => JText::_($title),
						'url' => $url,
						'height' => $height,
						'width' => $width
					);
					if($dynamicUrl) {
						$params['url'] = '\'+'.$url.'+\'';
					}
					if(!empty($id)) {
						$renderModal = JHtml::_('bootstrap.renderModal', 'modal-'.$id, $params);
						$html .= str_replace(
							array('id="modal-'.$id.'"'),
							array('id="modal-'.$id.'" style="width:'.($width+20).'px;height:'.($height+90).'px;margin-left:-'.(($width+20)/2).'px"'),
							$renderModal
						);
						$html .= '<script>'."\n".'jQuery(document).ready(function(){jQuery("#modal-'.$id.'").appendTo(jQuery(document.getElementById("hikashop_main_content") || document.body));});'."\n".'</script>'."\n";
					}
				}
				break;
			case 'mootools':
			default:
				{
					JHtml::_('behavior.modal');
					if($text === null)
						return $html;

					$onClick = '';
					if($dynamicUrl) {
						$onClick = ' onclick="this.href=' . str_replace('"', '\"', $url) . ';"';
						$url = '#';
					}
					$a = $attr;
					if(!empty($id))
						$a = $this->getAttr($attr,'modal');
					$html = '<a '.$a.$onClick.' id="'.$id.'" href="'.$url.'" rel="{handler: \'iframe\', size: {x: '.$width.', y: '.$height.'}}">';
					if($type == 'button')
						$html .= '<button class="btn" onclick="return false">';
					$html .= $text;
					if($type == 'button')
						$html .= '</button>';
					$html .= '</a>';
				}
				break;
		}
		return $html;
	}

	function getAttr($attr, $class) {
		if(empty($attr)) {
			return 'class="'.$class.'"';
		}
		$attr = ' '.$attr;
		if(strpos($attr, ' class="') !== false) {
			$attr = str_replace(' class="', ' class="'.$class.' ', $attr);
		} elseif(strpos($attr, ' class=\'') !== false) {
			$attr = str_replace(' class=\'', ' class=\''.$class.' ', $attr);
		} else {
			$attr .= ' class="'.$class.'"';
		}
		return trim($attr);
	}
}
