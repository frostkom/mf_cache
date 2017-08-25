<?php
/*
Plugin Name: MF Cache
Plugin URI: https://github.com/frostkom/mf_cache
Description: 
Version: 2.4.1
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
	register_activation_hook(__FILE__, 'activate_cache');
	register_uninstall_hook(__FILE__, 'uninstall_cache');

	add_action('admin_init', 'settings_cache');
	add_action('rwmb_meta_boxes', 'meta_boxes_cache', 11);
	add_action('post_updated', 'post_updated_cache', 10, 3);

	load_plugin_textdomain('lang_cache', false, dirname(plugin_basename(__FILE__)).'/lang/');
}

else
{
	if(get_option('setting_activate_cache') == 'yes')
	{
		$obj_cache = new mf_cache();

		add_action('get_header', array($obj_cache, 'header_cache'), 0);

		add_action('wp_print_styles', array($obj_cache, 'print_styles_cache'), 10);
		add_action('wp_print_scripts', array($obj_cache, 'print_scripts_cache'), 10);

		add_filter('style_loader_tag', array($obj_cache, 'style_tag_loader_cache'), 10);
		add_filter('script_loader_tag', array($obj_cache, 'script_tag_loader_cache'), 10);
	}
}

add_action('wp_ajax_check_page_expiry', 'check_page_expiry');
add_action('wp_ajax_clear_cache', 'clear_cache');
add_action('wp_ajax_populate_cache', 'populate_cache');
add_action('wp_ajax_test_cache', 'test_cache');

function activate_cache()
{
	mf_uninstall_plugin(array(
		'options' => array('setting_activate_logged_in_cache'),
	));
}

function uninstall_cache()
{
	mf_uninstall_plugin(array(
		'uploads' => 'mf_cache',
		'options' => array('setting_activate_cache', 'setting_activate_logged_in_cache', 'setting_cache_expires', 'setting_cache_prepopulate', 'setting_compress_html', 'setting_merge_css', 'setting_merge_js', 'setting_load_js', 'setting_cache_debug', 'mf_cache_prepopulated', 'mf_cache_prepopulated_length', 'mf_cache_prepopulated_one', 'mf_cache_prepopulated_total'),
	));
}