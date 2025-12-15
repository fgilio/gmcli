<?php

use App\Services\MessageBuilder;
use App\Services\MimeHelper;

describe('message building', function () {
    it('builds simple text message', function () {
        $builder = new MessageBuilder;
        $builder->from('sender@example.com')
            ->to(['recipient@example.com'])
            ->subject('Test Subject')
            ->body('Hello World');

        $raw = $builder->build();

        // Decode and check
        $mime = new MimeHelper;
        $decoded = $mime->decodeBase64Url($raw);

        expect($decoded)->toContain('From: sender@example.com');
        expect($decoded)->toContain('To: recipient@example.com');
        expect($decoded)->toContain('Subject: Test Subject');
        expect($decoded)->toContain('Hello World');
    });

    it('includes cc and bcc headers', function () {
        $builder = new MessageBuilder;
        $builder->from('sender@example.com')
            ->to(['to@example.com'])
            ->cc(['cc@example.com'])
            ->bcc(['bcc@example.com'])
            ->subject('Test')
            ->body('Test body');

        $raw = $builder->build();
        $mime = new MimeHelper;
        $decoded = $mime->decodeBase64Url($raw);

        expect($decoded)->toContain('Cc: cc@example.com');
        expect($decoded)->toContain('Bcc: bcc@example.com');
    });

    it('encodes non-ASCII subjects', function () {
        $builder = new MessageBuilder;
        $builder->from('test@example.com')
            ->to(['to@example.com'])
            ->subject('Héllo Wörld')
            ->body('Test');

        $raw = $builder->build();
        $mime = new MimeHelper;
        $decoded = $mime->decodeBase64Url($raw);

        // Should use encoded-word format
        expect($decoded)->toContain('=?UTF-8?B?');
    });

    it('handles multiple recipients', function () {
        $builder = new MessageBuilder;
        $builder->from('sender@example.com')
            ->to(['one@example.com', 'two@example.com', 'three@example.com'])
            ->subject('Test')
            ->body('Test');

        $raw = $builder->build();
        $mime = new MimeHelper;
        $decoded = $mime->decodeBase64Url($raw);

        expect($decoded)->toContain('To: one@example.com, two@example.com, three@example.com');
    });
});

describe('reply-to headers', function () {
    it('sets In-Reply-To header', function () {
        $builder = new MessageBuilder;
        $builder->from('test@example.com')
            ->to(['to@example.com'])
            ->subject('Re: Test')
            ->body('Reply')
            ->replyTo('<abc123@mail.gmail.com>');

        $raw = $builder->build();
        $mime = new MimeHelper;
        $decoded = $mime->decodeBase64Url($raw);

        expect($decoded)->toContain('In-Reply-To: <abc123@mail.gmail.com>');
    });

    it('sets References header', function () {
        $builder = new MessageBuilder;
        $builder->from('test@example.com')
            ->to(['to@example.com'])
            ->subject('Re: Test')
            ->body('Reply')
            ->replyTo('<msg1@mail.gmail.com>', '<msg0@mail.gmail.com> <msg1@mail.gmail.com>');

        $raw = $builder->build();
        $mime = new MimeHelper;
        $decoded = $mime->decodeBase64Url($raw);

        expect($decoded)->toContain('References: <msg0@mail.gmail.com> <msg1@mail.gmail.com>');
    });

    it('stores thread ID for API', function () {
        $builder = new MessageBuilder;
        $builder->from('test@example.com')
            ->to(['to@example.com'])
            ->subject('Re: Test')
            ->body('Reply')
            ->replyTo('<msg@mail.gmail.com>', null, 'thread123');

        expect($builder->getThreadId())->toBe('thread123');
    });
});

describe('multipart messages', function () {
    beforeEach(function () {
        $this->tempFile = sys_get_temp_dir() . '/gmcli-test-attachment-' . uniqid() . '.txt';
        file_put_contents($this->tempFile, 'Test attachment content');
    });

    afterEach(function () {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    });

    it('creates multipart message with attachment', function () {
        $builder = new MessageBuilder;
        $builder->from('test@example.com')
            ->to(['to@example.com'])
            ->subject('With Attachment')
            ->body('See attached')
            ->attach($this->tempFile);

        $raw = $builder->build();
        $mime = new MimeHelper;
        $decoded = $mime->decodeBase64Url($raw);

        expect($decoded)->toContain('multipart/mixed');
        expect($decoded)->toContain('Content-Disposition: attachment');
        expect($decoded)->toContain(basename($this->tempFile));
    });

    it('throws on missing attachment file', function () {
        $builder = new MessageBuilder;
        $builder->from('test@example.com')
            ->to(['to@example.com'])
            ->subject('Test')
            ->body('Test');

        expect(fn() => $builder->attach('/nonexistent/file.txt'))
            ->toThrow(RuntimeException::class, 'not found');
    });
});
