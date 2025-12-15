<?php

use App\Commands\Gmail\UrlCommand;

describe('Gmail URL building', function () {
    it('builds correct Gmail URL format', function () {
        $command = new UrlCommand;

        // Using reflection to test private method
        $method = new ReflectionMethod($command, 'buildGmailUrl');
        $method->setAccessible(true);

        $url = $method->invoke($command, '19aea1f2f3532db5', 'test@gmail.com');

        expect($url)->toStartWith('https://mail.google.com/mail/u/');
        expect($url)->toContain('authuser=test%40gmail.com');
        expect($url)->toContain('#all/');
    });

    it('URL encodes email addresses', function () {
        $command = new UrlCommand;

        $method = new ReflectionMethod($command, 'buildGmailUrl');
        $method->setAccessible(true);

        $url = $method->invoke($command, 'abc123', 'user+tag@gmail.com');

        expect($url)->toContain(urlencode('user+tag@gmail.com'));
    });
});
