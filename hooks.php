<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
add_hook('ClientAreaHeadOutput', 1, 'facebook_pixel_hook_base');
add_hook("ShoppingCartViewCartOutput", 1,"facebook_pixel_hook_addtocart");
add_hook("ShoppingCartCheckoutOutput", 1,"facebook_pixel_hook_checkout");

function facebook_pixel_hook_base($vars)
{
	$modulevars = [];
	$result = select_query("tbladdonmodules", "", array("module" => "facebook_pixel"));
    while ($data = mysql_fetch_array($result)) {
        $value = $data['value'];
        $value = explode("|", $value);
        $value = trim($value[0]);
        $modulevars[$data["setting"]] = $value;
    }

    if (!$modulevars["code"]) {
        return false;
    }
    $pixel = $modulevars["code"];

    $jscode = "
<!-- Plugin by Sinhcoms Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https:connect.facebook.net/en_US/fbevents.js');
fbq('init', '".$pixel."');
fbq('track', \"PageView\");
</script>
<noscript><img height=\"1\" width=\"1\" style=\"display:none\" src=\"https://www.facebook.com/tr?id=".$pixel."&ev=PageView&noscript=1\" /></noscript>
<!-- End Meta Pixel Code -->

    return $jscode;
}

function facebook_pixel_hook_addtocart($vars)
{

	if (is_null($vars['cart']['products']) && $vars['cart']['products'] == '' ) {
        return false;
    }
			
	$jscode ="
		<script>
  			fbq('track', 'AddToCart');
		</script>
	";
	return $jscode;
}


function facebook_pixel_hook_checkout($vars)
{
	
	$jscode ="
		<script>
		  fbq('track', 'InitiateCheckout');
		</script>
	";
	return $jscode;
}

