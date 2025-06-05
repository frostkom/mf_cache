<?php
/*
Plugin Name: MF Cache
Plugin URI: https://github.com/frostkom/mf_cache
Description:
Version: 4.12.27
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

		add_action('wp_ajax_api_cache_info', array($obj_cache, 'api_cache_info'));

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

	remove_action('wp_head', 'rsd_link');
	remove_action('wp_head', 'rest_output_link_wp_head'); // Disable REST API link tag

	remove_action('template_redirect', 'rest_output_link_header', 11, 0); // Disable REST API link in HTTP headers
	remove_action('wp_head', 'wlwmanifest_link');
	remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);

	remove_action('rest_api_init', 'wp_oembed_register_route');
	remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
	remove_action('wp_head', 'wp_oembed_add_discovery_links'); // Disable oEmbed Discovery Links
	remove_action('wp_head', 'wp_oembed_add_host_js');

	remove_action('wp_head', 'feed_links', 2);
	remove_action('wp_head', 'feed_links_extra', 3);

	add_filter('emoji_svg_url', '__return_false');
	remove_action('wp_head', 'print_emoji_detection_script', 7);
	remove_action('wp_print_styles', 'wp_enqueue_emoji_styles');
	remove_action('admin_print_scripts', 'print_emoji_detection_script');
	remove_action('admin_print_styles', 'wp_enqueue_emoji_styles');
	remove_action('wp_print_styles', 'print_emoji_styles');
	remove_action('admin_print_styles', 'print_emoji_styles');
	remove_filter('the_content_feed', 'wp_staticize_emoji');
	remove_filter('comment_text_rss', 'wp_staticize_emoji');
	remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
	add_filter('option_use_smilies', '__return_false');

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
			'options' => array('setting_cache_activate', 'setting_cache_combine', 'setting_cache_extract_inline', 'setting_cache_expires', 'setting_cache_activate_api', 'option_cache_api_include', 'setting_cache_api_include', 'setting_cache_api_expires', 'setting_cache_access_log', 'option_cache_access_log_read_daily', 'option_cache_access_log_read', 'setting_cache_debug'),
		));
	}
}