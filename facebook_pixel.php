<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
function facebook_pixel_config()
{
    $config = [
    	'name' => 'Facebook Pixel',
    	'description' => ' Add Facebook Pixel',
    	'version' => 'sncoms.7&2',
    	'author' => 'Sinhcoms LLP',
    	'language' => 'english',
    	'fields' => [
    		'code' => [
    			'FriendlyName' => 'Facebook Pixel Code',
    			'Type' => 'text',
    			'Size' => '25',
    			'Description' => 'get Facebook Pixel',
    		]
    	]
    ];

    return $config;
}
function facebook_pixel_output($vars)
{
    echo "<br /><br />\n<p align=\"center\"><input type=\"button\" value=\"Launch Facebook Business Manager\" onclick=\"window.open('https://business.facebook.com/events_manager/?selected_data_sources=PIXEL','ganalytics');\" class=\"btn btn-primary btn-lg\" /></p>\n<br /><br />\n<p>Configuration of the Facebok Pixel Addon is done via <a href=\"configaddonmods.php\"><b>Setup > Addon Modules</b></a>. Please also ensure your active client area footer.tpl template file includes the {\$footeroutput} template tag.</p> <input type=\"button\" value=\"Watch how to Setup\" onclick=\"window.open('https://sncoms.co.in/setup');\" class=\"btn btn-primary btn-lg\" />";
}