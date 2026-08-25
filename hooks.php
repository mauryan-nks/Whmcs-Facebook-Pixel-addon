<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

add_hook('ClientAreaHeadOutput', 1, 'facebook_pixel_hook_base');
add_hook('ShoppingCartViewCartOutput', 1, 'facebook_pixel_hook_addtocart');
add_hook('ShoppingCartCheckoutOutput', 1, 'facebook_pixel_hook_checkout');

/**
 * Return the configured Meta Pixel ID after strict validation.
 *
 * Meta Pixel IDs are numeric. Rejecting anything else prevents stored
 * configuration values from being injected into generated JavaScript/HTML.
 */
function facebook_pixel_get_id(): ?string
{
    try {
        $pixel = Capsule::table('tbladdonmodules')
            ->where('module', 'facebook_pixel')
            ->where('setting', 'code')
            ->value('value');
    } catch (\Throwable $e) {
        logModuleCall('facebook_pixel', 'read_pixel_id', [], $e->getMessage());
        return null;
    }

    if (!is_string($pixel)) {
        return null;
    }

    $pixel = trim(explode('|', $pixel, 2)[0]);

    if ($pixel === '' || !preg_match('/^[0-9]{5,30}$/', $pixel)) {
        return null;
    }

    return $pixel;
}

/**
 * Render the Meta Pixel bootstrap code in the client-area head.
 */
function facebook_pixel_hook_base($vars): string
{
    $pixel = facebook_pixel_get_id();
    if ($pixel === null) {
        return '';
    }

    $pixelJs = json_encode($pixel, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $pixelUrl = rawurlencode($pixel);

    return <<<HTML
<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', {$pixelJs});
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id={$pixelUrl}&amp;ev=PageView&amp;noscript=1" /></noscript>
<!-- End Meta Pixel Code -->
HTML;
}

/**
 * Fire AddToCart only when the cart actually contains at least one product.
 */
function facebook_pixel_hook_addtocart($vars): string
{
    $products = $vars['cart']['products'] ?? [];
    if (!is_array($products) || $products === []) {
        return '';
    }

    if (facebook_pixel_get_id() === null) {
        return '';
    }

    return "<script>if (typeof fbq === 'function') { fbq('track', 'AddToCart'); }</script>";
}

/**
 * Fire InitiateCheckout only when the pixel is configured and available.
 */
function facebook_pixel_hook_checkout($vars): string
{
    if (facebook_pixel_get_id() === null) {
        return '';
    }

    return "<script>if (typeof fbq === 'function') { fbq('track', 'InitiateCheckout'); }</script>";
}
