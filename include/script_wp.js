jQuery(function($)
{
	function run_ajax(obj)
	{
		obj.selector.html("<i class='fa fa-spinner fa-spin fa-2x'></i>");

		$.ajax(
		{
			url: script_cache_wp.ajax_url,
			type: 'post',
			dataType: 'json',
			data: {
				action: obj.action
			},
			success: function(data)
			{
				obj.selector.empty();

				if(obj.button.is("button"))
				{
					obj.button.addClass('is_disabled');
				}

				else
				{
					obj.button.addClass('hide');
				}

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

	$(document).on('click', "#wp-admin-bar-cache a, #notification_clear_cache_button", function(e)
	{
		var dom_button = $(e.currentTarget);

		if(dom_button.parents("#wp-admin-bar-cache").length > 0)
		{
			dom_button = dom_button.parents("#wp-admin-bar-cache");
		}

		else if(dom_button.parents(".error").length > 0)
		{
			dom_button = dom_button.parents(".error");
		}

		run_ajax(
		{
			'button': dom_button,
			'action': 'clear_cache',
			'selector': $("#cache_debug")
		});
	});

	$(document).on('click', "button[name='btnCacheClear']:not(.is_disabled)", function(e)
	{
		run_ajax(
		{
			'button': $(e.currentTarget),
			'action': 'clear_cache',
			'selector': $("#cache_debug")
		});
	});

	$(document).on('click', "button[name='btnCacheClearAll']:not(.is_disabled)", function(e)
	{
		run_ajax(
		{
			'button': $(e.currentTarget),
			'action': 'clear_all_cache',
			'selector': $("#cache_debug")
		});
	});

	$(document).on('click', "button[name='btnCachePopulate']:not(.is_disabled)", function(e)
	{
		run_ajax(
		{
			'button': $(e.currentTarget),
			'action': 'populate_cache',
			'selector': $("#cache_populate")
		});
	});

	$(document).on('click', "button[name='btnCacheTest']:not(.is_disabled)", function(e)
	{
		run_ajax(
		{
			'button': $(e.currentTarget),
			'action': 'test_cache',
			'selector': $("#cache_test")
		});
	});
});