jQuery(function($)
{
	function run_ajax(obj)
	{
		if(obj.button.is("button"))
		{
			obj.button.addClass('is_disabled');
		}

		else
		{
			obj.button.addClass('hide');
		}

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
				if(data.success)
				{
					obj.button.addClass('hide');
				}

				else
				{
					if(obj.button.is("button"))
					{
						obj.button.removeClass('is_disabled');
					}

					else
					{
						obj.button.removeClass('hide');
					}
				}

				obj.selector.html(data.html);
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
			'action': 'api_cache_clear',
			'selector': $(".api_cache_output")
		});
	});

	$(document).on('click', "button[name='btnCacheClear']:not(.is_disabled)", function(e)
	{
		run_ajax(
		{
			'button': $(e.currentTarget),
			'action': 'api_cache_clear',
			'selector': $(".api_cache_output")
		});
	});

	$(document).on('click', "button[name='btnCacheClearAll']:not(.is_disabled)", function(e)
	{
		run_ajax(
		{
			'button': $(e.currentTarget),
			'action': 'api_cache_clear_all',
			'selector': $(".api_cache_output")
		});
	});

	$(document).on('click', "button[name='btnCacheTest']:not(.is_disabled)", function(e)
	{
		run_ajax(
		{
			'button': $(e.currentTarget),
			'action': 'api_cache_test',
			'selector': $(".cache_test")
		});
	});
});