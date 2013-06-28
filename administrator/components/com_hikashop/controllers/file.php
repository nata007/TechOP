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
class FileController extends hikashopController{
	var $toggle = array();
	var $display = array();
	var $modify_views = array();
	var $add = array();
	var $modify = array('resetdownload');
	var $delete = array('delete');
	function resetdownload(){
		$download = hikashop_get('class.file');
		$download->resetdownload(JRequest::getInt('file_id'),JRequest::getInt('order_id'));
		$return = JRequest::getString('return');
		if(!empty($return)){
			$url = base64_decode(urldecode($return));
			if(strpos($url,HIKASHOP_LIVE)===false && preg_match('#^https?://.*#',$url)) return false;
			$this->setRedirect($url);
		}
	}
}
