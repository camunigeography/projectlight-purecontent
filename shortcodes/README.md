
This directory can contain shortcodes available to any site.

Shortcode files must use the same syntax as Wordpress, i.e.

```
<?php

add_shortcode ('shortcode_name', 'shortcode_name_handler');
function shortcode_name_handler ($attributes)
{
	return 'some string';	// Return, not echo
}

?>
```
