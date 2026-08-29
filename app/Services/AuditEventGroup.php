<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Event-family grouping for SuperAdmin activity audit (filter chips + counts).
 * Derived from route_name / http_method — no schema change.
 */
class AuditEventGroup
{
    /** @var list<string> */
    public const KEYS = [
        'auth',
        'password',
        'http',
        'events',
        'sessions',
        'church',
        'people',
        'finance',
        'other',
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return self::KEYS;
    }

    public static function normalize(?string $group, ?string $legacyModule = null): ?string
    {
        $candidate = $group ?: ($legacyModule === 'events' ? 'events' : null);
        if ($candidate === null || $candidate === '') {
            return null;
        }

        return in_array($candidate, self::KEYS, true) ? $candidate : null;
    }

    public static function apply(Builder $query, string $group): Builder
    {
        if ($group === 'other') {
            return $query->where(function (Builder $outer) {
                $outer->whereNot(function (Builder $anyFamily) {
                    $named = array_values(array_filter(self::KEYS, fn (string $k) => $k !== 'other'));
                    foreach ($named as $i => $key) {
                        if ($i === 0) {
                            self::applyNamed($anyFamily, $key);
                        } else {
                            $anyFamily->orWhere(function (Builder $inner) use ($key) {
                                self::applyNamed($inner, $key);
                            });
                        }
                    }
                });
            });
        }

        return self::applyNamed($query, $group);
    }

    /**
     * Classify a single activity row for display / rollups.
     */
    public static function classify(?string $routeName, ?string $httpMethod): string
    {
        $route = (string) $routeName;
        $method = (string) $httpMethod;

        if (self::isPasswordRoute($route)) {
            return 'password';
        }

        if (self::isAuthRoute($route)) {
            return 'auth';
        }

        if (Str::startsWith($route, 'events.action.')) {
            return 'events';
        }

        if (Str::startsWith($route, 'sessions.action.')) {
            return 'sessions';
        }

        if (Str::startsWith($route, 'church.') || Str::startsWith($route, 'church_application.')) {
            return 'church';
        }

        if (Str::startsWith($route, 'people.')
            || Str::startsWith($route, 'person.')
            || Str::startsWith($route, 'profile_photo.')) {
            return 'people';
        }

        if (Str::startsWith($route, 'finance.')
            || Str::startsWith($route, 'payroll.')
            || Str::startsWith($route, 'money_in.')
            || Str::startsWith($route, 'billing.')) {
            return 'finance';
        }

        if ($method !== '' && $method !== 'SYSTEM') {
            return 'http';
        }

        return 'other';
    }

    private static function applyNamed(Builder $query, string $group): Builder
    {
        return match ($group) {
            'auth' => $query->where(function (Builder $inner) {
                $inner->where(function (Builder $auth) {
                    $auth->where('route_name', 'like', 'auth.%')
                        ->where('route_name', '!=', 'auth.password_changed');
                })
                    ->orWhereIn('route_name', ['login', 'logout', 'logout.perform'])
                    ->orWhere('route_name', 'like', 'login.%')
                    ->orWhere('route_name', 'like', 'otp.%');
            }),
            'password' => $query->where(function (Builder $inner) {
                $inner->where('route_name', 'auth.password_changed')
                    ->orWhere('route_name', 'like', 'password.%')
                    ->orWhere('route_name', 'account.password.update')
                    ->orWhere('route_name', 'like', 'password_set%');
            }),
            'http' => $query->where('http_method', '!=', 'SYSTEM')
                ->whereNotNull('http_method')
                ->where(function (Builder $inner) {
                    $inner->whereNull('route_name')
                        ->orWhere(function (Builder $route) {
                            $route->where('route_name', 'not like', 'events.action.%')
                                ->where('route_name', 'not like', 'sessions.action.%')
                                ->where(function (Builder $notAuth) {
                                    $notAuth->where('route_name', 'not like', 'auth.%')
                                        ->whereNotIn('route_name', ['login', 'logout', 'logout.perform'])
                                        ->where('route_name', 'not like', 'login.%')
                                        ->where('route_name', 'not like', 'otp.%');
                                })
                                ->where('route_name', 'not like', 'password.%')
                                ->where('route_name', '!=', 'account.password.update')
                                ->where('route_name', 'not like', 'church.%')
                                ->where('route_name', 'not like', 'church_application.%')
                                ->where('route_name', 'not like', 'people.%')
                                ->where('route_name', 'not like', 'person.%')
                                ->where('route_name', 'not like', 'profile_photo.%')
                                ->where('route_name', 'not like', 'finance.%')
                                ->where('route_name', 'not like', 'payroll.%')
                                ->where('route_name', 'not like', 'money_in.%')
                                ->where('route_name', 'not like', 'billing.%');
                        });
                }),
            'events' => $query->where('route_name', 'like', 'events.action.%'),
            'sessions' => $query->where('route_name', 'like', 'sessions.action.%'),
            'church' => $query->where(function (Builder $inner) {
                $inner->where('route_name', 'like', 'church.%')
                    ->orWhere('route_name', 'like', 'church_application.%');
            }),
            'people' => $query->where(function (Builder $inner) {
                $inner->where('route_name', 'like', 'people.%')
                    ->orWhere('route_name', 'like', 'person.%')
                    ->orWhere('route_name', 'like', 'profile_photo.%');
            }),
            'finance' => $query->where(function (Builder $inner) {
                $inner->where('route_name', 'like', 'finance.%')
                    ->orWhere('route_name', 'like', 'payroll.%')
                    ->orWhere('route_name', 'like', 'money_in.%')
                    ->orWhere('route_name', 'like', 'billing.%');
            }),
            default => $query,
        };
    }

    private static function isPasswordRoute(string $route): bool
    {
        return $route === 'auth.password_changed'
            || Str::startsWith($route, 'password.')
            || $route === 'account.password.update'
            || Str::startsWith($route, 'password_set');
    }

    private static function isAuthRoute(string $route): bool
    {
        if ($route === 'auth.password_changed') {
            return false;
        }

        return Str::startsWith($route, 'auth.')
            || in_array($route, ['login', 'logout', 'logout.perform'], true)
            || Str::startsWith($route, 'login.')
            || Str::startsWith($route, 'otp.');
    }
}
