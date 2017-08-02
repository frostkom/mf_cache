<?php

function header_cache()
{
	global $file_address;

	if(get_option('setting_activate_cache') == 'yes')
	{
		$obj_cache = new mf_cache();
		$obj_cache->fetch_request();

		if($obj_cache->create_dir())
		{
			$file_address = $obj_cache->dir2create."/index.html";
		}

		else
		{
			$file_address = $obj_cache->upload_path.$obj_cache->http_host."-".md5($obj_cache->request_uri).".html";
		}

		if(count($_POST) == 0 && strlen($file_address) <= 255 && file_exists(realpath($file_address)) && filesize($file_address) > 0)
		{
			readfile(realpath($file_address));
			echo "<!-- Cached ".date("Y-m-d H:i:s")." -->";
			exit;
		}
		
		else
		{
			ob_start('compress_html');
		}
	}
}

function compress_html($in)
{
	$out = "";

	if(strlen($in) > 0)
	{
		if(get_option_or_default('setting_compress_html', 'yes') == 'yes')
		{
			$exkludera = array('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '/>(\n|\r|\t|\r\n|  |	)+/', '/(\n|\r|\t|\r\n|  |	)+</');
			$inkludera = array('', '>', '<');

			$out = preg_replace($exkludera, $inkludera, $in);

			//If content is empty at this stage something has gone wrong and should be reversed
			if(strlen($out) == 0)
			{
				$out = $in;
			}

			//$out .= "<!-- Compressed ".date("Y-m-d H:i:s")." -->";
		}

		else
		{
			$out = $in;
		}

		cache_save($out);

		$out .= "<!-- Dynamic ".date("Y-m-d H:i:s")." -->";
	}

	return $out;
}

function cache_save($in)
{
	global $file_address;

	if(count($_POST) == 0)
	{
		$success = set_file_content(array('file' => $file_address, 'mode' => 'w', 'content' => $in));

		/*if($success == false)
		{
			do_log(sprintf(__("I could not save the cache for %s", 'lang_cache'), $file_address));
		}*/
	}
}

function cron_cache()
{
	global $globals;

	$obj_cache = new mf_cache();
	
	$setting_cache_expires = get_option_or_default('setting_cache_expires', 24);

	if(get_option('setting_cache_prepopulate') == 'yes' && get_option('mf_cache_prepopulated') < date("Y-m-d H:i:s", strtotime("-".$setting_cache_expires." hour")))
	{
		do_log("Cleared cache since the cache was last populated ".get_option('mf_cache_prepopulated')." and ".$setting_cache_expires."h had passed ".date("Y-m-d H:i:s", strtotime("-".$setting_cache_expires." hour")));

		$obj_cache->clear();

		if($obj_cache->file_amount == 0)
		{
			$arr_data = array();
			get_post_children(array('post_type' => 'page'), $arr_data);

			foreach($arr_data as $post_id => $post_title)
			{
				list($content, $headers) = get_url_content(get_permalink($post_id), true);
			}

			update_option('mf_cache_prepopulated', date("Y-m-d H:i:s"));
		}
	}

	else
	{
		$obj_cache->clear(60 * 60 * $setting_cache_expires);
	}
}

function check_htaccess_cache($data)
{
	if(basename($data['file']) == ".htaccess")
	{
		$content = get_file_content(array('file' => $data['file']));

		if(!preg_match("/(BEGIN MF Cache)/", $content))
		{
			$cache_file_path = str_replace(ABSPATH, "", WP_CONTENT_DIR)."/uploads/mf_cache/%{SERVER_NAME}%{ENV:FILTERED_REQUEST}index.html";

			//AddDefaultCharset UTF-8
			$recommend_htaccess = "# BEGIN MF Cache
RewriteEngine On

RewriteCond %{THE_REQUEST} ^[A-Z]{3,9}\ (.*)\ HTTP/
RewriteRule ^(.*) - [E=FILTERED_REQUEST:%1]

RewriteCond %{REQUEST_URI} !^.*[^/]$
RewriteCond %{REQUEST_URI} !^.*//.*$
RewriteCond %{REQUEST_METHOD} !POST	
RewriteCond %{DOCUMENT_ROOT}/".$cache_file_path." -f
RewriteRule ^(.*) '".$cache_file_path."' [L]
# END MF Cache";

			echo "<div class='mf_form'>"
				."<h3>".sprintf(__("Copy this to %s", 'lang_cache'), ".htaccess")."</h3>"
				."<p class='input'>".nl2br($recommend_htaccess)."</p>"
			."</div>";
		}
	}
}

function delete_folders($data)
{
	@rmdir($data['path']."/".$data['child']);
}

function count_files()
{
	global $globals;

	$globals['count']++;
}

function do_clear_cache()
{
	$obj_cache = new mf_cache();

	return $obj_cache->clear();
}

function clear_cache()
{
	global $done_text, $error_text;

	$result = array();

	if(do_clear_cache() == 0)
	{
		$done_text = __("I successfully cleared the cache for you", 'lang_cache');
	}

	$out = get_notification();

	if($out != '')
	{
		$result['success'] = true;
		$result['message'] = $out;
	}

	else
	{
		$result['error'] = __("I could not clear the cache. Please make sure that the credentials are correct", 'lang_cache');
	}

	echo json_encode($result);
	die();
}

function settings_cache()
{
	mf_enqueue_script('script_cache', plugin_dir_url(__FILE__)."script_wp.js", array('plugin_url' => plugin_dir_url(__FILE__), 'ajax_url' => admin_url('admin-ajax.php')), get_plugin_version(__FILE__));

	$options_area = __FUNCTION__;

	add_settings_section($options_area, "", $options_area."_callback", BASE_OPTIONS_PAGE);

	$arr_settings = array();

	if(get_option('setting_no_public_pages') != 'yes' && get_option('setting_theme_core_login') != 'yes')
	{
		$arr_settings['setting_activate_cache'] = __("Activate", 'lang_cache');

		if(get_option('setting_activate_cache') == 'yes')
		{
			$arr_settings['setting_cache_expires'] = __("Expires", 'lang_cache');
			$arr_settings['setting_cache_prepopulate'] = __("Prepopulate", 'lang_cache');
			$arr_settings['setting_compress_html'] = __("Compress HTML", 'lang_cache');
		}

		else
		{
			do_clear_cache();
		}
	}

	else
	{
		$arr_settings['setting_cache_inactivated'] = __("Inactivated", 'lang_cache');

		delete_option('setting_activate_cache');
		do_clear_cache();
	}

	show_settings_fields(array('area' => $options_area, 'settings' => $arr_settings));
}

function settings_cache_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);

	echo settings_header($setting_key, __("Cache", 'lang_cache'));
}

function setting_activate_cache_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option_or_default($setting_key, 'no');

	echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));

	get_file_info(array('path' => get_home_path(), 'callback' => "check_htaccess_cache", 'allow_depth' => false));
}

function setting_cache_inactivated_callback()
{
	echo "<p>".__("Since visitors are being redirected to the login page it is not possible to activate the cache, because that would prevent the redirect to work properly.", 'lang_cache')."</p>";
}

function setting_cache_expires_callback()
{
	global $globals;

	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option_or_default($setting_key, 24);

	echo show_textfield(array('type' => 'number', 'name' => $setting_key, 'value' => $option, 'suffix' => __("hours", 'lang_cache')));

	$obj_cache = new mf_cache();

	if($obj_cache->count_files() > 0)
	{
		echo "<div class='form_buttons'>"
		.show_button(array('type' => 'button', 'name' => 'btnCacheClear', 'text' => __("Clear", 'lang_cache'), 'class' => 'button-secondary'))
		."</div>
		<div id='cache_debug'>".sprintf(__("%d cached files", 'lang_cache'), $obj_cache->file_amount)."</div>";
	}
}

function setting_cache_prepopulate_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option_or_default($setting_key, 'no');

	echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));
}

function setting_compress_html_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option_or_default($setting_key, 'yes');

	echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));
}

function post_updated_cache($post_id, $post_after, $post_before)
{
	$arr_include = array('page', 'posts');

	if(in_array(get_post_type($post_id), $arr_include) && $post_before->post_status == 'publish')
	{
		$post_url = get_permalink($post_id);

		$obj_cache = new mf_cache();
		$obj_cache->clean_url = str_replace(array("http://", "https://"), "", $post_url);

		$count_temp = $obj_cache->clear();

		/*if($count_temp > 0)
		{
			do_log($obj_cache->clean_url." was NOT removed");
		}

		else
		{
			do_log($obj_cache->clean_url." was removed");
		}*/
	}
}