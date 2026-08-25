<?php

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

/**
 * WHMCS addon module metadata and configuration fields.
 */
function facebook_pixel_config(): array
{
    return [
        'name' => 'Meta (Facebook) Pixel',
        'description' => 'Adds the Meta Pixel base code and selected ecommerce events to the WHMCS client area.',
        'version' => '2.0.0',
        'author' => 'Sinhcoms LLP / Nayan',
        'language' => 'english',
        'fields' => [
            'code' => [
                'FriendlyName' => 'Meta Pixel ID',
                'Type' => 'text',
                'Size' => '30',
                'Description' => 'Enter only the numeric Meta Pixel ID (for example: 123456789012345).',
            ],
        ],
    ];
}

/**
 * Addon administration page.
 */
function facebook_pixel_output($vars): void
{
    echo <<<'HTML'
<div class="container-fluid">
    <div class="row">
        <div class="col-md-10 col-lg-8">
            <h2>Meta (Facebook) Pixel</h2>
            <p>This addon injects the Meta Pixel base code into the WHMCS client area and tracks PageView, AddToCart, and InitiateCheckout events.</p>
            <p>Configure the numeric Pixel ID under <strong>Configuration &gt; System Settings &gt; Addon Modules</strong>.</p>
            <p>Your active client-area template must output WHMCS hook content in the standard head/footer locations.</p>
            <p>
                <a class="btn btn-primary" href="https://business.facebook.com/events_manager/" target="_blank" rel="noopener noreferrer">Open Meta Events Manager</a>
            </p>
            <div class="alert alert-info" role="alert">
                Compatible with WHMCS 9.0 and PHP 8.2/8.3. For security, non-numeric Pixel IDs are ignored rather than rendered into the page.
            </div>
        </div>
    </div>
</div>
HTML;
}
