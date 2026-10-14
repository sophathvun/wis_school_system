<?php

use Dotenv\Dotenv;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->originalEnvironmentPath = app()->environmentPath();
    $this->originalBootstrapPath = app()->bootstrapPath();
    $this->pushTestDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'wis-push-'.bin2hex(random_bytes(8));
    mkdir($this->pushTestDirectory);
    app()->useEnvironmentPath($this->pushTestDirectory);
    app()->useBootstrapPath($this->pushTestDirectory);
    file_put_contents(app()->environmentFilePath(), "APP_NAME=School\nWEBPUSH_VAPID_PUBLIC_KEY=\nWEBPUSH_VAPID_PRIVATE_KEY=\n");
    config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null, 'webpush.vapid.subject' => 'https://school.example.test']);
    Schema::create('push_subscriptions', function (Blueprint $table) { $table->id(); });
});

afterEach(function () {
    unlink(app()->environmentFilePath());
    app()->useEnvironmentPath($this->originalEnvironmentPath);
    app()->useBootstrapPath($this->originalBootstrapPath);
    rmdir($this->pushTestDirectory);
});

it('checks missing configuration without writing environment keys', function () {
    $before = file_get_contents(app()->environmentFilePath());
    $this->artisan('webpush:setup --check')->assertFailed();
    expect(file_get_contents(app()->environmentFilePath()))->toBe($before);
});

it('creates valid missing keys and preserves them when setup is repeated', function () {
    $this->artisan('webpush:setup')->assertSuccessful();
    $contents = file_get_contents(app()->environmentFilePath());
    $values = Dotenv::parse($contents);
    expect($values['WEBPUSH_VAPID_PUBLIC_KEY'])->not->toBeEmpty()
        ->and($values['WEBPUSH_VAPID_PRIVATE_KEY'])->not->toBeEmpty();
    $this->artisan('webpush:setup --check')->assertSuccessful();
    $this->artisan('webpush:setup')->assertSuccessful();
    expect(file_get_contents(app()->environmentFilePath()))->toBe($contents);
});

it('refuses to replace existing keys hidden by stale configuration', function () {
    $contents = "WEBPUSH_VAPID_PUBLIC_KEY=existing-key\nWEBPUSH_VAPID_PRIVATE_KEY=\n";
    file_put_contents(app()->environmentFilePath(), $contents);
    $this->artisan('webpush:setup')->assertFailed();
    expect(file_get_contents(app()->environmentFilePath()))->toBe($contents);
});

it('reports malformed environment configuration without printing its contents or modifying it', function () {
    $contents = "PRIVATE SETTING=invalid-name\n";
    file_put_contents(app()->environmentFilePath(), $contents);
    $this->artisan('webpush:setup')
        ->expectsOutput('Unable to parse the environment file. Correct its formatting before setup. No keys were changed.')
        ->assertFailed();
    expect(file_get_contents(app()->environmentFilePath()))->toBe($contents);
});
