<?php

class mf_cache
{
	var $post_type = 'mf_cache';
	var $upload_path;
	var $upload_url;
	var $clean_url;
	var $clean_url_orig;
	var $site_url;
	var $site_url_clean;
	var $file_name_xtra = "";
	var $dir2create = "";
	var $file_address = "";
	var $file_suffix = "";
	var $style_errors = "";
	var $combined_style_file_path = "";
	var $combined_style_file_url = "";
	var $script_errors = "";
	var $combined_script_file_path = "";
	var $combined_script_file_url = "";
	var $http_host = "";
	var $request_uri = "";
	var $file_amount;
	var $file_amount_date_first = "";
	var $file_amount_date_last = "";
	var $arr_posts = array();
	var $api_action;

	function __construct()
	{
		list($this->upload_path, $this->upload_url) = get_uploads_folder($this->post_type, true);
		$this->clean_url = $this->clean_url_orig = get_site_url_clean(array('trim' => "/"));

		$this->site_url = get_site_url();
		$this->site_url_clean = remove_protocol(array('url' => $this->site_url));
	}

	function get_file_amount_callback($data)
	{
		if(file_exists($data['file']))
		{
			$this->file_amount++;

			$file_date_time = date("Y-m-d H:i:s", filemtime($data['file']));

			if($this->file_amount_date_first == '' || $file_date_time < $this->file_amount_date_first)
			{
				$this->file_amount_date_first = $file_date_time;
			}

			if($this->file_amount_date_last == '' || $file_date_time > $this->file_amount_date_last)
			{
				$this->file_amount_date_last = $file_date_time;
			}
		}
	}

	function get_file_amount($data = array())
	{
		if(!isset($data['path'])){		$data['path'] = $this->upload_path.trim($this->clean_url_orig, "/");}

		$this->file_amount = 0;
		$this->file_amount_date_first = $this->file_amount_date_last = "";
		get_file_info(array('path' => $data['path'], 'callback' => array($this, 'get_file_amount_callback')));

		return $this->file_amount;
	}

	function delete_file_callback($data)
	{
		if(!isset($data['time_limit'])){		$data['time_limit'] = (DAY_IN_SECONDS * 2);}
		if(!isset($data['time_limit_api'])){	$data['time_limit_api'] = HOUR_IN_SECONDS;}

		if(file_exists($data['file']))
		{
			$time_now = time();
			$time_file = filemtime($data['file']);
			$file_suffix = get_file_suffix($data['file'], true);

			switch($file_suffix)
			{
				case 'json':
					if($data['time_limit_api'] == 0 || ($time_now - $time_file >= $data['time_limit_api']))
					{
						unlink($data['file']);
					}
				break;

				default:
					if($data['time_limit'] == 0 || ($time_now - $time_file >= $data['time_limit']))
					{
						unlink($data['file']);
					}
				break;
			}
		}
	}

	// Can be replaced delete_empty_folder_callback in MF Base
	function delete_empty_folder_callback($data)
	{
		$folder = $data['path']."/".$data['child'];

		if(file_exists($folder) && is_dir($folder) && is_array(scandir($folder)) && count(scandir($folder)) == 2)
		{
			rmdir($folder);
		}
	}

	function do_clear($data = array())
	{
		if(!isset($data['path'])){				$data['path'] = $this->upload_path.trim($this->clean_url_orig, "/");}
		if(!isset($data['time_limit'])){		$data['time_limit'] = 0;}
		if(!isset($data['time_limit_api'])){	$data['time_limit_api'] = ($data['time_limit'] * 60);}
		if(!isset($data['allow_depth'])){		$data['allow_depth'] = true;}

		$file_amount = $this->get_file_amount($data);

		if($file_amount > 0)
		{
			// Delete files
			#########################
			$data_temp = $data;
			$data_temp['callback'] = array($this, 'delete_file_callback');

			get_file_info($data_temp);
			#########################

			// Delete empty folders
			#########################
			$data_temp = $data;

			if(function_exists('delete_empty_folder_callback'))
			{
				$data_temp['folder_callback'] = 'delete_empty_folder_callback';
			}

			else
			{
				$data_temp['folder_callback'] = array($this, 'delete_empty_folder_callback');
			}

			get_file_info($data_temp);
			#########################

			$file_amount = $this->get_file_amount($data);

			if(file_exists($data['path']) && $file_amount == 0)
			{
				rmdir($data['path']);
			}
		}

		return $file_amount;
	}

	function cron_base()
	{
		global $globals;

		$obj_cron = new mf_cron();
		$obj_cron->start(__CLASS__);

		if($obj_cron->is_running == false)
		{
			if(get_option('setting_cache_activate') == 'yes' || get_option('setting_cache_activate_api') == 'yes')
			{
				$setting_cache_expires = get_site_option_or_default('setting_cache_expires', 24);
				$setting_cache_api_expires = get_site_option_or_default('setting_cache_api_expires', 15);

				$this->do_clear(array(
					'time_limit' => (HOUR_IN_SECONDS * $setting_cache_expires),
					'time_limit_api' => (MINUTE_IN_SECONDS * $setting_cache_api_expires),
				));
			}

			else
			{
				$this->do_clear();
			}
		}

		$obj_cron->end();
	}

	function set_cache($out)
	{
		if(strlen($out) > 0 && strpos($out, "error404"))
		{
			$out = "";
		}

		if(strlen($out) > 0)
		{
			switch($this->file_suffix)
			{
				case 'html':
					$out = $this->compress_html($out);
				break;
			}

			if($this->is_password_protected())
			{
				$type = 'protected';
			}

			else
			{
				$success = set_file_content(array('file' => $this->file_address, 'mode' => 'w', 'content' => $out, 'log' => false));

				$type = 'dynamic';
			}
		}

		else
		{
			$type = 'no_content';
		}

		if(get_option('setting_cache_debug') == 'yes')
		{
			switch($this->file_suffix)
			{
				case 'html':
					$out .= "<!-- ".$type." ".date("Y-m-d H:i:s")." -->";
				break;

				case 'json':
					$arr_out = json_decode($out, true);
					$arr_out[$type] = date("Y-m-d H:i:s");
					$out = json_encode($arr_out);
				break;
			}
		}

		return $out;
	}

	function get_or_set_api_content()
	{
		global $obj_base;

		$setting_cache_activate_api = get_option('setting_cache_activate_api', get_option('setting_cache_activate'));

		if($setting_cache_activate_api == 'yes')
		{
			// Update alternatives to choose from
			##########################
			$option_cache_api_include = get_option('option_cache_api_include', array());

			if(!isset($option_cache_api_include[$this->api_action]))
			{
				$option_cache_api_include[$this->api_action] = array(
					'action' => $this->api_action,
					'last_used' => date('Y-m-d H:i:s'),
				);

				$option_cache_api_include = $obj_base->array_sort(array('array' => $option_cache_api_include, 'on' => 'action', 'order' => 'asc', 'keep_index' => true));
			}

			else
			{
				$option_cache_api_include[$this->api_action]['last_used'] = date('Y-m-d H:i:s');
			}

			update_option('option_cache_api_include', $option_cache_api_include, false);
			##########################

			$setting_cache_api_include = get_option_or_default('setting_cache_api_include', array());

			if(in_array($this->api_action, $setting_cache_api_include))
			{
				$this->file_suffix = 'json';

				$this->parse_file_address(array('file_name' => "ajax"));

				if($this->file_address != '' && strlen($this->file_address) <= 255)
				{
					if(file_exists(realpath($this->file_address)) && filesize($this->file_address) > 0)
					{
						$out = $this->get_cache();

						echo $out;
						exit;
					}

					else
					{
						ob_start(array($this, 'set_cache'));
					}
				}
			}
		}
	}

	function init()
	{
		$this->api_action = check_var('action');

		if(strpos(rtrim($_SERVER['REQUEST_URI'], '/'), '/admin-ajax.php') !== false && $this->api_action != '')
		{
			$this->get_or_set_api_content();
		}
	}

	function wp_before_admin_bar_render()
	{
		global $wp_admin_bar;

		if(IS_ADMINISTRATOR && $this->get_file_amount() > 0)
		{
			$wp_admin_bar->add_node(array(
				'id' => 'cache',
				'title' => "<a href='#api_cache_clear' class='color_red'>".__("Clear Cache", 'lang_cache')."</a>",
			));
		}
	}

	function settings_cache()
	{
		$options_area_orig = $options_area = __FUNCTION__;

		add_settings_section($options_area, "", array($this, $options_area."_callback"), BASE_OPTIONS_PAGE);

		$arr_settings = array();

		$setting_cache_activate = get_option('setting_cache_activate');
		$setting_cache_activate_api = get_option('setting_cache_activate_api', get_option('setting_cache_activate'));

		$arr_settings['setting_cache_activate'] = __("Activate", 'lang_cache');

		if($setting_cache_activate == 'yes')
		{
			$server_protocol = $_SERVER['SERVER_PROTOCOL'];
			list($server_protocol_type, $server_protocol_version) = explode("/", $server_protocol);

			//do_log(__FUNCTION__." - HTTP version: ".$server_protocol_version); // Add setting to combine files only if not HTTP version 2
			if($server_protocol_version < 2)
			{
				$arr_settings['setting_cache_combine'] = "- ".__("Merge Files", 'lang_cache');
			}

			else
			{
				delete_option('setting_cache_combine');
			}

			$arr_settings['setting_cache_extract_inline'] = "- ".__("Extract Inline", 'lang_cache');
			$arr_settings['setting_cache_expires'] = "- ".__("Expires", 'lang_cache');
		}

		$arr_settings['setting_cache_activate_api'] = __("Activate", 'lang_cache')." (".__("API", 'lang_cache').")";

		if($setting_cache_activate_api == 'yes')
		{
			$arr_settings['setting_cache_api_include'] = "- ".__("Include", 'lang_cache');
			$arr_settings['setting_cache_api_expires'] = "- ".__("Expires", 'lang_cache');
		}

		if($setting_cache_activate == 'yes' || $setting_cache_activate_api == 'yes')
		{
			$arr_settings['setting_cache_debug'] = __("Debug", 'lang_cache');
		}

		show_settings_fields(array('area' => $options_area, 'object' => $this, 'settings' => $arr_settings));
	}

	function settings_cache_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);

		echo settings_header($setting_key, __("Cache", 'lang_cache'));
	}

		function setting_cache_activate_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option_or_default($setting_key, 'no');

			echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));

			$file_amount = $file_amount_all = $this->get_file_amount();

			if(IS_SUPER_ADMIN)
			{
				$file_amount_all = $this->get_file_amount(array('path' => $this->upload_path));
			}

			if($file_amount > 0 || $file_amount_all > 0)
			{
				echo "<div".get_form_button_classes().">";

					if($file_amount > 0)
					{
						echo show_button(array('type' => 'button', 'name' => 'btnCacheClear', 'text' => __("Clear", 'lang_cache'), 'class' => 'button-secondary'));
					}

					if(IS_SUPER_ADMIN && $file_amount_all > $file_amount)
					{
						echo show_button(array('type' => 'button', 'name' => 'btnCacheClearAll', 'text' => __("Clear All Sites", 'lang_cache'), 'class' => 'button-secondary'));
					}

				echo "</div>
				<div class='api_cache_output'>";

					if(IS_SUPER_ADMIN && is_multisite())
					{
						echo sprintf(__("%d cached files for this site %s and %d for all sites in the network %s", 'lang_cache'), $file_amount, "<i class='fa fa-info-circle blue' title='".$this->upload_path.$this->clean_url_orig."'></i>", $file_amount_all, "<i class='fa fa-info-circle blue' title='".$this->upload_path."'></i>");
					}

					else
					{
						if($file_amount_all > $file_amount)
						{
							echo sprintf(__("%d cached files for this site and %d in other cache folders", 'lang_cache'), $file_amount, $file_amount_all);
						}

						else
						{
							echo sprintf(__("%d cached files", 'lang_cache'), $file_amount);
						}
					}

					if($this->file_amount_date_first > DEFAULT_DATE)
					{
						echo " (".format_date($this->file_amount_date_first);

							if($this->file_amount_date_last > $this->file_amount_date_first && format_date($this->file_amount_date_last) != format_date($this->file_amount_date_first))
							{
								echo " - ".format_date($this->file_amount_date_last);
							}

						echo ")";
					}

				echo "</div>";
			}
		}

		function setting_cache_combine_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option_or_default($setting_key, 'yes');

			echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));
		}

		function setting_cache_extract_inline_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option_or_default($setting_key, 'yes');

			echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));
		}

		function setting_cache_expires_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			settings_save_site_wide($setting_key);
			$option = get_site_option_or_default($setting_key, get_option_or_default($setting_key, 24));

			echo show_textfield(array('type' => 'number', 'name' => $setting_key, 'value' => $option, 'xtra' => "min='1' max='240'", 'suffix' => __("hours", 'lang_cache')));
		}

		function setting_cache_activate_api_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option_or_default($setting_key, get_option('setting_cache_activate'));

			echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));
		}

		function setting_cache_api_include_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option_or_default($setting_key, array());

			$option_cache_api_include = get_option('option_cache_api_include', array());

			if(count($option_cache_api_include) > 0)
			{
				$arr_data = array();

				foreach($option_cache_api_include as $key => $arr_value)
				{
					if(isset($arr_value['last_used']) && $arr_value['last_used'] > date("Y-m-d H:i:s", strtotime("-1 month")))
					{
						$arr_data[$key] = $arr_value['action']; //." (".format_date($arr_value['last_used']).")"
					}

					else
					{
						unset($option_cache_api_include[$key]);
						update_option('option_cache_api_include', $option_cache_api_include, false);
					}
				}

				echo show_select(array('data' => $arr_data, 'name' => $setting_key."[]", 'value' => $option));
			}

			else
			{
				echo "<p>".__("No API calls have been made so far. Come back in a while, then you will be able to choose which ones to cache.", 'lang_cache')."</p>";
			}
		}

		function setting_cache_api_expires_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			settings_save_site_wide($setting_key);
			$option = get_site_option_or_default($setting_key, get_option_or_default($setting_key, 15));

			//$setting_max = (get_site_option_or_default('setting_cache_expires', 24) * 60);

			echo show_textfield(array('type' => 'number', 'name' => $setting_key, 'value' => $option, 'xtra' => "min='0'", 'suffix' => __("minutes", 'lang_cache'))); // max='".($setting_max > 0 ? $setting_max : 60)."'
		}

		function setting_cache_debug_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option_or_default($setting_key, 'no');

			list($option, $description) = setting_time_limit(array('key' => $setting_key, 'value' => $option, 'return' => 'array'));

			echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option, 'description' => $description));

			if($option == 'yes')
			{
				echo "<div>"
					.show_button(array('type' => 'button', 'name' => 'btnCacheTest', 'text' => __("Test", 'lang_cache'), 'class' => 'button-secondary'))
				."</div>
				<div class='cache_test'></div>";
			}
		}

	function admin_init()
	{
		$plugin_include_url = plugin_dir_url(__FILE__);

		mf_enqueue_script('script_cache_wp', $plugin_include_url."script_wp.js", array('plugin_url' => $plugin_include_url, 'ajax_url' => admin_url('admin-ajax.php')));
	}

	function fetch_request()
	{
		$this->http_host = (isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : "");
		$this->request_uri = strtolower($_SERVER['REQUEST_URI']);

		$this->clean_url = $this->http_host.$this->request_uri;
	}

	function admin_notices()
	{
		global $wpdb, $obj_base, $done_text, $error_text;

		if(IS_ADMINISTRATOR && $this->get_file_amount() > 0)
		{
			if(!isset($obj_base))
			{
				$obj_base = new mf_base();
			}

			$arr_post_types = $obj_base->get_post_types_for_metabox();
			$last_updated_manual_post_types = array_diff($arr_post_types, apply_filters('filter_last_updated_post_types', array(), 'manual'));

			$result = $wpdb->get_results("SELECT ID, post_title, post_type, post_modified FROM ".$wpdb->posts." WHERE post_type IN ('".implode("','", $last_updated_manual_post_types)."') AND post_status != 'auto-draft' ORDER BY post_modified DESC LIMIT 0, 1");

			foreach($result as $r)
			{
				$post_id_manual = $r->ID;
				$post_title_manual = $r->post_title;
				$post_type_manual = $r->post_type;
				$post_modified_manual = $r->post_modified;

				if($post_modified_manual > DEFAULT_DATE && $post_modified_manual > $this->file_amount_date_first)
				{
					$error_text = sprintf(__("The site was last updated %s and the oldest part of the cache was saved %s so you should %sclear the cache%s", 'lang_cache'), format_date($post_modified_manual)." <i class='fa fa-info-circle fa-lg blue' title='".$post_title_manual." (#".$post_id_manual.", ".$post_type_manual.")'></i>", format_date($this->file_amount_date_first), "<a id='notification_clear_cache_button' href='#api_cache_clear'>", "</a>");

					if(IS_SUPER_ADMIN && get_option('setting_cache_debug') == 'yes')
					{
						$error_text .= " (".$wpdb->last_query.")";
					}
				}
			}

			echo get_notification();
		}
	}

	function filter_sites_table_settings($arr_settings)
	{
		$arr_settings['settings_cache'] = array(
			'setting_cache_activate' => array(
				'type' => 'bool',
				'global' => false,
				'icon' => "fas fa-tachometer-alt",
				'name' => __("Cache", 'lang_cache')." - ".__("Activate", 'lang_cache'),
			),
		);

		return $arr_settings;
	}

	function api_cache_clear()
	{
		global $done_text, $error_text;

		$json_output = array(
			'success' => false,
		);

		// Needs to init a new object to work properly
		$obj_cache = new mf_cache();

		$file_amount_before = $obj_cache->get_file_amount();
		$file_amount_after = $obj_cache->do_clear();

		if($file_amount_after == 0)
		{
			$done_text = __("I successfully cleared the cache for you", 'lang_cache');
		}

		else if($file_amount_after < $file_amount_before)
		{
			$error_text = sprintf(__("I cleared %d/%d files in the cache. Repeat the action until all are cleared", 'lang_cache'), ($file_amount_before - $file_amount_after), $file_amount_before);
		}

		else
		{
			$error_text = __("I could not clear the cache. Please make sure that the credentials are correct", 'lang_cache');
		}

		if($done_text != '')
		{
			$json_output['success'] = true;
		}

		$json_output['html'] = get_notification();

		header('Content-Type: application/json');
		echo json_encode($json_output);
		die();
	}

	function api_cache_clear_all()
	{
		global $done_text, $error_text;

		$json_output = array();

		if(IS_SUPER_ADMIN)
		{
			// Needs to init a new object to work properly
			$obj_cache = new mf_cache();

			$file_amount_before = $obj_cache->get_file_amount(array('path' => $obj_cache->upload_path));
			$file_amount_after = $obj_cache->do_clear(array('path' => $obj_cache->upload_path));

			if($file_amount_after == 0)
			{
				$done_text = __("I successfully cleared the cache on all sites for you", 'lang_cache');
			}

			else if($file_amount_after < $file_amount_before)
			{
				$error_text = sprintf(__("I cleared %d/%d files in the cache. Repeat the action until all are cleared", 'lang_cache'), ($file_amount_before - $file_amount_after), $file_amount_before);
			}

			else
			{
				$error_text = __("I could not clear the cache on all sites. Please make sure that the credentials are correct", 'lang_cache');
			}
		}

		else
		{
			$error_text = __("You do not have the correct rights to perform this action", 'lang_cache');
		}

		if($done_text != '')
		{
			$json_output['success'] = true;
		}

		$json_output['html'] = get_notification();

		header('Content-Type: application/json');
		echo json_encode($json_output);
		die();
	}

	function api_cache_test()
	{
		global $done_text, $error_text;

		$json_output = array(
			'success' => false,
		);

		$site_url = get_site_url();

		list($content, $headers) = get_url_content(array('url' => $site_url, 'catch_head' => true));
		$time_1st = $headers['total_time'];

		if(preg_match("/\<\!\-\- Dynamic /i", $content))
		{
			list($content, $headers) = get_url_content(array('url' => $site_url, 'catch_head' => true));
			$time_2nd = $headers['total_time'];
		}

		if(!preg_match("/\<\!\-\- Dynamic /i", $content)) //preg_match("/\<\!\-\- Compressed /i", $content)
		{
			if(isset($time_2nd))
			{
				$done_text = sprintf(__("The cache was successfully tested. The site was loaded in %ss the first time and then again cached in %ss", 'lang_cache'), mf_format_number($time_1st, 1), mf_format_number($time_2nd, 2));
			}

			else
			{
				$done_text = sprintf(__("The cache was successfully tested. The site was loaded cached in %ss", 'lang_cache'), mf_format_number($time_1st, 2));
			}
		}

		else
		{
			$error_text = __("Something is not working as it should. Let an admin have a look and fix any issues with it", 'lang_cache');
		}

		if($done_text != '')
		{
			$json_output['success'] = true;
		}

		$json_output['html'] = get_notification();

		header('Content-Type: application/json');
		echo json_encode($json_output);
		die();
	}

	function create_dir()
	{
		$this->dir2create = strtolower($this->upload_path.trim($this->clean_url, "/"));

		if(!is_404())
		{
			$use_cache = true;

			$arr_ignore = array(
				'/.',
				'author=',
				'callback=',
				'favicon.',
				'fbclid=',
				'gad_source=',
				'gclid=',
				'pass=',
				//'plugins', // Then all /api/ in /plugins/ will be ignored
				'robots.',
				'tel:',
				'token=',
				'upgrade.',
				'upload.',
				//'uploads/', // Then all cache will be ignored
				'var_dump',
				'wp-add.',
				'wp-activate.',
				'wp-config.',
				'wp-cron.',
				'wp-json',
				'wp-login.',
				'wp-signup.',
				'wp-sitemap',
				'xmlrpc.',
			);

			$arr_ignore = apply_filters('filter_cache_ignore', $arr_ignore);

			foreach($arr_ignore as $str_ignore)
			{
				if(strpos($this->dir2create, $str_ignore) !== false || strpos($this->dir2create."/", $str_ignore) !== false)
				{
					if(get_option('setting_cache_debug') == 'yes')
					{
						do_log("create_dir: Ignored ".$this->dir2create." because ".$str_ignore);
					}

					$use_cache = false;
					break;
				}
			}

			if($use_cache == true && !is_dir($this->dir2create) && !file_exists($this->dir2create))
			{
				if(strlen($this->dir2create) > 256 || !@mkdir($this->dir2create, 0755, true))
				{
					return false;
				}
			}
		}

		return true;
	}

	function parse_file_address($data = array())
	{
		if(!isset($data['file_name'])){		$data['file_name'] = "index";}
		if(!isset($data['ignore_post'])){	$data['ignore_post'] = false;}

		if($this->file_name_xtra == '' && $data['ignore_post'] == false && count($_POST) > 0)
		{
			$this->file_name_xtra .= "_".md5(var_export($_POST, true));
		}

		if($this->create_dir())
		{
			$this->file_address = $this->dir2create."/".$data['file_name'].$this->file_name_xtra.".".$this->file_suffix;
		}

		else if(is_dir($this->upload_path.$this->http_host))
		{
			$this->file_address = $this->upload_path.$this->http_host."/".md5($this->request_uri).$this->file_name_xtra.".".$this->file_suffix;
		}

		else
		{
			$this->file_address = '';
		}
	}

	function is_password_protected()
	{
		global $post;

		return apply_filters('filter_is_password_protected', (isset($post->post_password) && $post->post_password != ''), array('post_id' => (isset($post->ID) ? $post->ID : 0), 'check_login' => false));
	}

	function compress_html($in)
	{
		$out = $in;

		if(get_option('setting_cache_extract_inline') == 'yes')
		{
			// Add inline style to external file
			##################
			if($this->combined_style_file_path != '' && strpos($out, "<style"))
			{
				$out_temp = "";

				$reg_exp = "/\<style.*?>(.*?)\<\/style>/is";

				$arr_styles = get_match_all($reg_exp, $out, false);

				foreach($arr_styles as $arr_style_content)
				{
					foreach($arr_style_content as $style_content)
					{
						$out_temp .= $style_content;
					}
				}

				if($out_temp != '')
				{
					$success = set_file_content(array('file' => $this->compress_css($this->combined_style_file_path), 'mode' => 'w', 'content' => $out_temp));

					if($success)
					{
						$style_tag_replace = "<link rel='stylesheet' id='mf_styles-css'";

						//do_log(__FUNCTION__.":".__LINE__.": Updated ".$this->combined_style_file_path);
						$out = preg_replace($reg_exp, "", $out);
						$out = str_replace($style_tag_replace, "<link rel='stylesheet' id='mf_styles_inline-css' href='".$this->combined_style_file_url."' media='all'>".$style_tag_replace, $out);
					}
				}
			}
			##################

			// Add inline script to external file
			##################
			if($this->combined_script_file_path != '' && strpos($out, "<script"))
			{
				$out_temp = "";

				$arr_reg_exp = array(
					"/<script>(.*?)<\/script>/is",
					"/<script id.*?>(.*?)<\/script>/is",
				);

				foreach($arr_reg_exp as $reg_exp)
				{
					$arr_scripts = get_match_all($reg_exp, $out, false);

					foreach($arr_scripts as $arr_script_content)
					{
						foreach($arr_script_content as $script_content)
						{
							$out_temp .= $script_content;
						}
					}
				}

				if($out_temp != '')
				{
					$success = set_file_content(array('file' => $this->compress_js($this->combined_script_file_path), 'mode' => 'w', 'content' => $out_temp));

					if($success)
					{
						foreach($arr_reg_exp as $reg_exp)
						{
							$out = preg_replace($reg_exp, "", $out);
						}

						$out = preg_replace('/<script src="(.*?)" id="mf_scripts-js"><\/script>/is', "$0<script src='".$this->combined_script_file_url."' id='mf_scripts_inline-js'></script>", $out);
					}
				}
			}
			##################
		}

		$exclude = $include = array();
		$exclude[] = '!/\*[^*]*\*+([^/][^*]*\*+)*/!';		$include[] = '';
		$exclude[] = '/>(\n|\r|\t|\r\n|  |	)+/';			$include[] = '>';
		$exclude[] = '/(\n|\r|\t|\r\n|  |	)+</';			$include[] = '<';
		$out = preg_replace($exclude, $include, $out);

		//If content is empty at this stage something has gone wrong and should be reversed
		if(strlen($out) == 0)
		{
			$out = $in;
		}

		else
		{
			if(get_option('setting_cache_debug') == 'yes')
			{
				$out .= "<!-- Compressed "
					//.$this->file_address." -> ".$this->file_suffix." "
				.date("Y-m-d H:i:s")." -->";
			}
		}

		return $out;
	}

	function get_cache()
	{
		$out = get_file_content(array('file' => $this->file_address));

		if(get_option('setting_cache_debug') == 'yes')
		{
			switch($this->file_suffix)
			{
				case 'html':
					$out .= "<!-- Cached ".date("Y-m-d H:i:s")." -->";
				break;

				case 'json':
					$arr_out = json_decode($out, true);
					$arr_out['cached'] = date("Y-m-d H:i:s");
					$arr_out['cached_file'] = $this->file_address;
					$out = json_encode($arr_out);
				break;
			}
		}

		return $out;
	}

	function is_cache_active()
	{
		return (get_option('setting_cache_activate') == 'yes' && apply_filters('filter_is_user_logged_in', is_user_logged_in()) != true);
	}

	function get_or_set_file_content($data = array())
	{
		if(!is_array($data))
		{
			$data = array(
				'suffix' => $data,
			);
		}

		if(!isset($data['suffix'])){			$data['suffix'] = 'html';}
		if(!isset($data['file_name_xtra'])){	$data['file_name_xtra'] = "";}

		$this->file_suffix = $data['suffix'];
		$this->file_name_xtra = ($data['file_name_xtra'] != '' ? "_".$data['file_name_xtra'] : '');

		$this->parse_file_address();

		if($this->file_address != '' && strlen($this->file_address) <= 255)
		{
			if($this->is_cache_active())
			{
				// We can never allow getting a previous cache if there is a POST present, this would mess up actions like login that is supposed to do something with the POST variables
				if(count($_POST) == 0 && file_exists(realpath($this->file_address)) && filesize($this->file_address) > 0)
				{
					$out = $this->get_cache();

					echo $out;

					if(get_option('setting_cache_debug') == 'yes')
					{
						$out .= "<!-- Test cached ".date("Y-m-d H:i:s")." -->";
					}

					exit;
				}

				else
				{
					ob_start(array($this, 'set_cache'));
				}
			}
		}

		else if(get_option('setting_cache_debug') == 'yes')
		{
			echo "<!-- No cache address ".date("Y-m-d H:i:s")." -->";
		}
	}

	function get_header()
	{
		if($this->is_cache_active())
		{
			$this->fetch_request();
			$this->get_or_set_file_content();
		}
	}

	function compress_css($in)
	{
		$exkludera = array('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '/(\n|\r|\t|\r\n|  |	)+/', '/(:|,) /', '/;}/');
		$inkludera = array('', '', '$1', '}');

		$out = preg_replace($exkludera, $inkludera, $in);

		return $out;
	}

	function get_sanitized_uri()
	{
		$uri_without_query = explode("?", $_SERVER['REQUEST_URI'])[0];

		$out = str_replace(array("/", "."), "-", trim($uri_without_query, "/"));

		if($out != "")
		{
			$out .= "-";
		}

		return $out;
	}

	function wp_head_combine_styles()
	{
		global $wp_styles, $error_text;

		if($this->is_cache_active() && get_option('setting_cache_combine') == 'yes')
		{
			$file_url_base = $this->site_url."/wp-content";
			$file_dir_base = WP_CONTENT_DIR;

			//$version = 0;
			$output = "";

			$arr_added = array();

			foreach($wp_styles->queue as $arr_style)
			{
				if(isset($wp_styles->registered[$arr_style]) && $wp_styles->registered[$arr_style] != null)
				{
					if(isset($wp_styles->registered[$arr_style]->src) && $wp_styles->registered[$arr_style]->src != false)
					{
						$content_after = "";

						if(isset($wp_styles->registered[$arr_style]->extra) && count($wp_styles->registered[$arr_style]->extra) > 0)
						{
							if(isset($wp_styles->registered[$arr_style]->extra['after']))
							{
								foreach($wp_styles->registered[$arr_style]->extra['after'] as $extra_after)
								{
									$content_after .= $extra_after;
								}
							}

							else if(isset($wp_styles->registered[$arr_style]->extra['path']))
							{
								// Should I load path here?

								//do_log(__FUNCTION__." - extra: ".var_export($wp_styles->registered[$arr_style], true));
								//array( 'handle' => 'wp-block-library', 'src' => '/wp-includes/css/dist/block-library/style.min.css', 'deps' => array ( ), 'ver' => false, 'args' => NULL, 'extra' => array ( 'path' => '/wp-includes/css/dist/block-library/style.min.css', 'rtl' => 'replace', 'suffix' => '.min', ), 'textdomain' => NULL, 'translations_path' => NULL, )
							}

							else
							{
								//_WP_Dependency::__set_state(array( 'handle' => 'customize-preview', 'src' => '/wp-includes/css/customize-preview.min.css', 'deps' => array ( 0 => 'dashicons', ), 'ver' => false, 'args' => NULL, 'extra' => array ( 'rtl' => 'replace', 'suffix' => '.min', ), 'textdomain' => NULL, 'translations_path' => NULL, ))
							}
						}

						if(isset($wp_styles->registered[$arr_style]->deps) && count($wp_styles->registered[$arr_style]->deps) > 0)
						{
							foreach($wp_styles->registered[$arr_style]->deps as $dependency)
							{
								switch($dependency)
								{
									case 'dashicons':
										// Should I load dashicons?
									break;

									default:
										//do_log(__FUNCTION__." - deps: ".$dependency);
									break;
								}
							}
						}

						$file_handle = $wp_styles->registered[$arr_style]->handle;
						$file_src = $wp_styles->registered[$arr_style]->src;
						$file_ver = $wp_styles->registered[$arr_style]->ver;

						$content = $resource_file_path = $fetch_type = "";

						//$version += point2int($file_ver, $file_handle);

						if(substr($file_src, 0, 3) == "/wp-")
						{
							$file_src = $this->site_url.$file_src;
						}

						$file_src = validate_url($file_src, false);

						if(strpos($file_src, $this->site_url_clean) === false)
						{
							list($content, $headers) = get_url_content(array('url' => $file_src, 'catch_head' => true));

							$fetch_type = "url_".$headers['http_code'];

							if($headers['http_code'] != 200)
							{
								$content = "";
							}
						}

						else
						{
							if(substr($file_url_base, 0, 8) == "https://")
							{
								$fetch_type = "non_url_https";

								$file_src = str_replace("http://", "https://", $file_src);
							}

							$resource_file_path = str_replace($file_url_base, $file_dir_base, $file_src);

							if(get_file_suffix($file_src) == 'php')
							{
								$fetch_type = "php";

								ob_start();

									include($resource_file_path);

								$content = ob_get_clean();
							}

							else
							{
								$fetch_type = "css";

								$content = get_file_content(array('file' => $resource_file_path));
							}
						}

						$content .= $content_after;

						if($content != '')
						{
							if($content != "@media all{}")
							{
								$output .= $content;
							}

							$arr_added[] = $file_handle;
						}

						else
						{
							$this->style_errors .= ($this->style_errors != '' ? "," : "").$file_handle
							." ("
								.$file_src
								." [".$fetch_type."]"
								." -> ".$resource_file_path
							.")";
						}
					}
				}
			}

			if($output != '')
			{
				$this->fetch_request();

				list($upload_path, $upload_url) = get_uploads_folder($this->post_type."/".$this->http_host."/styles", true);

				if($upload_path != '')
				{
					$version = date("YmdHis");
					$filename = "style-".$version.".min.css";
					$output = $this->compress_css($output);

					$success = set_file_content(array('file' => $upload_path.$filename, 'mode' => 'w', 'content' => $output));

					if($success && file_exists($upload_path.$filename))
					{
						foreach($arr_added as $handle)
						{
							wp_deregister_style($handle);
						}

						mf_enqueue_style('mf_styles', $upload_url.$filename, null);

						$file_name_inline = "style-inline-".$this->get_sanitized_uri().$version.".min.css";
						$this->combined_style_file_path = $upload_path.$file_name_inline;
						$this->combined_style_file_url = $upload_url.$file_name_inline;
					}

					if($this->style_errors != '')
					{
						$error_text = sprintf(__("The style resources %s were empty", 'lang_cache'), "'".$this->style_errors."'");
					}
				}

				if($error_text != '')
				{
					do_log($error_text, 'notification');

					$error_text = "";
				}
			}
		}
	}

	function compress_js($in)
	{
		$exkludera = array('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '/(\n|\r|\t|\r\n|  |	)+/');

		$out = preg_replace($exkludera, '', $in);

		return $out;
	}

	function wp_print_scripts_combine_scripts()
	{
		global $wp_scripts, $error_text;

		if($this->is_cache_active() && get_option('setting_cache_combine') == 'yes')
		{
			$file_url_base = $this->site_url."/wp-content";
			$file_dir_base = WP_CONTENT_DIR;

			//$version = 0;
			$output = $translation = $this->script_errors = "";
			$arr_deps = $arr_added = array();

			foreach($wp_scripts->queue as $arr_script)
			{
				if(isset($wp_scripts->registered[$arr_script]) && $wp_scripts->registered[$arr_script] != null)
				{
					if(isset($wp_scripts->registered[$arr_script]->src) && $wp_scripts->registered[$arr_script]->src != false)
					{
						if(isset($wp_scripts->registered[$arr_script]->extra) && count($wp_scripts->registered[$arr_script]->extra) > 0)
						{
							if(isset($wp_scripts->registered[$arr_script]->extra['data']))
							{
								$translation .= $wp_scripts->registered[$arr_script]->extra['data'];
							}

							/*else
							{
								do_log(__FUNCTION__." - extra: ".var_export($wp_scripts->registered[$arr_script], true));
							}*/
						}

						if(isset($wp_scripts->registered[$arr_script]->deps) && count($wp_scripts->registered[$arr_script]->deps) > 0)
						{
							$arr_deps = array_merge($arr_deps, $wp_scripts->registered[$arr_script]->deps);
						}

						$file_handle = $wp_scripts->registered[$arr_script]->handle;
						$file_src = $wp_scripts->registered[$arr_script]->src;
						$file_ver = $wp_scripts->registered[$arr_script]->ver;

						$content = $resource_file_path = $fetch_type = "";

						//$version += point2int($file_ver, $file_handle);

						if(substr($file_src, 0, 3) == "/wp-")
						{
							$file_src = $this->site_url.$file_src;
						}

						$file_src = validate_url($file_src, false);

						if(strpos($file_src, $this->site_url_clean) === false)
						{
							list($content, $headers) = get_url_content(array('url' => $file_src, 'catch_head' => true));

							$fetch_type = "url_".$headers['http_code'];

							if($headers['http_code'] != 200)
							{
								$content = "";
							}
						}

						else
						{
							if(substr($file_url_base, 0, 8) == "https://")
							{
								$fetch_type = "non_url_https";

								$file_src = str_replace("http://", "https://", $file_src);
							}

							$resource_file_path = str_replace($file_url_base, $file_dir_base, $file_src);

							if(get_file_suffix($file_src) == 'php')
							{
								$fetch_type = "php";

								ob_start();

									include($resource_file_path);

								$content = ob_get_clean();
							}

							else
							{
								$fetch_type = "js";

								$content = get_file_content(array('file' => $resource_file_path));
							}
						}

						if($content != '')
						{
							$output .= $content;

							$arr_added[] = $file_handle;
						}

						else
						{
							$this->script_errors .= ($this->script_errors != '' ? "," : "").$file_handle
							." ("
								.$file_src
								." [".$fetch_type."]"
								." -> ".$resource_file_path
							.")";
						}
					}
				}
			}

			if($output != '')
			{
				$this->fetch_request();

				list($upload_path, $upload_url) = get_uploads_folder($this->post_type."/".$this->http_host."/scripts", true);

				if($upload_path != '')
				{
					$version = date("YmdHis");
					$filename = "script-".$version.".min.js";
					$output = $this->compress_js($translation.$output);

					$success = set_file_content(array('file' => $upload_path.$filename, 'mode' => 'w', 'content' => $output));

					if($success)
					{
						if(file_exists($upload_path.$filename))
						{
							foreach($arr_added as $handle)
							{
								wp_deregister_script($handle);
							}

							wp_enqueue_script('mf_scripts', $upload_url.$filename, $arr_deps, null, true);

							$file_name_inline = "script-inline-".$this->get_sanitized_uri().$version.".min.js";
							$this->combined_script_file_path = $upload_path.$file_name_inline;
							$this->combined_script_file_url = $upload_url.$file_name_inline;
						}
					}

					if($this->script_errors != '')
					{
						$error_text = sprintf(__("The script resources %s were empty", 'lang_cache'), "'".$this->script_errors."'");
					}
				}

				else if($error_text != '')
				{
					do_log($error_text, 'notification');

					$error_text = "";
				}
			}
		}
	}

	function style_loader_tag($tag)
	{
		if($this->is_cache_active())
		{
			$tag = str_replace("  ", " ", $tag);
			$tag = str_replace(" />", ">", $tag);
			$tag = str_replace(" type='text/css'", "", $tag);
			$tag = str_replace(' type="text/css"', "", $tag);
		}

		return $tag;
	}

	function script_loader_tag($tag)
	{
		if($this->is_cache_active())
		{
			$tag = str_replace(" type='text/javascript'", "", $tag);
			$tag = str_replace(' type="text/javascript"', "", $tag);
		}

		return $tag;
	}

	/*function run_cache($data)
	{
		if($this->is_cache_active())
		{
			$this->fetch_request();
			$this->get_or_set_file_content($data);
		}
	}*/

	function recommend_config($data)
	{
		global $obj_base;

		if(!isset($data['file'])){		$data['file'] = '';}

		$update_with = "";

		if((!is_multisite() || is_main_site()) && get_option('setting_cache_activate') == 'yes')
		{
			$setting_cache_expires = get_site_option_or_default('setting_cache_expires', 24);
			$setting_cache_api_expires = get_site_option('setting_cache_api_expires', 15);

			$default_expires_months = 12;

			$file_page_expires = "modification plus ".$setting_cache_expires." ".($setting_cache_expires > 1 ? "hours" : "hour");
			$file_api_expires = ($setting_cache_api_expires > 0 ? "modification plus ".$setting_cache_api_expires." ".($setting_cache_api_expires > 1 ? "minutes" : "minute") : "");

			$cache_file_path = str_replace(ABSPATH, "", WP_CONTENT_DIR)."/uploads/mf_cache/%{SERVER_NAME}%{ENV:FILTERED_REQUEST}";

			if(!isset($obj_base))
			{
				$obj_base = new mf_base();
			}

			switch($obj_base->get_server_type())
			{
				default:
				case 'apache':
					$arr_cookies = apply_filters('filter_cache_logged_in_cookies', array('comment_author_', 'wordpress_logged_in', 'wp-postpass_'));

					$update_with = "AddDefaultCharset UTF-8\r\n"
					."\r\n"
					// Force UTF-8 for a number of file formats
					."<IfModule mod_mime.c>\r\n"
					."	AddCharset UTF-8 .atom .css .js .json .rss .vtt .xml\r\n"
					."</IfModule>\r\n"
					."\r\n"
					."<IfModule mod_rewrite.c>\r\n"
					."	RewriteEngine On\r\n"
					."\r\n"
					."	RewriteCond %{THE_REQUEST} ^[A-Z]{3,9}\ (.*)\ HTTP/\r\n"
					."	RewriteRule ^(.*) - [E=FILTERED_REQUEST:%1]\r\n"
					."\r\n"
					."	RewriteCond %{REQUEST_URI} !^.*[^/]$\r\n"
					."	RewriteCond %{REQUEST_URI} !^.*//.*$\r\n"
					."	RewriteCond %{REQUEST_METHOD} !POST\r\n"
					."	RewriteCond %{HTTP:Cookie} !^.*(".implode("|", $arr_cookies).").*$\r\n"
					."	RewriteCond %{DOCUMENT_ROOT}/".$cache_file_path."index.html -f\r\n"
					."	RewriteRule ^(.*) '".$cache_file_path."index.html' [L]\r\n"
					."\r\n"
					."	RewriteCond %{REQUEST_URI} !^.*[^/]$\r\n"
					."	RewriteCond %{REQUEST_URI} !^.*//.*$\r\n"
					."	RewriteCond %{REQUEST_METHOD} !POST\r\n"
					."	RewriteCond %{HTTP:Cookie} !^.*(".implode("|", $arr_cookies).").*$\r\n"
					."	RewriteCond %{DOCUMENT_ROOT}/".$cache_file_path."index.json -f\r\n"
					."	RewriteRule ^(.*) '".$cache_file_path."index.json' [L]\r\n"
					."</IfModule>\r\n"
					."\r\n"
					."<IfModule mod_expires.c>\r\n"
					."	ExpiresActive On\r\n"
					."	ExpiresDefault 'access plus ".$default_expires_months." month'\r\n"
					."	ExpiresByType text/html '".$file_page_expires."'\r\n"
					."	ExpiresByType text/xml '".$file_page_expires."'\r\n"
					."	ExpiresByType application/json '".($file_api_expires != '' ? $file_api_expires : $file_page_expires)."'\r\n"
					//."	ExpiresByType text/cache-manifest 'access plus 0 seconds'\r\n"
					."\r\n"
					."	Header unset Pragma\r\n"
					."	Header append Cache-Control 'public, must-revalidate'\r\n"
					."	Header unset Last-Modified\r\n"
					."\r\n"
					."	<IfModule mod_headers.c>\r\n"
					."		Header unset ETag\r\n"
					."	</IfModule>\r\n"
					."</IfModule>\r\n"
					."\r\n"
					."FileETag None\r\n"
					."\r\n"
					."<IfModule mod_filter.c>\r\n";

					$arr_file_type = array(
						"text/html",
						"text/plain",
						"text/xml",
						"application/xml",
						"application/xhtml+xml",
						"application/rss+xml",
						"text/css",
						"text/javascript",
						"application/javascript",
						"application/x-javascript",
						"application/json",
						"image/avif",
						"image/gif",
						"image/jpeg",
						"image/png",
						"image/webp",
						"image/x-icon",
						"font/woff2",
					);

					foreach($arr_file_type as $file_type)
					{
						$update_with .= "	AddOutputFilterByType DEFLATE ".$file_type."\r\n";
					}

					$update_with .= "</Ifmodule>";

					$default_expires_seconds = (MONTH_IN_SECONDS * $default_expires_months);
					$file_page_expires_seconds = (HOUR_IN_SECONDS * $setting_cache_expires);

					$update_with .= "\r\n"
					."\r\n<IfModule mod_headers.c>\r\n"
					."	<FilesMatch '\.(css|js|ico|avif|gif|jpg|jpeg|png|svg|webp|ttf|otf|woff|woff2)$'>\r\n"
					."		Header set Cache-Control 'max-age=".$default_expires_seconds."'\r\n" //, public
					."	</FilesMatch>\r\n"
					."	<FilesMatch '\.(html|htm|xml)$'>\r\n"
					."		Header set Cache-Control 'max-age=".$file_page_expires_seconds."'\r\n"
					."	</FilesMatch>\r\n"
					."</IfModule>";
				break;

				case 'nginx':
					$update_with = "";
				break;
			}
		}

		$data['html'] .= $obj_base->update_config(array(
			'plugin_name' => "MF Cache",
			'file' => $data['file'],
			'update_with' => $update_with,
			'auto_update' => true,
		));

		return $data;
	}
}