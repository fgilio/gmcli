<?php

use App\Services\MimeHelper;

describe('base64url decoding', function () {
    it('decodes standard base64url', function () {
        $mime = new MimeHelper;

        $encoded = 'SGVsbG8gV29ybGQh'; // "Hello World!"
        $decoded = $mime->decodeBase64Url($encoded);

        expect($decoded)->toBe('Hello World!');
    });

    it('handles URL-safe characters', function () {
        $mime = new MimeHelper;

        // Base64url uses - instead of + and _ instead of /
        $encoded = 'SGVsbG8tV29ybGQtVGVzdA'; // "Hello-World-Test"

        // This should decode correctly
        $decoded = $mime->decodeBase64Url($encoded);

        expect($decoded)->toContain('Hello');
    });

    it('handles missing padding', function () {
        $mime = new MimeHelper;

        // Base64url often omits padding
        $encoded = 'dGVzdA'; // "test" without padding

        $decoded = $mime->decodeBase64Url($encoded);

        expect($decoded)->toBe('test');
    });
});

describe('base64url encoding', function () {
    it('encodes to base64url format', function () {
        $mime = new MimeHelper;

        $encoded = $mime->encodeBase64Url('Hello World!');

        // Should not contain + or / (standard base64)
        expect($encoded)->not->toContain('+');
        expect($encoded)->not->toContain('/');
        // Should not have padding
        expect($encoded)->not->toContain('=');
    });

    it('roundtrips correctly', function () {
        $mime = new MimeHelper;

        $original = "Test message with special chars: +/=";
        $encoded = $mime->encodeBase64Url($original);
        $decoded = $mime->decodeBase64Url($encoded);

        expect($decoded)->toBe($original);
    });
});

describe('MIME traversal', function () {
    it('extracts text from simple text/plain message', function () {
        $mime = new MimeHelper;

        $payload = [
            'mimeType' => 'text/plain',
            'body' => [
                'data' => base64_encode('Simple message body'),
            ],
        ];

        // Need to use base64url encoding
        $payload['body']['data'] = rtrim(strtr(base64_encode('Simple message body'), '+/', '-_'), '=');

        $text = $mime->extractTextBody($payload);

        expect($text)->toBe('Simple message body');
    });

    it('extracts text from multipart/alternative', function () {
        $mime = new MimeHelper;

        $payload = [
            'mimeType' => 'multipart/alternative',
            'parts' => [
                [
                    'mimeType' => 'text/plain',
                    'body' => [
                        'data' => rtrim(strtr(base64_encode('Plain text version'), '+/', '-_'), '='),
                    ],
                ],
                [
                    'mimeType' => 'text/html',
                    'body' => [
                        'data' => rtrim(strtr(base64_encode('<p>HTML version</p>'), '+/', '-_'), '='),
                    ],
                ],
            ],
        ];

        $text = $mime->extractTextBody($payload);

        expect($text)->toBe('Plain text version');
    });

    it('handles nested multipart structures', function () {
        $mime = new MimeHelper;

        $payload = [
            'mimeType' => 'multipart/mixed',
            'parts' => [
                [
                    'mimeType' => 'multipart/alternative',
                    'parts' => [
                        [
                            'mimeType' => 'text/plain',
                            'body' => [
                                'data' => rtrim(strtr(base64_encode('Nested plain text'), '+/', '-_'), '='),
                            ],
                        ],
                    ],
                ],
                [
                    'mimeType' => 'application/pdf',
                    'filename' => 'document.pdf',
                    'body' => ['attachmentId' => 'abc123'],
                ],
            ],
        ];

        $text = $mime->extractTextBody($payload);

        expect($text)->toBe('Nested plain text');
    });

    it('returns empty string when no text/plain', function () {
        $mime = new MimeHelper;

        $payload = [
            'mimeType' => 'text/html',
            'body' => [
                'data' => rtrim(strtr(base64_encode('<p>HTML only</p>'), '+/', '-_'), '='),
            ],
        ];

        $text = $mime->extractTextBody($payload);

        expect($text)->toBe('');
    });
});

describe('attachment extraction', function () {
    it('extracts attachments from message', function () {
        $mime = new MimeHelper;

        $payload = [
            'mimeType' => 'multipart/mixed',
            'parts' => [
                [
                    'mimeType' => 'text/plain',
                    'body' => ['data' => 'dGVzdA'],
                ],
                [
                    'mimeType' => 'application/pdf',
                    'filename' => 'document.pdf',
                    'body' => [
                        'attachmentId' => 'ANGjdJ8xyz',
                        'size' => 12345,
                    ],
                ],
                [
                    'mimeType' => 'image/png',
                    'filename' => 'image.png',
                    'body' => [
                        'attachmentId' => 'ANGjdJ8abc',
                        'size' => 5000,
                    ],
                ],
            ],
        ];

        $attachments = $mime->getAttachments($payload);

        expect($attachments)->toHaveCount(2);
        expect($attachments[0]['filename'])->toBe('document.pdf');
        expect($attachments[0]['mimeType'])->toBe('application/pdf');
        expect($attachments[0]['size'])->toBe(12345);
        expect($attachments[1]['filename'])->toBe('image.png');
    });

    it('returns empty array when no attachments', function () {
        $mime = new MimeHelper;

        $payload = [
            'mimeType' => 'text/plain',
            'body' => ['data' => 'dGVzdA'],
        ];

        $attachments = $mime->getAttachments($payload);

        expect($attachments)->toBeEmpty();
    });
});

describe('header extraction', function () {
    it('extracts header by name', function () {
        $mime = new MimeHelper;

        $payload = [
            'headers' => [
                ['name' => 'From', 'value' => 'sender@example.com'],
                ['name' => 'To', 'value' => 'recipient@example.com'],
                ['name' => 'Subject', 'value' => 'Test Subject'],
            ],
        ];

        expect($mime->getHeader($payload, 'From'))->toBe('sender@example.com');
        expect($mime->getHeader($payload, 'Subject'))->toBe('Test Subject');
    });

    it('is case-insensitive', function () {
        $mime = new MimeHelper;

        $payload = [
            'headers' => [
                ['name' => 'Content-Type', 'value' => 'text/plain'],
            ],
        ];

        expect($mime->getHeader($payload, 'content-type'))->toBe('text/plain');
        expect($mime->getHeader($payload, 'CONTENT-TYPE'))->toBe('text/plain');
    });

    it('returns null for missing header', function () {
        $mime = new MimeHelper;

        $payload = [
            'headers' => [
                ['name' => 'From', 'value' => 'test@example.com'],
            ],
        ];

        expect($mime->getHeader($payload, 'X-Custom'))->toBeNull();
    });
});
