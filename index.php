<?php
/*
Plugin Name: MF Cache
Plugin URI: https://github.com/frostkom/mf_cache
Description:
Version: 4.10.8
Licence: GPLv2 or later
Author: Martin Fors
Author URI: https://martinfors.se
Text Domain: lang_cache
Domain Path: /lang

Depends: MF Base
GitHub Plugin URI: frostkom/mf_cache
*/

if(!function_exists('is_plugin_active') || function_exists('is_plugin_active') && is_plugin_active("mf_base/index.php"))
{
	include_once("include/classes.php");

	$obj_cache = new mf_cache();

	add_action('cron_base', 'activate_cache', mt_rand(1, 10));
	add_action('cron_base', array($obj_cache, 'cron_base'), mt_rand(1, 10));

	if(is_admin())
	{
		register_activation_hook(__FILE__, 'activate_cache');
		register_deactivation_hook(__FILE__, 'deactivate_cache');
		register_uninstall_hook(__FILE__, 'uninstall_cache');

		add_action('admin_init', array($obj_cache, 'settings_cache'));
		add_action('admin_init', array($obj_cache, 'admin_init'), 0);

		if(get_option('setting_activate_cache') == 'yes')
		{
			add_action('wp_before_admin_bar_render', array($obj_cache, 'wp_before_admin_bar_render'));

			add_action('admin_notices', array($obj_cache, 'admin_notices'));
		}

		add_filter('filter_sites_table_settings', array($obj_cache, 'filter_sites_table_settings'));

		add_action('wp_ajax_clear_cache', array($obj_cache, 'clear_cache'));
		add_action('wp_ajax_clear_all_cache', array($obj_cache, 'clear_all_cache'));

		if(get_option('setting_activate_cache') == 'yes')
		{
			add_action('wp_ajax_populate_cache', array($obj_cache, 'populate_cache'));
			add_action('wp_ajax_test_cache', array($obj_cache, 'test_cache'));
		}
	}

	else if(get_option('setting_activate_cache') == 'yes')
	{
		add_action('get_header', array($obj_cache, 'get_header'), 0);

		add_action('wp_head', array($obj_cache, 'wp_head_combine_styles'), 1);
		add_action('wp_print_scripts', array($obj_cache, 'wp_print_scripts_combine_scripts'), 1);

		add_filter('style_loader_tag', array($obj_cache, 'style_loader_tag'), 10);
		add_filter('script_loader_tag', array($obj_cache, 'script_loader_tag'), 10);

		add_action('run_cache', array($obj_cache, 'run_cache'));
	}

	add_filter('recommend_config', array($obj_cache, 'recommend_config'));

	load_plugin_textdomain('lang_cache', false, dirname(plugin_basename(__FILE__))."/lang/");

	function activate_cache()
	{
		mf_uninstall_plugin(array(
			'options' => array('setting_activate_compress', 'setting_activate_logged_in_cache', 'setting_cache_browser_expires', 'setting_compress_html', 'setting_merge_css', 'setting_merge_js', 'setting_load_js', 'setting_appcache_pages', 'setting_appcache_pages_old', 'setting_appcache_pages_url', 'setting_cache_js_cache', 'setting_cache_js_cache_pages', 'setting_cache_js_cache_timeout', 'setting_cache_admin_expires', 'setting_cache_admin_group_by', 'setting_cache_admin_pages', 'setting_appcache_activate'),
			'post_meta' => array($this->meta_prefix.'expires'),
		));
	}

	function deactivate_cache()
	{
		global $obj_cache;

		mf_uninstall_plugin(array(
			'uploads' => $obj_cache->post_type,
		));
	}

	function uninstall_cache()
	{
		include_once("include/classes.php");

		$obj_cache = new mf_cache();

		mf_uninstall_plugin(array(
			'uploads' => $obj_cache->post_type,
			'options' => array('setting_activate_cache', 'setting_cache_expires', 'setting_cache_api_expires', 'setting_cache_prepopulate', 'setting_cache_debug', 'option_cache_prepopulated', 'option_cache_prepopulated_length', 'option_cache_prepopulated_one', 'option_cache_prepopulated_total', 'setting_cache_browser_expires'),
		));
	}
}