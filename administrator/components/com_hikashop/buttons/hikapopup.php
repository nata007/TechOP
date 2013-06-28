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
if(!HIKASHOP_J30) {
	if(!HIKASHOP_PHP5){
		$toolbarInstance =& JToolbar::getInstance();
	} else {
		$toolbarInstance = JToolbar::getInstance();
	}
	$toolbarInstance->loadButtonType('Popup');
	class JButtonHikaPopup extends JButtonPopup {}
} else {
	JToolbar::getInstance()->loadButtonType('Popup');
	class JToolbarButtonHikapopup extends JToolbarButtonPopup {
		public function fetchButton($type = 'Modal', $name = '', $text = '', $url = '', $width = 640, $height = 480, $top = 0, $left = 0, $onClose = '', $title = '') {
			list($name, $icon) = explode('#', $name, 2);
			$ret = parent::fetchButton($type, $name, $text, $url, $width, $height, $top, $left, $onClose, $title);
			if(!empty($icon)) {
				$ret = str_replace('<i class="icon-out-2">', '<i class="icon-'.$icon.'">', $ret);
			}
			$ret .= '<script>'."\n".'jQuery(document).ready(function(){jQuery("#modal-'.$name.'").appendTo(jQuery(document.body));});'."\n".'</script>'."\n";
			return $ret;
		}
	}
}
