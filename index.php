<?php
/*
Plugin Name: MF Cache
Plugin URI: https://github.com/frostkom/mf_cache
Description: 
Version: 4.1.3
Licence: GPLv2 or later
Author: Martin Fors
Author URI: http://frostkom.se
Text Domain: lang_cache
Domain Path: /lang

Depends: MF Base
GitHub Plugin URI: frostkom/mf_cache
*/

include_once("include/classes.php");
include_once("include/functions.php");

$obj_cache = new mf_cache();

add_action('cron_base', array($obj_cache, 'run_cron'), mt_rand(1, 10));
add_action('cron_base', 'activate_cache', mt_rand(1, 10));

if(is_admin())
{
	register_activation_hook(__FILE__, 'activate_cache');
	register_uninstall_hook(__FILE__, 'uninstall_cache');

	add_action('admin_init', 'settings_cache');
	add_action('admin_init', array($obj_cache, 'admin_init'), 0);

	if($obj_cache->setting_activate_cache == 'yes')
	{
		add_action('wp_before_admin_bar_render', array($obj_cache, 'admin_bar'));

		add_action('rwmb_meta_boxes', 'meta_boxes_cache', 11);
		
		add_action('wp_ajax_check_page_expiry', 'check_page_expiry');
		add_action('wp_ajax_clear_cache', 'clear_cache');
		add_action('wp_ajax_clear_all_cache', 'clear_all_cache');
		add_action('wp_ajax_populate_cache', 'populate_cache');
		add_action('wp_ajax_test_cache', 'test_cache');
	}	

	load_plugin_textdomain('lang_cache', false, dirname(plugin_basename(__FILE__)).'/lang/');
}

else
{
	if($obj_cache->setting_activate_cache == 'yes')
	{
		add_action('get_header', array($obj_cache, 'get_header'), 0);
		add_filter('language_attributes', array($obj_cache, 'language_attributes'));
		add_action('wp_head', array($obj_cache, 'wp_head'), 0);
	}
}

if($obj_cache->setting_activate_cache == 'yes' || $obj_cache->setting_activate_compress == 'yes')
{
	add_action('mf_enqueue_script', array($obj_cache, 'enqueue_script'));
	add_action('mf_enqueue_style', array($obj_cache, 'enqueue_style'));

	add_action('admin_init', array($obj_cache, 'print_styles'), 1); //admin_print_styles
	add_action('login_init', array($obj_cache, 'print_styles'), 1); //login_print_styles
	add_action('wp_head', array($obj_cache, 'print_styles'), 1); //wp_print_styles

	add_action('wp_print_scripts', array($obj_cache, 'print_scripts'), 10);

	add_filter('style_loader_tag', array($obj_cache, 'style_loader_tag'), 10);
	add_filter('script_loader_tag', array($obj_cache, 'script_loader_tag'), 10);
}

function activate_cache()
{
	mf_uninstall_plugin(array(
		'options' => array('setting_activate_logged_in_cache', 'setting_cache_browser_expires', 'setting_compress_html', 'setting_merge_css', 'setting_merge_js', 'setting_load_js', 'setting_appcache_pages', 'setting_appcache_pages_old'),
	));

	replace_option(array('old' => 'mf_cache_prepopulated', 'new' => 'option_cache_prepopulated'));
	replace_option(array('old' => 'mf_cache_prepopulated_one', 'new' => 'option_cache_prepopulated_one'));
	replace_option(array('old' => 'mf_cache_prepopulated_total', 'new' => 'option_cache_prepopulated_total'));
}

function uninstall_cache()
{
	mf_uninstall_plugin(array(
		'uploads' => 'mf_cache',
		'options' => array('setting_activate_compress', 'setting_activate_cache', 'setting_cache_expires', 'setting_cache_prepopulate', 'setting_strip_domain', 'setting_cache_debug', 'option_cache_prepopulated', 'option_cache_prepopulated_length', 'option_cache_prepopulated_one', 'option_cache_prepopulated_total', 'setting_cache_browser_expires', 'setting_appcache_activate'),
	));
}