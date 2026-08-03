<?php

use Leaf\Sitemap;

test('add() + generate() writes public/sitemap.xml', function () {
    Sitemap::add('/about');

    expect(Sitemap::generate())->toBeTrue();
    expect(file_exists(sitemapFile()))->toBeTrue();

    $xml = file_get_contents(sitemapFile());

    expect($xml)->toStartWith('<?xml version="1.0" encoding="UTF-8"?>');
    expect($xml)->toContain('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"');
    expect($xml)->toContain('<loc>https://example.com/about</loc>');
    expect($xml)->toContain('</urlset>');
});

test('add() normalizes leading slashes in routes', function () {
    Sitemap::add('contact');
    Sitemap::generate();

    expect(file_get_contents(sitemapFile()))->toContain('<loc>https://example.com/contact</loc>');
});

test('loc entries are xml-escaped', function () {
    Sitemap::add('/search?q=leaf&page=2');
    Sitemap::generate();

    $xml = file_get_contents(sitemapFile());

    expect($xml)->toContain('<loc>https://example.com/search?q=leaf&amp;page=2</loc>');
    expect($xml)->not->toContain('q=leaf&page');
});

test('lastmod is omitted when not provided and present when given', function () {
    Sitemap::add('/no-lastmod');
    Sitemap::add('/with-lastmod', ['lastmod' => '2026-01-15']);
    Sitemap::generate();

    $xml = file_get_contents(sitemapFile());

    expect($xml)->toContain('<lastmod>2026-01-15</lastmod>');
    expect(substr_count($xml, '<lastmod>'))->toBe(1);
});

test('changefreq only appears when set', function () {
    Sitemap::add('/plain');
    Sitemap::add('/fresh', ['changefreq' => 'daily']);
    Sitemap::generate();

    $xml = file_get_contents(sitemapFile());

    expect($xml)->toContain('<changefreq>daily</changefreq>');
    expect(substr_count($xml, '<changefreq>'))->toBe(1);
});

test('priority defaults to 0.5', function () {
    Sitemap::add('/about');
    Sitemap::generate();

    expect(file_get_contents(sitemapFile()))->toContain('<priority>0.5</priority>');
});

test('an explicit priority of 0 is kept', function () {
    Sitemap::add('/unimportant', ['priority' => 0]);
    Sitemap::generate();

    expect(file_get_contents(sitemapFile()))->toContain('<priority>0</priority>');
});

test('generate() overwrites an existing sitemap file', function () {
    Sitemap::add('/first');
    Sitemap::generate();

    expect(file_get_contents(sitemapFile()))->toContain('/first');

    Sitemap::$sitemap = [];
    Sitemap::add('/second');
    Sitemap::generate();

    $xml = file_get_contents(sitemapFile());

    expect($xml)->toContain('/second');
    expect($xml)->not->toContain('/first');
});

// map() signatures

test('map() with a string url and values', function () {
    Sitemap::map('/blog/{slug}', '/blog/post-1', ['lastmod' => '2026-01-01']);

    expect(Sitemap::$mappings['/blog/{slug}'])->toBe([
        ['lastmod' => '2026-01-01', 'loc' => '/blog/post-1'],
    ]);
});

test('map() with a single loc array', function () {
    Sitemap::map('/blog/{slug}', ['loc' => '/blog/post-1', 'priority' => 0.9]);

    expect(Sitemap::$mappings['/blog/{slug}'])->toBe([
        ['loc' => '/blog/post-1', 'priority' => 0.9],
    ]);
});

test('map() with an array of arrays', function () {
    Sitemap::map('/blog/{slug}', [
        ['loc' => '/blog/post-1'],
        ['loc' => '/blog/post-2', 'changefreq' => 'weekly'],
    ]);

    expect(Sitemap::$mappings['/blog/{slug}'])->toBe([
        ['loc' => '/blog/post-1'],
        ['loc' => '/blog/post-2', 'changefreq' => 'weekly'],
    ]);
});

// init() / source() via the app() hook recorder

function runInitWithRoutes(array $routes): void
{
    Leaf\Sitemap::init();

    $hooks = app()->hooks['router.before.route'] ?? [];
    expect($hooks)->toHaveCount(1);

    $hooks[0](['routes' => $routes]);
}

test('init() builds the sitemap from GET routes and skips other methods', function () {
    runInitWithRoutes([
        'GET' => [['pattern' => '/'], ['pattern' => '/about']],
        'POST' => [['pattern' => '/submit']],
    ]);

    $xml = file_get_contents(sitemapFile());

    expect($xml)->toContain('<loc>https://example.com/about</loc>');
    expect($xml)->not->toContain('/submit');
});

test('init() skips unmapped dynamic routes with { or ( in the pattern', function () {
    runInitWithRoutes([
        'GET' => [
            ['pattern' => '/about'],
            ['pattern' => '/blog/{slug}'],
            ['pattern' => '/user/(\d+)'],
        ],
    ]);

    $xml = file_get_contents(sitemapFile());

    expect($xml)->toContain('/about');
    expect($xml)->not->toContain('{slug}');
    expect($xml)->not->toContain('(');
});

test('init() expands mapped dynamic routes', function () {
    Leaf\Sitemap::map('/blog/{slug}', [
        ['loc' => '/blog/post-1'],
        ['loc' => '/blog/post-2', 'priority' => 0],
    ]);

    runInitWithRoutes([
        'GET' => [['pattern' => '/blog/{slug}']],
    ]);

    $xml = file_get_contents(sitemapFile());

    expect($xml)->toContain('<loc>https://example.com/blog/post-1</loc>');
    expect($xml)->toContain('<loc>https://example.com/blog/post-2</loc>');
    expect($xml)->toContain('<priority>0</priority>');
    expect($xml)->not->toContain('{slug}');
});

test('init() skips routes flagged sitemap:false', function () {
    runInitWithRoutes([
        'GET' => [
            ['pattern' => '/about'],
            ['pattern' => '/secret', 'sitemap' => false],
        ],
    ]);

    expect(file_get_contents(sitemapFile()))->not->toContain('/secret');
});

test('init() reads per-route sitemap options', function () {
    runInitWithRoutes([
        'GET' => [
            ['pattern' => '/about', 'sitemap' => ['lastmod' => '2026-02-02', 'changefreq' => 'monthly', 'priority' => 0.8]],
        ],
    ]);

    $xml = file_get_contents(sitemapFile());

    expect($xml)->toContain('<lastmod>2026-02-02</lastmod>');
    expect($xml)->toContain('<changefreq>monthly</changefreq>');
    expect($xml)->toContain('<priority>0.8</priority>');
});

// shouldGenerate() behavior (via init/source registration)

test('init() does nothing when the sitemap exists and maxAge is null', function () {
    Leaf\Sitemap::add('/about');
    Leaf\Sitemap::generate();

    Leaf\Sitemap::init();

    expect(app()->hooks)->toBe([]);
});

test('source() registers a router.before hook when no sitemap exists', function () {
    $called = false;

    Leaf\Sitemap::source(function () use (&$called) {
        $called = true;
    });

    $hooks = app()->hooks['router.before'] ?? [];
    expect($hooks)->toHaveCount(1);

    $hooks[0]();
    expect($called)->toBeTrue();
});

test('source() does not register when the sitemap exists and maxAge is null', function () {
    Leaf\Sitemap::add('/about');
    Leaf\Sitemap::generate();

    Leaf\Sitemap::source(fn () => null);

    expect(app()->hooks)->toBe([]);
});

test('a stale sitemap is regenerated when maxAge is exceeded', function () {
    Leaf\Sitemap::add('/about');
    Leaf\Sitemap::generate();

    touch(sitemapFile(), time() - 3600);
    Leaf\Sitemap::$maxAge = 60;

    Leaf\Sitemap::init();
    expect(app()->hooks['router.before.route'] ?? [])->toHaveCount(1);
});

test('a fresh sitemap is kept when within maxAge', function () {
    Leaf\Sitemap::add('/about');
    Leaf\Sitemap::generate();

    Leaf\Sitemap::$maxAge = 3600;

    Leaf\Sitemap::init();
    expect(app()->hooks)->toBe([]);
});
