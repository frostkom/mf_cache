<?php

$wp_root = '../../../..';

if(file_exists($wp_root.'/wp-load.php'))
{
	require_once($wp_root.'/wp-load.php');
}

else
{
	require_once($wp_root.'/wp-config.php');
}

header("Content-Type: text/cache-manifest");

$setting_appcache_pages_url = array();
$option_cache_prepopulated = date("Y-m-d H:i:s");
$fallback_page = "";

if(get_option('setting_appcache_activate') == 'yes')
{
	$option_cache_prepopulated = get_option('option_cache_prepopulated');
	$setting_cache_expires = get_option('setting_cache_expires');
	$setting_appcache_fallback_page = get_option('setting_appcache_fallback_page');

	$fallback_page = get_permalink($setting_appcache_fallback_page);
	$fallback_page = str_replace(get_site_url(), "", $fallback_page);

	if(date("Y-m-d H:i:s") < date("Y-m-d H:i:s", strtotime($option_cache_prepopulated." +".$setting_cache_expires." hour")))
	{
		$setting_appcache_pages_url = get_option('setting_appcache_pages_url');
	}
}

echo "CACHE MANIFEST
# version ".$option_cache_prepopulated."

CACHE:
";

	if(count($setting_appcache_pages_url) > 0)
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