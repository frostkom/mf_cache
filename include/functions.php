<?php

function header_cache()
{
	if(get_option('setting_activate_cache') == 'yes' && (get_option('setting_activate_logged_in_cache') == 'yes' || !is_user_logged_in()))
	{
		$obj_cache = new mf_cache();
		$obj_cache->fetch_request();

		$obj_cache->parse_file_address();
		$obj_cache->get_or_set_file_content();
	}
}

function cron_cache()
{
	global $globals;

	$obj_cache = new mf_cache();

	$setting_cache_expires = get_option_or_default('setting_cache_expires', 24);

	if(get_option('setting_cache_prepopulate') == 'yes' && get_option('mf_cache_prepopulated') < date("Y-m-d H:i:s", strtotime("-".$setting_cache_expires." hour")))
	{
		$obj_cache->clear();

		if($obj_cache->file_amount == 0)
		{
			$obj_cache->populate();
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

		if(!preg_match("/BEGIN MF Cache/", $content) || !preg_match("/wordpress_logged_in/", $content))
		{
			$cache_file_path = str_replace(ABSPATH, "", WP_CONTENT_DIR)."/uploads/mf_cache/%{SERVER_NAME}%{ENV:FILTERED_REQUEST}";

			//AddDefaultCharset UTF-8
			//RewriteCond %{REQUEST_URI} !^(wp-(content|admin|includes).*) [NC]
			$recommend_htaccess = "# BEGIN MF Cache
RewriteEngine On

RewriteCond %{THE_REQUEST} ^[A-Z]{3,9}\ (.*)\ HTTP/
RewriteRule ^(.*) - [E=FILTERED_REQUEST:%1]

RewriteCond %{REQUEST_URI} !^.*[^/]$
RewriteCond %{REQUEST_URI} !^.*//.*$
RewriteCond %{REQUEST_METHOD} !POST
RewriteCond %{HTTP:Cookie} !^.*(comment_author_|wordpress_logged_in|wp-postpass_).*$
RewriteCond %{DOCUMENT_ROOT}/".$cache_file_path."index.html -f
RewriteRule ^(.*) '".$cache_file_path."index.html' [L]
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

function count_files($data)
{
	global $globals;

	$globals['count']++;

	$file_date_time = date("Y-m-d H:i:s", filemtime($data['file']));

	if($globals['date_first'] == '' || $file_date_time < $globals['date_first'])
	{
		$globals['date_first'] = $file_date_time;
	}

	if($globals['date_last'] == '' || $file_date_time > $globals['date_last'])
	{
		$globals['date_last'] = $file_date_time;
	}
}

function clear_cache()
{
	global $done_text, $error_text;

	$result = array();

	$obj_cache = new mf_cache();
	$obj_cache->clear();

	if($obj_cache->file_amount == 0)
	{
		delete_option('mf_cache_prepopulated');

		$done_text = __("I successfully cleared the cache for you", 'lang_cache');
	}

	else
	{
		$error_text = __("I could not clear the cache. Please make sure that the credentials are correct", 'lang_cache');
	}

	$out = get_notification();

	if($done_text != '')
	{
		$result['success'] = true;
		$result['message'] = $out;
	}

	else
	{
		$result['error'] = $out;
	}

	echo json_encode($result);
	die();
}

function populate_cache()
{
	global $done_text, $error_text;

	$result = array();

	$obj_cache = new mf_cache();
	$obj_cache->clear();

	$after_clear = $obj_cache->file_amount;

	if($obj_cache->file_amount == 0)
	{
		$obj_cache->populate();

		if($obj_cache->count_files() > 0)
		{
			$done_text = __("I successfully populated the cache for you", 'lang_cache');
		}

		else
		{
			$error_text = __("No files were populated", 'lang_cache');
		}

		$after_populate = $obj_cache->file_amount;
	}

	else
	{
		$error_text = __("I could not populate the cache. Please make sure that the credentials are correct", 'lang_cache');
	}

	$out = get_notification();

	if($done_text != '')
	{
		$result['success'] = true;
		$result['message'] = $out;
	}

	else
	{
		$result['error'] = $out;
	}

	echo json_encode($result);
	die();
}

function test_cache()
{
	global $done_text, $error_text;

	$result = array();

	$site_url = get_site_url();

	list($content, $headers) = get_url_content($site_url, true);

	if(preg_match("/\<\!\-\- Dynamic /i", $content))
	{
		list($content, $headers) = get_url_content($site_url, true);
	}

	if(!preg_match("/\<\!\-\- Dynamic /i", $content)) //preg_match("/\<\!\-\- Compressed /i", $content)
	{
		$done_text = __("The cache was successfully tested. All looks good and the site is ready for visitors", 'lang_cache');
	}

	else
	{
		$error_text = __("Something is not working as it should. Let an admin have a look and fix any issues with it", 'lang_cache');
	}

	$out = get_notification();

	if($done_text != '')
	{
		$result['success'] = true;
		$result['message'] = $out;
	}

	else
	{
		$result['error'] = $out;
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
			$arr_settings['setting_activate_logged_in_cache'] = __("Activate for logged in users", 'lang_cache');
			$arr_settings['setting_cache_expires'] = __("Expires", 'lang_cache');
			$arr_settings['setting_cache_prepopulate'] = __("Prepopulate", 'lang_cache');
			$arr_settings['setting_compress_html'] = __("Compress HTML", 'lang_cache');
			$arr_settings['setting_cache_debug'] = __("Debug", 'lang_cache');
		}

		else
		{
			$obj_cache = new mf_cache();
			$obj_cache->clear();
		}
	}

	else
	{
		$arr_settings['setting_cache_inactivated'] = __("Inactivated", 'lang_cache');

		delete_option('setting_activate_cache');

		$obj_cache = new mf_cache();
		$obj_cache->clear();
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

	if($option == 'yes')
	{
		get_file_info(array('path' => get_home_path(), 'callback' => "check_htaccess_cache", 'allow_depth' => false));
	}
}

function setting_activate_logged_in_cache_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option_or_default($setting_key, 'no');

	echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));
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
		$cache_debug_text = sprintf(__("%d cached files", 'lang_cache'), $obj_cache->file_amount);

		if($globals['date_first'] > DEFAULT_DATE)
		{
			$cache_debug_text .= " (".format_date($globals['date_first']);

				if($globals['date_last'] > $globals['date_first'] && format_date($globals['date_last']) != format_date($globals['date_first']))
				{
					$cache_debug_text .= " - ".format_date($globals['date_last']);
				}

			$cache_debug_text .= ")";
		}

		echo "<div class='form_buttons'>"
		.show_button(array('type' => 'button', 'name' => 'btnCacheClear', 'text' => __("Clear", 'lang_cache'), 'class' => 'button-secondary'))
		."</div>
		<div id='cache_debug'>".$cache_debug_text."</div>";
	}
}

function setting_cache_prepopulate_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option_or_default($setting_key, 'no');

	$suffix = "";

	if($option == 'yes')
	{
		$mf_cache_prepopulated = get_option('mf_cache_prepopulated');
		$setting_cache_expires = get_option('setting_cache_expires');

		if($mf_cache_prepopulated > DEFAULT_DATE)
		{
			$populate_next = format_date(date("Y-m-d H:i:s", strtotime($mf_cache_prepopulated." +".$setting_cache_expires." hour")));

			$suffix = sprintf(__("The cache was last populated %s and will be populated again %s", 'lang_cache'), format_date($mf_cache_prepopulated), $populate_next);
		}

		else
		{
			$suffix = sprintf(__("The cache has not been populated yet but will be %s", 'lang_cache'), get_next_cron());
		}
	}

	echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option, 'suffix' => $suffix));

	if($option == 'yes')
	{
		$obj_cache = new mf_cache();
		$obj_cache->get_posts2populate();

		$count_posts = count($obj_cache->arr_posts);

		$mf_cache_prepopulated_one = get_option('mf_cache_prepopulated_one');
		$mf_cache_prepopulated_total = get_option('mf_cache_prepopulated_total');

		$populate_info = "";
		$length_min = 0;

		if($mf_cache_prepopulated_total > 0)
		{
			$length_min = round($mf_cache_prepopulated_total / 60);

			if($length_min > 0)
			{
				$populate_info = " (".sprintf(__("%s files, %s min", 'lang_cache'), $count_posts, mf_format_number($length_min, 1)).")";
				$populate_info = " (".sprintf(__("%s min", 'lang_cache'), mf_format_number($length_min, 1)).")";
			}
		}

		else if($mf_cache_prepopulated_one > 0)
		{
			if($count_posts > 0)
			{
				$length_min = round($mf_cache_prepopulated_one * $count_posts / 60);

				if($length_min > 0)
				{
					//$populate_info = " (".sprintf(__("%s files, approx. %s min", 'lang_cache'), $count_posts, mf_format_number($length_min, 1)).")";
					$populate_info = " (".sprintf(__("Approx. %s min", 'lang_cache'), mf_format_number($length_min, 1)).")";
				}
			}
		}

		/*else if($count_posts > 0)
		{
			$populate_info = " (".sprintf(__("%s files", 'lang_cache'), $count_posts).")";
		}*/

		echo "<div class='form_buttons'>"
		.show_button(array('type' => 'button', 'name' => 'btnCachePopulate', 'text' => __("Populate", 'lang_cache').$populate_info, 'class' => 'button-secondary'))
		."</div>
		<div id='cache_populate'></div>";
	}
}

function setting_compress_html_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option_or_default($setting_key, 'yes');

	echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));
}

function setting_cache_debug_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option_or_default($setting_key, 'no');

	echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));

	if($option == 'yes')
	{
		echo "<div class='form_buttons'>"
		.show_button(array('type' => 'button', 'name' => 'btnCacheTest', 'text' => __("Test", 'lang_cache'), 'class' => 'button-secondary'))
		."</div>
		<div id='cache_test'></div>";
	}
}

function post_updated_cache($post_id, $post_after, $post_before)
{
	$arr_include = get_post_types(array('public' => true, 'names'));

	if(in_array(get_post_type($post_id), $arr_include) && $post_before->post_status == 'publish')
	{
		$post_url = get_permalink($post_id);

		$obj_cache = new mf_cache();
		$obj_cache->clean_url = str_replace(array("http://", "https://"), "", $post_url);

		$obj_cache->clear();
	}
}