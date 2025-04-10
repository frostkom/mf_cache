<?php
/*
Plugin Name: MF Cache
Plugin URI: https://github.com/frostkom/mf_cache
Description:
Version: 4.12.0
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

	add_action('cron_base', array($obj_cache, 'cron_base'), mt_rand(1, 10));

	add_action('init', array($obj_cache, 'init'), 0);

	if(is_admin())
	{
		register_deactivation_hook(__FILE__, 'deactivate_cache');
		register_uninstall_hook(__FILE__, 'uninstall_cache');

		add_action('admin_init', array($obj_cache, 'settings_cache'));
		add_action('admin_init', array($obj_cache, 'admin_init'), 0);

		if(get_option('setting_cache_activate') == 'yes')
		{
			add_action('wp_before_admin_bar_render', array($obj_cache, 'wp_before_admin_bar_render'));
			add_action('admin_notices', array($obj_cache, 'admin_notices'));
		}

		add_filter('filter_sites_table_settings', array($obj_cache, 'filter_sites_table_settings'));

		add_action('wp_ajax_api_cache_clear', array($obj_cache, 'api_cache_clear'));
		add_action('wp_ajax_api_cache_clear_all', array($obj_cache, 'api_cache_clear_all'));
		add_action('wp_ajax_api_cache_test', array($obj_cache, 'api_cache_test'));
	}

	else
	{
		add_action('wp_print_styles', array($obj_cache, 'wp_head_combine_styles'), 100);
		add_action('wp_print_scripts', array($obj_cache, 'wp_print_scripts_combine_scripts'), 1);

		add_filter('style_loader_tag', array($obj_cache, 'style_loader_tag'), 10);
		add_filter('script_loader_tag', array($obj_cache, 'script_loader_tag'), 10);
	}

	add_filter('recommend_config', array($obj_cache, 'recommend_config'));

	load_plugin_textdomain('lang_cache', false, dirname(plugin_basename(__FILE__))."/lang/");

	function deactivate_cache()
	{
		include_once("include/classes.php");

		$obj_cache = new mf_cache();

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
			'options' => array('setting_cache_activate', 'setting_cache_combine', 'setting_cache_extract_inline', 'setting_cache_expires', 'setting_cache_activate_api', 'option_cache_api_include', 'setting_cache_api_include', 'setting_cache_api_expires', 'setting_cache_debug'),
		));
	}
}