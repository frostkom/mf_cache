function on_load_cache()
{
	jQuery('img').error(function()
	{
		jQuery(this).removeAttr('srcset').removeAttr('sizes');
	});
}

jQuery(function($)
{
	on_load_cache();

	if(typeof collect_on_load == 'function')
	{
		collect_on_load('on_load_cache');
	}
});