<?php

class mf_cache
{
	function __construct()
	{
		list($this->upload_path, $this->upload_url) = get_uploads_folder('mf_cache', true);
		$this->clean_url = get_site_url_clean(array('trim' => "/"));

		$this->site_url = get_site_url();
		$this->site_url_clean = remove_protocol(array('url' => $this->site_url));

		$this->meta_prefix = "mf_cache_";

		$this->arr_styles = $this->arr_scripts = array();

		$this->setting_activate_cache = get_option('setting_activate_cache');
		$this->setting_activate_compress = get_option('setting_activate_compress');
	}

	function run_cron()
	{
		global $globals;

		//$obj_cache = new mf_cache();

		if($this->setting_activate_cache == 'yes')
		{
			//Overall expiry
			########################
			$setting_cache_expires = get_site_option('setting_cache_expires');
			$setting_cache_api_expires = get_site_option('setting_cache_api_expires');
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
				$this->clear(array(
					'time_limit' => 60 * 60 * $setting_cache_expires,
					'time_limit_api' => 60 * $setting_cache_api_expires,
				));
			}
			########################

			//Individual expiry
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
							$this->clear(array('time_limit' => 60 * $post_expires, 'allow_depth' => false));

							if($setting_cache_prepopulate == 'yes')
							{
								get_url_content($post_url);
							}
						}
					}
				}
			}
			########################
		}
	}

	function admin_init()
	{
		$plugin_include_url = plugin_dir_url(__FILE__);
		$plugin_version = get_plugin_version(__FILE__);

		mf_enqueue_script('script_cache', $plugin_include_url."script_wp.js", array('plugin_url' => $plugin_include_url, 'ajax_url' => admin_url('admin-ajax.php')), $plugin_version);
	}

	function wp_head()
	{
		$plugin_include_url = plugin_dir_url(__FILE__);
		$plugin_version = get_plugin_version(__FILE__);

		mf_enqueue_script('script_cache', $plugin_include_url."script.js", $plugin_version);

		if(get_option('setting_appcache_activate') == 'yes' && count(get_option('setting_appcache_pages_url')) > 0) //$this->is_user_cache_allowed() && 
		{
			echo "<meta name='apple-mobile-web-app-capable' content='yes'>
			<meta name='mobile-web-app-capable' content='yes'>";
		}
	}

	function fetch_request()
	{
		$this->http_host = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : "");
		$this->request_uri = $_SERVER['REQUEST_URI'];

		$this->clean_url = $this->http_host.$this->request_uri;
	}

	function get_header()
	{
		$this->fetch_request();
		$this->get_or_set_file_content();
	}

	function language_attributes($html)
	{
		if(get_option('setting_appcache_activate') == 'yes' && count(get_option('setting_appcache_pages_url')) > 0) //$this->is_user_cache_allowed() && 
		{
			$html .= " manifest='".$this->site_url."/wp-content/plugins/mf_cache/include/manifest.appcache.php'";
		}

		return $html;
	}

	function get_type($src)
	{
		return (substr(remove_protocol(array('url' => $src)), 0, strlen($this->site_url_clean)) == $this->site_url_clean ? 'internal' : 'external');
	}

	function admin_bar()
	{
		global $wp_admin_bar;

		if(IS_ADMIN)
		{
			//$setting_activate_cache = get_option('setting_activate_cache');
			$setting_cache_expires = get_site_option('setting_cache_expires');

			if($this->setting_activate_cache == 'yes' && $setting_cache_expires > 0)
			{
				//$obj_cache = new mf_cache();

				if($this->count_files() > 0)
				{
					$wp_admin_bar->add_node(array(
						'id' => 'cache',
						'title' => "<a href='#clear_cache' class='color_red'>".__("Clear Cache", 'lang_cache')."</a>",
					));
				}
			}
		}
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
		global $error_text;

		/*if($this->is_user_cache_allowed())
		{*/
			$file_url_base = $this->site_url."/wp-content";
			$file_dir_base = WP_CONTENT_DIR;

			//Does not work in files where relative URLs to images or fonts are used
			#####################
			/*global $wp_styles;

			//do_log("Styles: ".var_export($wp_styles, true));

			foreach($wp_styles->queue as $style)
			{
				if(isset($wp_styles->registered[$style]))
				{
					$handle = $wp_styles->registered[$style]->handle;
					$src = $wp_styles->registered[$style]->src;
					$data = isset($wp_styles->registered[$style]->extra['data']) ? $wp_styles->registered[$style]->extra['data'] : "";
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
				$output = $this->errors = "";

				foreach($this->arr_styles as $handle => $this->arr_resource)
				{
					$version += point2int($this->arr_resource['version']);

					if($this->should_load_as_url())
					{
						list($content, $headers) = get_url_content($this->arr_resource['file'], true);

						if($headers['http_code'] != 200)
						{
							$content = "";
						}
					}

					else if(get_file_suffix($this->arr_resource['file']) == 'php')
					{
						ob_start();

							include_once(str_replace($file_url_base, $file_dir_base, $this->arr_resource['file']));

						$content = ob_get_clean();
					}

					else
					{
						$content = get_file_content(array('file' => str_replace($file_url_base, $file_dir_base, $this->arr_resource['file'])));
					}

					if($content != '')
					{
						$output .= $content;
					}

					else
					{
						$this->errors .= ($this->errors != '' ? "," : "").$handle;

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

						if($this->errors != '')
						{
							$error_text = sprintf(__("There were errors in '%s' when fetching style resources (%s)", 'lang_cache'), $this->errors, var_export($this->arr_styles, true));
						}

						else if($success == true)
						{
							foreach($this->arr_styles as $handle => $this->arr_resource)
							{
								wp_deregister_style($handle);
							}

							mf_enqueue_style('mf_styles', $upload_url.$file);
						}
					}

					if($error_text != '')
					{
						do_log($error_text, 'auto-draft');
					}
				}
			}
		//}
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

			if($this->errors != '')
			{
				$error_text = sprintf(__("There were errors in %s when fetching script resources (%s)", 'lang_cache'), $this->errors, var_export($this->arr_scripts, true));
			}

			else if($success == true)
			{
				if(isset($data['handle']) && $data['handle'] != '')
				{
					wp_deregister_script($data['handle']);

					wp_enqueue_script($data['handle'], $upload_url.$data['filename'], array('jquery'), null, true); //$data['version']

					unset($this->arr_scripts[$data['handle']]);
				}

				else
				{
					foreach($this->arr_scripts as $handle => $this->arr_resource)
					{
						wp_deregister_script($handle);
					}

					mf_enqueue_script('mf_scripts', $upload_url.$data['filename']);

					if(isset($data['translation']) && $data['translation'] != '')
					{
						echo "<script>".$data['translation']."</script>";
					}
				}
			}
		}

		else if($error_text != '')
		{
			do_log($error_text, 'auto-draft');
		}
	}

	function print_scripts()
	{
		/*if($this->is_user_cache_allowed())
		{*/
			$setting_merge_js_type = array('known_internal', 'known_external'); //, 'unknown_internal', 'unknown_external'

			$file_url_base = $this->site_url."/wp-content";
			$file_dir_base = WP_CONTENT_DIR;

			//Does not work in files where relative URLs to images or fonts are used
			#####################
			/*global $wp_scripts;

			foreach($wp_scripts->queue as $script)
			{
				if(isset($wp_scripts->registered[$script]))
				{
					$handle = $wp_scripts->registered[$script]->handle;
					$src = $wp_scripts->registered[$script]->src;
					$data = isset($wp_scripts->registered[$script]->extra['data']) ? $wp_scripts->registered[$script]->extra['data'] : "";
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
				$output = $translation = $this->errors = "";

				foreach($this->arr_scripts as $handle => $this->arr_resource)
				{
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

					/*else if(isset($this->arr_resource['extra']))
					{
						$translation .= $this->arr_resource['extra'];
					}*/

					$content = "";

					if($this->should_load_as_url())
					{
						if(in_array($merge_type, $setting_merge_js_type))
						{
							list($content, $headers) = get_url_content($this->arr_resource['file'], true);

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
							$this->errors .= ($this->errors != '' ? "," : "").$handle;

							unset($this->arr_scripts[$handle]);
						}
					}

					else
					{
						if(in_array($merge_type, $setting_merge_js_type))
						{
							$content = get_file_content(array('file' => str_replace($file_url_base, $file_dir_base, $this->arr_resource['file'])));
						}

						if($content != '')
						{
							$output .= $content;
						}

						else
						{
							$this->errors .= ($this->errors != '' ? "," : "").$handle;

							unset($this->arr_scripts[$handle]);
						}
					}
				}

				if($output != '')
				{
					$this->output_js(array('content' => $output, 'version' => $version, 'translation' => $translation));
				}
			}
		//}
	}

	function style_loader_tag($tag)
	{
		/*if($this->is_user_cache_allowed())
		{*/
			$tag = str_replace("  ", " ", $tag);
			$tag = str_replace(" />", ">", $tag);
			$tag = str_replace(" type='text/css'", "", $tag);
			$tag = str_replace(' type="text/css"', "", $tag);
		//}

		return $tag;
	}

	function script_loader_tag($tag)
	{
		/*if($this->is_user_cache_allowed())
		{*/
			$tag = str_replace(" type='text/javascript'", "", $tag);
			$tag = str_replace(' type="text/javascript"', "", $tag);
			//$tag = str_replace(" src", " async src", $tag); //defer
		//}

		return $tag;
	}

	function is_password_protected()
	{
		global $post;

		return isset($post->post_password) && $post->post_password != '';
	}

	function create_dir()
	{
		$this->dir2create = $this->upload_path.trim($this->clean_url, "/");

		if(!@is_dir($this->dir2create)) // && !preg_match("/\?/", $this->dir2create) //Won't work with Webshop/JSON
		{
			if(strlen($this->dir2create) > 256 || !@mkdir($this->dir2create, 0755, true))
			{
				return false;
			}
		}

		return true;
	}

	function parse_file_address()
	{
		if($this->create_dir())
		{
			$this->file_address = $this->dir2create."/index.".$this->suffix;
		}

		else if(@is_dir($this->upload_path.$this->http_host))
		{
			$this->file_address = $this->upload_path.$this->http_host."/".md5($this->request_uri).".".$this->suffix;
		}

		else
		{
			$this->file_address = ""; //$this->upload_path.$this->http_host."-".md5($this->request_uri).".".$this->suffix;
		}
	}

	/*function is_user_cache_allowed()
	{
		return !is_user_logged_in();
	}*/

	function get_or_set_file_content($suffix = 'html')
	{
		$this->suffix = $suffix;

		/* It is important that is_user_logged_in() is checked here so that it never is saved as a logged in user. This will potentially mean that the admin bar will end up in the cached version of the site */
		if($this->setting_activate_cache == 'yes' && !is_user_logged_in()) //$this->is_user_cache_allowed()
		{
			$this->parse_file_address();

			if($this->file_address != '' && strlen($this->file_address) <= 255)
			{
				if(count($_POST) == 0 && file_exists(realpath($this->file_address)) && @filesize($this->file_address) > 0)
				{
					$out = get_file_content(array('file' => $this->file_address));

					/*if($this->suffix == 'json')
					{
						do_log("Fetching JSON from ".$this->file_address);
					}*/

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

					echo $out;
					exit;
				}

				else
				{
					ob_start(array($this, 'cache_save'));
				}
			}
		}
	}

	function strip_domain($code)
	{
		if(get_option('setting_strip_domain') == 'yes')
		{
			$code = str_replace(array("http:".$this->site_url_clean, "https:".$this->site_url_clean), "", $code); //$this->site_url
		}

		return $code;
	}

	function compress_html($in)
	{
		$exkludera = array('!/\*[^*]*\*+([^/][^*]*\*+)*/!',
			'/>(\n|\r|\t|\r\n|  |	)+/',
			'/(\n|\r|\t|\r\n|  |	)+</',
			"/(width|height)=[\"\']\d*[\"\']\s/"
		);
		$inkludera = array('', '>', '<', '');

		$out = preg_replace($exkludera, $inkludera, $in);
		$out = $this->strip_domain($out);

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
		$out = $this->strip_domain($out);

		return $out;
	}

	function compress_js($in)
	{
		$exkludera = array('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '/(\n|\r|\t|\r\n|  |	)+/');

		$out = preg_replace($exkludera, '', $in);
		$out = $this->strip_domain($out);

		return $out;
	}

	function cache_save($out)
	{
		if(strlen($out) > 0 && $this->is_password_protected() == false)
		{
			switch($this->suffix)
			{
				case 'html':
					$out = $this->compress_html($out);
				break;
			}

			if(count($_POST) == 0)
			{
				$success = set_file_content(array('file' => $this->file_address, 'mode' => 'w', 'content' => $out, 'log' => false));

				/*if($success && function_exists('gzencode'))
				{
					$success = set_file_content(array('file' => $this->file_address.".gz", 'mode' => 'w', 'content' => gzencode($out."<!-- gzip -->"), 'log' => false));
				}*/

				if(get_option_or_default('setting_cache_debug') == 'yes')
				{
					switch($this->suffix)
					{
						case 'html':
							$out .= "<!-- Dynamic ".date("Y-m-d H:i:s")." -->";
						break;

						case 'json':
							$arr_out = json_decode($out, true);
							$arr_out['dynamic'] = date("Y-m-d H:i:s");
							$out = json_encode($arr_out);
						break;
					}
				}
			}
		}

		return $out;
	}

	function count_files()
	{
		global $globals;

		$upload_path_site = $this->upload_path.trim($this->clean_url, "/");

		$globals['count'] = 0;
		$globals['date_first'] = $globals['date_last'] = "";
		get_file_info(array('path' => $upload_path_site, 'callback' => "count_files"));

		$this->file_amount = $globals['count'];

		return $this->file_amount;
	}

	function delete_file($data)
	{
		if(!isset($data['time_limit'])){		$data['time_limit'] = 60 * 60 * 24 * 2;} //2 days
		if(!isset($data['time_limit_api'])){	$data['time_limit_api'] = 60 * 60 * 24;} //1 day

		$time_now = time();
		$time_file = @filemtime($data['file']);
		$suffix_file = get_file_suffix($data['file'], true);

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
	}

	function delete_folder($data)
	{
		$folder = $data['path']."/".$data['child'];

		if(is_dir($folder) && count(scandir($folder)) == 2)
		{
			rmdir($folder);
			//do_log("Deleted Folder: ".$folder);
		}
	}

	function clear($data = array())
	{
		if(!isset($data['time_limit'])){		$data['time_limit'] = 0;}
		if(!isset($data['time_limit_api'])){	$data['time_limit_api'] = ($data['time_limit'] * 60);}
		if(!isset($data['allow_depth'])){		$data['allow_depth'] = true;}

		$upload_path_site = $this->upload_path.trim($this->clean_url, "/");

		if($this->count_files() > 0)
		{
			$data_temp = $data;
			$data_temp['path'] = $upload_path_site;
			$data_temp['callback'] = array($this, 'delete_file');
			$data_temp['folder_callback'] = array($this, 'delete_folder');

			get_file_info($data_temp);

			$this->count_files();
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
			error_log(sprintf(__("%s is needed for population to work properly", 'lang_cache'), "MF Theme Core"));
		}*/
	}

	function populate()
	{
		$obj_microtime = new mf_microtime();

		update_option('option_cache_prepopulated', date("Y-m-d H:i:s"), 'no');

		$i = 0;

		$this->get_posts2populate();

		if(is_array($this->arr_posts))
		{
			foreach($this->arr_posts as $post_id => $post_title)
			{
				if($i == 0)
				{
					$obj_microtime->save_now();
				}

				get_url_content(get_permalink($post_id));

				if($i == 0)
				{
					$microtime_old = $obj_microtime->now;

					$obj_microtime->save_now();

					update_option('option_cache_prepopulated_one', $obj_microtime->now - $microtime_old, 'no');
				}

				$i++;

				/*if($i % 10 == 0)
				{*/
					sleep(1);
					set_time_limit(60);
				//}
			}

			$obj_microtime->save_now();
			update_option('option_cache_prepopulated_total', $obj_microtime->now - $obj_microtime->time_orig, 'no');
			update_option('option_cache_prepopulated', date("Y-m-d H:i:s"), 'no');

			$this->update_appcache_urls();
		}
	}

	function update_appcache_urls()
	{
		$arr_urls = array();

		$arr_urls[md5($this->site_url."/")] = $this->site_url."/";

		foreach($this->arr_posts as $post_id => $post_title)
		{
			$post_url = get_permalink($post_id);

			$content = get_url_content($post_url);

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
							$content_style = get_url_content($resource_url);

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
	}
}