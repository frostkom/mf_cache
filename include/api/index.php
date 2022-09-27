<?php

if(!defined('ABSPATH'))
{
	header('Content-Type: application/json');

	$folder = str_replace("/wp-content/plugins/mf_cache/include/api", "/", dirname(__FILE__));

	require_once($folder."wp-load.php");
}

$json_output = array(
	'success' => false,
);

$type = check_var('type', 'char');

switch($type)
{
	case 'save_cache':
		$cache_url = check_var('url', 'char');
		$cache_html = check_var('html', 'char');

		//do_log("save_cache for ".$cache_url." -> ".htmlspecialchars($cache_html));

		$obj_cache = new mf_cache();
		$obj_cache->suffix = 'html';
		$obj_cache->allow_logged_in = false;

		$url_parts = parse_url($cache_url);

		if(isset($url_parts['host']))
		{
			$obj_cache->http_host = strtolower($url_parts['host']);
			$obj_cache->request_uri = strtolower($url_parts['path']);
			$obj_cache->clean_url = $obj_cache->http_host.$obj_cache->request_uri;

			//$obj_cache->get_or_set_file_content(array('suffix' => 'html')); //, 'allow_logged_in' => true*/
			//$obj_cache->file_address = $url;

			$obj_cache->parse_file_address(array('ignore_post' => true));

			//do_log("save_cache for ".var_export($url_parts, true)." -> ".$obj_cache->http_host." + ".$obj_cache->request_uri." -> ".$obj_cache->file_address); //." -> ".htmlspecialchars($cache_html)
			$obj_cache->set_cache(htmlspecialchars_decode(stripslashes(stripslashes($cache_html))));

			$json_output['success'] = true;
		}

		else if($cache_url != '')
		{
			$log_message = __("I could not parse the URL", 'lang_cache')." (".$cache_url." -> ".var_export($url_parts, true).")";

			do_log($log_message);
			$json_output['message'] = $log_message;
		}
	break;
}

echo json_encode($json_output);