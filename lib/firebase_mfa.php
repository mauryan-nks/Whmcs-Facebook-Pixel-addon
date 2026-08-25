<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

function facebook_pixel_get_setting(string $name, string $default = ''): string
{
    try {
        $value = Capsule::table('tbladdonmodules')
            ->where('module', 'facebook_pixel')
            ->where('setting', $name)
            ->value('value');
    } catch (\Throwable $e) {
        logModuleCall('facebook_pixel', 'read_setting', ['setting' => $name], $e->getMessage());
        return $default;
    }

    if (!is_string($value)) {
        return $default;
    }

    return trim(explode('|', $value, 2)[0]);
}

function facebook_pixel_setting_enabled(string $name): bool
{
    return in_array(strtolower(facebook_pixel_get_setting($name)), ['1', 'on', 'yes', 'true'], true);
}

function facebook_pixel_firebase_config(): array
{
    return [
        'apiKey' => facebook_pixel_get_setting('firebase_api_key'),
        'authDomain' => facebook_pixel_get_setting('firebase_auth_domain'),
        'projectId' => facebook_pixel_get_setting('firebase_project_id'),
        'appId' => facebook_pixel_get_setting('firebase_app_id'),
        'messagingSenderId' => facebook_pixel_get_setting('firebase_messaging_sender_id'),
    ];
}

function facebook_pixel_firebase_mfa_enabled(): bool
{
    if (!facebook_pixel_setting_enabled('firebase_mfa_enabled')) {
        return false;
    }

    $config = facebook_pixel_firebase_config();
    return $config['apiKey'] !== ''
        && $config['authDomain'] !== ''
        && $config['projectId'] !== ''
        && $config['appId'] !== '';
}

function facebook_pixel_normalize_e164(?string $phone): ?string
{
    if (!is_string($phone)) {
        return null;
    }

    $phone = trim($phone);
    if ($phone === '') {
        return null;
    }

    $phone = preg_replace('/[\s().-]+/', '', $phone);
    if (!is_string($phone) || !preg_match('/^\+[1-9][0-9]{7,14}$/', $phone)) {
        return null;
    }

    return $phone;
}

function facebook_pixel_client_phone(int $clientId): ?string
{
    if ($clientId < 1) {
        return null;
    }

    try {
        $phone = Capsule::table('tblclients')->where('id', $clientId)->value('phonenumber');
    } catch (\Throwable $e) {
        logModuleCall('facebook_pixel', 'read_client_phone', ['client_id' => $clientId], $e->getMessage());
        return null;
    }

    return facebook_pixel_normalize_e164(is_string($phone) ? $phone : null);
}

function facebook_pixel_mask_phone(string $phone): string
{
    $length = strlen($phone);
    if ($length <= 6) {
        return str_repeat('*', max(0, $length - 2)) . substr($phone, -2);
    }

    return substr($phone, 0, 3) . str_repeat('*', $length - 6) . substr($phone, -3);
}

function facebook_pixel_mfa_csrf_token(): string
{
    if (empty($_SESSION['facebook_pixel_mfa_csrf']) || !is_string($_SESSION['facebook_pixel_mfa_csrf'])) {
        $_SESSION['facebook_pixel_mfa_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['facebook_pixel_mfa_csrf'];
}

function facebook_pixel_mfa_check_csrf(?string $token): bool
{
    $stored = $_SESSION['facebook_pixel_mfa_csrf'] ?? '';
    return is_string($token) && is_string($stored) && $stored !== '' && hash_equals($stored, $token);
}

function facebook_pixel_mark_mfa_pending(int $clientId): void
{
    if ($clientId < 1) {
        return;
    }

    $_SESSION['facebook_pixel_mfa_pending'] = $clientId;
    unset($_SESSION['facebook_pixel_mfa_verified'], $_SESSION['facebook_pixel_mfa_csrf']);
}

function facebook_pixel_mark_mfa_verified(int $clientId): void
{
    $_SESSION['facebook_pixel_mfa_verified'] = $clientId;
    unset($_SESSION['facebook_pixel_mfa_pending'], $_SESSION['facebook_pixel_mfa_csrf']);
}

function facebook_pixel_mfa_is_verified(int $clientId): bool
{
    return $clientId > 0 && (int) ($_SESSION['facebook_pixel_mfa_verified'] ?? 0) === $clientId;
}

function facebook_pixel_clear_mfa_session(): void
{
    unset(
        $_SESSION['facebook_pixel_mfa_pending'],
        $_SESSION['facebook_pixel_mfa_verified'],
        $_SESSION['facebook_pixel_mfa_csrf']
    );
}

/**
 * Validate a Firebase ID token through Firebase Auth's accounts:lookup endpoint.
 * The token itself is never written to module logs.
 */
function facebook_pixel_verify_firebase_phone_token(string $idToken): array
{
    if (!facebook_pixel_firebase_mfa_enabled()) {
        return ['ok' => false, 'error' => 'Firebase MFA is not fully configured.'];
    }

    if ($idToken === '' || strlen($idToken) > 8192) {
        return ['ok' => false, 'error' => 'Invalid Firebase token.'];
    }

    $apiKey = facebook_pixel_get_setting('firebase_api_key');
    $url = 'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . rawurlencode($apiKey);
    $payload = json_encode(['idToken' => $idToken], JSON_UNESCAPED_SLASHES);
    if (!is_string($payload)) {
        return ['ok' => false, 'error' => 'Could not prepare Firebase verification request.'];
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'error' => 'Could not initialize Firebase verification.'];
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!is_string($response) || $response === '' || $status !== 200) {
        logModuleCall('facebook_pixel', 'firebase_token_verify', ['http_status' => $status], $curlError !== '' ? $curlError : 'Firebase rejected token');
        return ['ok' => false, 'error' => 'Firebase could not verify this OTP session. Please try again.'];
    }

    $data = json_decode($response, true);
    $user = is_array($data) && isset($data['users'][0]) && is_array($data['users'][0]) ? $data['users'][0] : null;
    if (!$user) {
        return ['ok' => false, 'error' => 'Firebase returned no verified user.'];
    }

    $phone = facebook_pixel_normalize_e164(isset($user['phoneNumber']) ? (string) $user['phoneNumber'] : null);
    if ($phone === null) {
        return ['ok' => false, 'error' => 'The Firebase account does not contain a verified phone number.'];
    }

    return [
        'ok' => true,
        'phone' => $phone,
        'firebase_uid' => isset($user['localId']) ? (string) $user['localId'] : '',
    ];
}
