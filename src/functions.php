<?php

declare(strict_types=1);

if (!function_exists('sitemap')) {
    /**
     * Sitemap info
     * @return \Leaf\Sitemap
     */
    function sitemap(): \Leaf\Sitemap
    {
        if (!\Leaf\Config::getStatic('sitemap')) {
            \Leaf\Config::singleton('sitemap', function () {
                return new \Leaf\Sitemap;
            });
        }

        return \Leaf\Config::get('sitemap');
    }
}
