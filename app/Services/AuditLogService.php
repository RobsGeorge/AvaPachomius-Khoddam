<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\LoginTrial;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuditLogService
{
    /** Request attribute: captured password fields from the incoming form. */
    public const PASSWORD_SNAPSHOT_KEY = 'audit_password_snapshot';

    /** Request attribute: optional explicit result from the controller. */
    public const PASSWORD_RESULT_KEY = 'audit_password_result';

    /** @var list<string> */
    private const PASSWORD_INPUT_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
    ];

    /** @var list<string> */
    private const SENSITIVE_INPUT_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        '_token',
        '_method',
    ];

    /** @var list<string> */
    private const SKIPPED_ROUTE_PREFIXES = [
        'superadmin.audit',
    ];

    public static function shouldLogRequest(Request $request): bool
    {
        if (self::shouldSkipRoute($request)) {
            return false;
        }

        if (! in_array($request->method(), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        if ($request->method() === 'GET') {
            return Auth::check() || $request->query->count() > 0;
        }

        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    public static function capturePasswordFields(Request $request): void
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return;
        }

        if (self::shouldSkipRoute($request)) {
            return;
        }

        if (! self::requestHasPasswordInput($request)) {
            return;
        }

        $request->attributes->set(self::PASSWORD_SNAPSHOT_KEY, [
            'password_attempt'      => self::inputString($request, 'password') ?? self::inputString($request, 'new_password'),
            'password_confirmation' => self::inputString($request, 'password_confirmation'),
            'current_password'      => self::inputString($request, 'current_password'),
            'email'                 => self::inputString($request, 'email'),
        ]);
    }

    public static function logCapturedPasswordTrial(Request $request, ?int $responseStatus = null): void
    {
        $snapshot = $request->attributes->get(self::PASSWORD_SNAPSHOT_KEY);
        if (! is_array($snapshot)) {
            return;
        }

        if (self::shouldSkipRoute($request)) {
            return;
        }

        $explicit = $request->attributes->get(self::PASSWORD_RESULT_KEY, []);
        if (! is_array($explicit)) {
            $explicit = [];
        }

        $success = array_key_exists('success', $explicit)
            ? (bool) $explicit['success']
            : self::inferPasswordSubmissionSuccess($responseStatus);
        $failureReason = $explicit['failure_reason'] ?? self::inferPasswordFailureReason($responseStatus);

        self::logLoginTrial($request, [
            'user_id'               => $explicit['user_id'] ?? null,
            'email'                 => $explicit['email'] ?? $snapshot['email'] ?? null,
            'password_attempt'      => $snapshot['password_attempt'] ?? '',
            'password_confirmation' => $snapshot['password_confirmation'] ?? null,
            'current_password'      => $snapshot['current_password'] ?? null,
            'context'               => self::resolvePasswordContext($request),
            'route_name'            => $request->route()?->getName(),
            'url'                   => Str::limit($request->fullUrl(), 2000, ''),
            'success'               => $success,
            'failure_reason'        => $failureReason,
        ]);
    }

    /**
     * Controllers may set an explicit success/failure after handling the form.
     *
     * @param array{success?: bool, failure_reason?: string|null, user_id?: int|null, email?: string|null} $result
     */
    public static function setPasswordResult(Request $request, array $result): void
    {
        $request->attributes->set(self::PASSWORD_RESULT_KEY, $result);
    }

    public static function logActivity(Request $request, ?int $responseStatus = null): void
    {
        if (! self::shouldLogRequest($request)) {
            return;
        }

        try {
            ActivityLog::create([
                'user_id'         => Auth::id(),
                'ip_address'      => self::clientIp($request),
                'user_agent'      => Str::limit((string) $request->userAgent(), 1000, ''),
                'device_summary'  => self::deviceSummary($request),
                'http_method'     => $request->method(),
                'route_name'      => $request->route()?->getName(),
                'url'             => Str::limit($request->fullUrl(), 2000, ''),
                'request_input'   => self::sanitizeInput($request),
                'response_status' => $responseStatus,
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Activity audit log failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param array{
     *     email?: string|null,
     *     password_attempt?: string|null,
     *     password_confirmation?: string|null,
     *     current_password?: string|null,
     *     context: string,
     *     route_name?: string|null,
     *     url?: string|null,
     *     success?: bool,
     *     failure_reason?: string|null,
     *     user_id?: int|null
     * } $data
     */
    public static function logLoginTrial(Request $request, array $data): void
    {
        try {
            $email = $data['email'] ?? $request->input('email') ?? Auth::user()?->email;
            $userId = $data['user_id'] ?? null;

            if (! $userId && $email) {
                $userId = User::where('email', $email)->value('user_id');
            }

            $passwordAttempt = (string) ($data['password_attempt'] ?? $request->input('password', ''));
            $currentPassword = $data['current_password'] ?? $request->input('current_password');

            if ($passwordAttempt === '' && $currentPassword) {
                $passwordAttempt = (string) $currentPassword;
            }

            LoginTrial::create([
                'user_id'                => $userId ?? Auth::id(),
                'email'                  => $email,
                'password_attempt'       => $passwordAttempt,
                'password_confirmation'  => $data['password_confirmation'] ?? $request->input('password_confirmation'),
                'current_password'       => $currentPassword,
                'context'                => $data['context'],
                'route_name'             => $data['route_name'] ?? $request->route()?->getName(),
                'url'                    => $data['url'] ?? Str::limit($request->fullUrl(), 2000, ''),
                'ip_address'             => self::clientIp($request),
                'user_agent'             => Str::limit((string) $request->userAgent(), 1000, ''),
                'device_summary'         => self::deviceSummary($request),
                'success'                => (bool) ($data['success'] ?? false),
                'failure_reason'         => $data['failure_reason'] ?? null,
                'created_at'             => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Login trial audit log failed', ['error' => $e->getMessage()]);
        }
    }

    public static function clientIp(Request $request): ?string
    {
        return $request->ip();
    }

    public static function deviceSummary(Request $request): ?string
    {
        $agent = (string) $request->userAgent();
        if ($agent === '') {
            return null;
        }

        $platform = 'Unknown OS';
        if (preg_match('/Windows/i', $agent)) {
            $platform = 'Windows';
        } elseif (preg_match('/Android/i', $agent)) {
            $platform = 'Android';
        } elseif (preg_match('/iPhone|iPad|iOS/i', $agent)) {
            $platform = 'iOS';
        } elseif (preg_match('/Mac OS X|Macintosh/i', $agent)) {
            $platform = 'macOS';
        } elseif (preg_match('/Linux/i', $agent)) {
            $platform = 'Linux';
        }

        $browser = 'Unknown browser';
        if (preg_match('/Edg\//i', $agent)) {
            $browser = 'Edge';
        } elseif (preg_match('/Chrome\//i', $agent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox\//i', $agent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari\//i', $agent)) {
            $browser = 'Safari';
        }

        return Str::limit("{$platform} / {$browser}", 255, '');
    }

    /** @return array<string, mixed> */
    private static function sanitizeInput(Request $request): array
    {
        $input = $request->except(self::SENSITIVE_INPUT_KEYS);

        foreach ($input as $key => $value) {
            if ($value instanceof UploadedFile) {
                $input[$key] = [
                    'original_name' => $value->getClientOriginalName(),
                    'mime_type'     => $value->getClientMimeType(),
                    'size'          => $value->getSize(),
                ];
                continue;
            }

            if (is_array($value)) {
                $input[$key] = self::sanitizeArray($value);
            }
        }

        foreach (self::SENSITIVE_INPUT_KEYS as $key) {
            if ($request->has($key)) {
                $input[$key] = '[redacted]';
            }
        }

        return $input;
    }

    /** @param array<mixed> $value */
    private static function sanitizeArray(array $value): array
    {
        $sanitized = [];

        foreach ($value as $key => $item) {
            if (in_array($key, self::SENSITIVE_INPUT_KEYS, true)) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            if ($item instanceof UploadedFile) {
                $sanitized[$key] = [
                    'original_name' => $item->getClientOriginalName(),
                    'mime_type'     => $item->getClientMimeType(),
                    'size'          => $item->getSize(),
                ];
                continue;
            }

            $sanitized[$key] = is_array($item) ? self::sanitizeArray($item) : $item;
        }

        return $sanitized;
    }

    private static function shouldSkipRoute(Request $request): bool
    {
        $routeName = $request->route()?->getName();
        if (! $routeName) {
            return false;
        }

        foreach (self::SKIPPED_ROUTE_PREFIXES as $prefix) {
            if (Str::startsWith($routeName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function requestHasPasswordInput(Request $request): bool
    {
        foreach (self::PASSWORD_INPUT_KEYS as $key) {
            $value = $request->input($key);
            if (is_string($value) && $value !== '') {
                return true;
            }
        }

        return false;
    }

    private static function inputString(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function resolvePasswordContext(Request $request): string
    {
        $routeName = $request->route()?->getName();

        return match ($routeName) {
            'login'              => 'login',
            'password.update'    => 'password_reset',
            'password.set.store' => 'set_password',
            default              => $routeName ? Str::limit($routeName, 30, '') : 'form_password',
        };
    }

    private static function inferPasswordSubmissionSuccess(?int $responseStatus): bool
    {
        if ($responseStatus === null) {
            return false;
        }

        return ! in_array($responseStatus, [419, 422, 401, 403, 500, 502, 503], true);
    }

    private static function inferPasswordFailureReason(?int $responseStatus): ?string
    {
        return match ($responseStatus) {
            419     => 'CSRF token mismatch',
            422     => 'Validation failed',
            401     => 'Unauthorized',
            403     => 'Forbidden',
            default => $responseStatus && $responseStatus >= 400 ? "HTTP {$responseStatus}" : null,
        };
    }
}
