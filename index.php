<?php
/*
Plugin Name: MF Cache
Plugin URI: https://github.com/frostkom/mf_cache
Description: 
Version: 3.7.10
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

add_action('cron_base', 'cron_cache', mt_rand(1, 10));
add_action('cron_base', 'activate_cache', mt_rand(1, 10));

$obj_cache = new mf_cache();

$setting_activate_cache = get_option('setting_activate_cache');

if(is_admin())
{
	register_activation_hook(__FILE__, 'activate_cache');
	register_uninstall_hook(__FILE__, 'uninstall_cache');

	add_action('admin_init', 'settings_cache');

	if($setting_activate_cache == 'yes')
	{
		add_action('wp_before_admin_bar_render', array($obj_cache, 'admin_bar'));

		add_action('rwmb_meta_boxes', 'meta_boxes_cache', 11);
	}

	load_plugin_textdomain('lang_cache', false, dirname(plugin_basename(__FILE__)).'/lang/');
}

else
{
	if($setting_activate_cache == 'yes')
	{
		add_action('init', array($obj_cache, 'init'));

		add_action('get_header', array($obj_cache, 'get_header'), 0);
		add_filter('language_attributes', array($obj_cache, 'language_attributes'));
		add_action('wp_head', array($obj_cache, 'get_head'));
	}

	/* Can only be allowed in is_admin() aswell when cron_cache() does not clean every x min */
	if($setting_activate_cache == 'yes' || get_option('setting_activate_compress', 'yes') == 'yes')
	{
		add_action('mf_enqueue_script', array($obj_cache, 'enqueue_script'));
		add_action('mf_enqueue_style', array($obj_cache, 'enqueue_style'));

		add_action('admin_print_styles', array($obj_cache, 'print_styles'), 10);
		//add_action('login_print_styles', array($obj_cache, 'print_styles'), 10);
		add_action('wp_print_styles', array($obj_cache, 'print_styles'), 10);

		add_action('wp_print_scripts', array($obj_cache, 'print_scripts'), 10);

		add_filter('style_loader_tag', array($obj_cache, 'style_tag_loader'), 10);
		add_filter('script_loader_tag', array($obj_cache, 'script_tag_loader'), 10);
	}
}

add_action('wp_ajax_check_page_expiry', 'check_page_expiry');
add_action('wp_ajax_clear_cache', 'clear_cache');
add_action('wp_ajax_clear_all_cache', 'clear_all_cache');
add_action('wp_ajax_populate_cache', 'populate_cache');
add_action('wp_ajax_test_cache', 'test_cache');

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