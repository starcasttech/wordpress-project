<?php
/**
 * Main view file of import section
 *
 * @link            
 *
 * @package  Wt_Import_Export_For_Woo
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<?php
do_action('wt_iew_importer_before_head');
?>
<style type="text/css">
.wt_iew_import_step{ display:none; }
.wt_iew_import_step_loader{ width:100%; height:400px; text-align:center; line-height:400px; font-size:14px; }
.wt_iew_import_step_main{ float:left; box-sizing:border-box; padding:15px; padding-bottom:0px; width:95%; margin:30px 2.5%; background:#fff; box-shadow:0px 2px 2px #ccc; border:solid 1px #efefef; }
.wt_iew_import_main{ padding:20px 0px; }

.wt-something-went-wrong{position:fixed; left:50%; top:60%; margin-left:-175px; margin-top:-220px; width:350px;height:auto;background: #FFF; padding:25px; box-sizing:border-box;border: 1px solid #8c0603;border-radius: 6px;z-index: 1000000009;}
a:active, a:focus, a:visited {
  outline: none !important;
  border: none !important;
  box-shadow: none !important;
  -moz-outline-style: none !important;
}

</style>
<div class="wt_iew_view_log wt_iew_popup" style="text-align:left">
	<div class="wt_iew_popup_hd">
		<span style="line-height:40px;" class="dashicons dashicons-media-text"></span>
		<span class="wt_iew_popup_hd_label"><?php _e('History Details');?></span>
		<div class="wt_iew_popup_close">X</div>
	</div>
    <div class="wt_iew_log_container" style="padding:25px;">
		
	</div>
</div>
<div class="wt_iew_import_progress_wrap wt_iew_popup">
		<div class="wt_iew_popup_hd wt_iew_import_progress_header">
			<span style="line-height:40px;" class="dashicons dashicons-media-text"></span>
			<span class="wt_iew_popup_hd_label"><?php _e('Import progress');?></span>
			<div class="wt_iew_popup_close">X</div>
		</div>
		<div class="wt_iew_import_progress_content"  style="max-height:620px;overflow: auto;">
					<table id="wt_iew_import_progress" class="widefat_importer widefat wt_iew_import_progress wp-list-table fixed striped history_list_tb log_list_tb">
						<thead>
							<tr>
								<th  style="width:15%" class="row"><?php _e( 'Row', 'wt-import-export-for-woo' ); ?></th>
								<th  style="width:20%"><?php _e( 'Item', 'wt-import-export-for-woo' ); ?></th>
								<th  style="width:50%"><?php _e( 'Message', 'wt-import-export-for-woo' ); ?></th>
								<th  style="width:20%" class="reason"><?php _e( 'Status', 'wt-import-export-for-woo' ); ?></th>
							</tr>
						</thead>
<!--						<tfoot>
							<tr class="importer-loading">
								<td colspan="5"></td>
							</tr>
						</tfoot>-->
						<tbody id="wt_iew_import_progress_tbody"></tbody>
					</table>
		</div>
		<br/>
		<div id="wt_iew_import_progress_end"></div>
		<div class="progressa">
			<div class="progressab" style="background-color: rgb(178, 222, 75);width:5px; "></div>
		</div>
		
	<div class="wt-iew-import-completed" style="display:none;border-top: 1px outset;">
		<h3><?php _e('Import Completed'); ?><span style="color:green" class="dashicons dashicons-yes-alt"></span></h3>
		<div class="wt-iew-import-results">
			<div class="wt-iew-import-result-row">
			<div class="wt-iew-import-results-total wt-iew-import-result-column"><?php _e('Total records identified'); ?>:<span id="wt-iew-import-results-total-count"></span></div>
			<div style="color:green" class="wt-iew-import-results-imported wt-iew-import-result-column"><?php _e('Imported successfully'); ?>:<span id="wt-iew-import-results-imported-count"></span></div>
			<div style="color:red" class="wt-iew-import-results-failed wt-iew-import-result-column"><?php _e('Failed/Skipped'); ?>:<span id="wt-iew-import-results-failed-count"></span></div>
			</div>
		</div>
	</div>
	
	
	<div class="wt-iew-plugin-toolbar bottom" style="padding:5px;margin-left:-10px;">
		<div style="float: left">
			<div class="wt-iew-import-time" style="display:none;padding-left: 40px;margin-top:10px;" ><?php _e( 'Time taken to complete' );?>:<span id="wt-iew-import-time-taken"></span></div>
		</div>
		<div style="float:right;">
			<div style="float:right;">
				<a target="_blank" href="#" class="button button-primary wt_iew_view_imported_items" data-log-file="" style="display:none"  type="button" style="margin-right:10px;"><?php _e( 'View Item' );?></a>
				<button class="button button-primary wt_iew_view_log_btn" data-log-file="" style="display:none"  type="button" style="margin-right:10px;"><?php _e( 'View Log' );?></button>
				<button class="button button-primary wt_iew_popup_cancel_btn"  type="button" style="margin-right:10px;"><?php _e( 'Cancel' );?></button>
				<button class="button button-primary wt_iew_popup_close_btn" style="display:none"  type="button" style="margin-right:10px;"><?php _e( 'Close' );?></button>
			</div>
		</div>
	</div>
	
	
	
</div>
<?php
Wt_Iew_IE_Helper::debug_panel($this->module_base);
?>
<?php include WT_IEW_PLUGIN_PATH."/admin/views/_save_template_popup.php"; ?>

<h2 class="wt_iew_page_hd"><?php _e('Import'); ?><span class="wt_iew_post_type_name"></span></h2>

<?php
	if($requested_rerun_id>0 && $this->rerun_id==0)
	{
		?>
		<div class="wt_iew_warn wt_iew_rerun_warn">
			<?php _e('Unable to handle Re-Run request.');?>
		</div>
		<?php
	}
?>

<div class="wt_iew_loader_info_box"></div>
<div class="wt_iew_overlayed_loader"></div>
	
	<div class="wt-something-went-wrong" style="display:none;">	
		<div>
			<p class="wt_iew_popup_close" style="float:right;margin-top:-18px; margin-right:0px;line-height: 0; right:0px; position:absolute;">
				<a href="javascript:void(0)">
					<img style="height: 26px;margin-top: -2px;margin-right: 2px;" src="<?php echo esc_url(WT_IEW_PLUGIN_URL.'/assets/images/wt-closse-button.png');?>"/>
				</a>
			</p>			
				
			<div class="wt-error-class" style =" display: flex;">
				<div style="text-align:left; float:left;">
				<img style="height: 20px; float:left;" src="<?php echo esc_url(WT_IEW_PLUGIN_URL . '/assets/images/wt-alert-icon.png'); ?>" />
				</div>
				<div class="wt_iew_fatal_error">
					<div style="text-align:left; font-size:20px; color:#000; float:left;margin-left:5px; font-weight: 600;"> <?php esc_html_e('Import failed'); ?> </div>			
					<div style="float:left; margin-left:5px;">			
						<p style="color:#000;text-align: left;"><?php esc_html_e('A fatal error has occured on your site. Please check your '); ?><a target="_blank" href="<?php echo esc_url(admin_url('admin.php?page=wc-status&tab=logs')) ?>"><?php esc_html_e('Fatal error log'); ?></a>.</p>
					</div>
					<a href="https://www.webtoffee.com/contact/" class="button button-primary" style ="float:right;"><?php esc_html_e('Contact support'); ?></a>
				</div>	
				<div class="wt_iew_resume_import" style="float:left; width:100%;">
					<div style="text-align:left; font-size:20px; color:#000; float:left;margin-left:5px; font-weight: 600;"> <?php esc_html_e('Import failed'); ?> </div>
					<div style="float:left; margin-left:5px;">
						<p style="color:#000;text-align: left;"><?php esc_html_e('Import has failed due to server time out. You can resume your import from where it stopped.'); ?></p>
					</div>			
					<a href="#" class="button button-primary resume-import-button" style ="float:right;"><?php esc_html_e('Resume Import'); ?></a>
				</div>	
			</div>
		</div>

	</div>
<div class="wt_iew_import_step_main">
	<?php
	foreach($this->steps as $stepk=>$stepv)
	{
		?>
		<div class="wt_iew_import_step wt_iew_import_step_<?php echo $stepk;?>" data-loaded="0"></div>
		<?php
	}
	?>
</div>
<script type="text/javascript">
/* external modules can hook */
function wt_iew_importer_validate(action, action_type, is_previous_step)
{
	var is_continue=true;
	<?php
	do_action('wt_iew_importer_validate');
	?>
	return is_continue;
}
function wt_iew_importer_reset_form_data()
{
	<?php
	do_action('wt_iew_importer_reset_form_data');
	?>
}
</script>