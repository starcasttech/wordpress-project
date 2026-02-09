var wt_iew_email_template=(function( $ ) {
	//'use strict';
	var wt_iew_email_template=
	{
		onEdit:false,
		poPupCrud:0,
		// poPupPage:'export', /* popup is called from export/import page */
		useProfileId:0,
		popupLoaded: false,
		activeEditor: null,
		// test_email_template_xhr:null,
		
		Set:function()
		{
			if($('.wt_iew_email_template_settings_page').length>0 && $('.wt_iew_popup_email_template_crud').length==0)
			{
				this.loadPage();
			}
			this.popUpCrud();
		},
	
		importer_reset_form_data:function()
		{
			this.import_profile=0;
			this.import_path='';
			this.import_file='';
		},
        validate_export_email_template_fields:function(is_continue, action, action_type, is_previous_step)
		{
			var validate_it=false;
			if(jQuery('[name="wt_iew_file_into_email"]').length>0)
			{
				if(action_type=='step')
				{
					if(!is_previous_step && jQuery('[name="wt_iew_file_into_email"]').is(':visible'))
					{
						validate_it=true;
					}
				}else
				{
					if(action!='export_image')
					{
						validate_it=true;
					}
				}
			}

			if(validate_it)
			{
				if(jQuery('select[name="wt_iew_file_into_email"]').length>0)  /* select box */
				{
					var file_into=jQuery('[name="wt_iew_file_into_email"]').val();
				}else
				{
					var file_into=jQuery('[name="wt_iew_file_into_email"]:checked').val();
				}
				if(file_into=='Yes')
				{
					if(parseInt(jQuery('[name="wt_iew_email_template"]').val())==0)
					{
						wt_iew_notify_msg.error(wt_iew_email_template_params.msgs.choose_a_profile);
						is_continue=false;
					}
                    if(jQuery('[name="wt_iew_email_ids"]').val()=='')
					{
						wt_iew_notify_msg.error(wt_iew_email_template_params.msgs.choose_a_email);
						is_continue=false;
					}
				}
			}
			return is_continue;
		},
		popUpCrud:function(page)
		{
			$('.wt_iew_email_template').off('click').on('click', function(){
				var pop_elm=$('.wt_iew_popup_email_template_crud');
				var ww=$(window).width();
				pop_w=(ww<1200 ? ww : 1200)-200;
				pop_w=(pop_w<200 ? 200 : pop_w);
				pop_elm.width(pop_w);

				wh=$(window).height();
				pop_h=(wh>=400 ? (wh-200) : wh);
				$('.wt_iew_email_template_settings_page').css({'max-height':pop_h+'px','overflow':'auto'});
				var target_tab=$(this).attr('data-tab');
				wt_iew_email_template.poPupCrud=1;
				wt_iew_email_template.poPupPage=page;
				wt_iew_popup.showPopup(pop_elm);
				wt_iew_email_template.loadPage(target_tab);
			});
		},
		loadPage:function(target_tab)
		{	
			if ( wt_iew_email_template.popupLoaded ) {
				if (wt_iew_email_template.activeEditor) {
					tinymce.remove(wt_iew_email_template.activeEditor);
					wt_iew_email_template.activeEditor = null;
				}
			}

			$('.wt_iew_email_template_settings_page').html('<div class="wt_iew_email_template_loader">'+wt_iew_params.msgs.loading+'</div>');
			$.ajax({
				url:wt_iew_params.ajax_url,
				data:{'action':'iew_email_template_ajax', _wpnonce:wt_iew_params.nonces.main, 'iew_email_template_action':'settings_page', 'popup_page':wt_iew_email_template.poPupCrud},
				type:'post',
				dataType:"html",
				success:function(data)
				{
					$('.wt_iew_email_template_settings_page').html(data);
					wt_iew_email_template.popupLoaded = true;

					/* Initiate the email content editor */
					tinymce.init({
						selector: '#wt_iew_email_template_body',
						setup: function(editor) {
							editor.on('change', function(e) {
								tinymce.triggerSave();  // Trigger save when content changes
							});
						},
						init_instance_callback: function(editor) {
							// After editor initialization, set default size for all images in the editor
							editor.on('NodeChange', function() {
								editor.dom.select('img').forEach(function(img) {
									img.setAttribute('width', '100');
									img.setAttribute('height', '100');
								});
							});
						}
					});

					wt_iew_email_template.activeEditor = tinymce.activeEditor;

					wt_iew_email_template.regMainEvents(target_tab);
                    $(".wt-iew-tips").tipTip({'attribute': 'data-wt-iew-tip'});
				},
				error:function()
				{
					wt_iew_notify_msg.error(wt_iew_params.msgs.error);
					$('.wt_iew_email_template_settings_page').html('<div class="wt_iew_email_template_loader">'+wt_iew_params.msgs.error+'</div>');
				}
			});
		},
		loadList:function()
		{
			$('.wt_iew_email_template_list').html('<div class="wt_iew_email_template_loader">'+wt_iew_params.msgs.loading+'</div>');
			$.ajax({
				url:wt_iew_params.ajax_url,
				data:{'action':'iew_email_template_ajax', _wpnonce:wt_iew_params.nonces.main, 'iew_email_template_action':'email_template_list','popup_page':wt_iew_email_template.poPupCrud},
				type:'post',
				dataType:"html",
				success:function(data)
				{
					$('.wt_iew_email_template_list').html(data);
					if(wt_iew_email_template.poPupCrud==1)
					{
						wt_iew_email_template.updateSelectBox(); /* update select box options with new data */
					}
					wt_iew_email_template.regEvents();
				},
				error:function()
				{
					wt_iew_notify_msg.error(wt_iew_params.msgs.error);
					$('.wt_iew_email_template_list').html('<div class="wt_iew_email_template_loader">'+wt_iew_params.msgs.error+'</div>');
				}
			});
		},
		regMainEvents:function(target_tab)
		{
			this.subTab($('.wt_iew_email_template_settings_page'));
			if(target_tab)
			{
				$('.wt_iew_sub_tab li[data-target="'+target_tab+'"]').trigger('click');
			}
			this.saveData();
			this.regEvents();
		},
		regEvents:function()
		{
			$(document).on('click', '.wt_iew_email_template_edit', function() {
				var id = $(this).attr('data-id');
				wt_iew_email_template.switchTab(wt_iew_email_template_params.msgs.edit, wt_iew_email_template_params.msgs.edit_hd, true);
				
				var form_data_dv = $(this).parents('td').find('.wt_iew_data_dv');
				
				// Collect data from the span elements in the form
				form_data_dv.find('span').each(function() {
					var cls = $(this).attr('class');
					if (cls === 'wt_iew_email_template_body') {
						tinymce.activeEditor.setContent($(this).html().trim());  // Set the TinyMCE content with the new value
					} else {
						$('[name="' + cls + '"]').val($(this).text().trim());
					}
				});

			});
			

			$('.wt_iew_sub_tab li[data-target="email-template"]').click(function(){
               
				wt_iew_email_template.switchTab(wt_iew_email_template_params.msgs.add_new, wt_iew_email_template_params.msgs.add_new_hd,false);	
				$('.wt_iew_overlay').css({'display':'none'});
			});

			$('.wt_iew_sub_tab li[data-target="add-new-email-template"]').click(function(){
               
				if(!wt_iew_email_template.onEdit)
				{
					$('#wt_iew_email_template_form').trigger('reset');
					$('[name="wt_iew_email_id"]').val(0);
				}
				
			});

			$('.wt_iew_email_template_delete').click(function(){
				if(confirm(wt_iew_email_template_params.msgs.sure))
				{
					wt_iew_email_template.deleteData($(this));
				}
			});

			if($('.wt_iew_email_template_add').length>0) /* no profile exists then show an extra add new button */
			{
				$('.wt_iew_email_template_add').click(function(){
					$('.wt_iew_sub_tab li[data-target="add-new-email-template"]').trigger('click');
				});
			}

			if(this.poPupCrud==1)
			{
				$('.wt_iew_email_template_use').unbind('click').click(function(){
					wt_iew_email_template.useData($(this));
					wt_iew_popup.hidePopup();
				});

				/* check any pending `use` requests and execute */
				if(parseInt(wt_iew_email_template.useProfileId)>0)
				{
					var trget_elm=$('.wt_iew_email_template_use[data-id="'+wt_iew_email_template.useProfileId+'"]');
					if(trget_elm.length>0)
					{
						trget_elm.trigger('click');
					}else
					{
						wt_iew_notify_msg.error(wt_iew_params.msgs.error);
					}
					wt_iew_email_template.useProfileId=0; /* reset pending request */
				}
			}	
		},
		subTab:function(wf_prnt_obj)
		{
			wf_prnt_obj.find('.wt_iew_sub_tab li').click(function(){
				var trgt=$(this).attr('data-target');
				var prnt=$(this).parent('.wt_iew_sub_tab');
				var ctnr=prnt.siblings('.wt_iew_sub_tab_container');
				prnt.find('li a').css({'color':'#0073aa','cursor':'pointer'});
				$(this).find('a').css({'color':'#000','cursor':'default'});
				ctnr.find('.wt_iew_sub_tab_content').hide();
				ctnr.find('.wt_iew_sub_tab_content[data-id="'+trgt+'"]').show();
			});
			wf_prnt_obj.find('.wt_iew_sub_tab').each(function(){
				var elm=$(this).children('li').eq(0);
				elm.click();
			});
		},
		useData:function(elm)
		{
			var id=elm.attr('data-id');
			$('[name="wt_iew_email_template"]').val(id).trigger('change');
		},
		updateSelectBox:function()
		{
			var email_template_list_sele_html='';
			var vl_bckup=$('[name="wt_iew_email_template"] option:selected').val();
			if($('.email_template_list_tb').length>0)
			{
				$('.email_template_list_tb').find('.wt_iew_data_dv').each(function(){
					var id=$(this).find('.wt_iew_email_id').text().trim();
					var email_templatename=$(this).find('.wt_iew_email_template_name').text().trim();
					email_template_list_sele_html+='<option value="'+id+'">'+email_templatename+'</option>';
				});
				$('[name="wt_iew_email_template"]').html(email_template_list_sele_html);
				vl_bckup=($('[name="wt_iew_email_template"]').find('option[value="'+vl_bckup+'"]').length==0 ? 0 : vl_bckup);
				$('[name="wt_iew_email_template"]').val(vl_bckup).trigger('change');
				$('.wt_iew_email_template').html($('.wt_iew_email_template').attr('data-label-email-template')).attr('data-tab', 'email_template');		
			}else
			{
				email_template_list_sele_html='<option value="0" data-path="">'+wt_iew_email_template_params.msgs.no_email_template_found+'</option>';
				$('[name="wt_iew_email_template"]').html(email_template_list_sele_html).trigger('change');
				$('.wt_iew_email_template').html($('.wt_iew_email_template').attr('data-label-add-email-template')).attr('data-tab', 'add-new-email-template');
			}
		},
		deleteData:function(elm)
		{
			var id=elm.attr('data-id');
			elm.html(wt_iew_email_template_params.msgs.wait);
			$.ajax({
				url:wt_iew_params.ajax_url,
				data:{'action':'iew_email_template_ajax', _wpnonce:wt_iew_params.nonces.main, 'iew_email_template_action':'delete_email_template','wt_iew_email_id':id},
				type:'post',
				dataType:"json",
				success:function(data)
				{
					if(data.status==true)
					{
						wt_iew_email_template.loadList();
						wt_iew_notify_msg.success(wt_iew_params.msgs.success);
					}else
					{
						wt_iew_notify_msg.error(data.msg);
						elm.html(wt_iew_email_template_params.msgs.delete);
					}
				},
				error:function()
				{
					wt_iew_notify_msg.error(wt_iew_params.msgs.error);
					elm.html(wt_iew_email_template_params.msgs.delete);
				}
			});
		},
		
		removeFormLoader:function()
		{
			var submit_btn=$('#wt_iew_email_template_form').find('input[type="submit"], input[type="button"]');
			var spinner=submit_btn.siblings('.spinner');
			spinner.css({'visibility':'hidden'});
			submit_btn.css({'opacity':'1','cursor':'pointer'}).prop('disabled',false);
		},
		setFormLoader:function()
		{
			var submit_btn=$('#wt_iew_email_template_form').find('input[type="submit"], input[type="button"]');
			var spinner=submit_btn.siblings('.spinner');
			spinner.css({'visibility':'visible'});
			submit_btn.css({'opacity':'.5','cursor':'default'}).prop('disabled',true);
		},
		saveData:function()
		{
			$('#wt_iew_email_template_form').on('submit', function(e) {  //wt_iew_email_template_form == wt_iew_email_template_form
				e.preventDefault();
				$('.wt_iew_email_template_warn').hide();
				$('[name="iew_email_template_action"]').val('save_email_template');
				wt_iew_email_template.setFormLoader();
				$.ajax({
					url:wt_iew_params.ajax_url,
					data:$(this).serialize(),
					type:'post',
					dataType:"json",
					success:function(data)
					{
						wt_iew_email_template.removeFormLoader();
						if(data.status==true)
						{
							$('.wt_iew_sub_tab li[data-target="email-template"]').trigger('click');  
							wt_iew_email_template.loadList();
							/* add/edit and use enabled for currently added/edited profile */
							if(wt_iew_email_template.poPupCrud==1 && $('[name="wt_iew_add_and_use_email_template"]').is(':checked'))
							{
								wt_iew_email_template.useProfileId=data.id;
							}
							wt_iew_notify_msg.success(wt_iew_params.msgs.success);
						}else
						{
							wt_iew_notify_msg.error(data.msg);
						}
					},
					error:function()
					{
						wt_iew_email_template.removeFormLoader();
						wt_iew_notify_msg.error(wt_iew_params.msgs.error);
					}
				});
			});
		},
		switchTab:function(new_txt, new_hd_txt, change_tab)
		{
			var elm=$('.wt_iew_sub_tab li[data-target="add-new-email-template"]');
			if(change_tab)
			{
				this.onEdit=true;
				elm.trigger('click');
			}else
			{
				this.onEdit=false;
			}
			elm.find('a').html(new_txt);
			$('.wt_iew_form_title').text(new_hd_txt);
		}
	}
	return wt_iew_email_template;
	
})( jQuery );

jQuery(function() {			
	wt_iew_email_template.Set();
});