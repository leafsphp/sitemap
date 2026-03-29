<!-- markdownlint-disable no-inline-html -->
<p align="center">
  <br>
  <img src="https://leafphp.dev/logo-circle.png" height="120"/>
  <br>
</p>

<h1 align="center">Sitemap Generator</h1>

<p align="center">
  <b>A simple and efficient sitemap generator for Leaf apps</b>
</p>

<p align="center">
	<a href="https://packagist.org/packages/leafs/sitemap"><img src="https://poser.pugx.org/leafs/sitemap/v/stable" alt="Latest Stable Version"/></a>
	<a href="https://packagist.org/packages/leafs/sitemap"><img src="https://poser.pugx.org/leafs/sitemap/downloads" alt="Total Downloads"/></a>
	<a href="https://packagist.org/packages/leafs/sitemap"><img src="https://poser.pugx.org/leafs/sitemap/license" alt="License"/></a>
</p>

<!-- <p align="center">
  <a href="#installation">Installation</a> •
  <a href="#quick-start">Quick Start</a> •
  <a href="#features">Features</a> •
  <a href="#api-reference">API Reference</a> •
  <!-- <a href="#examples">Examples</a> •
</p> -->
<br>

## Sitemap Generator

**Sitemap Generator** is a simple and efficient tool for generating sitemaps in Leaf PHP. It helps you create XML sitemaps quickly and easily, ensuring your website is properly indexed by search engines. It generates a real sitemap.xml file in the public directory of your Leaf application, which can be accessed by search engines to crawl your website effectively, and has support for custom datasources, allowing you to generate sitemaps from various data sources such as databases, APIs, or static files.

## 🚀 Installation

**Using [Leaf CLI](https://cli.leafphp.dev) (Recommended)**:

```bash
leaf install sitemap
```

**Using [Composer](https://getcomposer.org/)**:

```bash
composer require leafs/sitemap
```

## Quick Start

Once installed, your sitemap will be automatically generated based on the routes defined in your Leaf application. You can customize the sitemap generation by adding a new datasource or by adding sitemap config options directly to your routes.

## Regenerating Your Sitemap

Leaf automatically generates your sitemap when it doesn’t exist.

To regenerate your sitemap, you can:

- delete the existing sitemap.xml file
- or manually generate a new one:

    ```php
    sitemap()->generate();
    ```

## Auto Sitemaps

Since sitemaps automatically use your Leaf routes, you can add some config options directly to your routes to customize how they appear in the sitemap. For example:

```php
app()->get('/about', [
    'sitemap' => [
        'changefreq' => 'monthly',
        'priority' => 0.5,
    ],
    function() {
        echo 'About Us';
    }
]);
```

If you have a dynamic route like `/blog/{slug}`, you can also add the sitemap config to the route, you can tell the sitemap generator to replace the `{slug}` parameter with actual values from your model if you have one defined:

```php
app()->get('/blog/{slug}', [
    'sitemap' => [
        'changefreq' => 'weekly',
        'priority' => 0.8,
        'model' => \App\Models\Post::class, // or 'posts' if you don't have a model but have a table named 'posts',
        'parameter' => 'slug', // the parameter in the route to replace with the model value,
        'exclude' => [
            'status' => 'draft' // you can also exclude certain items from the sitemap based on model attributes
        ]
    ],
    'BlogController@show'
]);
```

This is typically a faster way to generate sitemaps for dynamic routes, as you don't have to manually add a datasource and fetch the data yourself, the sitemap generator will handle it for you. So you will only need to manually add a datasource if you want to do more complex link generation like adding multiple URLs for the same route, or if you want to add URLs that are not defined as routes in your application.

## Datasources

Your application may have dynamic routes, eg: `/blog/{slug}`. To include these routes in your sitemap, you can create a custom datasource that fetches the necessary data from your database and adds it to the sitemap. Here's an example of how to create a custom datasource for a blog:

```php
sitemap()->source(function() {
    // Fetch blog posts from the database
    $posts = db()->select('posts')->get(); // or with a model like Post::all();

    // Add each post to the sitemap
    foreach ($posts as $post) {
        sitemap()->map('/blog/{slug}', "/blog/{$post->slug}", [
            'lastmod' => $post->updated_at,
            'changefreq' => 'weekly',
            'priority' => 0.8,
        ]);
    }
});

// original route
app()->get('/blog/{slug}', 'BlogController@show');
```

You can also add multiple URLs at once:

```php
sitemap()->source(function() {
    // Fetch blog posts from the database
    $posts = db()->select('posts')->get(); // or with a model like Post::all();

    // Add each post to the sitemap
    foreach ($posts as $post) {
        sitemap()->map('/blog/{slug}', [
            "/en/blog/{$post->slug_en}" => [
                'lastmod' => $post->updated_at,
                'changefreq' => 'weekly',
                'priority' => 0.8,
            ],
            "/fr/blog/{$post->slug_fr}" => [
                'lastmod' => $post->updated_at,
                'changefreq' => 'weekly',
                'priority' => 0.8,
            ],
        ]);
    }
});

// original route
app()->get('/blog/{slug}', 'BlogController@show');
```

This will map the `/blog/{slug}` to the actual URLs of your blog posts, and include additional metadata such as last modification date, change frequency, and priority.

## Add URL

The examples above show how to map existing routes to actual URLs, but you can also add new URLs that are not defined as routes in your application:

```php
sitemap()->source(function() {
    // Fetch blog posts from the database
    $posts = db()->select('posts')->get(); // or with a model like Post::all();

    // Add each post to the sitemap
    foreach ($posts as $post) {
        sitemap()->add("/blog/{$post->slug}", [
            'lastmod' => $post->updated_at,
            'changefreq' => 'weekly',
            'priority' => 0.8,
        ]);
    }
});
```

This will add the specified URLs to the sitemap without replacing any existing routes.

You can also use `add` without being dynamic:

```php
sitemap()->source(function() {
    sitemap()->add('/about', [
        'changefreq' => 'monthly',
        'priority' => 0.5,
    ]);

    sitemap()->add('/contact', [
        'changefreq' => 'monthly',
        'priority' => 0.5,
    ]);
});
```

If you add a URL that already exists in the sitemap, it will be replaced with the new one. For example:

```php
sitemap()->source(function() {
    ...

    sitemap()->add('/about', [
        'changefreq' => 'monthly',
        'priority' => 0.5,
    ]);
});

app()->get('/about', function() {
    echo 'About Us';
});
```
