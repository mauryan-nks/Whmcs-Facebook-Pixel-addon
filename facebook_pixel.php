<?php

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/firebase_mfa.php';

/**
 * WHMCS addon module metadata and configuration fields.
 */
function facebook_pixel_config(): array
{
    return [
        'name' => 'Meta Pixel + Firebase MFA',
        'description' => 'Adds Meta Pixel ecommerce events and optional Firebase SMS OTP MFA to the WHMCS client area.',
        'version' => '2.1.0',
        'author' => 'Vismrit.tech / Nayan',
        'language' => 'english',
        'fields' => [
            'code' => [
                'FriendlyName' => 'Meta Pixel ID',
                'Type' => 'text',
                'Size' => '30',
                'Description' => 'Enter only the numeric Meta Pixel ID (for example: 123456789012345).',
            ],
            'firebase_mfa_enabled' => [
                'FriendlyName' => 'Enable Firebase Phone MFA',
                'Type' => 'yesno',
                'Description' => 'Require a Firebase SMS OTP after a successful WHMCS client login.',
            ],
            'firebase_api_key' => [
                'FriendlyName' => 'Firebase Web API Key',
                'Type' => 'text',
                'Size' => '60',
                'Description' => 'Firebase Web app apiKey. This is a browser configuration value, not a service-account private key.',
            ],
            'firebase_auth_domain' => [
                'FriendlyName' => 'Firebase Auth Domain',
                'Type' => 'text',
                'Size' => '60',
                'Description' => 'For example: your-project.firebaseapp.com',
            ],
            'firebase_project_id' => [
                'FriendlyName' => 'Firebase Project ID',
                'Type' => 'text',
                'Size' => '40',
                'Description' => 'Firebase projectId from your Web app configuration.',
            ],
            'firebase_app_id' => [
                'FriendlyName' => 'Firebase App ID',
                'Type' => 'text',
                'Size' => '60',
                'Description' => 'Firebase appId from your Web app configuration.',
            ],
            'firebase_messaging_sender_id' => [
                'FriendlyName' => 'Messaging Sender ID',
                'Type' => 'text',
                'Size' => '30',
                'Description' => 'Optional Firebase messagingSenderId from your Web app configuration.',
            ],
        ],
    ];
}

/**
 * Addon administration page.
 */
function facebook_pixel_output($vars): void
{
    $mfaEnabled = facebook_pixel_firebase_mfa_enabled();
    $statusClass = $mfaEnabled ? 'alert-success' : 'alert-warning';
    $statusText = $mfaEnabled
        ? 'Firebase phone MFA is enabled and the required Web configuration is present.'
        : 'Firebase phone MFA is disabled or its required Web configuration is incomplete.';

    echo '<div class="container-fluid"><div class="row"><div class="col-md-10 col-lg-8">';
    echo '<h2>Meta Pixel + Firebase MFA</h2>';
    echo '<p>This addon injects the Meta Pixel base code and can require Firebase SMS OTP as a second authentication step for WHMCS clients.</p>';
    echo '<h4>Firebase setup</h4>';
    echo '<ol>';
    echo '<li>Create or select a Firebase Web app.</li>';
    echo '<li>Enable <strong>Authentication &gt; Phone</strong> in Firebase.</li>';
    echo '<li>Add your WHMCS hostname to Firebase Authentication <strong>Authorized domains</strong>.</li>';
    echo '<li>Configure the Firebase SMS region policy for the countries you intend to support.</li>';
    echo '<li>Copy the Web app apiKey, authDomain, projectId, appId and optional messagingSenderId into this addon configuration.</li>';
    echo '<li>Ensure every WHMCS client phone number is stored in E.164 format, for example <code>+919876543210</code>.</li>';
    echo '</ol>';
    echo '<div class="alert ' . $statusClass . '" role="alert">' . htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') . '</div>';
    echo '<div class="alert alert-info" role="alert">The Firebase Web API key is intentionally used by browser code. Do not paste a Firebase service-account JSON file or private key into this addon.</div>';
    echo '<p><a class="btn btn-primary" href="https://business.facebook.com/events_manager/" target="_blank" rel="noopener noreferrer">Open Meta Events Manager</a></p>';
    echo '<p>Compatible with WHMCS 9.0 and PHP 8.2/8.3.</p>';
    echo '</div></div></div>';
}

/**
 * Firebase MFA challenge page.
 */
function facebook_pixel_clientarea($vars): array
{
    $clientId = (int) ($_SESSION['uid'] ?? 0);
    $error = '';

    if (!facebook_pixel_firebase_mfa_enabled()) {
        return [
            'pagetitle' => 'Verification unavailable',
            'breadcrumb' => [$vars['modulelink'] => 'Verification'],
            'templatefile' => 'mfa',
            'requirelogin' => true,
            'forcessl' => true,
            'vars' => [
                'mfaAvailable' => false,
                'mfaError' => 'Firebase phone MFA is not enabled or is not fully configured.',
            ],
        ];
    }

    $phone = facebook_pixel_client_phone($clientId);
    if ($phone === null) {
        $error = 'Your WHMCS phone number must be saved in E.164 format (for example, +919876543210) before Firebase OTP can be used.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['firebase_id_token'])) {
        $csrf = isset($_POST['mfa_csrf']) ? (string) $_POST['mfa_csrf'] : '';
        $idToken = trim((string) $_POST['firebase_id_token']);

        if (!facebook_pixel_mfa_check_csrf($csrf)) {
            $error = 'Your verification session expired. Reload this page and try again.';
        } elseif ($phone === null) {
            $error = 'Your WHMCS phone number is not valid for Firebase OTP.';
        } else {
            $verification = facebook_pixel_verify_firebase_phone_token($idToken);
            if (!($verification['ok'] ?? false)) {
                $error = (string) ($verification['error'] ?? 'Firebase verification failed.');
            } elseif (!hash_equals($phone, (string) $verification['phone'])) {
                $error = 'The phone number verified by Firebase does not match the phone number on this WHMCS account.';
            } else {
                facebook_pixel_mark_mfa_verified($clientId);
                header('Location: clientarea.php');
                exit;
            }
        }
    }

    $config = facebook_pixel_firebase_config();
    $publicConfig = json_encode(array_filter($config, static fn($value) => $value !== ''), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    return [
        'pagetitle' => 'Verify your phone',
        'breadcrumb' => [$vars['modulelink'] => 'Phone verification'],
        'templatefile' => 'mfa',
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'mfaAvailable' => $phone !== null,
            'mfaError' => $error,
            'maskedPhone' => $phone !== null ? facebook_pixel_mask_phone($phone) : '',
            'phoneE164' => $phone ?? '',
            'firebaseConfigJson' => is_string($publicConfig) ? $publicConfig : '{}',
            'mfaCsrf' => facebook_pixel_mfa_csrf_token(),
        ],
    ];
}
