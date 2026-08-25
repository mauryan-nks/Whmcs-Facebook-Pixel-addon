<?php

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/firebase_mfa.php';

add_hook('ClientAreaHeadOutput', 1, 'facebook_pixel_hook_base');
add_hook('ShoppingCartViewCartOutput', 1, 'facebook_pixel_hook_addtocart');
add_hook('ShoppingCartCheckoutOutput', 1, 'facebook_pixel_hook_checkout');
add_hook('ClientAreaPage', 1, 'facebook_pixel_hook_enforce_firebase_mfa');
add_hook('UserLogin', 1, 'facebook_pixel_hook_reset_firebase_mfa');
add_hook('UserLogout', 1, 'facebook_pixel_hook_clear_firebase_mfa');

/**
 * Return the configured Meta Pixel ID after strict validation.
 */
function facebook_pixel_get_id(): ?string
{
    $pixel = facebook_pixel_get_setting('code');
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
    if (!is_array($products) || $products === [] || facebook_pixel_get_id() === null) {
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

/**
 * Gate the authenticated client area until Firebase phone verification succeeds.
 * WHMCS performs the primary credential authentication first; this addon only
 * adds a second factor and never accepts Firebase as a replacement password.
 */
function facebook_pixel_hook_enforce_firebase_mfa($vars): array
{
    if (!facebook_pixel_firebase_mfa_enabled()) {
        return [];
    }

    $clientId = (int) ($_SESSION['uid'] ?? 0);
    if ($clientId < 1 || facebook_pixel_mfa_is_verified($clientId)) {
        return [];
    }

    $modulePage = isset($_GET['m']) && (string) $_GET['m'] === 'facebook_pixel';
    $filename = strtolower((string) ($vars['filename'] ?? ''));
    $logoutPage = in_array($filename, ['logout', 'dologout'], true);

    if ($modulePage || $logoutPage) {
        return [];
    }

    facebook_pixel_mark_mfa_pending($clientId);
    header('Location: index.php?m=facebook_pixel');
    exit;
}

/**
 * A fresh WHMCS login must always perform a fresh second-factor challenge.
 */
function facebook_pixel_hook_reset_firebase_mfa($vars): void
{
    facebook_pixel_clear_mfa_session();
}

function facebook_pixel_hook_clear_firebase_mfa($vars): void
{
    facebook_pixel_clear_mfa_session();
}
