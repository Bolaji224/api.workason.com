<?php

namespace App\Http\Controllers;

use App\Models\CmsSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CmsSettingsController extends Controller
{
    // ── Cache Configuration ──────────────────────────────────────────────────

    const CACHE_KEY_ALL           = 'cms_settings_all';
    const CACHE_KEY_MODULE_PREFIX = 'cms_module_';
    const CACHE_TTL               = 3600;

    // Browser cache TTL (5 minutes). Shorter than Redis TTL so browsers
    // revalidate frequently while Redis absorbs the load.
    const HTTP_CACHE_TTL = 300;

    // ── Public Endpoints ─────────────────────────────────────────────────────

    /**
     * GET /api/v1/cms/settings
     */
    public function index()
    {
        $data = $this->rememberSafe(self::CACHE_KEY_ALL, self::CACHE_TTL, function () {
            return CmsSetting::all(['module', 'content'])
                ->pluck('content', 'module')
                ->toArray();
        });

        $etag        = '"' . md5(serialize($data)) . '"';
        $lastModified = gmdate('D, d M Y H:i:s', time()) . ' GMT';

        // Support 304 Not Modified.
        if (request()->header('If-None-Match') === $etag) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'public, max-age=' . self::HTTP_CACHE_TTL);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ])
        ->header('Cache-Control', 'public, max-age=' . self::HTTP_CACHE_TTL . ', stale-while-revalidate=60')
        ->header('ETag', $etag)
        ->header('Last-Modified', $lastModified)
        ->header('Vary', 'Accept-Encoding');
    }

    /**
     * GET /api/v1/cms/settings/{module}
     */
    public function show(string $module)
    {
        if (!$this->isValidModuleName($module)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid module name. Use lowercase letters and underscores only.',
            ], 422);
        }

        $cacheKey = self::CACHE_KEY_MODULE_PREFIX . $module;

        // Cache a plain array, not an Eloquent model, to avoid serialization
        // overhead and keep the cached value driver-agnostic.
        $content = $this->rememberSafe($cacheKey, self::CACHE_TTL, function () use ($module) {
            $row = CmsSetting::where('module', $module)->first(['content']);
            return $row ? $row->content : null;
        });

        if ($content === null) {
            return response()->json([
                'status'  => 'error',
                'message' => "CMS module '{$module}' not found.",
            ], 404);
        }

        $etag        = '"' . md5(serialize($content)) . '"';
        $lastModified = gmdate('D, d M Y H:i:s', time()) . ' GMT';

        if (request()->header('If-None-Match') === $etag) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'public, max-age=' . self::HTTP_CACHE_TTL);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $content,
        ])
        ->header('Cache-Control', 'public, max-age=' . self::HTTP_CACHE_TTL . ', stale-while-revalidate=60')
        ->header('ETag', $etag)
        ->header('Last-Modified', $lastModified)
        ->header('Vary', 'Accept-Encoding');
    }

    // ── Admin Endpoints ───────────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/cms/settings
     */
    public function adminIndex()
    {
        $settings = CmsSetting::orderBy('module')->get();

        return response()->json([
            'status' => 'success',
            'data'   => $settings,
        ]);
    }

    /**
     * PUT /api/v1/admin/cms/settings/{module}
     */
    public function update(Request $request, string $module)
    {
        if (!$this->isValidModuleName($module)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid module name. Use lowercase letters and underscores only.',
            ], 422);
        }

        $request->validate([
            'content' => 'required|array',
        ]);

        $content = $this->sanitizeContent($request->input('content'));

        if (empty($content)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'The content field must not be empty.',
            ], 422);
        }

        try {
            $nextVersion = $this->nextVersion($module);
            $adminId     = $request->user() ? $request->user()->id : null;

            $setting = CmsSetting::updateOrCreate(
                ['module' => $module],
                [
                    'content'    => $content,
                    'version'    => $nextVersion,
                    'updated_by' => $adminId,
                ]
            );

            // Invalidate both cache entries. Safe to call even if Redis is down
            // — Cache::forget() will silently use the file driver fallback.
            Cache::forget(self::CACHE_KEY_ALL);
            Cache::forget(self::CACHE_KEY_MODULE_PREFIX . $module);

            Log::info('CMS module updated', [
                'module'     => $module,
                'version'    => $nextVersion,
                'updated_by' => $adminId,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => "CMS module '{$module}' updated successfully.",
                'data'    => $setting->content,
                'version' => $setting->version,
            ]);

        } catch (\Exception $e) {
            Log::error('CMS module update failed', [
                'module' => $module,
                'error'  => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update CMS module. Please try again.',
            ], 500);
        }
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Redis-safe Cache::remember wrapper.
     *
     * If the configured cache driver throws (e.g. Redis is unreachable),
     * this method catches the exception, logs a warning, and calls the
     * callback directly so the DB query still runs and the response is
     * returned. The application stays online during a Redis outage.
     *
     * @template T
     * @param  string   $key
     * @param  int      $ttl
     * @param  callable $callback
     * @return T
     */
    private function rememberSafe(string $key, int $ttl, callable $callback)
    {
        try {
            return Cache::remember($key, $ttl, $callback);
        } catch (\Exception $e) {
            Log::warning('CMS cache unavailable — falling back to DB', [
                'key'   => $key,
                'error' => $e->getMessage(),
            ]);
            return $callback();
        }
    }

    private function sanitizeContent(array $content): array
    {
        $sanitized = [];

        foreach ($content as $key => $value) {
            if (is_bool($value)) {
                $sanitized[$key] = $value;
            } elseif (is_string($value) && substr($key, -5) === '_html') {
                $sanitized[$key] = $this->sanitizeHtml($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = strip_tags($value);
            } else {
                $sanitized[$key] = '';
            }
        }

        return $sanitized;
    }

    private function sanitizeHtml(string $html): string
    {
        $allowed = '<p><br><strong><em><u><s><ul><ol><li><blockquote>'
            . '<h1><h2><h3><h4><h5><h6><a><img><table><thead><tbody>'
            . '<tr><th><td><span><div><figure><figcaption>';

        return strip_tags($html, $allowed);
    }

    private function isValidModuleName(string $module): bool
    {
        return (bool) preg_match('/^[a-z][a-z_0-9]{1,99}$/', $module);
    }

    private function nextVersion(string $module): string
    {
        $current = CmsSetting::where('module', $module)->value('version');

        if (!$current) {
            return '1.0.0';
        }

        $parts = explode('.', $current);

        if (count($parts) !== 3 || !is_numeric($parts[2])) {
            return '1.0.0';
        }

        return $parts[0] . '.' . $parts[1] . '.' . ((int) $parts[2] + 1);
    }
}