<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogAuthenticatedMutations
{
    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private const SENSITIVE_FIELDS = [
        '_token',
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'remember_token',
        'secret',
        'api_key',
        'authorization',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! in_array(strtoupper($request->method()), self::MUTATING_METHODS, true)) {
            return $response;
        }

        $actor = $request->user();
        if (! $actor) {
            return $response;
        }

        $route = $request->route();
        $routeName = $route?->getName();
        if ($routeName !== null && $this->isSecurityRoute($routeName)) {
            return $response;
        }

        try {
            $routeParameters = [];
            $subjectType = null;
            $subjectId = null;

            foreach ($route?->parameters() ?? [] as $name => $value) {
                if ($value instanceof Model) {
                    $routeParameters[$name] = $value->getKey();
                    if ($subjectType === null && ! ($value instanceof ActivityLog)) {
                        $subjectType = $value->getMorphClass();
                        $subjectId = $value->getKey();
                    }
                } elseif (is_int($value) || (is_string($value) && ctype_digit($value))) {
                    $routeParameters[$name] = (int) $value;
                }
            }

            $requestFields = collect(array_keys($request->except(self::SENSITIVE_FIELDS)))
                ->filter(static fn (string $field): bool => ! preg_match('/password|token|secret|api.?key|authorization/i', $field))
                ->take(40)
                ->values()
                ->all();

            ActivityLog::create([
                'actor_id' => $actor->getKey(),
                'actor_name' => mb_substr((string) $actor->name, 0, 255),
                'event' => strtolower($request->method()),
                'route_name' => $routeName,
                'route_uri' => $route?->uri(),
                'http_method' => strtoupper($request->method()),
                'http_status' => $response->getStatusCode(),
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'route_parameters' => $routeParameters ?: null,
                'request_fields' => $requestFields ?: null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('[ActivityLog] Unable to record authenticated mutation: '.$exception->getMessage());
        }

        return $response;
    }

    private function isSecurityRoute(string $routeName): bool
    {
        foreach (['login', 'logout', 'password.', 'verification.'] as $prefix) {
            if ($routeName === $prefix || str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
