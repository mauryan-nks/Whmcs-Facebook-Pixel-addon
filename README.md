# WHMCS Meta Pixel + Firebase MFA Addon

A lightweight WHMCS addon for Meta Pixel ecommerce tracking plus optional Firebase Authentication SMS OTP as a second factor for WHMCS client logins.

## Compatibility

- WHMCS 9.0
- PHP 8.2 and PHP 8.3
- Firebase Web Authentication (Phone)
- Uses `WHMCS\Database\Capsule` instead of removed legacy MySQL helpers

## Meta Pixel events

- `PageView`
- `AddToCart`
- `InitiateCheckout`

## Installation

Create a folder named `facebook_pixel` under `modules/addons/` and upload the complete repository contents, including:

- `facebook_pixel.php`
- `hooks.php`
- `index.php`
- `lib/firebase_mfa.php`
- `templates/mfa.tpl`

Then sign in to WHMCS Admin and open **Configuration > System Settings > Addon Modules**. Activate **Meta Pixel + Firebase MFA** and configure the required features.

## Meta Pixel configuration

Enter your numeric Meta Pixel ID. Example:

```text
123456789012345
```

Only numeric Pixel IDs are accepted by the frontend hook. Invalid values are ignored to prevent unsafe JavaScript/HTML injection.

## Firebase phone MFA configuration

The Firebase integration is optional. When enabled, WHMCS still performs the primary username/password authentication. The addon then gates the authenticated client area until the account's WHMCS phone number is verified through Firebase SMS OTP.

### Firebase Console

1. Create or select a Firebase project.
2. Register a **Web app** in that project.
3. Open **Authentication** and enable the **Phone** sign-in provider.
4. In Firebase Authentication settings, add your WHMCS hostname to **Authorized domains**.
5. Configure the Firebase SMS region policy to allow only countries you intend to serve.
6. Copy these Web app configuration values into the addon:
   - `apiKey`
   - `authDomain`
   - `projectId`
   - `appId`
   - `messagingSenderId` (optional)
7. Enable **Firebase Phone MFA** in the addon settings.

Do **not** upload or paste a Firebase service-account JSON/private key. This addon uses the Firebase Web configuration plus server-side validation of the Firebase ID token.

### WHMCS client phone numbers

Client phone numbers must be stored in E.164 format so the Firebase-verified phone can be compared exactly to the WHMCS account phone.

Examples:

```text
+919876543210
+14155552671
```

If the phone is missing or not valid E.164, the client is not allowed to complete Firebase OTP and is shown a configuration error instead.

## MFA security flow

1. WHMCS completes the normal client login.
2. The addon detects the authenticated client session and redirects it to the MFA challenge page.
3. Firebase Web Authentication uses reCAPTCHA and sends the SMS OTP.
4. The client enters the OTP and Firebase returns an ID token.
5. The addon sends that ID token from the WHMCS server to Firebase Authentication's `accounts:lookup` endpoint.
6. The phone number returned by Firebase must exactly match the WHMCS client's E.164 phone number.
7. Only then is the WHMCS session marked as MFA-verified.
8. MFA state is cleared on logout and is bound to the WHMCS client ID.

The browser reporting a successful OTP is never trusted by itself.

## Version 2.1.0

- Added Firebase Web app configuration to the addon.
- Added optional Firebase SMS OTP second-factor enforcement.
- Added a dedicated WHMCS client-area OTP page.
- Added Firebase reCAPTCHA integration.
- Added server-side Firebase ID-token validation.
- Added exact Firebase/WHMCS phone-number matching.
- Added E.164 validation and masked phone display.
- Added addon-specific CSRF protection for token submission.
- Added MFA session binding and logout cleanup.
- Pinned browser CDN imports to Firebase JS SDK `12.17.1`.

## WHMCS 9.0 changes introduced in version 2.0.0

- Removed legacy `select_query()` usage.
- Removed removed PHP `mysql_fetch_array()` usage.
- Migrated settings lookup to `WHMCS\Database\Capsule`.
- Added strict Pixel ID validation.
- Corrected the Meta `fbevents.js` URL.
- Added JavaScript guards before ecommerce event calls.
- Improved empty-cart handling.
- Added database failure handling and WHMCS module logging.
- Removed obsolete external setup-link dependency from the admin page.

## Testing after upgrade

### Meta Pixel

1. Clear WHMCS template/cache files if necessary.
2. Open a client-area page and verify `PageView` in Meta Events Manager or Meta Pixel Helper.
3. Add a product to the cart and verify `AddToCart`.
4. Enter checkout and verify `InitiateCheckout`.
5. Temporarily enter an invalid Pixel ID such as `abc<script>` and confirm no Meta Pixel code is rendered.

### Firebase MFA

1. Use a Firebase test phone number first if possible.
2. Save the matching E.164 number on the WHMCS client account.
3. Enable Firebase Phone MFA.
4. Log out fully, then sign in as that WHMCS client.
5. Confirm the client is redirected to `index.php?m=facebook_pixel`.
6. Complete reCAPTCHA and request the OTP.
7. Enter a wrong OTP and confirm access remains blocked.
8. Enter the correct OTP and confirm the client reaches `clientarea.php`.
9. Change the WHMCS phone to a different number and confirm a Firebase token for the old number is rejected.
10. Log out and sign in again to confirm MFA is required again.

## Operational notes

Firebase may throttle excessive SMS attempts and has project quotas. Configure SMS region restrictions and monitor Firebase Authentication usage to reduce abuse and unexpected cost.

Phone/SMS authentication is weaker than phishing-resistant MFA such as passkeys or security keys. Use it as a second factor rather than a password replacement.

## License

See `LICENSE`.
