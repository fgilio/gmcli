<?php

use App\Commands\Gmail\UrlCommand;

describe('Gmail URL building', function () {
    it('builds URL with hex thread ID and authuser', function () {
        $command = new UrlCommand;

        $method = new ReflectionMethod($command, 'buildGmailUrl');
        $method->setAccessible(true);

        $url = $method->invoke($command, '19aea1f2f3532db5', 'test@gmail.com');

        expect($url)->toBe('https://mail.google.com/mail/u/?authuser=test%40gmail.com#all/19aea1f2f3532db5');
    });

    it('lowercases hex thread IDs', function () {
        $command = new UrlCommand;

        $method = new ReflectionMethod($command, 'buildGmailUrl');
        $method->setAccessible(true);

        $url = $method->invoke($command, 'ABC123', 'user@example.com');

        expect($url)->toContain('#all/abc123');
    });
});
