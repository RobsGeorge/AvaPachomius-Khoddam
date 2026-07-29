<?php

namespace Tests\Unit\Mail;

use PHPUnit\Framework\TestCase;

/**
 * Guard: staging must not attempt SMTP to the local-only Mailpit hostname.
 */
class StagingMailConfigTest extends TestCase
{
    public function test_staging_mailpit_default_falls_back_to_log_mailer(): void
    {
        $previous = [
            'APP_ENV' => getenv('APP_ENV'),
            'MAIL_MAILER' => getenv('MAIL_MAILER'),
            'MAIL_HOST' => getenv('MAIL_HOST'),
        ];

        putenv('APP_ENV=staging');
        putenv('MAIL_MAILER=smtp');
        putenv('MAIL_HOST=mailpit');

        $config = require dirname(__DIR__, 3).'/config/mail.php';

        $this->assertSame('log', $config['default']);

        foreach ($previous as $key => $value) {
            if ($value === false) {
                putenv($key);
            } else {
                putenv($key.'='.$value);
            }
        }
    }

    public function test_staging_real_smtp_host_is_not_overridden(): void
    {
        $previous = [
            'APP_ENV' => getenv('APP_ENV'),
            'MAIL_MAILER' => getenv('MAIL_MAILER'),
            'MAIL_HOST' => getenv('MAIL_HOST'),
        ];

        putenv('APP_ENV=staging');
        putenv('MAIL_MAILER=smtp');
        putenv('MAIL_HOST=smtp.example.com');

        $config = require dirname(__DIR__, 3).'/config/mail.php';

        $this->assertSame('smtp', $config['default']);

        foreach ($previous as $key => $value) {
            if ($value === false) {
                putenv($key);
            } else {
                putenv($key.'='.$value);
            }
        }
    }
}
