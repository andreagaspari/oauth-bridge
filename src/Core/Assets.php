<?php
namespace Immaginificio\OAuthProxyBridge\Core;

/**
 * Simple PHP Asset Manager
 * - enqueue styles/scripts with handles
 * - print styles in head and scripts in footer
 * - add cache-busting based on file mtime
 * 
 * @package Immaginificio\OAuthProxyBridge\Core
 * @since 0.0.1
 */
class Assets
{
    protected static array $styles = [];
    protected static array $scripts = [];
    protected static bool $printedHead = false;
    // default component directories (can be overridden per-call)
    protected const COMPONENT_CSS_DIR = '/assets/css/components';
    protected const COMPONENT_JS_DIR = '/assets/js/components';
    /**
     * Component dependency map. When a component is enqueued via `enqueueComponents`,
     * any entries listed here will also be enqueued automatically to relieve callers
     * from remembering low-level dependencies (eg. pager needs button + select).
     * Keys: component name, Value: array of component names
     */
    protected const COMPONENT_DEP_MAP = [
        'pager' => ['button', 'select'],
        'modal' => ['button'],
        // 'filters' aggregates all assets required by the inline/mobile filters UI
        'filters' => ['modal', 'select', 'autocomplete', 'date-range', 'filter-group', 'filters-modal'],
    ];

    public static function enqueueStyle(string $handle, string $path, array $deps = []): void
    {
        self::$styles[$handle] = ['path' => $path, 'deps' => $deps];
    }

    /**
     * Enqueue a stylesheet by handle and path.
     *
     * @param string $handle Unique handle for the stylesheet
     * @param string $path   Web path to the stylesheet (eg. '/assets/css/foo.css')
     * @param array  $deps   Optional dependency handles
     * @return void
     * @since 0.0.1
     */

    /**
     * Enqueue a component stylesheet by name. Example: 'button' -> /assets/css/components/button.css
     * Accepts a single component name or an array of names.
     */
    public static function enqueueComponentStyle(string|array $component, ?string $baseDir = null): void
    {
        $base = $baseDir ?? self::COMPONENT_CSS_DIR;
        $items = is_array($component) ? $component : [$component];
        foreach ($items as $name) {
            $handle = 'c-' . str_replace(['/', '\\', '.'], '-', $name);
            $path = self::normalizeComponentPath($name, $base, 'css');
            // if path is remote URL, enqueue as-is; otherwise check filesystem existence
            if (preg_match('#^https?://#', $path)) {
                self::enqueueStyle($handle, $path, []);
                continue;
            }
            $fs = self::webToFilesystem($path);
            if ($fs && file_exists($fs)) {
                self::enqueueStyle($handle, $path, []);
            }
        }
    }

    /**
     * Enqueue one or more component stylesheet(s) by component name.
     * Example: 'button' -> '/assets/css/components/button.css'
     *
     * @param string|array $component Component name or list of names
     * @param string|null  $baseDir   Optional base directory override
     * @return void
     * @since 0.0.1
     */

    public static function enqueueScript(string $handle, string $path, array $deps = [], bool $inFooter = true, array $attrs = []): void
    {
        self::$scripts[$handle] = ['path' => $path, 'deps' => $deps, 'in_footer' => $inFooter, 'attrs' => $attrs];
    }

    /**
     * Enqueue a script by handle and path.
     *
     * @param string $handle   Unique handle for the script
     * @param string $path     Web path to the script (eg. '/assets/js/foo.js')
     * @param array  $deps     Optional dependency handles
     * @param bool   $inFooter Whether to print in footer (true) or head (false)
     * @return void
     * @since 0.0.1
     */

    /**
     * Enqueue a component script by name. Example: 'toggle-password' -> /assets/js/components/toggle-password.js
     * Accepts a single component name or an array of names.
     */
    public static function enqueueComponentScript(string|array $component, ?string $baseDir = null, array $deps = [], bool $inFooter = true): void
    {
        $base = $baseDir ?? self::COMPONENT_JS_DIR;
        $items = is_array($component) ? $component : [$component];
        foreach ($items as $name) {
            $handle = 'c-' . str_replace(['/', '\\', '.'], '-', $name);
            $path = self::normalizeComponentPath($name, $base, 'js');
            if (preg_match('#^https?://#', $path)) {
                self::enqueueScript($handle, $path, $deps, $inFooter);
                continue;
            }
            $fs = self::webToFilesystem($path);
            if ($fs && file_exists($fs)) {
                self::enqueueScript($handle, $path, $deps, $inFooter);
            }
        }
    }

    /**
     * Enqueue one or more component script(s) by component name.
     * Example: 'toggle-password' -> '/assets/js/components/toggle-password.js'
     *
     * @param string|array $component Component name or list of names
     * @param string|null  $baseDir   Optional base directory override
     * @param array        $deps      Optional dependency handles
     * @param bool         $inFooter  Whether to print in footer
     * @return void
     * @since 0.0.1
     */

    /**
     * Enqueue a batch/list of components. Each entry can be:
     * - a string component name (enqueues both css and js using conventions),
     * - or an associative array with keys: name, css (bool), js (bool), deps (array), in_footer (bool)
     *
     * Examples:
     *  Assets::enqueueComponents(['button','toggle-password']);
     *  Assets::enqueueComponents([['name'=>'modal','css'=>true,'js'=>true,'deps'=>['c-toggle-password']]]);
     */
    public static function enqueueComponents(array $components, ?string $cssBase = null, ?string $jsBase = null, array $defaultDeps = [], bool $defaultInFooter = true): void
    {
        foreach ($components as $item) {
            if (is_string($item)) {
                $name = $item;
                $css = true; $js = true; $deps = $defaultDeps; $inFooter = $defaultInFooter;
            } elseif (is_array($item)) {
                $name = $item['name'] ?? ($item[0] ?? null);
                if (!$name) continue;
                $css = array_key_exists('css', $item) ? (bool)$item['css'] : true;
                $js = array_key_exists('js', $item) ? (bool)$item['js'] : true;
                $deps = $item['deps'] ?? $defaultDeps;
                $inFooter = array_key_exists('in_footer', $item) ? (bool)$item['in_footer'] : $defaultInFooter;
            } else {
                continue;
            }

            if ($css) {
                // enqueue component CSS for the component and any mapped dependencies
                // (dependency mapping is optional and keeps callers simpler)
                if (array_key_exists($name, (array)self::COMPONENT_DEP_MAP)) {
                    foreach (self::COMPONENT_DEP_MAP[$name] as $dep) {
                        self::enqueueComponentStyle($dep, $cssBase);
                    }
                }
                self::enqueueComponentStyle($name, $cssBase);
            }
            if ($js) {
                // enqueue component JS for mapped dependencies as well
                if (array_key_exists($name, (array)self::COMPONENT_DEP_MAP)) {
                    foreach (self::COMPONENT_DEP_MAP[$name] as $dep) {
                        self::enqueueComponentScript($dep, $jsBase, [], $inFooter);
                    }
                }
                self::enqueueComponentScript($name, $jsBase, $deps, $inFooter);
            }
        }
    }

    /**
     * Enqueue a batch/list of components. Each entry can be:
     * - a string component name (enqueues both css and js using conventions),
     * - or an associative array with keys: name, css (bool), js (bool), deps (array), in_footer (bool)
     *
     * @param array       $components     List of component names or config arrays
     * @param string|null $cssBase        Optional css base dir override
     * @param string|null $jsBase         Optional js base dir override
     * @param array       $defaultDeps    Default deps for component scripts
     * @param bool        $defaultInFooter Default in_footer value
     * @return void
     * @since 0.0.1
     */

    protected static function resolveUrlWithVersion(string $path): string
    {
        // if absolute URL, return as-is
        if (preg_match('#^https?://#', $path)) return $path;
        $doc = $_SERVER['DOCUMENT_ROOT'] ?? getcwd();
        $file = rtrim($doc, '/') . (strpos($path, '/') === 0 ? $path : '/' . ltrim($path, '/'));
        if (file_exists($file)) {
            $mtime = filemtime($file);
            return $path . '?v=' . $mtime;
        }
        return $path;
    }

    /**
     * Resolve a local URL and append a `?v=<mtime>` cache-busting query when the file exists.
     * If the path is an absolute URL, it is returned unchanged.
     *
     * @param string $path
     * @return string
     * @since 0.0.1
     */

    /**
     * Normalize a component name into a file path under base dir.
     * If the provided name already looks like a path or url, return it unchanged.
     */
    protected static function normalizeComponentPath(string $name, string $base, string $ext): string
    {
        // if looks like absolute url or starts with /, return as-is or ensure extension
        if (preg_match('#^https?://#', $name)) return $name;
        if (strpos($name, '/') === 0) {
            // has leading slash — assume full path
            return preg_match('/\\.' . preg_quote($ext, '/') . '$/', $name) ? $name : rtrim($name, '/') . '.' . $ext;
        }
        // if already has extension
        if (preg_match('/\\.(' . preg_quote($ext, '/') . ')$/', $name)) {
            return rtrim($base, '/') . '/' . ltrim($name, '/');
        }
        return rtrim($base, '/') . '/' . $name . '.' . $ext;
    }

    /**
     * Convert a web path (eg. '/assets/js/foo.js' or 'assets/js/foo.js') to a filesystem path
     * using `$_SERVER['DOCUMENT_ROOT']` when available, otherwise `getcwd()`.
     * Returns the filesystem path string.
     *
     * @param string $webPath
     * @return string
     * @since 0.0.1
     */
    protected static function webToFilesystem(string $webPath): string
    {
        $doc = $_SERVER['DOCUMENT_ROOT'] ?? getcwd();
        // normalize
        if (strpos($webPath, '/') === 0) {
            return rtrim($doc, '/') . $webPath;
        }
        return rtrim($doc, '/') . '/' . ltrim($webPath, '/');
    }

    public static function printStyles(): void
    {
        // prevent double-print
        if (self::$printedHead) return;
        foreach (self::$styles as $h => $meta) {
            $url = self::resolveUrlWithVersion($meta['path']);
            echo '<link rel="stylesheet" href="' . htmlspecialchars($url) . '">'."\n";
        }
        self::$printedHead = true;
    }

    public static function printScripts(bool $inFooter = true): void
    {
        foreach (self::$scripts as $h => $meta) {
            if (($meta['in_footer'] ?? true) !== $inFooter) continue;
            $url = self::resolveUrlWithVersion($meta['path']);
            $attrs = '';
            if (!empty($meta['attrs']) && is_array($meta['attrs'])) {
                foreach ($meta['attrs'] as $k => $v) {
                    // handle boolean attributes like 'defer' or 'async'
                    if ($v === true || $v === 1) {
                        $attrs .= ' ' . htmlspecialchars($k);
                    } else {
                        $attrs .= ' ' . htmlspecialchars($k) . '="' . htmlspecialchars((string)$v) . '"';
                    }
                }
            }
            echo '<script src="' . htmlspecialchars($url) . '"' . $attrs . '></script>' . "\n";
        }
    }
}
