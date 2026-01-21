<?php

use App\Services\GmailIdHelper;

describe('URL extraction', function () {
    it('extracts ID from inbox URL', function () {
        $helper = new GmailIdHelper;

        $id = $helper->extractFromUrl('https://mail.google.com/mail/u/1/#inbox/FMfcgzQdzmSkKNRjSnNvmnxrjlNJTKlg');

        expect($id)->toBe('FMfcgzQdzmSkKNRjSnNvmnxrjlNJTKlg');
    });

    it('extracts ID from all mail URL', function () {
        $helper = new GmailIdHelper;

        $id = $helper->extractFromUrl('https://mail.google.com/mail/u/?authuser=user@gmail.com#all/19aea1f2f3532db5');

        expect($id)->toBe('19aea1f2f3532db5');
    });

    it('extracts ID from sent URL', function () {
        $helper = new GmailIdHelper;

        $id = $helper->extractFromUrl('https://mail.google.com/mail/u/0/#sent/ABC123');

        expect($id)->toBe('ABC123');
    });

    it('returns null for non-Gmail URLs', function () {
        $helper = new GmailIdHelper;

        expect($helper->extractFromUrl('https://example.com'))->toBeNull();
        expect($helper->extractFromUrl('not a url'))->toBeNull();
    });
});

describe('format detection', function () {
    it('recognizes hex thread IDs', function () {
        $helper = new GmailIdHelper;

        expect($helper->isHexId('19aea1f2f3532db5'))->toBeTrue();
        expect($helper->isHexId('ABC123DEF456'))->toBeTrue();
        expect($helper->isHexId('19be18e6fb3c6391'))->toBeTrue();
    });

    it('rejects non-hex strings as hex IDs', function () {
        $helper = new GmailIdHelper;

        expect($helper->isHexId('FMfcgzQdz'))->toBeFalse();
        expect($helper->isHexId('short'))->toBeFalse();
        expect($helper->isHexId('contains-dash'))->toBeFalse();
    });

    it('recognizes FMfcg tokens', function () {
        $helper = new GmailIdHelper;

        expect($helper->isFMfcgToken('FMfcgzQdzmSkKNRjSnNvmnxrjlNJTKlg'))->toBeTrue();
        expect($helper->isFMfcgToken('FMfcgxvwzcMvCVqtTprDSvtNVBhnMBzq'))->toBeTrue();
    });

    it('rejects non-FMfcg strings', function () {
        $helper = new GmailIdHelper;

        expect($helper->isFMfcgToken('19aea1f2f3532db5'))->toBeFalse();
        expect($helper->isFMfcgToken('randomstring'))->toBeFalse();
    });
});

describe('parse integration', function () {
    it('handles hex ID directly', function () {
        $helper = new GmailIdHelper;

        $result = $helper->parse('19AEA1F2F3532DB5');

        expect($result['threadId'])->toBe('19aea1f2f3532db5');
        expect($result['source'])->toBe('hex');
    });

    it('handles full URL with hex ID', function () {
        $helper = new GmailIdHelper;

        $result = $helper->parse('https://mail.google.com/mail/u/0/#all/19aea1f2f3532db5');

        expect($result['threadId'])->toBe('19aea1f2f3532db5');
        expect($result['source'])->toBe('hex');
    });

    it('handles FMfcg token from URL', function () {
        $helper = new GmailIdHelper;

        $result = $helper->parse('https://mail.google.com/mail/u/1/#inbox/FMfcgzQdzmSkKNRjSnNvmnxrjlNJTKlg');

        expect($result['threadId'])->toBe('19b0eb4f87753db4');
        expect($result['source'])->toBe('fmfcg');
    });

    it('handles FMfcg token directly', function () {
        $helper = new GmailIdHelper;

        $result = $helper->parse('FMfcgzQdzmSkKNRjSnNvmnxrjlNJTKlg');

        expect($result['threadId'])->toBe('19b0eb4f87753db4');
        expect($result['source'])->toBe('fmfcg');
    });

    it('preserves original input', function () {
        $helper = new GmailIdHelper;

        $original = 'https://mail.google.com/mail/u/1/#inbox/19aea1f2f3532db5';
        $result = $helper->parse($original);

        expect($result['original'])->toBe($original);
    });

    it('returns unknown source for unrecognized formats', function () {
        $helper = new GmailIdHelper;

        $result = $helper->parse('some-random-string');

        expect($result['threadId'])->toBe('some-random-string');
        expect($result['source'])->toBe('unknown');
    });
});
