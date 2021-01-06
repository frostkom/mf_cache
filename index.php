<?php
/*
Plugin Name: MF Cache
Plugin URI: https://github.com/frostkom/mf_cache
Description: 
Version: 4.8.2
Licence: GPLv2 or later
Author: Martin Fors
Author URI: https://frostkom.se
Text Domain: lang_cache
Domain Path: /lang

Depends: MF Base
GitHub Plugin URI: frostkom/mf_cache
*/

include_once("include/classes.php");

$obj_cache = new mf_cache();

$is_activated = (get_option('setting_activate_cache') == 'yes' || get_option('setting_activate_compress') == 'yes');

add_action('cron_base', 'activate_cache', mt_rand(1, 10));
add_action('cron_base', array($obj_cache, 'cron_base'), mt_rand(1, 10));

if(is_admin())
{
	register_activation_hook(__FILE__, 'activate_cache');
	register_deactivation_hook(__FILE__, 'deactivate_cache');
	register_uninstall_hook(__FILE__, 'uninstall_cache');

	add_action('admin_init', array($obj_cache, 'settings_cache'));
	add_action('admin_init', array($obj_cache, 'admin_init'), 0);

	if($is_activated)
	{
		add_action('wp_before_admin_bar_render', array($obj_cache, 'wp_before_admin_bar_render'));
	}

	add_action('rwmb_meta_boxes', array($obj_cache, 'rwmb_meta_boxes'), 11);

	add_action('wp_ajax_check_page_expiry', array($obj_cache, 'check_page_expiry'));
	add_action('wp_ajax_clear_cache', array($obj_cache, 'clear_cache'));
	add_action('wp_ajax_clear_all_cache', array($obj_cache, 'clear_all_cache'));
	add_action('wp_ajax_populate_cache', array($obj_cache, 'populate_cache'));
	add_action('wp_ajax_test_cache', array($obj_cache, 'test_cache'));

	// Clear Admin Cache
	add_action('clear_admin_cache', array($obj_cache, 'clear_admin_cache'));

	add_action('user_register', array($obj_cache, 'clear_user_cache'));
	add_action('profile_update', array($obj_cache, 'clear_user_cache'));
	add_action('delete_user', array($obj_cache, 'clear_user_cache'));

	load_plugin_textdomain('lang_cache', false, dirname(plugin_basename(__FILE__))."/lang/");
}

else
{
	add_action('get_header', array($obj_cache, 'get_header'), 0);
	//add_filter('language_attributes', array($obj_cache, 'language_attributes'));
	add_action('wp_head', array($obj_cache, 'wp_head'), 0);
}

add_action('run_cache', array($obj_cache, 'run_cache'));

add_filter('recommend_config', array($obj_cache, 'recommend_config'));

if($is_activated)
{
	add_action('mf_enqueue_script', array($obj_cache, 'enqueue_script'));
	add_action('mf_enqueue_style', array($obj_cache, 'enqueue_style'));

	add_action('admin_init', array($obj_cache, 'print_styles'), 1); //admin_print_styles
	add_action('login_init', array($obj_cache, 'print_styles'), 1); //login_print_styles
	add_action('wp_head', array($obj_cache, 'print_styles'), 1); //wp_print_styles

	add_action('wp_print_scripts', array($obj_cache, 'wp_print_scripts'), 10);

	add_filter('style_loader_tag', array($obj_cache, 'style_loader_tag'), 10);
	add_filter('script_loader_tag', array($obj_cache, 'script_loader_tag'), 10);
}

function activate_cache()
{
	mf_uninstall_plugin(array(
		'options' => array('setting_activate_logged_in_cache', 'setting_cache_browser_expires', 'setting_compress_html', 'setting_merge_css', 'setting_merge_js', 'setting_load_js', 'setting_appcache_pages', 'setting_appcache_pages_old', 'setting_appcache_pages_url'),
	));
}

function deactivate_cache()
{
	mf_uninstall_plugin(array(
		'uploads' => 'mf_cache',
	));
}

function uninstall_cache()
{
	mf_uninstall_plugin(array(
		'uploads' => 'mf_cache',
		'options' => array('setting_activate_compress', 'setting_activate_cache', 'setting_cache_js_cache', 'setting_cache_js_cache_pages', 'setting_cache_js_cache_timeout', 'setting_cache_expires', 'setting_cache_api_expires', 'setting_cache_admin_expires', 'setting_cache_admin_group_by', 'setting_cache_admin_pages', 'setting_cache_prepopulate', 'setting_cache_debug', 'option_cache_prepopulated', 'option_cache_prepopulated_length', 'option_cache_prepopulated_one', 'option_cache_prepopulated_total', 'setting_cache_browser_expires', 'setting_appcache_activate'),
	));
}