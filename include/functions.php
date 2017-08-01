<?php

function header_cache()
{
	global $file_address, $upload_path, $file_url;

	if(get_option('setting_activate_cache') == 'yes')
	{
		$http_host = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : "");
		$request_uri = $_SERVER['REQUEST_URI'];

		list($upload_path, $upload_url) = get_uploads_folder('mf_cache');

		$dir2create = $upload_path."/".trim($http_host.$request_uri, "/");
		$dir_exists = true;

		if(!is_dir($dir2create))
		{
			if(!mkdir($dir2create, 0755, true))
			{
				do_log(sprintf(__("I could not create %s", 'lang_cache'), $dir2create));

				$dir_exists = false;
				break;
			}
		}

		if($dir_exists == true)
		{
			$file_address = $dir2create."/index.html";
		}

		else
		{
			$file_address = $upload_path.$http_host."-".md5($request_uri).".html";
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

		if($success == false)
		{
			do_log(sprintf(__("I could not save the cache for %s", 'lang_cache'), $file_address));
		}
	}
}

function cron_cache()
{
	global $globals;

	//Clean up expired cache
	############################
	list($upload_path, $upload_url) = get_uploads_folder('mf_cache');

	$setting_cache_expires = get_option_or_default('setting_cache_expires', 24);

	$upload_path_site = $upload_path."/".get_site_url_clean(array('trim' => "/"));

	get_file_info(array('path' => $upload_path_site, 'callback' => "delete_files", 'folder_callback' => "delete_folders", 'time_limit' => (60 * 60 * $setting_cache_expires)));
	############################

	if(get_option('setting_cache_prepopulate') == 'yes')
	{
		list($upload_path, $upload_url) = get_uploads_folder('mf_cache');

		$globals['count'] = 0;

		$upload_path_site = $upload_path."/".get_site_url_clean(array('trim' => "/"));

		get_file_info(array('path' => $upload_path_site, 'callback' => "count_files"));

		if($globals['count'] == 0)
		{
			$arr_data = array();
			get_post_children(array('post_type' => 'page'), $arr_data);

			foreach($arr_data as $post_id => $post_title)
			{
				list($content, $headers) = get_url_content(get_permalink($post_id), true);
			}
		}
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
	rmdir($data['path']."/".$data['child']);
}

function count_files()
{
	global $globals;

	$globals['count']++;
}

function do_clear_cache()
{
	global $globals;

	list($upload_path, $upload_url) = get_uploads_folder('mf_cache');

	$globals['count'] = 0;

	$upload_path_site = $upload_path."/".get_site_url_clean(array('trim' => "/"));

	get_file_info(array('path' => $upload_path_site, 'callback' => "count_files"));

	if($globals['count'] > 0)
	{
		get_file_info(array('path' => $upload_path_site, 'callback' => "delete_files", 'folder_callback' => "delete_folders", 'time_limit' => 0));

		get_file_info(array('path' => $upload_path_site, 'callback' => "count_files"));
	}

	return $globals['count'];
}

function clear_cache()
{
	global $done_text, $error_text;

	$result = array();

	$count_temp = do_clear_cache();

	if($count_temp == 0)
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
		$result['error'] = __("I could not clear the cache. Please make sure that the credentials are correct", 'lang_cache')." (".$count_temp.")";
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

	list($upload_path, $upload_url) = get_uploads_folder('mf_cache');

	$globals['count'] = 0;
	get_file_info(array('path' => $upload_path, 'callback' => "count_files"));

	if($globals['count'] > 0)
	{
		echo "<div class='form_buttons'>"
		.show_button(array('type' => 'button', 'name' => 'btnCacheClear', 'text' => __("Clear", 'lang_cache'), 'class' => 'button-secondary'))
		."</div>
		<div id='cache_debug'>".sprintf(__("%d cached files", 'lang_cache'), $globals['count'])."</div>";
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