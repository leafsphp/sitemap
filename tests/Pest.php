<?php

/*
|--------------------------------------------------------------------------
| Environment stubs
|--------------------------------------------------------------------------
|
| leafs/leaf is not installed here, and the installed leafs/anchor does not
| ship Leaf\Config, so we provide minimal test stand-ins.
|
*/

if (!class_exists('Leaf\Config')) {
    class TestsLeafConfigPolyfill
    {
        protected static array $singletons = [];
        protected static array $resolved = [];

        public static function getStatic(string $key)
        {
            return static::$singletons[$key] ?? null;
        }

        public static function singleton(string $key, callable $resolver): void
        {
            static::$singletons[$key] = $resolver;
            unset(static::$resolved[$key]);
        }

        public static function get(string $key)
        {
            if (!isset(static::$resolved[$key]) && isset(static::$singletons[$key])) {
                static::$resolved[$key] = (static::$singletons[$key])();
            }

            return static::$resolved[$key] ?? null;
        }
    }

    class_alias(TestsLeafConfigPolyfill::class, 'Leaf\Config');
}

/**
 * Records hooks registered by Sitemap::init()/source() so tests can
 * inspect and invoke them without a full Leaf app.
 */
class TestApp
{
    public array $hooks = [];

    public function hook(string $name, callable $handler): void
    {
        $this->hooks[$name][] = $handler;
    }
}

if (!function_exists('app')) {
    function app(): TestApp
    {
        static $instance = null;

        if (($GLOBALS['__test_app_reset'] ?? false) === true) {
            $instance = null;
            $GLOBALS['__test_app_reset'] = false;
        }

        return $instance ??= new TestApp();
    }
}

function resetTestApp(): void
{
    $GLOBALS['__test_app_reset'] = true;
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function scratchDir(): string
{
    return sys_get_temp_dir() . '/leaf-sitemap-tests-' . getmypid();
}

function sitemapFile(): string
{
    return scratchDir() . '/public/sitemap.xml';
}

function resetSitemap(): void
{
    \Leaf\Sitemap::$sitemap = [];
    \Leaf\Sitemap::$mappings = [];
    \Leaf\Sitemap::$maxAge = null;
}

uses()
    ->beforeEach(function () {
        $this->originalCwd = getcwd();

        if (!is_dir(scratchDir() . '/public')) {
            mkdir(scratchDir() . '/public', 0777, true);
        }

        chdir(scratchDir());

        $_ENV['APP_URL'] = 'https://example.com';
        putenv('APP_URL=https://example.com');

        resetSitemap();
        resetTestApp();
    })
    ->afterEach(function () {
        chdir($this->originalCwd);

        if (is_dir(scratchDir())) {
            if (file_exists(sitemapFile())) {
                unlink(sitemapFile());
            }

            @rmdir(scratchDir() . '/public');
            @rmdir(scratchDir());
        }

        resetSitemap();
    })
    ->in(__DIR__);
