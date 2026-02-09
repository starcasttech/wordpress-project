<?php
/**
 * Email template section of the plugin
 *
 * @link          
 *
 * @package  Wt_Import_Export_For_Woo 
 */
if (!defined('ABSPATH')) {
    exit;
}

class Wt_Import_Export_For_Woo_Email
{
    public static $module_id_static='';
    public $module_base='email';	
    public $module_id='';
    public $lables=array();
	public $email_template_form_fields =array();
	public $popup_page=0; //is ajax call from popup. May be it from export/import page



    public function __construct()
	{
        $this->module_id=Wt_Import_Export_For_Woo::get_module_id($this->module_base);
        self::$module_id_static=$this->module_id;
        add_filter('wt_iew_exporter_alter_advanced_fields',array($this,'exporter_alter_advanced_fields'),10,3);
		add_action('admin_enqueue_scripts', array($this,'enqueue_assets'),10,1);
		add_action('wt_iew_exporter_before_head', array($this,'add_popup_crud_html'),10,1);
		add_action('wp_ajax_iew_email_template_ajax',array($this,'ajax_main'),11);
		add_action('wt_iew_exporter_file_into_js_fn',array( $this,'exporter_file_into_js_fn'));
		/* validate email template entries before doing an action */
		add_action('wt_iew_exporter_validate', array($this,'exporter_validate'));
		
		$this->lables['select_one']=__('Select atleast one.');
		$this->lables['no_email_template_found']=__('No Email template found.');

		$this->email_template_form_fields=array('wt_iew_email_template_name', 'wt_iew_email_template_subject', 'wt_iew_email_template_body');


    }

	public function enqueue_assets()
	{
		if(isset($_GET['page']) && (sanitize_text_field(wp_unslash($_GET['page'])) == Wt_Import_Export_For_Woo::get_module_id('export') || sanitize_text_field(wp_unslash($_GET['page'])) == WT_IEW_PLUGIN_ID))
		{
			wp_enqueue_script($this->module_id, plugin_dir_url( __FILE__ ).'assets/js/main.js',array('jquery'), WT_IEW_VERSION);
			$params=array(
				'nonces' => array(
		            'main'=>wp_create_nonce($this->module_id),
		        ),
		        'ajax_url' => admin_url('admin-ajax.php'),
				'msgs'=>array(
					'add_new'=>esc_html__('Add new', 'wt-import-export-for-woo'),
					'add_new_hd'=>esc_html__('Add new Email template', 'wt-import-export-for-woo'),
					'edit'=>esc_html__('Edit', 'wt-import-export-for-woo'),
					'edit_hd'=>esc_html__('Edit template', 'wt-import-export-for-woo'),
					'mandatory'=>esc_html__('All fields are mandatory', 'wt-import-export-for-woo'),
					'sure'=>esc_html__('Confirm? All import/export profiles associated with this FTP profile will not work. You can\'t undo this action.', 'wt-import-export-for-woo'),
					'delete'=>esc_html__('Delete', 'wt-import-export-for-woo'),
					'some_mandatory'=>esc_html__('Please fill mandatory fields', 'wt-import-export-for-woo'),
					'choose_a_profile'=>esc_html__('Please choose an Email template', 'wt-import-export-for-woo'),
					'choose_a_email'=>esc_html__('Please fill an Email address', 'wt-import-export-for-woo'),
					'select_one'=>esc_html__($this->lables['select_one']),
					'no_email_template_found'=>esc_html__($this->lables['no_email_template_found']),
				)
			);
			wp_localize_script($this->module_id, 'wt_iew_email_template_params', $params);

			wp_enqueue_style($this->module_id, plugin_dir_url( __FILE__ ).'assets/css/main.css', array(), WT_IEW_VERSION, 'all');
			wp_enqueue_media();
			wp_enqueue_editor();
		}
	}
	/**
	*	JS code to toggle FTP form fields 
	*/
	public function exporter_file_into_js_fn()
	{
		?>
			if ('Yes' === file_into) {
				wt_iew_email_template.popUpCrud();
				wt_iew_toggle_schedule_btn(1); /* show cron btn, if exists */
			}
		<?php
	}
	public function exporter_validate()
	{
		?>
		if(is_continue)
		{
			is_continue=wt_iew_email_template.validate_export_email_template_fields(is_continue, action, action_type, is_previous_step);
		}
		<?php
	}
	/**
	*	Add HTML for Email tamplate popup
	*/
	public function add_popup_crud_html()
	{
		?>
		<div class="wt_iew_popup_email_template_crud wt_iew_popup">
			<div class="wt_iew_popup_hd">
				<span class="wt_iew_popup_hd_label"><?php echo esc_html__('Email Template', 'wt-import-export-for-woo'); ?></span>
				<div class="wt_iew_popup_close">X</div>
			</div>
			<div class="wt_iew_email_template_settings_page" style="padding:15px; text-align:left;">
				
			</div>
		</div>
		<?php	
	}
	
	/**
	* Main ajax hook for ajax actions. 
	*/
	public function ajax_main()
	{
		$allowed_actions=array('save_email_template', 'delete_email_template', 'email_template_list','settings_page');
		$action = (isset($_POST['iew_email_template_action']) ? sanitize_text_field(wp_unslash($_POST['iew_email_template_action'])) : '');
		$out=array('status'=>true, 'msg'=>'');
		if(!Wt_Iew_Sh_Pro::check_write_access(WT_IEW_PLUGIN_ID))
		{
			$out['status']=false;

		}else
		{
			if(in_array($action,$allowed_actions))
			{
				if(method_exists($this,$action))
				{
					$out=$this->{$action}($out); //some methods will not retrive array
				}
			}
		}
		echo json_encode($out);
		exit();	
	}

    /**
	* Add Email template related fields to the exporter advanced step
	*
	*/
	public function exporter_alter_advanced_fields($fields,$base,$advanced_form_data)
	{
        $email_list=$this->get_emai_template_for_select();

        $label_email_template =__("View/Add Email templates", 'wt-import-export-for-woo');
        $label_add_email_template = __("Add new Email templates", 'wt-import-export-for-woo');
    
		$out=array();
		foreach($fields as $fieldk=>$fieldv)
		{
			$out[$fieldk]=$fieldv;
				$out['file_into_email'] = array(
					'label'=>__("Email export file as attachment", 'wt-import-export-for-woo'),
					'type'=>'radio',
					'radio_fields' => array('Yes'=>__('Enable'), 'No'=>__('Disable')),
					'field_name'=>'file_into_email',
					'default_value'=>'No',
					'form_toggler'=>array(
						'type'=>'parent',
						'target'=>'wt_iew_file_into_email'
					)
				);
				
				$out['email_template']=array(
					'label'=>__("Email template", 'wt-import-export-for-woo'),
					'type'=>'select',
					'tr_id'=>'email_template_tr',
					'sele_vals'=>$email_list,
					'field_name'=>'email_template',
					'form_toggler'=>array(
						'type'=>'child',
						'id'=>'wt_iew_file_into_email',
						'val'=>'Yes',
					),
					'validation_rule'=>array('type'=>'int'),
					'after_form_field_html'=>'<a class="wt_iew_email_template" data-label-email_template="'.$label_email_template.'" data-label-add-email_template="'.$label_add_email_template.'" data-tab="'.(count($email_list)>1 ? 'email-template' : 'add-new-email-template').'">'.(count($email_list)>1 ? $label_email_template : $label_add_email_template).'</a>',
				);
				
				$out['email_ids']=array(
					'label'=>__("Select email address of recepient", 'wt-import-export-for-woo'),
					'type'=>'text',
					'value'=>"",
					'field_name'=>'email_ids',
					'form_toggler'=>array(
						'type'=>'child',
						'id'=>'wt_iew_file_into_email',
						'val'=>'Yes',
					)
				);
			
		}
		return $out;
	}
    /**
	* Process Email template list for select boxes
	*/
	public function get_emai_template_for_select()
	{
		$profiles = $this->get_email_template_data();
		$sele_arr = array();
		if ($profiles && is_array($profiles) && count($profiles) > 0) {
			$sele_arr[0] = array('value' => __('No template selected', 'wt-import-export-for-woo'));
			foreach ($profiles as $profile) {
				$sele_arr[$profile['id']] = array('value' => $profile['name']);
			}
		} else {
			$sele_arr[0] = array('value' => __('No template found', 'wt-import-export-for-woo'));
		}
		return $sele_arr;
	}

    /**
	* Get Email lemplates list from DB
	* @return array list of Email templates
	*/
	public static function get_email_template_data()
	{
		global $wpdb;
		$tb=$wpdb->prefix.Wt_Import_Export_For_Woo::$email_tb;	
		$val = $wpdb->get_results("SELECT * FROM $tb  ORDER BY id DESC", ARRAY_A);
		return $val ? $val : array();
	}

	public static function get_email_template_data_by_id($mail_template){
        global $wpdb;
		$tb=$wpdb->prefix.Wt_Import_Export_For_Woo::$email_tb;
		$val=$wpdb->get_row($wpdb->prepare("SELECT * FROM $tb WHERE id= %d", $mail_template), ARRAY_A);
        return $val;
    }

	public function get_email_template_data_by_name($name){
		global $wpdb;
		$tb=$wpdb->prefix.Wt_Import_Export_For_Woo::$email_tb;	
		$qry=$wpdb->prepare("SELECT * FROM $tb WHERE name=%s",array($name));
		$val=$wpdb->get_results($qry,ARRAY_A);
		return $val ? $val : array();
	}

    public static function sent_emails($file_path, $mail_template=0, $email_ids=''){
		if(0 != $mail_template && '' != $email_ids && file_exists($file_path)){
            $email_template_data = self::get_email_template_data_by_id($mail_template);
            $subject =  $email_template_data['subject'];
            $body = $email_template_data['body'];
			$current_date = date("Y-m-d");
    		$current_time = date("H:i:s");

			// Replace placeholders with actual date and time
			$body = str_replace("[Export Date]", $current_date, $body);
			$body = str_replace("[Export Time]", $current_time, $body);
			$site_url = get_site_url();

			$body = preg_replace_callback(
				'/<img[^>]+src=["\'](\/?(?:\.\.\/)?wp-content\/uploads\/[^"\']+)["\']/i',
				function ($matches) use ($site_url) {
					$relative_path = preg_replace('/^\.\.\//', '', $matches[1]);
					$absolute_url = $site_url . '/' . ltrim($relative_path, '/');
					return str_replace($matches[1], $absolute_url, $matches[0]);
				},
				$body
			);
            $email_ids = explode ('|', $email_ids);
            $zip = new ZipArchive();
            $file_name = explode('.', basename($file_path))[0];
            $file_name = WP_CONTENT_DIR. '/webtofee_export' . $file_name . '.zip';
            $file = $file_path;
            if ($zip->open($file_name, ZIPARCHIVE::CREATE) === TRUE) {
                if ($zip->addFile($file_path, basename($file_path))) {
                    $file = $file_name;
                }
            }
            $zip->close();
			$headers = array('Content-Type: text/html; charset=UTF-8');

            foreach ($email_ids as $email_id){
                wp_mail($email_id, $subject, $body, $headers, array($file) );
            }
   
            unlink($file_name . '.zip');
            return true;
        }else{
            return false;
        }
    }

	/**
	* Delete Email template
	* @param array $out output array sample
	*/
	public function delete_email_template($out)
	{
		$id=(isset($_POST['wt_iew_email_id']) ? intval($_POST['wt_iew_email_id']) : 0);
		if($id>0)
		{
			global $wpdb;
			$tb=$wpdb->prefix.Wt_Import_Export_For_Woo::$email_tb;
			$wpdb->delete($tb,array('id'=>$id),array('%d')); 
		}else
		{
			$out['msg']=__("Error");
    		$out['status']=false;
		}
		return $out;
	}
	private function save_email_template($out)
	{
    	foreach($this->email_template_form_fields as $emai_template_form_field)
    	{
			foreach($this->email_template_form_fields as $emai_template_form_field)
			{
				if ($emai_template_form_field === 'wt_iew_email_template_body') {
					$val = (isset($_POST[$emai_template_form_field]) ? stripslashes($_POST[$emai_template_form_field]) : '');
				} else {
					$val = Wt_Iew_Sh_Pro::sanitize_data((isset($_POST[$emai_template_form_field]) ? $_POST[$emai_template_form_field] : ''), $emai_template_form_field);
				}
				$update_data[$emai_template_form_field] = $val;
			}

    		
    		$update_data[$emai_template_form_field]=$val;
    	} 
    	$id=(isset($_POST['wt_iew_email_id']) ? intval($_POST['wt_iew_email_id']) : 0);
    	$name= stripslashes($update_data['wt_iew_email_template_name']);
    	if($out['status']) //no validation error, email edit call, check for duplcate name.
    	{
    		$email_template_data=$this->get_email_template_data_by_name($name);
    		if(count($email_template_data)>1) //least case
    		{
    			$out['msg']=__("Email template with same name already exists.", 'wt-import-export-for-woo');
    			$out['status']=false;
    		}else 
    		{ 	
    			if(isset($email_template_data[0]['id']) && $email_template_data[0]['id']!=$id) /* profile with same name exists */
    			{
    				$out['msg']=__("Email template with same name already exists.", 'wt-import-export-for-woo');
    				$out['status']=false;
    			}
    		}
    	}
    	if($out['status']) //no validation error
    	{
    		$db_data=array(
				'name'=>$name,
				'subject'=>$update_data['wt_iew_email_template_subject'],
				'body'=>$update_data['wt_iew_email_template_body'],
			);
			$db_data_type=array('%s','%s','%s');
			if($id>0)
			{
				$out['id']=$id;
				if(!$this->update_email_template_data($id,$db_data,$db_data_type))
				{
					$out['msg']=__("Error", 'wt-import-export-for-woo');
    				$out['status']=false;
				}else{
					$out['msg']=__("success", 'wt-import-export-for-woo');
    				$out['status']=true;
				}
			}else
			{
				$id=$this->add_email_template_data($db_data,$db_data_type);
				$out['id']=$id;
				if($id==0)
				{
					$out['msg']=__("Error", 'wt-import-export-for-woo');
					$out['status']=false;
				}else{
					$out['msg']=__("success", 'wt-import-export-for-woo');
					$out['status']=true;
				}
			}
    	}
    	return $out;
	}

	/**
	* Create Email template
	* @param array $insert_data array of insert data
	* @param array $insert_data_type array of insert data format
	* @return array
	*/
	private function add_email_template_data($insert_data,$insert_data_type)
	{
		global $wpdb;
		$tb=$wpdb->prefix.Wt_Import_Export_For_Woo::$email_tb;	
		if($wpdb->insert($tb,$insert_data,$insert_data_type)) //success
		{
			return $wpdb->insert_id;
		}
		return 0;
	}

	/**
	* Update Email template
	* @param int $id id of Email template
	* @param array $update_data array of update data
	* @param array $update_data_type array of update data format
	* @return array
	*/
	private function update_email_template_data($id,$update_data,$update_data_type)
	{
		global $wpdb;
		//updating the data
		$tb=$wpdb->prefix.Wt_Import_Export_For_Woo::$email_tb;
		$update_where=array(
			'id'=>$id
		);
		$update_where_type=array(
			'%d'
		);
		if($wpdb->update($tb,$update_data,$update_where,$update_data_type,$update_where_type)!==false)
		{
			return true;
		}
		return false;
	}
   /**
	* Print Settings page HTML Ajax function
	*/
	private function settings_page()
	{
		$this->popup_page=(isset($_POST['popup_page']) ? intval($_POST['popup_page']) : 0);
		include plugin_dir_path( __FILE__ ).'views/_settings_page.php';
		exit(); //not return anything, prints html
	}

	/**
	* Print Email template list HTML
	*/
	private function get_email_template_list_html()
	{
		$email_template_list=$this->get_email_template_data();
		include plugin_dir_path( __FILE__ ).'views/_email-template-list.php';
	}

	/**
	* Print Email template list HTML Ajax function
	*/
	private function email_template_list($out)
	{
		$this->popup_page=(isset($_POST['popup_page']) ? intval($_POST['popup_page']) : 0);
		$this->get_email_template_list_html();
		exit(); //not return anything, prints html
	}

}
new Wt_Import_Export_For_Woo_Email();