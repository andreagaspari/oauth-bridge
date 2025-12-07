<?php
namespace Immaginificio\OAuthProxyBridge\Core;

/**
 * Simple SVG inliner
 *
 * Reads SVG files from `assets/imgs/` and returns inline SVG markup so icons
 * can be styled with CSS. Use `Svg::inline('name.svg', ['class' => 'icon'])`.
 *
 * @package Immaginificio\OAuthProxyBridge\Core
 * @since 0.0.1
 */
class Svg
{
    protected static array $cache = [];

    /**
     * Inline an SVG file from /assets/imgs/
     * @param string $file filename or path (like 'menu.svg' or '/assets/imgs/menu.svg')
     * @param array $attrs attributes to add to the root <svg> element
     * @return string raw svg markup or an empty string if not found
     */
    public static function inline(string $file, array $attrs = []): string
    {
        $path = self::resolvePath($file);
        if (!$path || !file_exists($path)) return '';

        if (isset(self::$cache[$path])) {
            $svg = self::$cache[$path];
        } else {
            $svg = (string)file_get_contents($path);
            // remove XML prolog
            $svg = preg_replace('/^\s*<\?xml.*?\?>/s', '', $svg);
            // strip DOCTYPE
            $svg = preg_replace('/<!DOCTYPE.*?>/is', '', $svg);
            $svg = trim($svg);
            self::$cache[$path] = $svg;
        }

        // inject attributes into opening <svg ...>
        if ($attrs) {
            $svg = preg_replace_callback('/^<svg(\s[^>]*)?>/i', function ($m) use ($attrs) {
                $existing = $m[1] ?? '';
                $map = [];
                // parse existing attributes into map
                if ($existing) {
                    preg_match_all('/(\S+)=\"([^\"]*)\"/', $existing, $matches, PREG_SET_ORDER);
                    foreach ($matches as $mm) $map[$mm[1]] = $mm[2];
                }
                // merge/override with provided attrs
                foreach ($attrs as $k => $v) $map[$k] = $v;
                $parts = [];
                foreach ($map as $k => $v) $parts[] = $k . '="' . htmlspecialchars((string)$v, ENT_QUOTES) . '"';
                return '<svg ' . implode(' ', $parts) . '>';
            }, $svg, 1);

            // Fallback: if some attrs were not injected (rare cases due to SVG formatting),
            // ensure missing attributes are added to the opening <svg> tag.
            $missing = [];
            foreach ($attrs as $k => $v) {
                if (!preg_match('/<svg[^>]*\b' . preg_quote($k, '/') . '\s*=/i', $svg)) {
                    $missing[$k] = $v;
                }
            }
            if ($missing) {
                $parts = [];
                foreach ($missing as $k => $v) $parts[] = $k . '="' . htmlspecialchars((string)$v, ENT_QUOTES) . '"';
                $ins = ' ' . implode(' ', $parts);
                $svg = preg_replace('/^<svg/i', '<svg' . $ins, $svg, 1);
            }
        }

        return $svg;
    }

    protected static function resolvePath(string $file): string|false
    {
        // if absolute URL, we cannot inline
        if (preg_match('#^https?://#', $file)) return false;
        // if given full web path starting with /assets, map to document root
        if (strpos($file, '/assets/') === 0) {
            $doc = $_SERVER['DOCUMENT_ROOT'] ?? getcwd();
            return rtrim($doc, '/') . $file;
        }
        // otherwise assume file under /assets/imgs/
        $doc = $_SERVER['DOCUMENT_ROOT'] ?? getcwd();
        return rtrim($doc, '/') . '/assets/imgs/' . ltrim($file, '/');
    }
}
