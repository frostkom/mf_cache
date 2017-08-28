<?php

class mf_cache
{
	function __construct()
	{
		list($this->upload_path, $this->upload_url) = get_uploads_folder('mf_cache', true);
		$this->clean_url = get_site_url_clean(array('trim' => "/"));

		$this->meta_prefix = "mf_cache_";
	}

	function fetch_request()
	{
		$this->http_host = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : "");
		$this->request_uri = $_SERVER['REQUEST_URI'];

		$this->clean_url = $this->http_host.$this->request_uri;
	}

	function header_cache()
	{
		$this->fetch_request();

		$this->parse_file_address();
		$this->get_or_set_file_content();
	}

	function print_styles_cache()
	{
		if(get_option_or_default('setting_merge_css', 'yes') == 'yes' && $this->is_user_cache_allowed())
		{
			$site_url = site_url();
			$file_url_base = $site_url."/wp-content";
			$file_dir_base = WP_CONTENT_DIR;

			//Does not work in files where relative URLs to images or fonts are used
			#####################
			/*if(!isset($GLOBALS['mf_styles']))
			{
				$GLOBALS['mf_styles'] = array();
			}

			global $wp_styles;

			//do_log("Styles: ".var_export($wp_styles, true));

			foreach($wp_styles->queue as $style)
			{
				if(isset($wp_styles->registered[$style]))
				{
					$handle = $wp_styles->registered[$style]->handle;
					$src = $wp_styles->registered[$style]->src;
					$data = isset($wp_styles->registered[$style]->extra['data']) ? $wp_styles->registered[$style]->extra['data'] : "";
					$ver = $wp_styles->registered[$style]->ver;

					if(!isset($GLOBALS['mf_styles'][$handle]))
					{
						if(substr($src, 0, 2) == "//")
						{
							$src = "http:".$src;
						}

						$GLOBALS['mf_styles'][$handle] = array(
							'type' => (substr($src, 0, strlen($site_url)) == $site_url ? 'internal' : 'external'),
							'file' => $src,
							'version' => $ver,
						);
					}
				}
			}

			//do_log("Styles: ".var_export($GLOBALS['mf_styles'], true));*/
			#####################

			if(isset($GLOBALS['mf_styles']) && count($GLOBALS['mf_styles']) > 0)
			{
				$version = 0;
				$output = "";

				foreach($GLOBALS['mf_styles'] as $handle => $arr_style)
				{
					$version += point2int($arr_style['version']);

					if(get_file_suffix($arr_style['file']) == 'php' || preg_match("/(".str_replace("/", "\/", $this->clean_url).")/i", $arr_style['file']) == false)
					{
						list($content, $headers) = get_url_content($arr_style['file'], true);

						if(isset($headers['http_code']) && $headers['http_code'] == 200)
						{
							$output .= $content;
						}

						else
						{
							unset($GLOBALS['mf_styles'][$handle]);
						}
					}

					else
					{
						$output .= get_file_content(array('file' => str_replace($file_url_base, $file_dir_base, $arr_style['file'])));
					}
				}

				if($output != '')
				{
					$this->fetch_request();

					list($upload_path, $upload_url) = get_uploads_folder("mf_cache/".$this->http_host."/styles");

					if($upload_path != '')
					{
						$version = int2point($version);

						$file = "style-".$version.".min.css"; //md5($this->request_uri.$version)

						$output = $this->compress_css($output);

						$success = set_file_content(array('file' => $upload_path.$file, 'mode' => 'w', 'content' => $output));

						if($success == true)
						{
							foreach($GLOBALS['mf_styles'] as $handle => $arr_style)
							{
								wp_deregister_style($handle);
							}

							wp_enqueue_style('mf_styles', $upload_url.$file, array(), null); //$version
						}
					}

					else if($error_text != '')
					{
						do_log($error_text);
					}
				}
			}
		}
	}

	function print_scripts_cache()
	{
		if(get_option_or_default('setting_merge_js', 'yes') == 'yes' && $this->is_user_cache_allowed())
		{
			$site_url = site_url();
			$file_url_base = $site_url."/wp-content";
			$file_dir_base = WP_CONTENT_DIR;

			//Does not work in files where relative URLs to images or fonts are used
			#####################
			/*if(!isset($GLOBALS['mf_scripts']))
			{
				$GLOBALS['mf_scripts'] = array();
			}

			global $wp_scripts;

			foreach($wp_scripts->queue as $script)
			{
				if(isset($wp_scripts->registered[$script]))
				{
					$handle = $wp_scripts->registered[$script]->handle;
					$src = $wp_scripts->registered[$script]->src;
					$data = isset($wp_scripts->registered[$script]->extra['data']) ? $wp_scripts->registered[$script]->extra['data'] : "";
					$ver = $wp_scripts->registered[$script]->ver;

					if(!isset($GLOBALS['mf_scripts'][$handle]))
					{
						if(substr($src, 0, 2) == "//")
						{
							$src = "http:".$src;
						}

						if(substr($src, 0, 3) == "/wp-")
						{
							$src = $site_url.$src;
						}

						$GLOBALS['mf_scripts'][$handle] = array(
							'type' => (substr($src, 0, strlen($site_url)) == $site_url ? 'internal' : 'external'),
							'file' => $src,
							//'translation' => $translation,
							'extra' => $data,
							'version' => $ver,
						);
					}
				}
			}

			//do_log("Scripts: ".var_export($GLOBALS['mf_scripts'], true));*/
			#####################

			if(isset($GLOBALS['mf_scripts']) && count($GLOBALS['mf_scripts']) > 0)
			{
				global $error_text;

				$version = 0;
				$output = $translation = "";
				$error = false;

				foreach($GLOBALS['mf_scripts'] as $handle => $arr_script)
				{
					$version += point2int($arr_script['version']);

					if(isset($arr_script['translation']))
					{
						$count_temp = count($arr_script['translation']);

						if(is_array($arr_script['translation']) && $count_temp > 0)
						{
							$translation .= "var ".$handle." = {";

								$i = 1;

								foreach($arr_script['translation'] as $key => $value)
								{
									$translation .= "'".$key."': \"".$value."\"";

									if($i < $count_temp)
									{
										$translation .= ",";
									}

									$i++;
								}

							$translation .= "};";
						}
					}

					/*else if(isset($arr_script['extra']))
					{
						$translation .= $arr_script['extra'];
					}

					$content = "";
					
					if($arr_script['type'] == 'external')
					{
						$content = get_url_content($arr_script['file']);
					}
					
					else
					{*/
						$content = get_file_content(array('file' => str_replace($file_url_base, $file_dir_base, $arr_script['file'])));
					//}

					if($content != '')
					{
						$output .= $content;
					}

					else
					{
						unset($GLOBALS['mf_scripts'][$handle]);
					}
				}

				if($output != '' && $error == false)
				{
					$this->fetch_request();

					list($upload_path, $upload_url) = get_uploads_folder("mf_cache/".$this->http_host."/scripts");

					if($upload_path != '')
					{
						$version = int2point($version);

						$file = "script-".$version.".min.js"; //md5($this->request_uri.$version)

						$output = $this->compress_js($output);

						$success = set_file_content(array('file' => $upload_path.$file, 'mode' => 'w', 'content' => $output));

						if($success == true)
						{
							foreach($GLOBALS['mf_scripts'] as $handle => $arr_script)
							{
								wp_deregister_script($handle);
							}

							wp_enqueue_script('mf_scripts', $upload_url.$file, array('jquery'), null, true); //$version

							if($translation != '')
							{
								echo "<script>".$translation."</script>";
							}
						}
					}

					else if($error_text != '')
					{
						do_log($error_text);
					}
				}
			}
		}
	}

	function style_tag_loader_cache($tag)
	{
		if($this->is_user_cache_allowed())
		{
			$tag = str_replace("  ", " ", $tag);
			$tag = str_replace(" />", ">", $tag);
			$tag = str_replace(" type='text/css'", "", $tag);
			$tag = str_replace(' type="text/css"', "", $tag);
		}

		return $tag;
	}

	function script_tag_loader_cache($tag)
	{
		if($this->is_user_cache_allowed())
		{
			$tag = str_replace(" type='text/javascript'", "", $tag);
			$tag = str_replace(' type="text/javascript"', "", $tag);

			/*$setting_load_js = get_option('setting_load_js', 'async');

			if($setting_load_js != '')
			{
				$tag = str_replace(" src", " ".$setting_load_js." src", $tag);
			}*/
		}

		return $tag;
	}

	function create_dir()
	{
		$this->dir2create = $this->upload_path.trim($this->clean_url, "/");

		if(!is_dir($this->dir2create)) // && !preg_match("/\?/", $this->dir2create) //Won't work with Webshop/JSON
		{
			if(strlen($this->dir2create) > 256 || !@mkdir($this->dir2create, 0755, true))
			{
				//do_log(sprintf(__("I could not create %s", 'lang_cache'), $this->dir2create));

				return false;
			}
		}

		return true;
	}

	function parse_file_address($suffix = 'html')
	{
		$this->suffix = $suffix;

		if($this->create_dir())
		{
			$this->file_address = $this->dir2create."/index.".$this->suffix;
		}

		else
		{
			$this->file_address = $this->upload_path.$this->http_host."-".md5($this->request_uri).".".$this->suffix;
		}
	}

	function is_user_cache_allowed()
	{
		if(is_user_logged_in())
		{
			/*if(get_option('setting_activate_logged_in_cache') == 'yes')
			{
				return true;
			}

			else
			{*/
				return false;
			//}
		}

		else
		{
			return true;
		}
	}

	function get_or_set_file_content()
	{
		if(get_option('setting_activate_cache') == 'yes' && $this->is_user_cache_allowed())
		{
			if(count($_POST) == 0 && strlen($this->file_address) <= 255 && file_exists(realpath($this->file_address)) && filesize($this->file_address) > 0)
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

				echo $out;
				exit;
			}

			else
			{
				ob_start(array($this, 'cache_save'));
			}
		}
	}

	function compress_html($in)
	{
		$exkludera = array('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '/>(\n|\r|\t|\r\n|  |	)+/', '/(\n|\r|\t|\r\n|  |	)+</');
		$inkludera = array('', '>', '<');

		$out = preg_replace($exkludera, $inkludera, $in);

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

		return preg_replace($exkludera, $inkludera, $in);
	}

	function compress_js($in)
	{
		$exkludera = array('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '/(\n|\r|\t|\r\n|  |	)+/');

		return preg_replace($exkludera, '', $in);
	}

	function cache_save($out)
	{
		if(strlen($out) > 0)
		{
			switch($this->suffix)
			{
				case 'html':
					if(get_option_or_default('setting_compress_html', 'yes') == 'yes')
					{
						$out = $this->compress_html($out);
					}
				break;
			}

			if(count($_POST) == 0)
			{
				$success = set_file_content(array('file' => $this->file_address, 'mode' => 'w', 'content' => $out, 'log' => false));

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

				/*if($success == false)
				{
					do_log(sprintf(__("I could not save the cache for %s", 'lang_cache'), $this->file_address));
				}*/
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

	function clear($data = array())
	{
		if(!isset($data['time_limit'])){	$data['time_limit'] = 0;}
		if(!isset($data['allow_depth'])){	$data['allow_depth'] = true;}

		$upload_path_site = $this->upload_path.trim($this->clean_url, "/");

		if($this->count_files() > 0)
		{
			get_file_info(array('path' => $upload_path_site, 'callback' => "delete_files", 'folder_callback' => "delete_folders", 'time_limit' => $data['time_limit'], 'allow_depth' => $data['allow_depth']));

			$this->count_files();
		}
	}

	function get_posts2populate()
	{
		$arr_post_types = $this->arr_posts = array();

		foreach(get_post_types(array('public' => true, 'exclude_from_search' => false), 'names') as $post_type)
		{
			if($post_type != 'attachment')
			{
				get_post_children(array('post_type' => $post_type), $arr_post_types);
			}
		}

		foreach($arr_post_types as $post_id => $post_title)
		{
			if(get_post_status($post_id) == 'publish')
			{
				$this->arr_posts[$post_id] = $post_title;
			}
		}
	}

	function populate()
	{
		$microtime_exists = class_exists('mf_microtime');

		if($microtime_exists)
		{
			$obj_microtime = new mf_microtime();
		}

		update_option('mf_cache_prepopulated', date("Y-m-d H:i:s"));

		$i = 0;

		$this->get_posts2populate();

		foreach($this->arr_posts as $post_id => $post_title)
		{
			if($i == 0 && $microtime_exists)
			{
				$obj_microtime->check_time();
			}

			get_url_content(get_permalink($post_id));

			if($i == 0 && $microtime_exists)
			{
				$microtime_old = $obj_microtime->now;

				$obj_microtime->check_time();

				update_option('mf_cache_prepopulated_one', $obj_microtime->now - $microtime_old);
			}

			$i++;

			if($i % 10 == 0)
			{
				sleep(0.1);
				set_time_limit(60);
			}
		}

		if($microtime_exists)
		{
			$obj_microtime->check_time();

			update_option('mf_cache_prepopulated_total', $obj_microtime->now - $obj_microtime->time_orig);
		}

		update_option('mf_cache_prepopulated', date("Y-m-d H:i:s"));
	}
}