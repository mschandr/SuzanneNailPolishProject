<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * One matrix for the route table: every signed-in route turns guests away, and every admin route turns
 * ordinary users away. A new route is covered automatically, so a forgotten middleware fails here.
 */
class RouteAccessTest extends TestCase
{
    use RefreshDatabase;

    private const UUID = '00000000-0000-4000-8000-000000000000';

    /** @return array<string, array{string, string}> */
    public static function signedInRoutes(): array
    {
        return self::routes('auth');
    }

    /** @return array<string, array{string, string}> */
    public static function adminRoutes(): array
    {
        return self::routes('admin');
    }

    public function test_the_matrix_sees_the_routes(): void
    {
        $this->assertGreaterThan(40, count(self::signedInRoutes()));
        $this->assertGreaterThan(20, count(self::adminRoutes()));
    }

    #[DataProvider('signedInRoutes')]
    public function test_a_guest_is_sent_to_sign_in(string $method, string $uri): void
    {
        $this->call($method, $uri)->assertRedirect('/login');
    }

    #[DataProvider('adminRoutes')]
    public function test_an_ordinary_user_gets_403_on_admin_routes(string $method, string $uri): void
    {
        $this->actingAs(User::factory()->create())->call($method, $uri)->assertForbidden();
    }

    /**
     * Providers run before the app boots, so the route table is read from a separate artisan process
     * (booting a second app inside the test process leaves PHPUnit's handler checks unhappy).
     */
    private static function routes(string $middleware): array
    {
        $json = shell_exec('cd '.escapeshellarg(dirname(__DIR__, 2)).' && APP_ENV=testing php artisan route:list --json 2>/dev/null');
        $cases = [];

        foreach (json_decode((string) $json, true) ?: [] as $route) {
            if (! in_array($middleware, $route['middleware'] ?? [], true)) {
                continue;
            }
            $method = explode('|', $route['method'])[0];
            $uri = '/'.ltrim(preg_replace('/\{[^}]+\}/', self::UUID, $route['uri']), '/');
            $cases["$method $uri"] = [$method, $uri];
        }

        return $cases;
    }
}
