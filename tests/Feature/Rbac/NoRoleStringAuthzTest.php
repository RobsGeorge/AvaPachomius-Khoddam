<?php

namespace Tests\Feature\Rbac;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * Grep gate: no role-name string authorization in app/ (CLAUDE.md rule 4).
 */
class NoRoleStringAuthzTest extends TestCase
{
    /** Paths (relative to app/) allowed to mention template slugs / preview matching. */
    private const ALLOWLIST = [
        'Services/RolePreviewService.php',
        'Services/RoleTemplateService.php',
        'Models/Role.php',
        'Services/CoursePermissionResolver.php',
        'Models/User.php',
        'Http/Middleware/RoleMiddleware.php',
        'Console/Commands/SyncPermissionsCommand.php',
    ];

    /** @var list<string> */
    private const FORBIDDEN = [
        '->hasRole(',
        '->hasAnyRole(',
        'hasRole(',
        'hasAnyRole(',
        'LOWER(role_name)',
        "strcasecmp(\$role",
        "middleware('role:",
        'middleware("role:',
    ];

    public function test_app_has_no_role_string_authorization(): void
    {
        $root = dirname(__DIR__, 3);
        $violations = [];

        foreach ($this->phpFiles($root.DIRECTORY_SEPARATOR.'app') as $absolute => $relative) {
            $relative = str_replace('\\', '/', $relative);
            if (in_array($relative, self::ALLOWLIST, true)) {
                continue;
            }

            $contents = file_get_contents($absolute) ?: '';
            foreach (self::FORBIDDEN as $needle) {
                if (str_contains($contents, $needle)) {
                    $violations[] = "app/{$relative} contains [{$needle}]";
                }
            }
        }

        foreach ($this->phpFiles($root.DIRECTORY_SEPARATOR.'routes') as $absolute => $relative) {
            $contents = file_get_contents($absolute) ?: '';
            foreach (["middleware('role:", 'middleware("role:', "'role:admin", "'role:instructor"] as $needle) {
                if (str_contains($contents, $needle)) {
                    $violations[] = 'routes/'.str_replace('\\', '/', $relative)." contains [{$needle}]";
                }
            }
        }

        $this->assertSame([], $violations, "Role-string authz found:\n".implode("\n", $violations));
    }

    /** @return \Generator<string, string> absolute => relative */
    private function phpFiles(string $dir): \Generator
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        $php = new RegexIterator($iterator, '/\.php$/');
        foreach ($php as $file) {
            /** @var \SplFileInfo $file */
            $absolute = $file->getPathname();
            yield $absolute => substr($absolute, strlen($dir) + 1);
        }
    }
}
