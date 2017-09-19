<?php

class mf_cache
{
	function __construct()
	{
		list($this->upload_path, $this->upload_url) = get_uploads_folder('mf_cache', true);
		$this->clean_url = get_site_url_clean(array('trim' => "/"));

		$this->site_url = site_url();
		$this->site_url_clean = $this->clean_url($this->site_url);

		$this->meta_prefix = "mf_cache_";

		$this->arr_styles = $this->arr_scripts = array();
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
		$this->get_or_set_file_content();
	}

	function clean_url($url)
	{
		return str_replace(array("http:", "https:"), "", $url);
	}

	function get_type($src)
	{
		return (substr($this->clean_url($src), 0, strlen($this->site_url_clean)) == $this->site_url_clean ? 'internal' : 'external');
	}

	function should_load_as_url()
	{
		if(substr($this->arr_resource['file'], 0, 3) == "/wp-")
		{
			$this->arr_resource['file'] = $this->site_url.$this->arr_resource['file'];
		}

		/*else if(substr($this->clean_url($this->arr_resource['file']), 0, strlen($this->site_url_clean)) != $this->site_url_clean)
		{
			$this->arr_resource['type'] = 'external';
		}*/

		return ($this->arr_resource['type'] == 'external' || get_file_suffix($this->arr_resource['file']) == 'php');
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

	function print_styles_cache()
	{
		global $error_text;

		//if(get_option_or_default('setting_merge_css', 'yes') == 'yes' && $this->is_user_cache_allowed())
		if($this->is_user_cache_allowed())
		{
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
				$output = "";

				foreach($this->arr_styles as $handle => $this->arr_resource)
				{
					$version += point2int($this->arr_resource['version']);

					if($this->should_load_as_url())
					{
						list($content, $headers) = get_url_content($this->arr_resource['file'], true);

						if(isset($headers['http_code']) && $headers['http_code'] == 200)
						{
							$output .= $content;
						}

						else
						{
							unset($this->arr_styles[$handle]);
						}
					}

					else
					{
						$output .= get_file_content(array('file' => str_replace($file_url_base, $file_dir_base, $this->arr_resource['file'])));
					}
				}

				if($output != '')
				{
					$this->fetch_request();

					list($upload_path, $upload_url) = get_uploads_folder("mf_cache/".$this->http_host."/styles");

					if($upload_path != '')
					{
						$version = int2point($version);

						$file = "style-".$version.".min.css";

						$output = $this->compress_css($output);

						$success = set_file_content(array('file' => $upload_path.$file, 'mode' => 'w', 'content' => $output));

						if($success == true)
						{
							foreach($this->arr_styles as $handle => $this->arr_resource)
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

		list($upload_path, $upload_url) = get_uploads_folder("mf_cache/".$this->http_host."/scripts");

		if($upload_path != '')
		{
			if(isset($data['handle']) && $data['handle'] != '')
			{
				$data['filename'] = "script-".$data['handle'].".js";
				//$data['content'] = $this->compress_js($data['content']);
			}

			else
			{
				$data['version'] = int2point($data['version']);
				$data['filename'] = "script-".$data['version'].".min.js";
				$data['content'] = $this->compress_js($data['content']);
			}

			$success = set_file_content(array('file' => $upload_path.$data['filename'], 'mode' => 'w', 'content' => $data['content']));

			if($success == true)
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

					wp_enqueue_script('mf_scripts', $upload_url.$data['filename'], array('jquery'), null, true); //$data['version']

					if(isset($data['translation']) && $data['translation'] != '')
					{
						echo "<script>".$data['translation']."</script>";
					}
				}
			}
		}

		else if($error_text != '')
		{
			do_log($error_text);
		}
	}

	function print_scripts_cache()
	{
		//if(get_option_or_default('setting_merge_js', 'yes') == 'yes' && $this->is_user_cache_allowed())
		if($this->is_user_cache_allowed())
		{
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
				$output = $translation = "";

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
							$content = get_url_content($this->arr_resource['file']);
						}

						if($content != '')
						{
							$this->output_js(array('handle' => $handle, 'content' => $content, 'version' => $this->arr_resource['version']));
						}

						else
						{
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
							unset($this->arr_scripts[$handle]);
						}
					}
				}

				if($output != '')
				{
					$this->output_js(array('content' => $output, 'version' => $version, 'translation' => $translation));
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

	function is_password_protected()
	{
		global $post;

		return isset($post->post_password) && $post->post_password != '';
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

	function parse_file_address()
	{
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

	function get_or_set_file_content($suffix = 'html')
	{
		if(get_option('setting_activate_cache') == 'yes' && $this->is_user_cache_allowed())
		{
			$this->suffix = $suffix;
			$this->parse_file_address();

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
		if(strlen($out) > 0 && $this->is_password_protected() == false)
		{
			switch($this->suffix)
			{
				case 'html':
					/*if(get_option_or_default('setting_compress_html', 'yes') == 'yes')
					{*/
						$out = $this->compress_html($out);
					//}
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
				get_post_children(array('post_type' => $post_type, 'where' => "post_password = ''"), $arr_post_types);
			}
		}

		foreach($arr_post_types as $post_id => $post_title)
		{
			$this->arr_posts[$post_id] = $post_title;
		}
	}

	function populate()
	{
		$obj_microtime = new mf_microtime();

		update_option('mf_cache_prepopulated', date("Y-m-d H:i:s"));

		$i = 0;

		$this->get_posts2populate();

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

				update_option('mf_cache_prepopulated_one', $obj_microtime->now - $microtime_old);
			}

			$i++;

			if($i % 10 == 0)
			{
				sleep(0.1);
				set_time_limit(60);
			}
		}

		$obj_microtime->save_now();
		update_option('mf_cache_prepopulated_total', $obj_microtime->now - $obj_microtime->time_orig);
		update_option('mf_cache_prepopulated', date("Y-m-d H:i:s"));
	}
}