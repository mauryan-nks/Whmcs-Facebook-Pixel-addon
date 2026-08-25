# WHMCS Meta (Facebook) Pixel Addon

A lightweight WHMCS addon that injects the Meta Pixel into the WHMCS client area and tracks common ecommerce events.

## Compatibility

- WHMCS 9.0
- PHP 8.2 and PHP 8.3
- Uses `WHMCS\Database\Capsule` instead of removed legacy MySQL helpers

## Events

- `PageView`
- `AddToCart`
- `InitiateCheckout`

## Installation

1. Create a folder named `facebook_pixel` under `modules/addons/` in your WHMCS installation.
2. Upload these files into that folder:
   - `facebook_pixel.php`
   - `hooks.php`
   - `index.php`
3. Sign in to the WHMCS admin area.
4. Go to **Configuration > System Settings > Addon Modules**.
5. Activate **Meta (Facebook) Pixel**.
6. Configure the addon and enter your numeric Meta Pixel ID.

Example Pixel ID:

```text
123456789012345
```

Only numeric Pixel IDs are accepted by the frontend hook. Invalid values are ignored to prevent unsafe JavaScript/HTML injection.

## WHMCS 9.0 changes in version 2.0.0

- Removed legacy `select_query()` usage.
- Removed removed PHP `mysql_fetch_array()` usage.
- Migrated settings lookup to `WHMCS\Database\Capsule`.
- Added strict Pixel ID validation.
- Corrected the Meta `fbevents.js` URL.
- Added JavaScript guards before ecommerce event calls.
- Improved empty-cart handling.
- Added database failure handling and WHMCS module logging.
- Removed obsolete external setup-link dependency from the admin page.
- Updated module metadata and installation documentation.

## Security notes

The Pixel ID is treated as untrusted administrator-configured input. The addon validates it as digits only before rendering it into HTML or JavaScript. JavaScript string serialization uses `json_encode()` and the tracking URL uses URL encoding.

## Testing after upgrade

After upgrading the addon:

1. Clear WHMCS template/cache files if necessary.
2. Open a client-area page and verify `PageView` in Meta Events Manager or Meta Pixel Helper.
3. Add a product to the cart and verify `AddToCart`.
4. Enter checkout and verify `InitiateCheckout`.
5. Temporarily enter an invalid Pixel ID such as `abc<script>` and confirm no Meta Pixel code is rendered.

## License

See `LICENSE`.
