<?php

function check_htaccess_cache($data)
{
	if(basename($data['file']) == ".htaccess")
	{
		$content = get_file_content(array('file' => $data['file']));

		$setting_cache_expires = get_site_option('setting_cache_expires', 24);
		$setting_cache_api_expires = get_site_option('setting_cache_api_expires');

		$file_page_expires = "modification plus ".$setting_cache_expires." ".($setting_cache_expires > 1 ? "hours" : "hour");
		$file_api_expires = $setting_cache_api_expires > 0 ? "modification plus ".$setting_cache_api_expires." ".($setting_cache_api_expires > 1 ? "minutes" : "minute") : "";

		$cache_file_path = str_replace(ABSPATH, "", WP_CONTENT_DIR)."/uploads/mf_cache/%{SERVER_NAME}%{ENV:FILTERED_REQUEST}";
		$cache_logged_in_file_path = str_replace(ABSPATH, "", WP_CONTENT_DIR)."/uploads/mf_cache/logged_in/%{SERVER_NAME}%{ENV:FILTERED_REQUEST}";

		//RewriteCond %{REQUEST_URI} !^(wp-(content|admin|includes).*) [NC]
		$recommend_htaccess = "AddDefaultCharset UTF-8

		RewriteEngine On

		RewriteCond %{THE_REQUEST} ^[A-Z]{3,9}\ (.*)\ HTTP/
		RewriteRule ^(.*) - [E=FILTERED_REQUEST:%1]\n";

		//Header always set X-HEADER-REQUEST "%{FILTERED_REQUEST}e"
		$unused_test = "<IfModule mod_headers.c>
			RewriteCond %{REQUEST_URI} !^.*[^/]$
			RewriteCond %{REQUEST_URI} !^.*//.*$
			RewriteCond %{REQUEST_METHOD} !POST
			RewriteCond %{HTTP:Cookie} !^.*(comment_author_|wordpress_logged_in|wp-postpass_).*$
			RewriteCond '%{HTTP:Accept-encoding}' 'gzip'
			RewriteCond %{DOCUMENT_ROOT}/wp-content/uploads/mf_cache/%{SERVER_NAME}%{ENV:FILTERED_REQUEST}index.html.gz -f
			RewriteRule ^(.*) 'wp-content/uploads/mf_cache/%{SERVER_NAME}%{ENV:FILTERED_REQUEST}index.html.gz' [L]

			# Serve gzip compressed CSS files if they exist and the client accepts gzip.
			RewriteCond '%{HTTP:Accept-encoding}' 'gzip'
			RewriteCond '%{REQUEST_FILENAME}\.gz' -s
			RewriteRule '^(.*)\.css' '$1\.css\.gz' [QSA]

			# Serve gzip compressed JS files if they exist and the client accepts gzip.
			RewriteCond '%{HTTP:Accept-encoding}' 'gzip'
			RewriteCond '%{REQUEST_FILENAME}\.gz' -s
			RewriteRule '^(.*)\.js' '$1\.js\.gz' [QSA]

			# Serve correct content types, and prevent mod_deflate double gzip.
			RewriteRule '\.css\.gz$' '-' [T=text/css,E=no-gzip:1]
			RewriteRule '\.js\.gz$' '-' [T=text/javascript,E=no-gzip:1]

			<FilesMatch '(\.js\.gz|\.css\.gz)$'>
				# Serve correct encoding type.
				Header append Content-Encoding gzip

				# Force proxies to cache gzipped & non-gzipped css/js files separately.
				Header append Vary Accept-Encoding
			</FilesMatch>
		</IfModule>";

		if(1 == 2 && get_option('setting_activate_cache_logged_in') == 'yes')
		{
			$recommend_htaccess .= "\nRewriteCond %{REQUEST_URI} !^.*[^/]$
			RewriteCond %{REQUEST_URI} !^.*//.*$
			RewriteCond %{REQUEST_METHOD} !POST
			RewriteCond %{HTTP:Cookie} ^.*(wordpress_logged_in).*$
			RewriteCond %{DOCUMENT_ROOT}/".$cache_logged_in_file_path."index.html -f
			RewriteRule ^(.*) '".$cache_logged_in_file_path."index.html' [L]

			RewriteCond %{REQUEST_URI} !^.*[^/]$
			RewriteCond %{REQUEST_URI} !^.*//.*$
			RewriteCond %{REQUEST_METHOD} !POST
			RewriteCond %{HTTP:Cookie} ^.*(wordpress_logged_in).*$
			RewriteCond %{DOCUMENT_ROOT}/".$cache_logged_in_file_path."index.json -f
			RewriteRule ^(.*) '".$cache_logged_in_file_path."index.json' [L]";
		}

		$recommend_htaccess .= "\nRewriteCond %{REQUEST_URI} !^.*[^/]$
		RewriteCond %{REQUEST_URI} !^.*//.*$
		RewriteCond %{REQUEST_METHOD} !POST
		RewriteCond %{HTTP:Cookie} !^.*(comment_author_|wordpress_logged_in|wp-postpass_).*$
		RewriteCond %{DOCUMENT_ROOT}/".$cache_file_path."index.html -f
		RewriteRule ^(.*) '".$cache_file_path."index.html' [L]

		RewriteCond %{REQUEST_URI} !^.*[^/]$
		RewriteCond %{REQUEST_URI} !^.*//.*$
		RewriteCond %{REQUEST_METHOD} !POST
		RewriteCond %{HTTP:Cookie} !^.*(comment_author_|wordpress_logged_in|wp-postpass_).*$
		RewriteCond %{DOCUMENT_ROOT}/".$cache_file_path."index.json -f
		RewriteRule ^(.*) '".$cache_file_path."index.json' [L]

		<IfModule mod_expires.c>
			ExpiresActive On
			ExpiresDefault 'access plus 1 month'
			ExpiresByType text/html '".$file_page_expires."'
			ExpiresByType text/xml '".$file_page_expires."'
			ExpiresByType application/json '".($file_api_expires != '' ? $file_api_expires : $file_page_expires)."'
			ExpiresByType text/cache-manifest 'access plus 0 seconds'

			Header append Cache-Control 'public, must-revalidate'

			Header unset ETag
		</IfModule>

		FileETag None

		<IfModule mod_filter.c>
			AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript image/jpeg image/png image/gif image/x-icon
		</Ifmodule>";

		/*<filesMatch '\.(html|xml|txt|css|js|jpeg|jpg|png|gif)$'>
			SetOutputFilter DEFLATE
		</filesMatch>

		<filesMatch '\.(html|xml)$'>
			ExpiresDefault '".$file_page_expires."'
		</filesMatch>*/

		$old_md5 = get_match("/BEGIN MF Cache \((.*?)\)/is", $content, false);
		$new_md5 = md5($recommend_htaccess);

		if($new_md5 != $old_md5)
		{
			echo "<div class='mf_form'>"
				."<h3 class='display_warning'><i class='fa fa-warning yellow'></i> ".sprintf(__("Add this to the beginning of %s", 'lang_cache'), ".htaccess")."</h3>"
				."<p class='input'>".nl2br("# BEGIN MF Cache (".$new_md5.")\n".htmlspecialchars($recommend_htaccess)."\n# END MF Cache")."</p>"
			."</div>";
		}
	}
}

function check_page_expiry()
{
	$result = array();

	$out = "";

	$obj_cache = new mf_cache();
	$obj_cache->get_posts2populate();

	$arr_posts_with_expiry = array();

	if(isset($obj_cache->arr_posts) && is_array($obj_cache->arr_posts))
	{
		foreach($obj_cache->arr_posts as $post_id => $post_title)
		{
			$post_expires = get_post_meta($post_id, $obj_cache->meta_prefix.'expires', true);

			if($post_expires > 0)
			{
				$arr_posts_with_expiry[$post_id] = array('title' => $post_title, 'expires' => $post_expires);
			}
		}
	}

	if(count($arr_posts_with_expiry) > 0)
	{
		$out .= "<h4>".__("Exceptions", 'lang_cache')." <a href='".admin_url("edit.php?post_type=page")."'><i class='fa fa-lg fa-plus'></i></a></h4>
		<table class='widefat striped'>";

			foreach($arr_posts_with_expiry as $post_id => $post)
			{
				$out .= "<tr>
					<td><a href='".admin_url("post.php?post=".$post_id."&action=edit")."'>".$post['title']."</a></td>
					<td><a href='".get_permalink($post_id)."'><i class='fa fa-lg fa-link'></i></a></td>
					<td>".$post['expires']." ".__("minutes", 'lang_cache')."</td>
				</tr>";
			}

		$out .= "</table>";
	}

	else
	{
		$page_on_front = get_option('page_on_front');

		if($page_on_front > 0)
		{
			$out .= "<p><em>".sprintf(__("You can override the default value on individual pages, for example on the %shome page%s by editing and scrolling down to Cache in the right column", 'lang_cache'), "<a href='".admin_url("post.php?post=".$page_on_front."&action=edit")."'>", "</a>")."</em></p>";
		}
	}

	if($out != '')
	{
		$result['success'] = true;
		$result['message'] = $out;
	}

	else
	{
		$result['success'] = false;
		$result['error'] = "";
	}

	header('Content-Type: application/json');
	echo json_encode($result);
	die();
}

function clear_cache()
{
	global $done_text, $error_text;

	$result = array();

	$obj_cache = new mf_cache();
	$obj_cache->clear();

	if($obj_cache->file_amount == 0)
	{
		delete_option('option_cache_prepopulated');

		$done_text = __("I successfully cleared the cache for you", 'lang_cache');
	}

	else
	{
		$error_text = __("I could not clear the cache. Please make sure that the credentials are correct", 'lang_cache');
	}

	$out = get_notification();

	if($done_text != '')
	{
		$result['success'] = true;
		$result['message'] = $out;
	}

	else
	{
		$result['error'] = $out;
	}

	header('Content-Type: application/json');
	echo json_encode($result);
	die();
}

function clear_all_cache()
{
	global $done_text, $error_text;

	$result = array();

	$obj_cache = new mf_cache();

	$obj_cache->clean_url = "";

	$obj_cache->clear();

	if($obj_cache->file_amount == 0)
	{
		delete_option('option_cache_prepopulated');

		$done_text = __("I successfully cleared the cache on all sites for you", 'lang_cache');
	}

	else
	{
		$error_text = __("I could not clear the cache on all sites. Please make sure that the credentials are correct", 'lang_cache');
	}

	$out = get_notification();

	if($done_text != '')
	{
		$result['success'] = true;
		$result['message'] = $out;
	}

	else
	{
		$result['error'] = $out;
	}

	header('Content-Type: application/json');
	echo json_encode($result);
	die();
}

function populate_cache()
{
	global $done_text, $error_text;

	$result = array();

	$obj_cache = new mf_cache();
	$obj_cache->clear();

	$after_clear = $obj_cache->file_amount;

	if($obj_cache->file_amount == 0)
	{
		$obj_cache->populate();

		if($obj_cache->count_files() > 0)
		{
			$done_text = __("I successfully populated the cache for you", 'lang_cache');
		}

		else
		{
			$error_text = __("No files were populated", 'lang_cache');
		}

		$after_populate = $obj_cache->file_amount;
	}

	else
	{
		$error_text = __("I could not clear the cache before population. Please make sure that the credentials are correct", 'lang_cache');
	}

	$out = get_notification();

	if($done_text != '')
	{
		$result['success'] = true;
		$result['message'] = $out;
	}

	else
	{
		$result['error'] = $out;
	}

	header('Content-Type: application/json');
	echo json_encode($result);
	die();
}

function test_cache()
{
	global $done_text, $error_text;

	$result = array();

	$site_url = get_site_url();

	list($content, $headers) = get_url_content($site_url, true);
	$time_1st = $headers['total_time'];

	if(preg_match("/\<\!\-\- Dynamic /i", $content))
	{
		list($content, $headers) = get_url_content($site_url, true);
		$time_2nd = $headers['total_time'];
	}

	if(!preg_match("/\<\!\-\- Dynamic /i", $content)) //preg_match("/\<\!\-\- Compressed /i", $content)
	{
		if(isset($time_2nd))
		{
			$done_text = sprintf(__("The cache was successfully tested. The site was loaded in %ss the first time and then again cached in %ss", 'lang_cache'), mf_format_number($time_1st, 1), mf_format_number($time_2nd, 2));
		}

		else
		{
			$done_text = sprintf(__("The cache was successfully tested. The site was loaded cached in %ss", 'lang_cache'), mf_format_number($time_1st, 2));
		}
	}

	else
	{
		$error_text = __("Something is not working as it should. Let an admin have a look and fix any issues with it", 'lang_cache');
	}

	$out = get_notification();

	if($done_text != '')
	{
		$result['success'] = true;
		$result['message'] = $out;
	}

	else
	{
		$result['error'] = $out;
	}

	header('Content-Type: application/json');
	echo json_encode($result);
	die();
}

function setting_strip_domain_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option_or_default($setting_key, 'no');

	echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));
}

function setting_appcache_activate_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option($setting_key, 'no');

	$setting_appcache_pages_url = get_option('setting_appcache_pages_url');
	$count_temp = count($setting_appcache_pages_url);

	if($count_temp > 0 && $option == 'yes')
	{
		$suffix = sprintf(__("There are %d resources added to the AppCache right now", 'lang_cache'), $count_temp);
	}

	else
	{
		$suffix = __("This will further improve the cache performance since it caches all pages on the site for offline use", 'lang_cache');
	}

	echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option, 'suffix' => $suffix));
}

function setting_appcache_fallback_page_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option($setting_key);

	$arr_data = array();
	get_post_children(array('add_choose_here' => true), $arr_data);

	echo show_select(array('data' => $arr_data, 'name' => $setting_key, 'value' => $option, 'suffix' => "<a href='".admin_url("post-new.php?post_type=page")."'><i class='fa fa-lg fa-plus'></i></a>", 'description' => __("This page will be displayed as a fallback if the visitor is offline and a page on the site is not cached", 'lang_cache')));
}

function setting_cache_debug_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option_or_default($setting_key, 'no');

	echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));

	if($option == 'yes')
	{
		echo "<div class='form_buttons'>"
			.show_button(array('type' => 'button', 'name' => 'btnCacheTest', 'text' => __("Test", 'lang_cache'), 'class' => 'button-secondary'))
		."</div>
		<div id='cache_test'></div>";
	}
}