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
class VoteController extends hikashopController{

	var $modify_views = array();
	var $add = array();
	var $modify = array();
	var $delete = array();
	function __construct($config = array(),$skip=false){
		parent::__construct($config,$skip);
		if(!$skip){
			$this->registerDefaultTask('save');
		}
		$this->display[]='save';
	}
	function save(){
		$voteClass = hikashop_get('class.vote');

		$element = new stdClass();
			$element->hikashop_vote_type = JRequest::getVar('hikashop_vote_type', 0, 'default', 'string', 0);
			$element->vote_ref_id 		 = JRequest::getVar('hikashop_vote_ref_id', 0, 'default', 'int');
			if(empty($element->vote_ref_id))
				$element->vote_ref_id 		 = JRequest::getVar('hikashop_vote_product_id', 0, 'default', 'int');
			$element->user_id 			 = JRequest::getVar('hikashop_vote_user_id', 0, 'default', 'int');
			$element->pseudo_comment	 = JRequest::getVar('pseudo_comment', 0, 'default', 'string', 0);
			$element->email_comment		 = JRequest::getVar('email_comment', 0, 'default', 'string', 0);
			$element->vote_type			 = JRequest::getVar('vote_type', 0, 'default', 'string', 0);
			$element->vote 				 = JRequest::getVar('hikashop_vote', 0, 'default', 'int');
			$element->comment 			 = JRequest::getVar('hikashop_vote_comment','','','string',JREQUEST_ALLOWRAW); // JRequest::getVar('hikashop_vote_comment', 0, 'default', 'string', 0);
		$voteClass->save($element);
	}
}
?>
