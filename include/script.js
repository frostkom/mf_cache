jQuery(function($)
{
	$("img[srcset!='']").error(function()
	{
		$(this).removeAttr('srcset').removeAttr('sizes');
	});
});