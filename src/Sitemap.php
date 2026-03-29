<?php

declare(strict_types=1);

namespace Leaf;

/**
 * Sitemap Generator
 * ----------------------
 * A simple and efficient sitemap generator for Leaf apps
 *
 * @author Michael Darko
 * @since 4.x
 */
class Sitemap
{
    /**
     * Routes to build sitemap with
     * @var array
     */
    public static $sitemap = [];

    /**
     * Mappings for dynamic routes in the sitemap
     * @var array
     */
    public static $mappings = [];

    /**
     * Add a datasource for generating the sitemap. This allows you to include dynamic URLs from databases, APIs, or other sources.
     * @param callable $datasource A callback function that fetches data and adds URLs to the sitemap.
     * @return void
     */
    public static function source(callable $datasource)
    {
        app()->hook('router.before', $datasource);
    }

    /**
     * Replace an existing route in the sitemap generator. This is useful if you want to switch a dynamic route param with all possible values from a datasource, ensuring that all relevant URLs are included in the sitemap.
     * @param string $route The route pattern to replace (e.g., '/blog/{slug}').
     * @param string|array $newUrl The new URL or an array of URLs to replace the route with (e.g., '/blog/post-1' or ['/blog/post-1' => [...], '/blog/post-2' => [...]] ).
     * @param array $values An array of values to replace the route parameter with (e.g., ['post-1', 'post-2']).
     * @return void
     */
    public static function map(string $route, $newUrl, array $values = [])
    {
        if (is_string($newUrl)) {
            $values['loc'] = $newUrl;
            self::$mappings[$route][] = $values;
            return;
        }

        if (isset($newUrl['loc'])) {
            self::$mappings[$route][] = $newUrl;
            return;
        }

        foreach ($newUrl as $url) {
            self::$mappings[$route][] = $url;
        }
    }

    /**
     * Add a static route to the sitemap. This is useful for including fixed URLs that are not generated from datasources, such as the homepage, about page, or contact page.
     * @param string $route The static route to add (e.g., '/about').
     * @param array $options Optional parameters such as 'priority', 'changefreq', and 'lastmod' to provide additional information about the URL for search engines.
     * @return void
     */
    public static function add(string $route, array $options = [])
    {
        self::$sitemap[] = [
            'loc' => _env('APP_URL') . '/' . ltrim($route, '/'),
            'lastmod' => $options['lastmod'] ?? date('c'),
            'changefreq' => $options['changefreq'] ?? null,
            'priority' => $options['priority'] ?? 0.5,
        ];
    }

    /**
     * Generate the sitemap.xml file based on the added datasources and defined routes. This method compiles all the URLs and creates the sitemap file in the public directory.
     * @return bool
     */
    public static function generate()
    {
        $sitemapFile = 'public' . DIRECTORY_SEPARATOR . 'sitemap.xml';
        $sitemapContent = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $sitemapContent .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . PHP_EOL;

        foreach (self::$sitemap as $url) {
            $sitemapContent .= '  <url>' . PHP_EOL;
            $sitemapContent .= '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') . '</loc>' . PHP_EOL;

            if (!empty($url['lastmod'])) {
                $sitemapContent .= '    <lastmod>' . htmlspecialchars($url['lastmod'], ENT_XML1, 'UTF-8') . '</lastmod>' . PHP_EOL;
            }

            if (!empty($url['changefreq'])) {
                $sitemapContent .= '    <changefreq>' . htmlspecialchars($url['changefreq'], ENT_XML1, 'UTF-8') . '</changefreq>' . PHP_EOL;
            }

            if (!empty($url['priority'])) {
                $sitemapContent .= '    <priority>' . htmlspecialchars((string) $url['priority'], ENT_XML1, 'UTF-8') . '</priority>' . PHP_EOL;
            }

            $sitemapContent .= '  </url>' . PHP_EOL;
        }

        $sitemapContent .= '</urlset>';

        if (storage()->exists($sitemapFile)) {
            storage()->delete($sitemapFile);
        }

        return storage()->createFile($sitemapFile, $sitemapContent);
    }


    public static function init()
    {
        app()->hook('router.before.route', function ($context) {
            foreach ($context['routes'] as $method => $routeGroup) {
                if ($method !== 'GET') {
                    continue;
                }

                foreach ($routeGroup as $route) {
                    if (isset($route['sitemap']) && $route['sitemap'] === false) {
                        continue;
                    }

                    if (in_array($route['pattern'], array_keys(self::$mappings))) {
                        foreach (self::$mappings[$route['pattern']] as $mapping) {
                            self::$sitemap[] = [
                                'loc' => _env('APP_URL') . '/' . ltrim($mapping['loc'], '/'),
                                'lastmod' => $mapping['lastmod'] ?? date('c'),
                                'changefreq' => $mapping['changefreq'] ?? null,
                                'priority' => $mapping['priority'] ?? 0.5,
                            ];
                        }

                        continue;
                    }

                    self::$sitemap[] = [
                        'loc' => _env('APP_URL') . '/' . ltrim($route['pattern'], '/'),
                        'lastmod' => $route['sitemap']['lastmod'] ?? date('c'),
                        'changefreq' => $route['sitemap']['changefreq'] ?? null,
                        'priority' => $route['sitemap']['priority'] ?? 0.5,
                    ];
                }
            }

            if (!file_exists('public' . DIRECTORY_SEPARATOR . 'sitemap.xml')) {
                self::generate();
            }
        });
    }
}
