<?php
if (! defined('WPINC')) {
	die;
}
?>
<ul class="wt_iew_sub_tab">
	<li style="border-left:none; padding-left: 0px;" data-target="email-template"><a><?php _e('Email template', 'wt-import-export-for-woo'); ?></a></li>
	<li data-target="add-new-email-template"><a><?php _e('Add new'); ?></a></li>
</ul>
<div class="wt_iew_sub_tab_container">
	<div class="wt_iew_sub_tab_content" data-id="add-new-email-template" style="display:block;">
		<h3 class="wt_iew_form_title"> <?php _e("Add new Email template profile", 'wt-import-export-for-woo'); ?></h3>
		<form method="post" action="<?php echo esc_url($_SERVER["REQUEST_URI"]); ?>" id="wt_iew_email_template_form">
			<input type="hidden" value="0" name="wt_iew_email_id" />
			<input type="hidden" value="iew_email_template_ajax" name="action" />
			<input type="hidden" value="save_email_template" name="iew_email_template_action" />
			<?php
			// Set nonce:
			if (function_exists('wp_nonce_field')) {
				wp_nonce_field(WT_IEW_PLUGIN_ID);
			}
			?>
			<table class="form-table wt-iew-form-table">
				<tr>
					<th><label><?php _e("Template Name", 'wt-import-export-for-woo'); ?></label></th>
					<td>
						<?php
						$user_email_template_name_default = "Webtoffee test";
						$user_email_template_name = get_option("wt_iew_user_email_name", $user_email_template_name_default);
						?>
						<input type="text" name="wt_iew_email_template_name" value="<?php echo esc_attr($user_email_template_name); ?>">
					</td>
					<td></td>
				</tr>
				<tr>
					<th><label><?php _e("Email Subject", 'wt-import-export-for-woo'); ?></label></th>
					<td>
						<?php
						$user_email_subject_default = "Export Completed and File Attached!";
						$user_email_subject = get_option("wt_iew_email_template_subject", $user_email_subject_default);
						?>
						<input type="text" name="wt_iew_email_template_subject" value="<?php echo esc_attr($user_email_subject); ?>">
					</td>
					<td></td>
				</tr>
				<tr>
					<th style="width: 25% !important;"><label><?php esc_html_e("Email Body", 'wt-import-export-for-woo'); ?></label></th>
					<td style="width: 60%">
						<?php
						$editor_settings = array('textarea_rows' => 40, 'editor_height' => 400);
						$user_email_body_default = "The requested export has been completed. Please find the file attached to this email.\n\nExport Details:\n\nDate: [Export Date]\n\nTime: [Export Time]"; 
						$user_email_body = get_option("wt_iew_email_template_body", $user_email_body_default);

						// Convert newlines to HTML line breaks
						$user_email_body = wpautop($user_email_body);

						wp_editor($user_email_body, 'wt_iew_email_template_body', $editor_settings);
						?>
					</td>
					<td></td>
				</tr>
			</table>
			<?php
			$settings_button_title = __('Save settings', 'wt-import-export-for-woo');
			include WT_IEW_PLUGIN_PATH . "admin/views/admin-settings-save-button.php";
			?>
		</form>
	</div>
	<div class="wt_iew_sub_tab_content" data-id="email-template">
		<h3><?php _e("Email template", 'wt-import-export-for-woo'); ?></h3>
		<div class="wt_iew_email_template_list">
			<?php
			$this->get_email_template_list_html();
			?>
		</div>
	</div>
</div>