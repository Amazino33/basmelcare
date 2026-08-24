<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * Builds Cloudinary delivery URLs for product images.
 *
 * Deliberately free of the Cloudinary SDK: the public site displays images
 * but never uploads them, and a URL is only string assembly. Only the staff
 * app, which writes, pulls in the SDK.
 *
 * A Cloudinary image is addressed by its "public id" - the path with no file
 * extension - and resized by putting a transformation in the URL itself:
 *
 *   https://res.cloudinary.com/<cloud>/image/upload/<transform>/<public-id>.<ext>
 *
 * Nothing is generated ahead of time. The first request for a size renders
 * it, and every request after that is served from cache, which is why sizes
 * can be added later without reprocessing the catalogue.
 */
class CloudinaryImage
{
    /**
     * Named sizes, so the numbers live in one place rather than being
     * scattered through views as raw transformation strings.
     *
     * g_auto picks the crop around the subject rather than the centre, which
     * matters for product photos shot off-centre. q_auto chooses a quality
     * per image, and f_auto serves WebP or AVIF to browsers that accept them
     * and JPEG to those that do not - typically a large saving on the mobile
     * connections most customers are on.
     */
    public const PRESETS = [
        // c_pad, not c_fill. Product photography is portrait - bottles, boxes,
        // tall containers - and cropping one to a square cuts off the top and
        // bottom of the packaging, which is the part a customer recognises.
        // Padding onto white keeps the whole product visible and gives a grid
        // of mismatched supplier photos one consistent ground.
        'thumb' => 'w_100,h_100,c_pad,b_white,q_auto,f_auto',
        'card'  => 'w_400,h_400,c_pad,b_white,q_auto,f_auto',
        // No crop at all: the product page shows the photo as taken.
        'zoom'  => 'w_1200,c_limit,q_auto,f_auto',
    ];

    /**
     * Settings live in the database, and imageUrl() is called once per
     * product in a grid. Without memoising, a page of 24 products would run
     * dozens of identical queries.
     */
    private static ?array $config = null;

    public static function config(): array
    {
        return static::$config ??= [
            'enabled'    => AppSetting::bool('cloudinary_enabled', false),
            'cloud_name' => trim((string) AppSetting::get('cloudinary_cloud_name', '')),
            'api_key'    => trim((string) AppSetting::get('cloudinary_api_key', '')),
            'api_secret' => trim((string) AppSetting::get('cloudinary_api_secret', '')),
            'folder'     => trim((string) AppSetting::get('cloudinary_folder', 'basmelcare'), '/ '),
        ];
    }

    /** Drop the memo after settings change, and between tests. */
    public static function forget(): void
    {
        static::$config = null;
    }

    /**
     * Credentials alone are not enough to serve an image, and an enabled
     * flag with no cloud name would point every URL at a host that does not
     * exist. Both must be present.
     */
    public static function enabled(): bool
    {
        $config = static::config();

        return $config['enabled'] && $config['cloud_name'] !== '';
    }

    /** Enabled, and able to upload - which additionally needs the key pair. */
    public static function canUpload(): bool
    {
        $config = static::config();

        return static::enabled() && $config['api_key'] !== '' && $config['api_secret'] !== '';
    }

    public static function folder(): string
    {
        return static::config()['folder'];
    }

    /**
     * "products/abc.jpg" becomes "basmelcare/products/abc".
     *
     * The extension is dropped because Cloudinary treats it as a delivery
     * format rather than part of the identity - asking for the same public
     * id with .webp returns a converted copy. Keeping it in the id produces
     * "abc.jpg.jpg" in the URL, which resolves to nothing.
     */
    public static function publicId(string $path): string
    {
        $path = ltrim($path, '/');

        $directory = pathinfo($path, PATHINFO_DIRNAME);
        $filename  = pathinfo($path, PATHINFO_FILENAME);

        $id = ($directory && $directory !== '.')
            ? $directory . '/' . $filename
            : $filename;

        $folder = static::folder();

        return $folder ? $folder . '/' . $id : $id;
    }

    /**
     * @param  string|null  $preset  A key of self::PRESETS, or null for the
     *                               image as uploaded. An unknown preset is
     *                               ignored rather than fatal: a mistyped
     *                               size should degrade to a working image,
     *                               not a broken page.
     */
    public static function url(string $path, ?string $preset = null): string
    {
        $extension    = pathinfo($path, PATHINFO_EXTENSION);
        $transformation = $preset === null ? null : (static::PRESETS[$preset] ?? null);

        return 'https://res.cloudinary.com/' . static::config()['cloud_name'] . '/image/upload/'
            . ($transformation ? $transformation . '/' : '')
            . static::publicId($path)
            . ($extension ? '.' . $extension : '');
    }

    /**
     * The one way to turn a stored image path into a URL.
     *
     * Views that hold a Product call $product->imageUrl(), which comes here.
     * Views that hold only a path - a cart line, an upload preview - call
     * this directly, so both routes obey the Cloudinary switch identically.
     */
    public static function deliver(?string $path, ?string $preset = null): ?string
    {
        if (! $path) {
            return null;
        }

        if (static::enabled()) {
            return static::url($path, $preset);
        }

        return static::localUrl($path);
    }

    /** Cloudinary off: this site's own storage, served through public/storage. */
    private static function localUrl(string $path): string
    {
        return asset('storage/' . ltrim($path, '/'));
    }
}
