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

		/* https://stackoverflow.com/questions/3219758/detect-changes-in-the-dom */

		/*$("#main").on('DOMSubtreeModified', function()
		{
			console.log("DOM changed...");

			save_cache();
		});*/

		/*window.addEventListener('popstate', function(e)
		{
			console.log("URL changed...");

			save_cache();
		});*/

		/*$(window).on('beforeunload', function()
		{
			console.log("Unload...");

			save_cache();
		});*/

		setTimeout(save_cache, (script_cache.js_cache_timeout * 1000));
	}
});