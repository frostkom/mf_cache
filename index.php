<?php
/*
Plugin Name: MF Cache
Plugin URI: https://github.com/frostkom/mf_cache
Description: 
Version: 1.5.8
Author: Martin Fors
Author URI: http://frostkom.se
Text Domain: lang_cache
Domain Path: /lang

GitHub Plugin URI: frostkom/mf_cache
*/

include_once("include/classes.php");
include_once("include/functions.php");

add_action('cron_base', 'cron_cache', mt_rand(1, 10));

if(is_admin())
{
	register_uninstall_hook(__FILE__, 'uninstall_cache');

	add_action('admin_init', 'settings_cache');

	add_action('post_updated', 'post_updated_cache', 10, 3);

	load_plugin_textdomain('lang_cache', false, dirname(plugin_basename(__FILE__)).'/lang/');
}

else
{
	add_action('get_header', 'header_cache', 0);
}

add_action('wp_ajax_clear_cache', 'clear_cache');

function uninstall_cache()
{
	mf_uninstall_plugin(array(
		'uploads' => 'mf_cache',
		'options' => array('setting_activate_cache', 'setting_cache_expires', 'setting_cache_prepopulate', 'setting_compress_html', 'setting_cache_debug', 'mf_cache_prepopulated'),
	));
}