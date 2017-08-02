<?php

class mf_cache
{
	function __construct()
	{
		
	}

	function fetch_request()
	{
		$this->http_host = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : "");
		$this->request_uri = $_SERVER['REQUEST_URI'];

		$this->clean_url = $this->http_host.$this->request_uri;
	}

	function get_file_dir()
	{
		list($this->upload_path, $this->upload_url) = get_uploads_folder('mf_cache');
	}

	function create_dir()
	{
		$this->dir2create = $this->upload_path.trim($this->clean_url, "/"); //."/"

		if(!is_dir($this->dir2create))
		{
			if(!mkdir($this->dir2create, 0755, true))
			{
				do_log(sprintf(__("I could not create %s", 'lang_cache'), $this->dir2create));

				return false;
				break;
			}
		}

		return true;
	}

	function clear()
	{
		global $globals;

		$globals['count'] = 0;

		$upload_path_site = $this->upload_path."/".trim($this->clean_url, "/");

		get_file_info(array('path' => $upload_path_site, 'callback' => "count_files"));

		if($globals['count'] > 0)
		{
			get_file_info(array('path' => $upload_path_site, 'callback' => "delete_files", 'folder_callback' => "delete_folders", 'time_limit' => 0));

			get_file_info(array('path' => $upload_path_site, 'callback' => "count_files"));
		}

		return $globals['count'];
	}
}