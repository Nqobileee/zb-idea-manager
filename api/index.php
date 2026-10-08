<?php

/**
 * Vercel entry point. Vercel's filesystem is read-only except /tmp, so Laravel's storage folder
 * is created there on every cold start, then the normal public/index.php flow runs.
 */
foreach (['framework/views', 'framework/cache/data', 'framework/sessions', 'logs', 'app/public', 'app/livewire-tmp'] as $dir) {
    if (! is_dir('/tmp/storage/'.$dir)) {
        mkdir('/tmp/storage/'.$dir, 0777, true);
    }
}

$_ENV['LARAVEL_STORAGE_PATH'] = $_SERVER['LARAVEL_STORAGE_PATH'] = '/tmp/storage';

require __DIR__.'/../public/index.php';
