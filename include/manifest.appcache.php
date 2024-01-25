<?php

if(!defined('ABSPATH'))
{
	header("Content-Type: text/cache-manifest");

	$folder = str_replace("/wp-content/plugins/mf_cache/include", "/", dirname(__FILE__));

	require_once($folder."wp-load.php");
}

$setting_appcache_pages_url = array();

$option_cache_prepopulated = get_option_or_default('option_cache_prepopulated', date("Y-m-d H:i:s"));
$setting_cache_expires = get_site_option('setting_cache_expires', 24);
$setting_appcache_fallback_page = get_option('setting_appcache_fallback_page');

$fallback_page = get_permalink($setting_appcache_fallback_page);
$fallback_page = str_replace(get_site_url(), "", $fallback_page);

if(date("Y-m-d H:i:s") < date("Y-m-d H:i:s", strtotime($option_cache_prepopulated." +".$setting_cache_expires." hour")))
{
	$setting_appcache_pages_url = get_option('setting_appcache_pages_url');
}

echo "CACHE MANIFEST
# version ".$option_cache_prepopulated."

CACHE:
";

	if(is_array($setting_appcache_pages_url) && count($setting_appcache_pages_url) > 0)
	{
		foreach($setting_appcache_pages_url as $url)
		{
			echo $url."\n";
		}

		echo "\n";
	}

if($fallback_page != '')
{
	echo "FALLBACK:
	/ ".$fallback_page."\n";
}

echo "\nNETWORK:
*";