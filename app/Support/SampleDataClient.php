<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sends requests through the application's HTTP kernel as a signed-in user (same middleware,
 * validation and services as the browser). CSRF is skipped because no browser is involved.
 */
class SampleDataClient
{
    use InteractsWithAuthentication;
    use MakesHttpRequests;

    public $app;

    public function __construct($app, User $user)
    {
        $this->app = $app;
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->actingAs($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function ok(string $method, string $uri, array $data = []): array
    {
        $response = $this->json($method, $uri, $data);

        if ($response->getStatusCode() >= 300) {
            throw new RuntimeException("{$method} {$uri} failed ({$response->getStatusCode()}): ".Str::limit((string) $response->getContent(), 600));
        }

        return (array) $response->json();
    }
}
