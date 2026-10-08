<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tighten\Ziggy\Ziggy;

/**
 * Guards that the runtime route manifest can never be bound to a fixed
 * origin again (spec 9.4 / gaps G27, G51, G52).
 *
 * The manifest is served by the @routes Blade directive, which builds a
 * Ziggy instance without a URL argument and therefore resolves against the
 * request root (`url('/')`) rather than config('app.url'). These tests
 * pin that behaviour and would fail if the app ever baked the manifest
 * again (e.g. by importing a `ziggy:generate` output into the build).
 */
class ZiggyRouteManifestTest extends TestCase
{
    public function test_the_manifest_url_resolves_against_the_requesting_host(): void
    {
        $this->app->instance('request', Request::create('http://192.168.1.57:8000/login', 'GET'));
        app('url')->forceRootUrl(null);

        $url = (new Ziggy)->toArray()['url'];

        $this->assertSame('http://192.168.1.57:8000', $url);
        $this->assertNotSame('http://localhost', $url);
    }

    public function test_the_manifest_contains_the_routes_the_frontend_depends_on(): void
    {
        $routes = (new Ziggy)->toArray()['routes'];

        $this->assertArrayHasKey('order.store', $routes);
        $this->assertArrayHasKey('sanctum.csrf-cookie', $routes);
    }

    public function test_no_route_name_ends_with_an_empty_leaf(): void
    {
        $routes = (new Ziggy)->toArray()['routes'];

        foreach (array_keys($routes) as $name) {
            $this->assertFalse(
                str_ends_with($name, '.'),
                "Route name [{$name}] has an empty leaf.",
            );
        }
    }

    public function test_no_built_asset_bakes_a_fixed_route_origin(): void
    {
        if (! is_dir(public_path('build/assets'))) {
            $this->markTestSkipped('Front-end assets are not built.');
        }

        foreach (glob(public_path('build/assets/*.js')) as $asset) {
            $this->assertStringNotContainsString(
                '"url":"http:\\/\\/localhost"',
                (string) file_get_contents($asset),
                "Asset [{$asset}] bakes a fixed route origin.",
            );
        }
    }
}