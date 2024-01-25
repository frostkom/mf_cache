<?php

class mf_cache
{
	var $post_type = 'mf_cache';
	var $meta_prefix = '';
	var $upload_path = "";
	var $upload_url = "";
	var $clean_url = "";
	var $clean_url_orig = "";
	var $site_url = "";
	var $site_url_clean = "";
	var $arr_styles = array();
	var $arr_scripts = array();
	var $file_name_xtra = "";
	var $allow_logged_in = false;
	var $dir2create = "";
	var $file_address = "";
	var $suffix = "";
	var $style_errors = "";
	var $arr_resource = array();
	var $http_host = "";
	var $request_uri = "";
	var $print_styles_run = "";
	var $public_cache = "";
	var $script_errors = "";
	var $print_scripts_run = "";
	var $file_amount = "";
	var $file_amount_old = "";
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
					$this->clear();

					if($this->file_amount == 0)
					{
						$this->populate();
					}
				}

				else
				{
					$setting_cache_api_expires = get_site_option_or_default('setting_cache_api_expires', 15);
					$setting_cache_admin_expires = get_site_option_or_default('setting_cache_admin_expires', 0);

					$this->clear(array(
						'time_limit' => (HOUR_IN_SECONDS * $setting_cache_expires),
						'time_limit_api' => (MINUTE_IN_SECONDS * $setting_cache_api_expires),
						'time_limit_admin' => ($setting_cache_admin_expires > 0 ? (MINUTE_IN_SECONDS * $setting_cache_admin_expires) : 0),
					));
				}
				########################

				// Individual expiry
				########################
				$this->get_posts2populate();

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
				}
				########################
			}

			else if(get_option('setting_activate_compress') != 'yes')
			{
				$this->clear();
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
		}

		else
		{
			//delete_option('setting_appcache_pages_url');
			delete_option('option_cache_prepopulated');
		}

		if($setting_activate_cache != 'yes')
		{
			$arr_settings['setting_activate_compress'] = __("Compress and Merge", 'lang_cache');
		}

		show_settings_fields(array('area' => $options_area, 'object' => $this, 'settings' => $arr_settings));

		// Advanced
		############################
		if($setting_activate_cache == 'yes')
		{
			$options_area = $options_area_orig."_advanced";

			add_settings_section($options_area, "", array($this, $options_area."_callback"), BASE_OPTIONS_PAGE);

			$arr_settings = array();

			if($this->public_cache == true && is_plugin_active("mf_theme_core/index.php"))
			{
				$arr_settings['setting_cache_prepopulate'] = __("Prepopulate", 'lang_cache');
			}

			$arr_settings['setting_cache_api_expires'] = __("API Expires", 'lang_cache');
			$arr_settings['setting_cache_admin_expires'] = __("Admin Expires", 'lang_cache');

			if(get_option('setting_cache_admin_expires') > 0)
			{
				$arr_settings['setting_cache_admin_group_by'] = "- ".__("Group by", 'lang_cache');
				$arr_settings['setting_cache_admin_pages'] = "- ".__("Pages", 'lang_cache');
			}

			$arr_settings['setting_appcache_activate'] = __("Activate AppCache", 'lang_cache');
			$arr_settings['setting_cache_js_cache'] = __("Activate Javascript Cache", 'lang_cache');

			if(get_option('setting_cache_js_cache') == 'yes')
			{
				$arr_settings['setting_cache_js_cache_pages'] = "- ".__("Pages", 'lang_cache');
				$arr_settings['setting_cache_js_cache_timeout'] = "- ".__("Timeout", 'lang_cache');
			}

			$arr_settings['setting_cache_debug'] = __("Debug", 'lang_cache');

			show_settings_fields(array('area' => $options_area, 'object' => $this, 'settings' => $arr_settings));
		}
		############################
	}

	function settings_cache_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);

		echo settings_header($setting_key, __("Cache", 'lang_cache'));
	}

		function setting_activate_compress_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option_or_default($setting_key, 'no');

			echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option, 'suffix' => __("This will gather styles and scripts into one file each for faster delivery", 'lang_cache')));
		}

		function setting_activate_cache_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option_or_default($setting_key, 'no');

			echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));

			/*if($option == 'yes' && $this->public_cache == true)
			{
				get_file_info(array('path' => get_home_path(), 'callback' => array($this, 'check_htaccess'), 'allow_depth' => false));
			}*/

			$file_amount = $file_amount_all = $this->count_files();

			if(IS_SUPER_ADMIN) // && is_multisite() // Removed because there might be other folders in the cache folder even on non-MultSites
			{
				$file_amount_all = $this->count_files(array('type' => 'all'));
			}

			if($file_amount > 0 || $file_amount_all > 0)
			{
				if(IS_SUPER_ADMIN && is_multisite())
				{
					$cache_debug_text = sprintf(__("%d cached files for this site and %d for all sites in the network", 'lang_cache'), $file_amount, $file_amount_all);
				}

				else
				{
					if($file_amount_all > $file_amount)
					{
						$cache_debug_text = sprintf(__("%d cached files for this site and %d in other cache folders", 'lang_cache'), $file_amount, $file_amount_all);
					}

					else
					{
						$cache_debug_text = sprintf(__("%d cached files", 'lang_cache'), $file_amount);
					}
				}

				if($this->file_amount_date_first > DEFAULT_DATE)
				{
					$cache_debug_text .= " (".format_date($this->file_amount_date_first);

						if($this->file_amount_date_last > $this->file_amount_date_first && format_date($this->file_amount_date_last) != format_date($this->file_amount_date_first))
						{
							$cache_debug_text .= " - ".format_date($this->file_amount_date_last);
						}

					$cache_debug_text .= ")";
				}

				echo "<div class='form_button'>";

					if($file_amount > 0)
					{
						echo show_button(array('type' => 'button', 'name' => 'btnCacheClear', 'text' => __("Clear", 'lang_cache'), 'class' => 'button-secondary'));

						if(IS_SUPER_ADMIN)
						{
							echo show_button(array('type' => 'button', 'name' => 'btnCacheArchive', 'text' => __("Archive", 'lang_cache'), 'class' => 'button-secondary'));
						}
					}

					if(IS_SUPER_ADMIN && $file_amount_all > $file_amount)
					{
						echo show_button(array('type' => 'button', 'name' => 'btnCacheClearAll', 'text' => __("Clear All Sites", 'lang_cache'), 'class' => 'button-secondary'));
					}

				echo "</div>
				<div id='cache_debug'>".$cache_debug_text."</div>";
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

	function settings_cache_advanced_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);

		echo settings_header($setting_key, __("Cache", 'lang_cache')." - ".__("Advanced", 'lang_cache'));
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

		function setting_cache_admin_expires_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option($setting_key);

			$setting_max = get_site_option_or_default('setting_cache_expires', 24) * 60;

			echo show_textfield(array('type' => 'number', 'name' => $setting_key, 'value' => $option, 'xtra' => "min='0' max='".($setting_max > 0 ? $setting_max : 60)."'", 'suffix' => __("minutes", 'lang_cache')));
		}

		function setting_cache_admin_group_by_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option($setting_key, 'role');

			$arr_data = array(
				'all' => __("All", 'lang_cache'),
				'role' => __("Role", 'lang_cache'),
				'user' => __("User", 'lang_cache'),
			);

			echo show_select(array('data' => $arr_data, 'name' => $setting_key, 'value' => $option));
		}

		function setting_cache_admin_pages_callback()
		{
			global $menu, $submenu;

			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option($setting_key);

			$arr_data = $arr_parent_items = array();

			if(count($menu) > 0)
			{
				if(!in_array('profile.php', $menu))
				{
					$menu[71] = array(
						0 => __("Profile", 'lang_cache'),
						1 => 'read',
						2 => 'profile.php',
					);
				}

				foreach($menu as $item)
				{
					if($item[0] != '')
					{
						$update_count = get_match("/(\<span.*\<\/span\>)/is", $item[0], false);
						$item_name = trim(strip_tags(str_replace($update_count, "", $item[0])));

						if($item_name != '')
						{
							$item_capability = $item[1];
							$item_url = $item[2];

							$item_key = $item_url.'|'.$item_name;

							if(!(is_array($option) && count($option) > 0 && isset($option[$item_key])))
							{
								$arr_data[$item_url] = $item_name;

								if(isset($submenu[$item_url]) && is_array($submenu[$item_url]))
								{
									foreach($submenu[$item_url] as $subkey => $subitem)
									{
										$subitem_name = trim(strip_tags($subitem[0]));

										if($subitem_name != '')
										{
											$subitem_url = $subitem[2];

											if($subitem_url != $item_url)
											{
												$subitem_key = $item_url.'|'.$subitem_url.'|'.$subitem_name;
												$subitem_capability = $subitem[1];

												$arr_data[$subitem_url] = " - ".$subitem_name;
											}
										}
									}
								}
							}
						}
					}
				}

				echo show_select(array('data' => $arr_data, 'name' => $setting_key.'[]', 'value' => $option));
			}
		}

		function setting_appcache_activate_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option_or_default($setting_key, 'no');

			echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));
		}

		function setting_cache_js_cache_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option_or_default($setting_key, 'no');

			echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));
		}

		function setting_cache_js_cache_pages_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option_or_default($setting_key, array());

			$arr_data = array();
			get_post_children(array('add_choose_here' => true), $arr_data);

			echo show_select(array('data' => $arr_data, 'name' => $setting_key."[]", 'value' => $option));
		}

		function setting_cache_js_cache_timeout_callback()
		{
			$setting_key = get_setting_key(__FUNCTION__);
			$option = get_option_or_default($setting_key, 3);

			echo show_textfield(array('type' => 'number', 'name' => $setting_key, 'value' => $option, 'xtra' => "min='1' max='10'", 'suffix' => __("s", 'lang_cache')));
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
		global $pagenow;

		$setting_cache_admin_pages = get_option('setting_cache_admin_pages');

		if(is_array($setting_cache_admin_pages) && count($setting_cache_admin_pages) > 0)
		{
			$page = check_var('page');

			if(in_array($pagenow, $setting_cache_admin_pages) || $page != '' && in_array($page, $setting_cache_admin_pages))
			{
				$this->fetch_request();
				$this->get_or_set_file_content(array('suffix' => 'html', 'allow_logged_in' => true));
			}
		}

		$plugin_include_url = plugin_dir_url(__FILE__);
		$plugin_version = get_plugin_version(__FILE__);

		mf_enqueue_script('script_cache_wp', $plugin_include_url."script_wp.js", array('plugin_url' => $plugin_include_url, 'ajax_url' => admin_url('admin-ajax.php')), $plugin_version);
	}

	function wp_head()
	{
		global $post;

		$arr_script_data = array('js_cache' => 'no');

		if(is_user_logged_in() == false)
		{
			$arr_script_data['js_cache'] = get_option('setting_cache_js_cache', 'no');

			if($arr_script_data['js_cache'] == 'yes')
			{
				$setting_cache_js_cache_pages = get_option('setting_cache_js_cache_pages', array());
				$setting_cache_js_cache_timeout = get_option('setting_cache_js_cache_timeout', 3);

				if(!(count($setting_cache_js_cache_pages) > 0 && isset($post->ID) && in_array($post->ID, $setting_cache_js_cache_pages)))
				{
					$arr_script_data['js_cache'] = 'no';
				}
			}
		}

		if(get_option('setting_activate_cache') == 'yes' && (apply_filters('is_theme_active', false) || $arr_script_data['js_cache'] == 'yes'))
		{
			$plugin_include_url = plugin_dir_url(__FILE__);
			$plugin_version = get_plugin_version(__FILE__);

			if($arr_script_data['js_cache'] == 'yes')
			{
				$arr_script_data['js_cache_timeout'] = ($setting_cache_js_cache_timeout * 1000);
				$arr_script_data['plugin_url'] = $plugin_include_url;
			}

			if($arr_script_data['js_cache'] == 'yes')
			{
				mf_enqueue_script('script_cache', $plugin_include_url."script.js", $arr_script_data, $plugin_version);
			}

			if(get_option('setting_appcache_activate') == 'yes') // && count(get_option('setting_appcache_pages_url')) > 0
			{
				echo "<meta name='apple-mobile-web-app-capable' content='yes'>
				<meta name='mobile-web-app-capable' content='yes'>";
			}
		}
	}

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
			$file_api_expires = $setting_cache_api_expires > 0 ? "modification plus ".$setting_cache_api_expires." ".($setting_cache_api_expires > 1 ? "minutes" : "minute") : "";

			$cache_file_path = str_replace(ABSPATH, "", WP_CONTENT_DIR)."/uploads/mf_cache/%{SERVER_NAME}%{ENV:FILTERED_REQUEST}";
			$cache_logged_in_file_path = str_replace(ABSPATH, "", WP_CONTENT_DIR)."/uploads/mf_cache/logged_in/%{SERVER_NAME}%{ENV:FILTERED_REQUEST}";

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

					$default_expires_seconds = (MONTH_IN_SECONDS * $default_expires_months); //30 * 24 * 60 * 60
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

					/*<IfModule mod_gzip.c>
						mod_gzip_on Yes
						mod_gzip_dechunk Yes
						mod_gzip_item_include file \.(html?|txt|css|js|php)$
						mod_gzip_item_include handler ^cgi-script$
						mod_gzip_item_include mime ^text/.*
						mod_gzip_item_include mime ^application/x-javascript.*
						mod_gzip_item_exclude mime ^image/.*
						mod_gzip_item_exclude rspheader ^Content-Encoding:.*gzip.*
					</IfModule>*/

					if(1 == 2)
					{
						$update_with .= "\r\n"
						."\r\n<IfModule mod_headers.c>\r\n"
						."	RewriteCond %{REQUEST_URI} !^.*[^/]$\r\n"
						."	RewriteCond %{REQUEST_URI} !^.*//.*$\r\n"
						."	RewriteCond %{REQUEST_METHOD} !POST\r\n"
						."	RewriteCond %{HTTP:Cookie} !^.*(comment_author_|wordpress_logged_in|wp-postpass_).*$\r\n"
						."	RewriteCond '%{HTTP:Accept-encoding}' 'gzip'\r\n"
						."	RewriteCond %{DOCUMENT_ROOT}/wp-content/uploads/mf_cache/%{SERVER_NAME}%{ENV:FILTERED_REQUEST}index.html.gz -f\r\n"
						."	RewriteRule ^(.*) 'wp-content/uploads/mf_cache/%{SERVER_NAME}%{ENV:FILTERED_REQUEST}index.html.gz' [L]\r\n"
						."\r\n"
						."	# Serve gzip compressed CSS files if they exist and the client accepts gzip.\r\n"
						."	RewriteCond '%{HTTP:Accept-encoding}' 'gzip'\r\n"
						."	RewriteCond '%{REQUEST_FILENAME}\.gz' -s\r\n"
						."	RewriteRule '^(.*)\.css' '$1\.css\.gz' [QSA]\r\n"
						."\r\n"
						."	# Serve gzip compressed JS files if they exist and the client accepts gzip.\r\n"
						."	RewriteCond '%{HTTP:Accept-encoding}' 'gzip'\r\n"
						."	RewriteCond '%{REQUEST_FILENAME}\.gz' -s\r\n"
						."	RewriteRule '^(.*)\.js' '$1\.js\.gz' [QSA]\r\n"
						."\r\n"
						."	# Serve correct content types, and prevent mod_deflate double gzip.\r\n"
						."	RewriteRule '\.css\.gz$' '-' [T=text/css,E=no-gzip:1]\r\n"
						."	RewriteRule '\.js\.gz$' '-' [T=text/javascript,E=no-gzip:1]\r\n"
						."\r\n"
						."	<FilesMatch '(\.js\.gz|\.css\.gz)$'>\r\n"
						."		# Serve correct encoding type.\r\n"
						."		Header append Content-Encoding gzip\r\n"
						."\r\n"
						."		# Force proxies to cache gzipped & non-gzipped css/js files separately.\r\n"
						."		Header append Vary Accept-Encoding\r\n"
						."	</FilesMatch>\r\n"
						."</IfModule>";
					}

					if(1 == 2) // && get_option('setting_activate_cache_logged_in') == 'yes'
					{
						$update_with .= "\r\n"
						."\r\nRewriteCond %{REQUEST_URI} !^.*[^/]$\r\n"
						."RewriteCond %{REQUEST_URI} !^.*//.*$\r\n"
						."RewriteCond %{REQUEST_METHOD} !POST\r\n"
						."RewriteCond %{HTTP:Cookie} ^.*(wordpress_logged_in).*$\r\n"
						."RewriteCond %{DOCUMENT_ROOT}/".$cache_logged_in_file_path."index.html -f\r\n"
						."RewriteRule ^(.*) '".$cache_logged_in_file_path."index.html' [L]\r\n"
						."\r\n"
						."RewriteCond %{REQUEST_URI} !^.*[^/]$\r\n"
						."RewriteCond %{REQUEST_URI} !^.*//.*$\r\n"
						."RewriteCond %{REQUEST_METHOD} !POST\r\n"
						."RewriteCond %{HTTP:Cookie} ^.*(wordpress_logged_in).*$\r\n"
						."RewriteCond %{DOCUMENT_ROOT}/".$cache_logged_in_file_path."index.json -f\r\n"
						."RewriteRule ^(.*) '".$cache_logged_in_file_path."index.json' [L]";
					}
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

	/*function language_attributes($html)
	{
		if(get_option('setting_activate_cache') == 'yes' && get_option('setting_appcache_activate') == 'yes' && count(get_option('setting_appcache_pages_url')) > 0)
		{
			$html .= " manifest='".$this->site_url."/wp-content/plugins/mf_cache/include/manifest.appcache.php'";
		}

		return $html;
	}*/

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

		if(IS_ADMINISTRATOR && $this->count_files() > 0)
		{
			if(!isset($obj_base))
			{
				$obj_base = new mf_base();
			}

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

	function rwmb_meta_boxes($meta_boxes)
	{
		if(function_exists('is_plugin_active') && is_plugin_active("mf_theme_core/index.php") && get_option('setting_activate_cache') == 'yes' && get_site_option('setting_cache_expires') > 0)
		{
			$setting_cache_expires = get_site_option('setting_cache_expires');

			$meta_boxes[] = array(
				'id' => $this->meta_prefix.'cache',
				'title' => __("Cache", 'lang_cache'),
				'post_types' => array('page', 'post'),
				'context' => 'side',
				'priority' => 'low',
				'fields' => array(
					array(
						'name' => __("Expires", 'lang_cache')." (".__("minutes", 'lang_cache').")",
						'id' => $this->meta_prefix.'expires',
						'type' => 'number',
						'attributes' => array(
							'min' => -1,
							'max' => ($setting_cache_expires * 60),
						),
						'desc' => sprintf(__("Overrides the default value (if less than %s). -1 = inactivated on this page", 'lang_cache'), $setting_cache_expires." ".__("hours", 'lang_cache')),
					),
				)
			);
		}

		return $meta_boxes;
	}

	function check_page_expiry()
	{
		$result = array();

		$out = "";

		$this->get_posts2populate();

		$arr_posts_with_expiry = array();

		if(isset($this->arr_posts) && is_array($this->arr_posts))
		{
			foreach($this->arr_posts as $post_id => $post_title)
			{
				$post_expires = get_post_meta($post_id, $this->meta_prefix.'expires', true);

				if($post_expires != '' && ($post_expires > 0 || $post_expires < 0))
				{
					$arr_posts_with_expiry[$post_id] = array('title' => $post_title." (".sprintf(__("%s min", 'lang_cache'), $post_expires).")", 'expires' => $post_expires);
				}
			}
		}

		if(count($arr_posts_with_expiry) > 0)
		{
			$out .= "<h4>".__("Exceptions", 'lang_cache')." <a href='".admin_url("edit.php?post_type=page")."'><i class='fa fa-plus-circle fa-lg'></i></a></h4>
			<table class='widefat striped'>";

				foreach($arr_posts_with_expiry as $post_id => $post)
				{
					$out .= "<tr>
						<td><a href='".admin_url("post.php?post=".$post_id."&action=edit")."'>".$post['title']."</a></td>
						<td><a href='".get_permalink($post_id)."'><i class='fa fa-link fa-lg'></i></a></td>
						<td>";

							if($post['expires'] > 0)
							{
								$out .= $post['expires']." ".__("minutes", 'lang_cache');
							}

							else
							{
								$out .= __("Inactivated", 'lang_cache');
							}

						$out .= "</td>
					</tr>";
				}

			$out .= "</table>";
		}

		else
		{
			$page_on_front = get_option('page_on_front');

			if($page_on_front > 0)
			{
				$out .= "<p><em>".sprintf(__("You can override the default value on individual pages, for example on the %shome page%s by editing and scrolling down to Cache in the right column", 'lang_cache'), "<a href='".admin_url("post.php?post=".$page_on_front."&action=edit")."'>", "</a>")."</em></p>";
			}
		}

		if($out != '')
		{
			$result['success'] = true;
			$result['message'] = $out;
		}

		else
		{
			$result['success'] = false;
			$result['error'] = "";
		}

		header('Content-Type: application/json');
		echo json_encode($result);
		die();
	}

	function clear_cache()
	{
		global $done_text, $error_text;

		$result = array();

		// Needs to init a new object to work properly
		$obj_cache = new mf_cache();

		$obj_cache->count_files();
		$obj_cache->file_amount_old = $obj_cache->file_amount;

		$obj_cache->clear();

		if($obj_cache->file_amount == 0 || $obj_cache->file_amount < $obj_cache->file_amount_old)
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

	function archive_cache()
	{
		global $done_text, $error_text;

		$result = array();

		// Needs to init a new object to work properly
		$obj_cache = new mf_cache();

		if($obj_cache->do_archive())
		{
			delete_option('option_cache_prepopulated');

			$done_text = __("I successfully archived the cache for you", 'lang_cache');
		}

		else
		{
			$error_text = __("I could not archive the cache. Please make sure that the credentials are correct", 'lang_cache');
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
			$obj_cache->clean_url = "";

			$obj_cache->count_files();
			$obj_cache->file_amount_old = $obj_cache->file_amount;

			$obj_cache->clear();

			if($obj_cache->file_amount == 0 || $obj_cache->file_amount < $obj_cache->file_amount_old)
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

		$obj_cache->count_files();
		$obj_cache->file_amount_old = $obj_cache->file_amount;

		$obj_cache->clear();

		$after_clear = $this->file_amount;

		if($obj_cache->file_amount == 0 || $obj_cache->file_amount < $obj_cache->file_amount_old)
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

		//$site_url = get_site_url();

		list($content, $headers) = get_url_content(array('url' => $this->site_url, 'catch_head' => true));
		$time_1st = $headers['total_time'];

		if(preg_match("/\<\!\-\- Dynamic /i", $content))
		{
			list($content, $headers) = get_url_content(array('url' => $this->site_url, 'catch_head' => true));
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

	function clear_admin_cache($url)
	{
		$this->clean_url = remove_protocol(array('url' => admin_url(), 'clean' => true));
		$this->folder2clear = $url;

		$admin_clean_url = $this->upload_path.trim(remove_protocol(array('url' => admin_url(), 'clean' => true)), "/");
		get_file_info(array('path' => $admin_clean_url, 'folder_callback' => array($this, 'clear_folder')));
	}

	function clear_user_cache()
	{
		$this->clear_admin_cache("users.php");
	}

	function should_load_as_url()
	{
		if(substr($this->arr_resource['file'], 0, 3) == "/wp-")
		{
			$this->arr_resource['file'] = $this->site_url.$this->arr_resource['file'];
		}

		$this->arr_resource['file'] = validate_url($this->arr_resource['file'], false);

		/*else if(substr(remove_protocol(array('url' => $this->arr_resource['file'])), 0, strlen($this->site_url_clean)) != $this->site_url_clean)
		{
			$this->arr_resource['type'] = 'external';
		}*/

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
		if(!isset($this->print_styles_run))
		{
			global $error_text;

			$file_url_base = $this->site_url."/wp-content";
			$file_dir_base = WP_CONTENT_DIR;

			// Does not work in files where relative URLs to images or fonts are used
			#####################
			/*global $wp_styles;

			//do_log("Styles: ".var_export($wp_styles, true));

			foreach($wp_styles->queue as $style)
			{
				if(isset($wp_styles->registered[$style]))
				{
					$handle = $wp_styles->registered[$style]->handle;
					$src = $wp_styles->registered[$style]->src;
					$data = (isset($wp_styles->registered[$style]->extra['data']) ? $wp_styles->registered[$style]->extra['data'] : '');
					$ver = $wp_styles->registered[$style]->ver;

					if(!isset($this->arr_styles[$handle]))
					{
						$this->arr_styles[$handle] = array(
							'source' => 'unknown',
							'type' => $this->get_type($src),
							'file' => $src,
							'version' => $ver,
						);
					}
				}
			}

			//do_log("Styles: ".var_export($this->arr_styles, true));*/
			#####################

			if(count($this->arr_styles) > 0)
			{
				$version = 0;
				$output = ""; //$this->style_errors = 

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
						/*if(get_current_visitor_ip() == "")
						{
							list($content, $headers) = get_url_content(array('url' => $this->arr_resource['file'], 'catch_head' => true));

							$fetch_type = "php_".$headers['http_code'];

							do_log("Fetched (".$fetch_type."): ".$this->arr_resource['file']." -> ".$headers['http_code']." -> ".strlen($content));

							if($headers['http_code'] != 200)
							{
								$content = "";
							}
						}

						else
						{*/
							$fetch_type = "php";

							ob_start();

								$resource_file_path = str_replace($file_url_base, $file_dir_base, $this->arr_resource['file']);

								include($resource_file_path);

							$content = ob_get_clean();

							/*if(get_current_visitor_ip() == "")
							{
								do_log("Fetched (".$fetch_type."): ".$this->arr_resource['file']." -> ".str_replace($file_url_base, $file_dir_base, $this->arr_resource['file'])." -> ".strlen($content));
							}
						}*/
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
							//." (".$file_url_base." vs. ".$file_dir_base.")"
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

						/*if($success && function_exists('gzencode'))
						{
							$success = set_file_content(array('file' => $upload_path.$file.".gz", 'mode' => 'w', 'content' => gzencode($output)));
						}*/

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

			/*if($success && function_exists('gzencode'))
			{
				$success = set_file_content(array('file' => $upload_path.$data['filename'].".gz", 'mode' => 'w', 'content' => gzencode($data['content'])));
			}*/

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
		if(!isset($this->print_scripts_run))
		{
			$setting_merge_js_type = array('known_internal', 'known_external'); //, 'unknown_internal', 'unknown_external'

			$file_url_base = $this->site_url."/wp-content";
			$file_dir_base = WP_CONTENT_DIR;

			// Does not work in files where relative URLs to images or fonts are used
			#####################
			/*global $wp_scripts;

			foreach($wp_scripts->queue as $script)
			{
				if(isset($wp_scripts->registered[$script]))
				{
					$handle = $wp_scripts->registered[$script]->handle;
					$src = $wp_scripts->registered[$script]->src;
					$data = (isset($wp_scripts->registered[$script]->extra['data']) ? $wp_scripts->registered[$script]->extra['data'] : '');
					$ver = $wp_scripts->registered[$script]->ver;

					if(!isset($this->arr_scripts[$handle]))
					{
						if(substr($src, 0, 3) == "/wp-")
						{
							$src = $this->site_url.$src;
						}

						$this->arr_scripts[$handle] = array(
							'source' => 'unknown',
							'type' => $this->get_type($src),
							'file' => $src,
							//'translation' => $translation,
							'extra' => $data,
							'version' => $ver,
						);
					}
				}
			}

			//do_log("Scripts: ".var_export($this->arr_scripts, true));*/
			#####################

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
		//$tag = str_replace(" src", " async src", $tag); //defer

		return $tag;
	}

	function is_password_protected()
	{
		global $post;

		return apply_filters('filter_is_password_protected', (isset($post->post_password) && $post->post_password != ''), array('post_id' => (isset($post->ID) ? $post->ID : 0), 'check_login' => false));
	}

	function create_dir()
	{
		$this->dir2create = str_replace(array(".", "?", "="), "-", strtolower($this->upload_path.trim($this->clean_url, "/")));

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
				//'wp-content/uploads', // This will ignore all caching
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
				if(!@is_dir($this->dir2create)) // && !preg_match("/\?/", $this->dir2create) // Won't work with Webshop/JSON
				{
					if(strlen($this->dir2create) > 256 || !@mkdir($this->dir2create, 0755, true))
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

		if($this->allow_logged_in == true && is_user_logged_in() == true)
		{
			$setting_cache_admin_group_by = get_option('setting_cache_admin_group_by');

			switch($setting_cache_admin_group_by)
			{
				default:
				case 'all':
					// Do nothing
				break;

				case 'role':
					if(IS_SUPER_ADMIN)
					{
						$this->file_name_xtra .= "_super_admin";
					}

					else if(IS_ADMINISTRATOR)
					{
						$this->file_name_xtra .= "_admin";
					}

					else if(IS_EDITOR)
					{
						$this->file_name_xtra .= "_editor";
					}

					else
					{
						$this->file_name_xtra .= "_".get_current_user_id();
					}
				break;

				case 'user':
					$this->file_name_xtra .= "_".get_current_user_id();
				break;
			}
		}

		if($this->create_dir())
		{
			$this->file_address = $this->dir2create."/index".$this->file_name_xtra.".".$this->suffix;
		}

		else if(@is_dir($this->upload_path.$this->http_host))
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

		$this->suffix = $data['suffix'];
		$this->allow_logged_in = $data['allow_logged_in'];
		$this->file_name_xtra = ($data['file_name_xtra'] != '' ? "_".$data['file_name_xtra'] : '');

		// It is important that is_user_logged_in() is checked here so that it never is saved as a logged in user. This will potentially mean that the admin bar will end up in the cached version of the site
		if(get_option('setting_activate_cache') == 'yes' && ($this->allow_logged_in == true || is_user_logged_in() == false))
		{
			$this->parse_file_address();

			if($this->file_address != '' && strlen($this->file_address) <= 255)
			{
				// We can never allow getting a previous cache if there is a POST present, this would mess up actions like login that is supposed to do something with the POST variables
				if(count($_POST) == 0 && file_exists(realpath($this->file_address)) && @filesize($this->file_address) > 0)
				{
					$out = $this->get_cache();

					echo $out;

					/*if(get_option_or_default('setting_cache_debug') == 'yes')
					{
						$out .= "<!-- Test cached ".date("Y-m-d H:i:s")." -->";
					}*/

					exit;
				}

				else
				{
					ob_start(array($this, 'set_cache'));
				}
			}

			else if(get_option_or_default('setting_cache_debug') == 'yes')
			{
				echo "<!-- No cache address ".date("Y-m-d H:i:s")." -->";
				//do_log("No file address (".$this->file_address.")");
			}
		}

		else if(get_option_or_default('setting_cache_debug') == 'yes')
		{
			echo "<!-- Cache not allowed ".date("Y-m-d H:i:s")." -->";
			//do_log("Not allowed (".$this->file_address.", ".$this->allow_logged_in.", ".is_user_logged_in().")");
		}
	}

	function get_cache()
	{
		$out = get_file_content(array('file' => $this->file_address));

		if(get_option_or_default('setting_cache_debug') == 'yes')
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

		// This will remove all height/width from img/iframe etc. which we do not want
		//$exclude[] = "/(width|height)=[\"\']\d*[\"\']\s/";	$include[] = '';

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

			/*else if(apply_filters('filter_deny_before_set_cache', false, $this->file_address) == true)
			{
				$type = 'denied';
			}*/

			else
			{
				$success = set_file_content(array('file' => $this->file_address, 'mode' => 'w', 'content' => $out, 'log' => false));

				/*if($success && function_exists('gzencode'))
				{
					$success = set_file_content(array('file' => $this->file_address.".gz", 'mode' => 'w', 'content' => gzencode($out."<!-- gzip -->"), 'log' => false));
				}*/

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

		/*if(get_option_or_default('setting_cache_debug') == 'yes')
		{
			$out .= "<!-- Test non-cached ".date("Y-m-d H:i:s")." -->";
		}*/

		return $out;
	}

	function gather_count_files($data)
	{
		$this->file_amount++;

		$file_date_time = date("Y-m-d H:i:s", @filemtime($data['file']));

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
		if(!isset($data['type'])){		$data['type'] = 'current';}

		switch($data['type'])
		{
			default:
			case 'current':
				$upload_path_site = $this->upload_path.trim($this->clean_url_orig, "/");
			break;

			case 'all':
				$upload_path_site = $this->upload_path;
			break;
		}

		$this->file_amount = 0;
		$this->file_amount_date_first = $this->file_amount_date_last = "";
		get_file_info(array('path' => $upload_path_site, 'callback' => array($this, 'gather_count_files')));

		return $this->file_amount;
	}

	function delete_file($data)
	{
		if(!isset($data['time_limit'])){		$data['time_limit'] = (DAY_IN_SECONDS * 2);}
		if(!isset($data['time_limit_api'])){	$data['time_limit_api'] = HOUR_IN_SECONDS;}
		if(!isset($data['time_limit_admin'])){	$data['time_limit_admin'] = HOUR_IN_SECONDS;}

		$time_now = time();
		$time_file = @filemtime($data['file']);
		$suffix_file = get_file_suffix($data['file'], true);

		switch($suffix_file)
		{
			case 'css':
			case 'js':
				// The HTML might have been saved at a later time than the JS/CSS, therefor we have to take this in consideration and let those files hang around a bit longer
				$data['time_limit'] *= 2;
				$data['time_limit_admin'] * 2;
			break;
		}

		if($suffix_file == 'json')
		{
			if($data['time_limit_api'] == 0 || ($time_now - $time_file >= $data['time_limit_api']))
			{
				@unlink($data['file']);
			}
		}

		else if($data['time_limit'] == 0 || ($time_now - $time_file >= $data['time_limit']))
		{
			@unlink($data['file']);
		}

		else if(strpos("/wp-admin/", $data['file']) !== false && ($data['time_limit_admin'] == 0 || ($time_now - $time_file >= $data['time_limit_admin'])))
		{
			@unlink($data['file']);
		}
	}

	function delete_empty_folder($data)
	{
		$folder = $data['path']."/".$data['child'];

		if(is_dir($folder) && is_array(scandir($folder)) && count(scandir($folder)) == 2)
		{
			@rmdir($folder);
		}
	}

	function clear($data = array())
	{
		if(!isset($data['time_limit'])){		$data['time_limit'] = 0;}
		if(!isset($data['time_limit_api'])){	$data['time_limit_api'] = ($data['time_limit'] * 60);}
		if(!isset($data['allow_depth'])){		$data['allow_depth'] = true;}

		if($this->count_files() > 0)
		{
			$upload_path_site = $this->upload_path.trim($this->clean_url, "/");

			$data_temp = $data;
			$data_temp['path'] = $upload_path_site;
			$data_temp['callback'] = array($this, 'delete_file');
			$data_temp['folder_callback'] = array($this, 'delete_empty_folder');

			get_file_info($data_temp);

			$this->count_files();
		}
	}

	function do_archive()
	{
		$upload_path_site = $this->upload_path.trim($this->clean_url, "/");
		$upload_path_archive = $this->upload_path.trim($this->clean_url, "/")."_".date("YmdHis");

		if(rename($upload_path_site, $upload_path_archive))
		{
			return true;
		}
	}

	function get_posts2populate()
	{
		if(class_exists('mf_theme_core'))
		{
			$obj_theme_core = new mf_theme_core();

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

				sleep(1);
				@set_time_limit(60);
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

			//$this->update_appcache_urls();
		}
	}

	/*function update_appcache_urls()
	{
		$arr_urls = array();

		$arr_urls[md5($this->site_url."/")] = $this->site_url."/";

		foreach($this->arr_posts as $post_id => $post_title)
		{
			$post_url = get_permalink($post_id);

			list($content, $headers) = get_url_content(array('url' => $post_url, 'catch_head' => true));

			$arr_urls[md5($post_url)] = $post_url;

			if($content != '')
			{
				$arr_tags = get_match_all('/\<img(.*?)\>/is', $content);

				foreach($arr_tags as $tag)
				{
					$resource_url = get_match('/src=[\'"](.*?)[\'"]/is', $tag, false);

					if($resource_url != '' && substr($resource_url, 0, 2) != "//")
					{
						$arr_urls[md5($resource_url)] = $resource_url;
					}
				}

				$arr_tags = get_match_all('/\<link(.*?)\>/is', $content);

				foreach($arr_tags as $tag)
				{
					if(!preg_match("/(shortlink|dns-prefetch)/", $tag))
					{
						$resource_url = get_match('/href=[\'"](.*?)[\'"]/is', $tag, false);

						if($resource_url != '' && substr($resource_url, 0, 2) != "//")
						{
							$arr_urls[md5($resource_url)] = $resource_url;
						}

						if(preg_match('/rel=[\'"]stylesheet[\'"]/', $tag))
						{
							list($content_style, $headers) = get_url_content(array('url' => $resource_url, 'catch_head' => true));

							if($content_style != '')
							{
								$arr_style_urls = get_match_all('/url\((.*?)\)/is', $content_style, false);

								foreach($arr_style_urls[0] as $style_resource_url)
								{
									$style_resource_url = trim($style_resource_url, "'");
									$style_resource_url = trim($style_resource_url, '"');

									if(substr($style_resource_url, 0, 5) != 'data:')
									{
										$resourse_suffix = get_file_suffix($style_resource_url);

										if(!in_array($resourse_suffix, array('eot', 'woff', 'woff2')))
										{
											$arr_urls[md5($style_resource_url)] = $style_resource_url;
										}
									}
								}
							}
						}
					}
				}

				$arr_tags = get_match_all('/\<script(.*?)\>/is', $content);

				foreach($arr_tags as $tag)
				{
					$resource_url = get_match('/src=[\'"](.*?)[\'"]/is', $tag, false);

					if($resource_url != '' && substr($resource_url, 0, 2) != "//")
					{
						$arr_urls[md5($resource_url)] = $resource_url;
					}
				}
			}
		}

		update_option('setting_appcache_pages_url', $arr_urls, 'no');
	}*/
}