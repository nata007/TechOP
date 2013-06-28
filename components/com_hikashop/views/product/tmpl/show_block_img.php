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
$variant_name = '';
$variant_main = '_main';
$display_mode = '';
if(!empty($this->variant_name)) {
	$variant_name = $this->variant_name;
	if(substr($variant_name, 0, 1) != '_')
		$variant_name = '_' . $variant_name;
	$variant_main = $variant_name;
	$display_mode = 'display:none;';
}
?><div id="hikashop_product_image<?php echo $variant_main;?>" class="hikashop_global_image_div" style="<?php echo $display_mode;?>">
	<div id="hikashop_main_image_div<?php echo $variant_name;?>" class="hikashop_main_image_div">
		<?php
			if(!empty ($this->element->images)){
				$image = reset($this->element->images);
			}
			$height = $this->config->get('product_image_y');
			$width = $this->config->get('product_image_x');
			if(empty($height)) $height=$this->config->get('thumbnail_y');
			if(empty($width)) $width=$this->config->get('thumbnail_x');
			$divWidth=$width;
			$divHeight=$height;
			$this->image->checkSize($divWidth,$divHeight,$image);

			if (!$this->config->get('thumbnail')) {
				if(!empty ($this->element->images)){
					echo '<img src="' . $this->image->uploadFolder_url . $image->file_path . '" alt="' . $image->file_name . '" id="hikashop_main_image" style="margin-top:10px;margin-bottom:10px;display:inline-block;vertical-align:middle" />';
				}
			} else {
				$style = '';
				if (!empty ($this->element->images) && count($this->element->images) > 1) {
					if (!empty($height)) {
						$style = ' style="height:' . ($height + 20) . 'px;"';
					}
				}
				$variant_name='';
				if(isset($this->variant_name)){
					$variant_name=$this->variant_name;
				}

				?>

				<div class="hikashop_product_main_image_thumb" id="hikashop_image_main_thumb_div<?php echo $variant_name;?>" <?php echo $style;?> >
					<div style="<?php if(!empty($divHeight)){ echo 'height:'.($divHeight+20).'px;'; } ?>text-align:center;clear:both;" class="hikashop_product_main_image">
						<div style="position:relative;text-align:center;clear:both;<?php if(!empty($divWidth)){ echo 'width:'.$divWidth.'px;'; } ?>margin: auto;" class="hikashop_product_main_image_subdiv">
						<?php
							echo $this->image->display(@$image->file_path,true,@$image->file_name,'id="hikashop_main_image'.$variant_name.'" style="margin-top:10px;margin-bottom:10px;display:inline-block;vertical-align:middle"','id="hikashop_main_image_link"', $width,  $height);
							if(!empty($this->element->badges))
								$this->classbadge->placeBadges($this->image, $this->element->badges, '0', '0');
						?>
						</div>
					</div>
				</div>
			<?php
			}
			?>
			</div>
			<div id="hikashop_small_image_div<?php echo $variant_name;?>" class="hikashop_small_image_div">
			<?php
			if (!empty ($this->element->images) && count($this->element->images) > 1) {
				foreach ($this->element->images as $image) {
					echo $this->image->display($image->file_path, 'hikashop_main_image'.$variant_name, $image->file_name, 'class="hikashop_child_image"','', $width,  $height);
				}
			}

		?>
	</div>
</div>
