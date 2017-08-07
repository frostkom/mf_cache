jQuery(function($)
{
	$(document).on('click', "button[name=btnCacheClear]", function()
	{
		$('#cache_debug').html("<i class='fa fa-spinner fa-spin fa-2x'></i>");

		$.ajax(
		{
			type: "post",
			dataType: "json",
			url: script_cache.ajax_url,
			data: {
				action: "clear_cache"
			},
			success: function(data)
			{
				$('#cache_debug').empty();

				if(data.success)
				{
					$('button[name=btnCacheClear]').attr('disabled', true);
					$('#cache_debug').html(data.message);
				}

				else
				{
					$('#cache_debug').html(data.error);
				}
			}
		});

		return false;
	});

	$(document).on('click', "button[name=btnCachePopulate]", function()
	{
		$('#cache_populate').html("<i class='fa fa-spinner fa-spin fa-2x'></i>");

		$.ajax(
		{
			type: "post",
			dataType: "json",
			url: script_cache.ajax_url,
			data: {
				action: "populate_cache"
			},
			success: function(data)
			{
				$('#cache_populate').empty();

				if(data.success)
				{
					$('button[name=btnCachePopulate]').attr('disabled', true);
					$('#cache_populate').html(data.message);
				}

				else
				{
					$('#cache_populate').html(data.error);
				}
			}
		});

		return false;
	});
});