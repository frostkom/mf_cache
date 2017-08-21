jQuery(function($)
{
	function check_page_expiry(obj)
	{
		obj.selector.append("<br><p><i class='fa fa-spinner fa-spin fa-2x'></i></p>");

		$.ajax(
		{
			type: "post",
			dataType: "json",
			url: script_cache.ajax_url,
			data: {
				action: obj.action
			},
			success: function(data)
			{
				if(data.success)
				{
					obj.selector.children('p').replaceWith(data.message);
				}

				else
				{
					obj.selector.children('p').replaceWith(data.error);
				}
			}
		});
	}

	function run_ajax(obj)
	{
		obj.selector.html("<i class='fa fa-spinner fa-spin fa-2x'></i>");

		$.ajax(
		{
			type: "post",
			dataType: "json",
			url: script_cache.ajax_url,
			data: {
				action: obj.action
			},
			success: function(data)
			{
				obj.selector.empty();
				obj.button.attr('disabled', true);

				if(data.success)
				{
					obj.selector.html(data.message);
				}

				else
				{
					obj.selector.html(data.error);
				}
			}
		});

		return false;
	}

	check_page_expiry({
		'action': 'check_page_expiry',
		'selector': $('#cache_debug')
	});

	$(document).on('click', "button[name=btnCacheClear]", function(e)
	{
		run_ajax(
		{
			'button': $(e.currentTarget),
			'action': 'clear_cache',
			'selector': $('#cache_debug')
		});
	});

	$(document).on('click', "button[name=btnCachePopulate]", function(e)
	{
		run_ajax(
		{
			'button': $(e.currentTarget),
			'action': 'populate_cache',
			'selector': $('#cache_populate')
		});
	});

	$(document).on('click', "button[name=btnCacheTest]", function(e)
	{
		run_ajax(
		{
			'button': $(e.currentTarget),
			'action': 'test_cache',
			'selector': $('#cache_test')
		});
	});
});