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
	$id=$this->widget->widget_id;
	$row_id=0;
?>
<div id="table_<?php echo $id; ?>" style="margin:auto; margin-b" align="center">
	<?php

	echo '<table class="widget_table" style="margin-bottom:10px;">';
	foreach($this->widget->widget_params->table as $key => $row){
		$name = str_replace(' ','_',strtoupper($row->row_name));?>
			<tr>
				<td class="key">
					<?php
					if(JText::_($name)==$name) echo $row->row_name;
					else echo JText::_($name); ?>
					<br/>
				</td>
				<td class="data">
					<?php
					if(empty($row->elements)){
						echo JText::_('NO_DATA');
					}
					if(is_numeric($row->elements)){	echo round($row->elements,2); }
					else{ echo $row->elements; }

					if(isset($this->edit)){ ?>
						<td style="float:right; padding:10px; width:50px;">
							<a onclick="document.getElementById('delete_row').value = '<?php echo $key; ?>';submitbutton('apply_table');" href="#">
								<img src="<?php echo HIKASHOP_IMAGES.'delete.png'; ?>" alt="delete"/>
							</a>
							<a class="modal" href=" <?php echo hikashop_completeLink('report&task=tableform&widget_id='.$id.'&row_id='.$key,true, true ); ?>" rel="{handler: 'iframe', size: {x: 900, y: 480}}">
								<img src="<?php echo HIKASHOP_IMAGES.'edit.png'; ?>" alt="edit"/>
							</a>
						</td>
					<?php } ?>
				</td>
			</tr>
	<?php }
	echo '</table>';
	foreach($this->widget->widget_params->table as $key => $row){
		$row_id=$key+1;
	}

	if(isset($this->edit)){?>
	<a class="modal" href=" <?php echo hikashop_completeLink('report&task=tableform&widget_id='.$id.'&row_id='.$row_id,true, true ); ?>" rel="{handler: 'iframe', size: {x: 900, y: 480}}">
		<button class="btn" type="button" onclick="return false">
						<img src="<?php echo HIKASHOP_IMAGES; ?>add.png"/><?php echo JText::_('ADD');?>
		</button>
	</a>
	<?php } ?>
</div>
<br/>
