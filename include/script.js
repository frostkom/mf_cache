jQuery(function($)
{
	/*$("img[srcset!='']").error(function()
	{
		$(this).removeAttr('srcset').removeAttr('sizes');
	});*/

	if(script_cache.js_cache == 'yes')
	{
		function save_cache()
		{
			var dom_html = "";

			$("html").each(function()
			{
				dom_html += "<!DOCTYPE html>"
				+ "<html";

					$.each(this.attributes, function()
					{
						if(this.specified)
						{
							dom_html += " " + this.name + "='" + this.value + "'";
						}
					});

				dom_html += ">";
			});

			dom_html += $("html").html();
			dom_html += "</html>";

			$.ajax(
			{
				url: script_cache.plugin_url + 'api/?type=save_cache',
				type: 'post',
				dataType: 'json',
				data: {
					url: location.href,
					html: dom_html
				},
				success: function(data){}
			});
		}

		setTimeout(save_cache, script_cache.js_cache_timeout);

		/* Check Interval */
		/* ################### */
		var check_timout,
			url_old = location.href;

		function check_url_change()
		{
			var url_current = location.href;

			if(url_current != url_old)
			{
				clearTimeout(check_timout);
				check_timout = setTimeout(save_cache, script_cache.js_cache_timeout);

				url_old = url_current;
			}

			setTimeout(function()
			{
				check_url_change();
			}, script_cache.js_cache_timeout);
		}

		check_url_change();
		/* ################### */
	}
});