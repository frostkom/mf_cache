<?php

class mf_cache
{
	var $post_type = 'mf_cache';
	var $meta_prefix;
	var $upload_path;
	var $upload_url;
	var $clean_url = "";
	var $clean_url_orig;
	var $site_url;
	var $site_url_clean;
	var $arr_styles = array();
	var $arr_scripts = array();
	var $file_name_xtra = "";
	var $dir2create = "";
	var $file_address = "";
	var $suffix = "";
	var $style_errors = "";
	var $arr_resource = array();
	var $http_host = "";
	var $request_uri = "";
	var $print_styles_run = false;
	var $public_cache = "";
	var $script_errors = "";
	var $print_scripts_run = false;
	var $file_amount;
	var $file_amount_date_first = "";
	var $file_amount_date_last = "";
	var $arr_posts = array();
	var $folder2clear;

	function __construct()
	{
		$this->meta_prefix = $this->post_type.'_';

		list($this->upload_path, $this->upload_url) = get_uploads_folder($this->post_type, true);
		$this->clean_url = $this->clean_url_orig = get_site_url_clean(array('trim' => "/"));

		$this->site_url = get_site_url();
		$this->site_url_clean = remove_protocol(array('url' => $this->site_url));
	}

	function gather_count_files($data)
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

	function count_files($data = array())
	{
		if(!isset($data['path'])){		$data['path'] = $this->upload_path.trim($this->clean_url_orig, "/");}

		$this->file_amount = 0;
		$this->file_amount_date_first = $this->file_amount_date_last = "";
		get_file_info(array('path' => $data['path'], 'callback' => array($this, 'gather_count_files'), 'folder_callback' => array($this, 'delete_empty_folder')));

		return $this->file_amount;
	}

	function clear($data = array())
	{
		if(!isset($data['path'])){				$data['path'] = $this->upload_path.trim($this->clean_url_orig, "/");}
		if(!isset($data['time_limit'])){		$data['time_limit'] = 0;}
		if(!isset($data['time_limit_api'])){	$data['time_limit_api'] = ($data['time_limit'] * 60);}
		if(!isset($data['allow_depth'])){		$data['allow_depth'] = true;}

		$file_amount = $this->count_files();

		if($file_amount > 0)
		{
			$data_temp = $data;
			$data_temp['callback'] = array($this, 'delete_file');
			$data_temp['folder_callback'] = array($this, 'delete_empty_folder');

			get_file_info($data_temp);

			$file_amount = $this->count_files();

			rmdir($data['path']);
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
			if(get_option('setting_activate_cache') == 'yes')
			{
				// Overall expiry
				########################
				$setting_cache_expires = get_site_option_or_default('setting_cache_expires', 24);
				$setting_cache_prepopulate = get_option('setting_cache_prepopulate');

				if($setting_cache_prepopulate == 'yes' && $setting_cache_expires > 0 && get_option('option_cache_prepopulated') < date("Y-m-d H:i:s", strtotime("-".$setting_cache_expires." hour")))
				{
					$file_amount = $this->clear();

					if($file_amount == 0)
					{
						$this->populate();
					}
				}

				else
				{
					$setting_cache_api_expires = get_site_option_or_default('setting_cache_api_expires', 15);

					$this->clear(array(
						'time_limit' => (HOUR_IN_SECONDS * $setting_cache_expires),
						'time_limit_api' => (MINUTE_IN_SECONDS * $setting_cache_api_expires),
					));
				}
				########################

				// Individual expiry
				########################
				/*$this->get_posts2populate();

				if(isset($this->arr_posts) && is_array($this->arr_posts))
				{
					foreach($this->arr_posts as $post_id => $post_title)
					{
						$post_expires = get_post_meta($post_id, $this->meta_prefix.'expires', true);

						if($post_expires > 0)
						{
							$post_date = get_the_date("Y-m-d H:i:s", $post_id);

							if($post_date < date("Y-m-d H:i:s", strtotime("-".$post_expires." minute")))
							{
								$post_url = get_permalink($post_id);

								$this->clean_url = remove_protocol(array('url' => $post_url, 'clean' => true));
								$this->clear(array(
									'time_limit' => (60 * $post_expires),
									'allow_depth' => false,
								));

								if($setting_cache_prepopulate == 'yes')
								{
									get_url_content(array('url' => $post_url));
								}
							}
						}
					}
				}*/
				########################
			}
		}

		$obj_cron->end();
	}

	function settings_cache()
	{
		$options_area_orig = $options_area = __FUNCTION__;

		add_settings_section($options_area, "", array($this, $options_area."_callback"), BASE_OPTIONS_PAGE);

		$arr_settings = array();

		$setting_activate_cache = get_option('setting_activate_cache');
		$this->public_cache = (get_option('setting_no_public_pages') != 'yes' && get_option('setting_theme_core_login') != 'yes');

		$arr_settings['setting_activate_cache'] = __("Activate", 'lang_cache');

		if($setting_activate_cache == 'yes')
		{
			$arr_settings['setting_cache_expires'] = __("Expires", 'lang_cache');

			if($this->public_cache == true && is_plugin_active("mf_theme_core/index.php"))
			{
				$arr_settings['setting_cache_prepopulate'] = __("Prepopulate", 'lang_cache');
			}

			$arr_settings['setting_cache_api_expires'] = __("API Expires", 'lang_cache');

			$arr_settings['setting_cache_debug'] = __("Debug", 'lang_cache');
		}

		else
		{
			delete_option('option_cache_prepopulated');
		}

		show_settings_fields(array('area' => $options_area, 'object' => $this, 'settings' => $arr_settings));
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

			$file_amount = $file_amount_all = $this->count_files();

			if(IS_SUPER_ADMIN)
			{
				$file_amount_all = $this->count_files(array('path' => $this->upload_path));
			}

			if($file_amount > 0 || $file_amount_all > 0)
			{
				echo "<div class='form_button'>";

					if($file_amount > 0)
					{
						echo show_button(array('type' => 'button', 'name' => 'btnCacheClear', 'text' => __("Clear", 'lang_cache'), 'class' => 'button-secondary'));
					}

					if(IS_SUPER_ADMIN && $file_amount_all > $file_amount)
					{
						echo show_button(array('type' => 'button', 'name' => 'btnCacheClearAll', 'text' => __("Clear All Sites", 'lang_cache'), 'class' => 'button-secondary'));
					}

				echo "</div>
				<div id='cache_debug'>";

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

		function setting_cache_inactivated_callback()
		{
			echo "<p>".__("Since visitors are being redirected to the login page it is not possible to activate the cache, because that would prevent the redirect to work properly.", 'lang_cache')."</p>";
		}

		function setting_cache_expires_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			settings_save_site_wide($setting_key);
			$option = get_site_option_or_default($setting_key, get_option_or_default($setting_key, 24));

			echo show_textfield(array('type' => 'number', 'name' => $setting_key, 'value' => $option, 'xtra' => "min='1' max='240'", 'suffix' => __("hours", 'lang_cache')));
		}

		function setting_cache_prepopulate_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option_or_default($setting_key, 'no');

			$suffix = "";

			if($option == 'yes')
			{
				$setting_cache_expires = get_site_option('setting_cache_expires');

				if($setting_cache_expires > 0)
				{
					$option_cache_prepopulated = get_option('option_cache_prepopulated');

					if($option_cache_prepopulated > DEFAULT_DATE)
					{
						$populate_next = format_date(date("Y-m-d H:i:s", strtotime($option_cache_prepopulated." +".$setting_cache_expires." hour")));

						$suffix = sprintf(__("The cache was last populated %s and will be populated again %s", 'lang_cache'), format_date($option_cache_prepopulated), $populate_next);
					}

					else
					{
						$suffix = sprintf(__("The cache has not been populated yet but will be %s", 'lang_cache'), get_next_cron());
					}
				}
			}

			echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option, 'suffix' => $suffix));

			if($option == 'yes')
			{
				$this->get_posts2populate();

				$count_posts = count($this->arr_posts);

				$option_cache_prepopulated_one = get_option('option_cache_prepopulated_one');
				$option_cache_prepopulated_total = get_option('option_cache_prepopulated_total');

				$populate_info = "";
				$length_min = 0;

				if($option_cache_prepopulated_total > 0)
				{
					$length_min = round($option_cache_prepopulated_total / 60);

					if($length_min > 0)
					{
						$populate_info = " (".sprintf(__("%s files, %s min", 'lang_cache'), $count_posts, mf_format_number($length_min, 1)).")";
						$populate_info = " (".sprintf(__("%s min", 'lang_cache'), mf_format_number($length_min, 1)).")";
					}
				}

				else if($option_cache_prepopulated_one > 0)
				{
					if($count_posts > 0)
					{
						$length_min = round($option_cache_prepopulated_one * $count_posts / 60);

						if($length_min > 0)
						{
							$populate_info = " (".sprintf(__("Approx. %s min", 'lang_cache'), mf_format_number($length_min, 1)).")";
						}
					}
				}

				echo "<div>"
					.show_button(array('type' => 'button', 'name' => 'btnCachePopulate', 'text' => __("Populate", 'lang_cache').$populate_info, 'class' => 'button-secondary'))
				."</div>
				<div id='cache_populate'></div>";
			}
		}

		function setting_cache_api_expires_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			settings_save_site_wide($setting_key);
			$option = get_site_option($setting_key, get_option_or_default($setting_key, 15));

			$setting_max = get_site_option_or_default('setting_cache_expires', 24) * 60;

			echo show_textfield(array('type' => 'number', 'name' => $setting_key, 'value' => $option, 'xtra' => "min='0' max='".($setting_max > 0 ? $setting_max : 60)."'", 'suffix' => __("minutes", 'lang_cache')));
		}

		function setting_cache_debug_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option_or_default($setting_key, 'no');

			$description = setting_time_limit(array('key' => $setting_key, 'value' => $option));

			echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option, 'description' => $description));

			if($option == 'yes')
			{
				echo "<div>"
					.show_button(array('type' => 'button', 'name' => 'btnCacheTest', 'text' => __("Test", 'lang_cache'), 'class' => 'button-secondary'))
				."</div>
				<div id='cache_test'></div>";
			}
		}

	function admin_init()
	{
		$plugin_include_url = plugin_dir_url(__FILE__);
		$plugin_version = get_plugin_version(__FILE__);

		mf_enqueue_script('script_cache_wp', $plugin_include_url."script_wp.js", array('plugin_url' => $plugin_include_url, 'ajax_url' => admin_url('admin-ajax.php')), $plugin_version);
	}

	/*function wp_head()
	{
		echo "<meta name='apple-mobile-web-app-capable' content='yes'>
		<meta name='mobile-web-app-capable' content='yes'>";
	}*/

	function run_cache($data)
	{
		$this->fetch_request();
		$this->get_or_set_file_content($data);
	}

	function recommend_config($data)
	{
		global $obj_base;

		if(!isset($data['file'])){		$data['file'] = '';}

		$update_with = "";

		if((!is_multisite() || is_main_site()) && get_option('setting_activate_cache') == 'yes' && $this->public_cache == true)
		{
			$setting_cache_expires = get_site_option_or_default('setting_cache_expires', 24);
			$setting_cache_api_expires = get_site_option('setting_cache_api_expires', 15);

			$default_expires_months = 1;

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
					."	RewriteCond %{HTTP:Cookie} !^.*(comment_author_|wordpress_logged_in|wp-postpass_).*$\r\n"
					."	RewriteCond %{DOCUMENT_ROOT}/".$cache_file_path."index.html -f\r\n"
					."	RewriteRule ^(.*) '".$cache_file_path."index.html' [L]\r\n"
					."\r\n"
					."	RewriteCond %{REQUEST_URI} !^.*[^/]$\r\n"
					."	RewriteCond %{REQUEST_URI} !^.*//.*$\r\n"
					."	RewriteCond %{REQUEST_METHOD} !POST\r\n"
					."	RewriteCond %{HTTP:Cookie} !^.*(comment_author_|wordpress_logged_in|wp-postpass_).*$\r\n"
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
					."	ExpiresByType text/cache-manifest 'access plus 0 seconds'\r\n"
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
					."<IfModule mod_filter.c>\r\n"
					."	AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json image/jpeg image/png image/gif image/x-icon\r\n"
					."</Ifmodule>";

					$default_expires_seconds = (MONTH_IN_SECONDS * $default_expires_months);
					$file_page_expires_seconds = (HOUR_IN_SECONDS * $setting_cache_expires);

					$update_with .= "\r\n"
					."\r\n<IfModule mod_headers.c>\r\n"
					."	<FilesMatch '\.(ico|gif|jpg|jpeg|png|pdf|js|css)$'>\r\n"
					."		Header set Cache-Control 'max-age=".$default_expires_seconds."'\r\n" //, public
					."	</FilesMatch>\r\n"
					."	<FilesMatch '\.(html|htm|txt|xml)$'>\r\n"
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

	function fetch_request()
	{
		$this->http_host = (isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : "");
		$this->request_uri = strtolower($_SERVER['REQUEST_URI']);

		$this->clean_url = $this->http_host.$this->request_uri;
	}

	function is_page_inactivated()
	{
		global $post;

		if(isset($post->ID))
		{
			if(get_post_meta($post->ID, $this->meta_prefix.'expires', true) == -1)
			{
				return true;
			}
		}

		return false;
	}

	function get_header()
	{
		if(get_option('setting_activate_cache') == 'yes' && $this->is_page_inactivated() == false)
		{
			$this->fetch_request();
			$this->get_or_set_file_content();
		}
	}

	function get_type($src)
	{
		return (substr(remove_protocol(array('url' => $src)), 0, strlen($this->site_url_clean)) == $this->site_url_clean ? 'internal' : 'external');
	}

	function wp_before_admin_bar_render()
	{
		global $wp_admin_bar;

		if(IS_ADMINISTRATOR && $this->count_files() > 0)
		{
			$wp_admin_bar->add_node(array(
				'id' => 'cache',
				'title' => "<a href='#clear_cache' class='color_red'>".__("Clear Cache", 'lang_cache')."</a>",
			));
		}
	}

	function admin_notices()
	{
		global $wpdb, $obj_base, $done_text, $error_text;

		if(!isset($obj_base))
		{
			$obj_base = new mf_base();
		}

		if(IS_ADMINISTRATOR && $this->count_files() > 0)
		{
			$arr_post_types = $obj_base->get_post_types_for_metabox();
			$last_updated_manual_post_types = array_diff($arr_post_types, apply_filters('filter_last_updated_post_types', array(), 'manual'));

			$result = $wpdb->get_results("SELECT ID, post_title, post_modified FROM ".$wpdb->posts." WHERE post_type IN ('".implode("','", $last_updated_manual_post_types)."') AND post_status != 'auto-draft' ORDER BY post_modified DESC LIMIT 0, 1");

			foreach($result as $r)
			{
				$post_id_manual = $r->ID;
				$post_modified_manual = $r->post_modified;

				if($post_modified_manual > DEFAULT_DATE && $post_modified_manual > $this->file_amount_date_first)
				{
					$error_text = sprintf(__("The site was last updated %s and the oldest part of the cache was saved %s so you should %sclear the cache%s", 'lang_cache'), format_date($post_modified_manual), format_date($this->file_amount_date_first), "<a id='notification_clear_cache_button' href='#clear_cache'>", "</a>");
				}
			}

			echo get_notification();
		}
	}

	function filter_sites_table_settings($arr_settings)
	{
		$arr_settings['settings_cache'] = array(
			'setting_activate_cache' => array(
				'type' => 'bool',
				'global' => false,
				'icon' => "fas fa-tachometer-alt",
				'name' => __("Cache", 'lang_cache')." - ".__("Activate", 'lang_cache'),
			),
		);

		return $arr_settings;
	}

	function clear_cache()
	{
		global $done_text, $error_text;

		$result = array();

		// Needs to init a new object to work properly
		$obj_cache = new mf_cache();
		$file_amount = $obj_cache->clear();

		if($file_amount == 0)
		{
			delete_option('option_cache_prepopulated');

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

		header('Content-Type: application/json');
		echo json_encode($result);
		die();
	}

	function clear_all_cache()
	{
		global $done_text, $error_text;

		$result = array();

		if(IS_SUPER_ADMIN)
		{
			// Needs to init a new object to work properly
			$obj_cache = new mf_cache();
			//$obj_cache->clean_url = "";
			$file_amount = $obj_cache->clear();

			if($file_amount == 0)
			{
				delete_option('option_cache_prepopulated');

				$done_text = __("I successfully cleared the cache on all sites for you", 'lang_cache');
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

		header('Content-Type: application/json');
		echo json_encode($result);
		die();
	}

	function populate_cache()
	{
		global $done_text, $error_text;

		$result = array();

		// Needs to init a new object to work properly
		$obj_cache = new mf_cache();

		$file_amount = $obj_cache->clear();

		if($file_amount == 0)
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
			$error_text = __("I could not clear the cache before population. Please make sure that the credentials are correct", 'lang_cache');
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

		header('Content-Type: application/json');
		echo json_encode($result);
		die();
	}

	function test_cache()
	{
		global $done_text, $error_text;

		$result = array();

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

		header('Content-Type: application/json');
		echo json_encode($result);
		die();
	}

	function clear_folder($data)
	{
		$folder = $data['path']."/".$data['child'];

		if(is_dir($folder) && substr($data['child'], 0, strlen($this->folder2clear)) == $this->folder2clear)
		{
			$this->clean_url = str_replace($this->upload_path, "", $folder);
			$this->clear();
		}
	}

	function should_load_as_url()
	{
		if(substr($this->arr_resource['file'], 0, 3) == "/wp-")
		{
			$this->arr_resource['file'] = $this->site_url.$this->arr_resource['file'];
		}

		$this->arr_resource['file'] = validate_url($this->arr_resource['file'], false);

		return ($this->arr_resource['type'] == 'external'); // || get_file_suffix($this->arr_resource['file']) == 'php'
	}

	function enqueue_style($data)
	{
		if($data['file'] != '')
		{
			$this->arr_styles[$data['handle']] = array(
				'source' => 'known',
				'type' => $this->get_type($data['file']),
				'file' => $data['file'],
				'version' => $data['version'],
			);
		}
	}

	function print_styles()
	{
		if($this->print_styles_run == false)
		{
			global $error_text;

			$file_url_base = $this->site_url."/wp-content";
			$file_dir_base = WP_CONTENT_DIR;

			if(count($this->arr_styles) > 0)
			{
				$version = 0;
				$output = "";

				foreach($this->arr_styles as $handle => $this->arr_resource)
				{
					$resource_file_path = $fetch_type = "";

					$version += point2int($this->arr_resource['version']);

					if($this->should_load_as_url() == false)
					{
						$fetch_type = "non_url";

						// Just in case HTTPS is not forced on all pages
						if(substr($file_url_base, 0, 8) == "https://")
						{
							$fetch_type = "non_url_https";

							$this->arr_resource['file'] = str_replace("http://", "https://", $this->arr_resource['file']);
						}
					}

					if($this->should_load_as_url())
					{
						list($content, $headers) = get_url_content(array('url' => $this->arr_resource['file'], 'catch_head' => true));

						$fetch_type = "url_".$headers['http_code'];

						if($headers['http_code'] != 200)
						{
							$content = "";
						}
					}

					else if(get_file_suffix($this->arr_resource['file']) == 'php')
					{
						$fetch_type = "php";

						ob_start();

							$resource_file_path = str_replace($file_url_base, $file_dir_base, $this->arr_resource['file']);

							include($resource_file_path);

						$content = ob_get_clean();
					}

					else
					{
						$fetch_type = "css";

						$resource_file_path = str_replace($file_url_base, $file_dir_base, $this->arr_resource['file']);

						$content = get_file_content(array('file' => $resource_file_path));
					}

					if($content != '')
					{
						if($content != "@media all{}")
						{
							$output .= $content;
						}
					}

					else
					{
						$this->style_errors .= ($this->style_errors != '' ? "," : "").$handle
						." ("
							.$this->arr_resource['file']
							." [".$fetch_type."]"
							." -> ".$resource_file_path
						.")";

						unset($this->arr_styles[$handle]);
					}
				}

				if($output != '')
				{
					$this->fetch_request();

					list($upload_path, $upload_url) = get_uploads_folder("mf_cache/".$this->http_host."/styles", true);

					if($upload_path != '')
					{
						$version = int2point($version);

						$file = "style-".$version.".min.css";

						$output = $this->compress_css($output);

						$success = set_file_content(array('file' => $upload_path.$file, 'mode' => 'w', 'content' => $output));

						if($success && file_exists($upload_path.$file))
						{
							foreach($this->arr_styles as $handle => $this->arr_resource)
							{
								wp_deregister_style($handle);
							}

							mf_enqueue_style('mf_styles', $upload_url.$file, null);
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

			$this->print_styles_run = true;
		}
	}

	function enqueue_script($data)
	{
		if($data['file'] != '')
		{
			$this->arr_scripts[$data['handle']] = array(
				'source' => 'known',
				'type' => $this->get_type($data['file']),
				'file' => $data['file'],
				'translation' => $data['translation'],
				'version' => $data['version'],
			);
		}
	}

	function output_js($data)
	{
		global $error_text;

		$this->fetch_request();

		list($upload_path, $upload_url) = get_uploads_folder("mf_cache/".$this->http_host."/scripts", true);

		if($upload_path != '')
		{
			if(isset($data['handle']) && $data['handle'] != '')
			{
				$data['filename'] = "script-".$data['handle'].".js";
			}

			else
			{
				$data['version'] = int2point($data['version']);
				$data['filename'] = "script-".$data['version'].".min.js";
				$data['content'] = $this->compress_js($data['content']);
			}

			$success = set_file_content(array('file' => $upload_path.$data['filename'], 'mode' => 'w', 'content' => $data['content']));

			if($success)
			{
				if(isset($data['handle']) && $data['handle'] != '')
				{
					wp_deregister_script($data['handle']);

					wp_enqueue_script($data['handle'], $upload_url.$data['filename'], array('jquery'), null, true); //$data['version']

					unset($this->arr_scripts[$data['handle']]);
				}

				else if(file_exists($upload_path.$data['filename']))
				{
					foreach($this->arr_scripts as $handle => $this->arr_resource)
					{
						wp_deregister_script($handle);
					}

					mf_enqueue_script('mf_scripts', $upload_url.$data['filename'], null);

					if(isset($data['translation']) && $data['translation'] != '')
					{
						echo "<script>".$data['translation']."</script>";
					}
				}
			}

			if($this->script_errors != '')
			{
				$error_text = sprintf(__("The script resources %s were empty", 'lang_cache'), "'".$this->script_errors."'"); //, var_export($this->arr_scripts, true)
			}
		}

		else if($error_text != '')
		{
			do_log($error_text, 'notification');

			$error_text = "";
		}
	}

	function wp_print_scripts()
	{
		if($this->print_scripts_run == false)
		{
			$setting_merge_js_type = array('known_internal', 'known_external'); //, 'unknown_internal', 'unknown_external'

			$file_url_base = $this->site_url."/wp-content";
			$file_dir_base = WP_CONTENT_DIR;

			if(count($this->arr_scripts) > 0)
			{
				$version = 0;
				$output = $translation = $this->script_errors = "";

				foreach($this->arr_scripts as $handle => $this->arr_resource)
				{
					$resource_file_path = "";

					$merge_type = $this->arr_resource['source']."_".$this->arr_resource['type'];

					$version += point2int($this->arr_resource['version']);

					if(isset($this->arr_resource['translation']))
					{
						$count_temp = count($this->arr_resource['translation']);

						if(is_array($this->arr_resource['translation']) && $count_temp > 0)
						{
							$translation_values = "";

							foreach($this->arr_resource['translation'] as $key => $value)
							{
								$translation_values .= ($translation_values != '' ? "," : "")."'".$key."': ".(is_array($value) ? wp_json_encode($value) : "\"".$value."\"");
							}

							if($translation_values != '')
							{
								$translation .= "var ".$handle." = {".$translation_values."};";
							}
						}
					}

					$content = "";

					if($this->should_load_as_url())
					{
						if(in_array($merge_type, $setting_merge_js_type))
						{
							list($content, $headers) = get_url_content(array('url' => $this->arr_resource['file'], 'catch_head' => true));

							if($headers['http_code'] != 200)
							{
								$content = "";
							}
						}

						if($content != '')
						{
							$this->output_js(array('handle' => $handle, 'content' => $content, 'version' => $this->arr_resource['version']));
						}

						else
						{
							$this->script_errors .= ($this->script_errors != '' ? "," : "").$handle;

							unset($this->arr_scripts[$handle]);
						}
					}

					else
					{
						if(in_array($merge_type, $setting_merge_js_type))
						{
							$resource_file_path = str_replace($file_url_base, $file_dir_base, $this->arr_resource['file']);

							$content = get_file_content(array('file' => $resource_file_path));
						}

						if($content != '')
						{
							$output .= $content;
						}

						else
						{
							$this->script_errors .= ($this->script_errors != '' ? "," : "").$handle;

							unset($this->arr_scripts[$handle]);
						}
					}
				}

				if($output != '')
				{
					$this->output_js(array('content' => $output, 'version' => $version, 'translation' => $translation));
				}
			}

			$this->print_scripts_run = true;
		}
	}

	function style_loader_tag($tag)
	{
		$tag = str_replace("  ", " ", $tag);
		$tag = str_replace(" />", ">", $tag);
		$tag = str_replace(" type='text/css'", "", $tag);
		$tag = str_replace(' type="text/css"', "", $tag);

		return $tag;
	}

	function script_loader_tag($tag)
	{
		$tag = str_replace(" type='text/javascript'", "", $tag);
		$tag = str_replace(' type="text/javascript"', "", $tag);

		return $tag;
	}

	function is_password_protected()
	{
		global $post;

		return apply_filters('filter_is_password_protected', (isset($post->post_password) && $post->post_password != ''), array('post_id' => (isset($post->ID) ? $post->ID : 0), 'check_login' => false));
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
				'fbclid=',
				'pass=',
				'tel:',
				'token=',
				'var_dump',
				'wp-activate.',
				'wp-config.',
				'wp-signup.',
				'wp-sitemap',
				'xmlrpc.',
			);

			$arr_ignore = apply_filters('filter_cache_ignore', $arr_ignore);

			foreach($arr_ignore as $str_ignore)
			{
				if(strpos($this->dir2create, $str_ignore) !== false || strpos($this->dir2create."/", $str_ignore) !== false)
				{
					if(get_option_or_default('setting_cache_debug') == 'yes')
					{
						do_log("create_dir: Ignored ".$this->dir2create." because ".$str_ignore);
					}

					$use_cache = false;
					break;
				}
			}

			if($use_cache == true)
			{
				if(!is_dir($this->dir2create))
				{
					if(strlen($this->dir2create) > 256 || !mkdir($this->dir2create, 0755, true))
					{
						return false;
					}
				}
			}
		}

		return true;
	}

	function parse_file_address($data = array())
	{
		if(!isset($data['ignore_post'])){	$data['ignore_post'] = false;}

		if($this->file_name_xtra == '' && $data['ignore_post'] == false && count($_POST) > 0)
		{
			$this->file_name_xtra .= "_".md5(var_export($_POST, true));
		}

		if($this->create_dir())
		{
			$this->file_address = $this->dir2create."/index".$this->file_name_xtra.".".$this->suffix;
		}

		else if(is_dir($this->upload_path.$this->http_host))
		{
			$this->file_address = $this->upload_path.$this->http_host."/".md5($this->request_uri).$this->file_name_xtra.".".$this->suffix;
		}

		else
		{
			$this->file_address = '';
		}
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
		if(!isset($data['allow_logged_in'])){	$data['allow_logged_in'] = false;}
		if(!isset($data['file_name_xtra'])){	$data['file_name_xtra'] = "";}

		// It is important that is_user_logged_in() is checked here so that it never is saved as a logged in user. This will potentially mean that the admin bar will end up in the cached version of the site
		if(get_option('setting_activate_cache') == 'yes' && ($data['allow_logged_in'] == true || is_user_logged_in() == false))
		{
			$this->suffix = $data['suffix'];
			$this->file_name_xtra = ($data['file_name_xtra'] != '' ? "_".$data['file_name_xtra'] : '');

			$this->parse_file_address();

			if($this->file_address != '' && strlen($this->file_address) <= 255)
			{
				// We can never allow getting a previous cache if there is a POST present, this would mess up actions like login that is supposed to do something with the POST variables
				if(count($_POST) == 0 && file_exists(realpath($this->file_address)) && filesize($this->file_address) > 0)
				{
					$out = $this->get_cache();

					echo $out;

					if(get_option('setting_cache_debug') == 'yes')
					{
						//$out .= "<!-- Test cached ".date("Y-m-d H:i:s")." -->";
					}

					exit;
				}

				else
				{
					ob_start(array($this, 'set_cache'));
				}
			}

			else if(get_option('setting_cache_debug') == 'yes')
			{
				echo "<!-- No cache address ".date("Y-m-d H:i:s")." -->";
				//do_log("No file address (".$this->file_address.")");
			}
		}

		else if(get_option('setting_cache_debug') == 'yes')
		{
			echo "<!-- Cache not allowed ".date("Y-m-d H:i:s")." -->";
			//do_log("Not allowed (".$this->file_address.", ".$data['allow_logged_in'].", ".is_user_logged_in().")");
		}
	}

	function get_cache()
	{
		$out = get_file_content(array('file' => $this->file_address));

		if(get_option('setting_cache_debug') == 'yes')
		{
			switch($this->suffix)
			{
				case 'html':
					$out .= "<!-- Cached ".date("Y-m-d H:i:s")." -->";
				break;

				case 'json':
					$arr_out = json_decode($out, true);
					$arr_out['cached'] = date("Y-m-d H:i:s");
					//$arr_out['cached_file'] = $this->file_address;
					$out = json_encode($arr_out);
				break;
			}
		}

		return $out;
	}

	function compress_html($in)
	{
		$exclude = $include = array();
		$exclude[] = '!/\*[^*]*\*+([^/][^*]*\*+)*/!';		$include[] = '';
		$exclude[] = '/>(\n|\r|\t|\r\n|  |	)+/';			$include[] = '>';
		$exclude[] = '/(\n|\r|\t|\r\n|  |	)+</';			$include[] = '<';
		$out = preg_replace($exclude, $include, $in);

		//If content is empty at this stage something has gone wrong and should be reversed
		if(strlen($out) == 0)
		{
			$out = $in;
		}

		else
		{
			if(get_option_or_default('setting_cache_debug') == 'yes')
			{
				$out .= "<!-- Compressed ".date("Y-m-d H:i:s")." -->";
			}
		}

		return $out;
	}

	function compress_css($in)
	{
		$exkludera = array('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '/(\n|\r|\t|\r\n|  |	)+/', '/(:|,) /', '/;}/');
		$inkludera = array('', '', '$1', '}');

		$out = preg_replace($exkludera, $inkludera, $in);

		return $out;
	}

	function compress_js($in)
	{
		$exkludera = array('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '/(\n|\r|\t|\r\n|  |	)+/');

		$out = preg_replace($exkludera, '', $in);

		return $out;
	}

	function set_cache($out)
	{
		if(strlen($out) > 0)
		{
			switch($this->suffix)
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

		if(get_option_or_default('setting_cache_debug') == 'yes')
		{
			switch($this->suffix)
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

		if(get_option('setting_cache_debug') == 'yes')
		{
			//$out .= "<!-- Test non-cached ".date("Y-m-d H:i:s")." -->";
		}

		return $out;
	}

	function delete_file($data)
	{
		if(!isset($data['time_limit'])){		$data['time_limit'] = (DAY_IN_SECONDS * 2);}
		if(!isset($data['time_limit_api'])){	$data['time_limit_api'] = HOUR_IN_SECONDS;}

		if(file_exists($data['file']))
		{
			$time_now = time();
			$time_file = filemtime($data['file']);
			$suffix_file = get_file_suffix($data['file'], true);

			if($suffix_file == 'json')
			{
				if($data['time_limit_api'] == 0 || ($time_now - $time_file >= $data['time_limit_api']))
				{
					unlink($data['file']);
				}
			}

			else if($data['time_limit'] == 0 || ($time_now - $time_file >= $data['time_limit']))
			{
				unlink($data['file']);
			}
		}
	}

	function delete_empty_folder($data)
	{
		$folder = $data['path']."/".$data['child'];

		if(is_dir($folder) && is_array(scandir($folder)) && count(scandir($folder)) == 2)
		{
			rmdir($folder);
		}
	}

	function get_posts2populate()
	{
		if(class_exists('mf_theme_core'))
		{
			global $obj_theme_core;

			if(!isset($obj_theme_core))
			{
				$obj_theme_core = new mf_theme_core();
			}

			$obj_theme_core->get_public_posts(array('allow_noindex' => true));
			$this->arr_posts = $obj_theme_core->arr_public_posts;
		}

		/*else
		{
			do_log(sprintf("%s is needed for population to work properly", "MF Theme Core"));
		}*/
	}

	function populate()
	{
		$obj_microtime = new mf_microtime();

		update_option('option_cache_prepopulated', date("Y-m-d H:i:s"), 'no');

		$i = 0;

		$this->get_posts2populate();

		if(isset($this->arr_posts) && is_array($this->arr_posts))
		{
			foreach($this->arr_posts as $post_id => $post_title)
			{
				if($i == 0)
				{
					$obj_microtime->save_now();
				}

				get_url_content(array('url' => get_permalink($post_id)));

				if($i == 0)
				{
					$microtime_old = $obj_microtime->now;

					$obj_microtime->save_now();

					update_option('option_cache_prepopulated_one', ($obj_microtime->now - $microtime_old), 'no');
				}

				$i++;

				sleep(0.1);
				set_time_limit(60);
			}

			$obj_microtime->save_now();

			$length_sec = $obj_microtime->now - $obj_microtime->time_orig;
			$length_min = round($length_sec / 60);

			update_option('option_cache_prepopulated_total', $length_sec, 'no');
			update_option('option_cache_prepopulated', date("Y-m-d H:i:s"), 'no');

			if($length_min >= 10)
			{
				update_option('setting_cache_prepopulate', 'no');

				do_log("Prepopulation was inactivated because it took ".$length_min." minutes to run");
			}
		}
	}
}