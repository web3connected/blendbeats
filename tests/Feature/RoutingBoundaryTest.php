<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoutingBoundaryTest extends TestCase
{
    #[DataProvider('internalRouteProvider')]
    public function test_internal_app_routes_are_web_owned(string $routeName): void
    {
        $route = $this->routeByName($routeName);

        $this->assertContains('web', $route->middleware());
        $this->assertNotContains('api', $route->middleware());
        $this->assertContains(PreventRequestForgery::class, $route->excludedMiddleware());
    }

    #[DataProvider('externalRouteProvider')]
    public function test_external_service_routes_are_api_owned(string $routeName): void
    {
        $route = $this->routeByName($routeName);

        $this->assertContains('api', $route->middleware());
        $this->assertNotContains('web', $route->middleware());
    }

    public static function internalRouteProvider(): array
    {
        return [
            'dj lounge' => ['api.dj-lounge.posts.index'],
            'notifications' => ['api.notifications.index'],
            'commerce' => ['api.commerce.products'],
            'ratings' => ['api.ratings.show'],
            'wallet' => ['api.wallet.show'],
            'battles' => ['api.battles.index'],
            'account battles' => ['api.account.battles.index'],
            'booking requests' => ['api.dj-hub.booking-requests.store'],
            'account bookings' => ['api.account.bookings.index'],
            'auth' => ['api.auth.login'],
        ];
    }

    public static function externalRouteProvider(): array
    {
        return [
            'paypal webhook' => ['api.paypal.webhook'],
            'automation service' => ['api.automation.news.rules'],
        ];
    }

    private function routeByName(string $routeName): Route
    {
        $route = app('router')->getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "Route [{$routeName}] was not found.");

        return $route;
    }
}
