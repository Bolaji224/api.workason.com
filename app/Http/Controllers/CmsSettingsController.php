<?php

namespace App\Http\Controllers;

use App\Models\CmsSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CmsSettingsController extends Controller
{
    // ── Cache Configuration ──────────────────────────────────────────────────

    /**
     * Cache key for the full settings map (all modules combined).
     * Cleared whenever any module is updated.
     */
    const CACHE_KEY_ALL = 'cms_settings_all';

    /**
     * Per-module cache key prefix.
     * Full key is e.g. 'cms_module_homepage'.
     */
    const CACHE_KEY_MODULE_PREFIX = 'cms_module_';

    /**
     * Cache TTL in seconds. 3600 = 1 hour.
     * Driver-agnostic — works with file (current) or Redis (future).
     */
    const CACHE_TTL = 3600;

    // ── Public Endpoints (no auth required) ──────────────────────────────────

    /**
     * GET /api/v1/cms/settings
     *
     * Returns all CMS modules as a flat key→content map.
     * This is the primary endpoint consumed by the kason frontend
     * on application startup to hydrate the Redux CMS slice.
     *
     * Response shape:
     * {
     *   "status": "success",
     *   "data": {
     *     "homepage": { "hero_title": "...", ... },
     *     "employer_dashboard": { ... },
     *     ...
     *   }
     * }
     */
    public function index()
    {
        $data = Cache::remember(self::CACHE_KEY_ALL, self::CACHE_TTL, function () {
            return CmsSetting::all(['module', 'content'])
                ->pluck('content', 'module')
                ->toArray();
        });

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    /**
     * GET /api/v1/cms/settings/{module}
     *
     * Returns a single module's content.
     * Useful for targeted refetches or server-side rendering of one section.
     *
     * Response shape:
     * {
     *   "status": "success",
     *   "data": { "hero_title": "...", ... }
     * }
     */
    public function show(string $module)
    {
        if (!$this->isValidModuleName($module)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid module name. Use lowercase letters and underscores only (e.g. homepage, employer_dashboard).',
            ], 422);
        }

        $cacheKey = self::CACHE_KEY_MODULE_PREFIX . $module;

        $setting = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($module) {
            return CmsSetting::where('module', $module)->first(['module', 'content']);
        });

        if (!$setting) {
            return response()->json([
                'status'  => 'error',
                'message' => "CMS module '{$module}' not found.",
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $setting->content,
        ]);
    }

    // ── Admin Endpoints (admin.auth middleware required) ──────────────────────

    /**
     * GET /api/v1/admin/cms/settings
     *
     * Returns all modules with full metadata (id, version, updated_by,
     * timestamps). Used by the Admin Dashboard CMS editor to populate
     * the module list and display last-saved information.
     *
     * Response shape:
     * {
     *   "status": "success",
     *   "data": [
     *     { "id": 1, "module": "homepage", "content": {...}, "version": "1.0.3", ... }
     *   ]
     * }
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
     *
     * Creates or updates a CMS module's content, then invalidates
     * the relevant cache entries so the next public request fetches
     * fresh data.
     *
     * This route bypasses XssSanitizer (see routes/api.php) because CKEditor
     * fields contain legitimate HTML. Sanitization is applied here instead:
     *   - Fields ending in '_html': dangerous tags are stripped, safe HTML preserved.
     *   - All other string fields: strip_tags() applied (same as XssSanitizer).
     *   - Boolean fields: cast to bool, no string sanitization.
     *
     * Note on updated_by: The AdminAuth middleware sets $request->user()
     * only when authenticating via Bearer token. API-key auth does not
     * set a user resolver, so updated_by is stored as null in that case.
     *
     * Response shape (success):
     * {
     *   "status": "success",
     *   "message": "CMS module 'homepage' updated successfully.",
     *   "data": { "hero_title": "New Title", ... },
     *   "version": "1.0.4"
     * }
     */
    public function update(Request $request, string $module)
    {
        // Validate module name format before touching the database.
        if (!$this->isValidModuleName($module)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid module name. Use lowercase letters and underscores only (e.g. homepage, employer_dashboard).',
            ], 422);
        }

        // Validate request body.
        $request->validate([
            'content' => 'required|array',
        ]);

        // Apply selective field sanitization (XssSanitizer is bypassed for this route).
        $content = $this->sanitizeContent($request->input('content'));

        // Reject empty content arrays — they would wipe all module fields.
        if (empty($content)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'The content field must not be empty.',
            ], 422);
        }

        try {
            $nextVersion = $this->nextVersion($module);

            // PHP 7.3-compatible null check (no null-safe ?-> operator).
            $adminId = $request->user() ? $request->user()->id : null;

            $setting = CmsSetting::updateOrCreate(
                ['module' => $module],
                [
                    'content'    => $content,
                    'version'    => $nextVersion,
                    'updated_by' => $adminId,
                ]
            );

            // Invalidate both the combined cache and this module's individual cache.
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
                'trace'  => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update CMS module. Please try again.',
            ], 500);
        }
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Sanitizes each field in the content map based on its key type.
     *
     * - Keys ending in '_html': strip only dangerous tags (script, iframe, etc.)
     *   while preserving the safe HTML markup produced by CKEditor.
     * - Boolean values: cast to bool, skip string sanitization.
     * - All other string values: strip_tags() (same behaviour as XssSanitizer).
     *
     * @param  array<string, mixed> $content
     * @return array<string, mixed>
     */
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
                // Reject unexpected types (arrays, objects) silently.
                $sanitized[$key] = '';
            }
        }

        return $sanitized;
    }

    /**
     * Strips dangerous HTML tags while preserving the safe formatting
     * tags output by CKEditor Classic Build.
     *
     * Allowed tags cover CKEditor's full standard toolbar output.
     * <script>, <iframe>, <object>, <embed>, <form>, and event-handler
     * attributes are not in the allowed list and will be stripped.
     */
    private function sanitizeHtml(string $html): string
    {
        $allowed = '<p><br><strong><em><u><s><ul><ol><li><blockquote>'
            . '<h1><h2><h3><h4><h5><h6><a><img><table><thead><tbody>'
            . '<tr><th><td><span><div><figure><figcaption>';

        return strip_tags($html, $allowed);
    }

    /**
     * Validates that a module name only contains lowercase letters and
     * underscores, starts with a letter, and is between 2–100 characters.
     *
     * Valid:   homepage, employer_dashboard, global, footer_v2
     * Invalid: HomePage, -footer, 123module, dashboard settings
     */
    private function isValidModuleName(string $module): bool
    {
        return (bool) preg_match('/^[a-z][a-z_0-9]{1,99}$/', $module);
    }

    /**
     * Calculates the next semantic patch version for a module.
     *
     * Examples:
     *   1.0.0  →  1.0.1
     *   1.0.9  →  1.0.10
     *   null   →  1.0.0  (first save — module didn't exist yet)
     *   corrupt →  1.0.0  (defensive fallback)
     */
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
