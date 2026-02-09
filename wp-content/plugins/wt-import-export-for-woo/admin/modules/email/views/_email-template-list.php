<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<table class="wp-list-table widefat fixed striped email_template_list_tb" style="margin-bottom:15px;">
<thead>
	<tr>
		<th><?php _e("Template name"); ?></th>
		<th><?php _e("Actions"); ?></th>
	</tr>
</thead>
<tbody>
<?php
if(isset($email_template_list) && is_array($email_template_list) && count($email_template_list)>0)
{
	foreach($email_template_list as $key =>$email_template_item)
	{
		?>
		<tr>
			<td><?php echo $email_template_item['name']; ?></td>
			<td>
				<div class="wt_iew_data_dv">
					<span class="wt_iew_email_id"><?php echo $email_template_item['id']; ?></span>
					<span class="wt_iew_email_template_name"><?php echo $email_template_item['name']; ?></span>
					<span class="wt_iew_email_template_subject"><?php echo $email_template_item['subject']; ?></span>
					<span class="wt_iew_email_template_body"><?php echo $email_template_item['body']; ?></span>				
				</div>
				<a class="wt_iew_email_template_edit wt_iew_action_btn" data-id="<?php echo esc_attr($email_template_item['id']); ?>"><?php _e('Edit');?></a> | <a class="wt_iew_email_template_delete wt_iew_action_btn" data-id="<?php echo esc_attr($email_template_item['id']); ?>"><?php _e('Delete');?></a>
				<?php
				if($this->popup_page==1)
				{
				?>
				 | <a class="wt_iew_email_template_use wt_iew_action_btn" data-id="<?php echo esc_attr($email_template_item['id']); ?>" title="<?php _e('Use this profile');?>"><?php _e('Use this profile');?></a>
				<?php
				}
				?>
			</td>
		</tr>
		<?php	
	}
}else
{
	?>
	<tr>
		<td colspan="2" style="text-align:center;">
			<?php _e("No email template  found."); ?> <?php _e("Click here to"); ?> <a class="wt_iew_email_template_add" style="cursor:pointer;"><?php _e('Add new');?></a>
		</td>
	</tr>
	<?php
}
?>
</tbody>
</table>