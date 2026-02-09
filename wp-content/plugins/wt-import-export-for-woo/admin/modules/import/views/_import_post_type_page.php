<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wt_iew_import_main">
    <?php if(empty($post_types)){ ?>
        <div class="wt_iew_warn wt_iew_post_type_wrn">
            <?php printf(__('Atleast one of the <b>WebToffee add-ons(Product/Reviews, User, Order/Coupon/Subscription)</b> should be activated to start importing the respective post type.
                Go to <a href="%s" target="_blank">My accounts->API Downloads</a> to download and activate the add-on. If already installed activate the respective add-on plugin under <a href="%s" target="_blank">Plugins</a>.'),'https://www.webtoffee.com/my-account/my-api-downloads/',admin_url('plugins.php?s=webtoffee'));?>
        </div>
    <?php } else { ?>
		<p><?php _e($this->step_description, 'wt-import-export-for-woo');?></p>
		
		<div class="wt_iew_post-type-cards">
		<?php
		foreach ($post_types as $key => $value) {  
			$postImageLink = WT_IEW_PLUGIN_URL . 'assets/images/post_types/' . strtolower($key) . '.svg';
			$postImageLinkactive = WT_IEW_PLUGIN_URL . 'assets/images/post_types/' . strtolower($key) . 'active.svg'; 
			?>
			<div class="wt_iew_post-type-card <?php echo ($item_type == $key) ? 'selected' : ''; ?>" data-post-type="<?php echo esc_attr($key); ?>">
				<div class="wt_iew_post-type-card2">
					<div class="wt_iew_image <?php echo 'wt_iew_image_' . esc_html($key); ?>" style="display : <?php echo ($item_type == $key) ? 'none' : 'block'; ?>">
						<img src="<?php echo esc_url($postImageLink); ?>" />
					</div>
					<div class="<?php echo 'wt_iew_active_image_' . esc_html($key); ?>" style="display : <?php echo ($item_type == $key) ? 'block' : 'none'; ?>">
						<img src="<?php echo esc_url($postImageLinkactive); ?>" />
					</div>
				</div>
				<h3 class="wt_iew_post-type-card-hd"><?php echo esc_html($value); ?></h3>
				<div class="wt_iew_free_addon_warn <?php echo 'wt_iew_type_' . esc_html($key); ?>" style="display:block;">
				<?php ?>
					</div>
				</div>
			<?php } ?>
		</div>
	<?php } ?>
</div>