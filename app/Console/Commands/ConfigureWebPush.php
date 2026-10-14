<?php

namespace App\Console\Commands;

use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Minishlink\WebPush\VAPID;

class ConfigureWebPush extends Command
{
    protected $signature = 'webpush:setup {--check : Check configuration without changing keys or files}';
    protected $description = 'Configure missing Web Push keys safely, or check phone notification readiness.';

    public function handle(): int
    {
        $public = config('webpush.vapid.public_key');
        $private = config('webpush.vapid.private_key');
        if ($this->option('check')) return $this->report();

        $path = app()->environmentFilePath();
        if (!is_file($path) || !is_writable($path)) {
            $this->error('The application environment file must exist and be writable. No keys were changed.');
            return self::FAILURE;
        }
        $contents = file_get_contents($path);
        try {
            $environment = Dotenv::parse($contents);
        } catch (\Throwable) {
            $this->error('Unable to parse the environment file. Correct its formatting before setup. No keys were changed.');
            return self::FAILURE;
        }
        $storedKey = false;
        foreach (['WEBPUSH_VAPID_PUBLIC_KEY', 'WEBPUSH_VAPID_PRIVATE_KEY'] as $name) {
            if (preg_match('/^(?:export\s+)?'.preg_quote($name, '/').'\s*=([^\r\n]*)/m', $contents, $match)
                && !in_array(trim($match[1]), ['', '""', "''"], true)) $storedKey = true;
        }
        // Never replace either half of an existing pair, including malformed or cached keys.
        if ($public || $private || $storedKey || !empty($environment['WEBPUSH_VAPID_PUBLIC_KEY']) || !empty($environment['WEBPUSH_VAPID_PRIVATE_KEY'])) {
            $this->info('Existing keys preserved. This command does not rotate keys.');
            return $this->report();
        }

        try {
            $keys = VAPID::createVapidKeys();
        } catch (\Throwable) {
            $this->error('Unable to generate keys. Enable PHP OpenSSL and check its OPENSSL_CONF configuration. No keys were changed.');
            return self::FAILURE;
        }
        $values = ['WEBPUSH_VAPID_PUBLIC_KEY' => $keys['publicKey'], 'WEBPUSH_VAPID_PRIVATE_KEY' => $keys['privateKey']];
        $newline = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        foreach ($values as $name => $value) {
            $pattern = '/^'.preg_quote($name, '/').'=[^\r\n]*/m';
            if (preg_match($pattern, $contents)) $contents = preg_replace_callback($pattern, fn () => $name.'='.$value, $contents);
            else $contents = rtrim($contents, "\r\n").$newline.$name.'='.$value.$newline;
        }
        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            $this->error('Unable to save Web Push keys.');
            return self::FAILURE;
        }
        $this->callSilent('config:clear');
        config(['webpush.vapid.public_key' => $keys['publicKey'], 'webpush.vapid.private_key' => $keys['privateKey']]);
        $this->info('Web Push keys saved. Private key values are never displayed.');
        return $this->report();
    }

    private function report(): int
    {
        if (!config('webpush.vapid.public_key') || !config('webpush.vapid.private_key')) {
            $this->error('Web Push keys are missing or incomplete. Run php artisan webpush:setup. If keys already exist, clear stale configuration with php artisan config:clear.');
            return self::FAILURE;
        }
        try {
            $subject = (string) config('webpush.vapid.subject');
            $validSubject = (str_starts_with($subject, 'https://') && filter_var($subject, FILTER_VALIDATE_URL))
                || (str_starts_with($subject, 'mailto:') && filter_var(substr($subject, 7), FILTER_VALIDATE_EMAIL));
            if (!$validSubject) throw new \RuntimeException('Invalid contact');
            VAPID::validate([
                'subject' => config('webpush.vapid.subject'),
                'publicKey' => config('webpush.vapid.public_key'),
                'privateKey' => config('webpush.vapid.private_key'),
            ]);
        } catch (\Throwable) {
            $this->error('Web Push configuration is invalid. Check the configured key pair and WEBPUSH_VAPID_SUBJECT. No keys were changed.');
            return self::FAILURE;
        }
        if (!Schema::hasTable('push_subscriptions')) {
            $this->error('Push subscription storage is missing. Run php artisan migrate --force.');
            return self::FAILURE;
        }
        $this->info('Web Push configuration and subscription storage are ready.');
        $this->line('Users must enable notifications on each device. Successful delivery still requires a device test and outbound HTTPS access.');
        return self::SUCCESS;
    }
}
