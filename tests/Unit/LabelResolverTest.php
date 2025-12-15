<?php

use App\Services\LabelResolver;

describe('label resolution', function () {
    it('resolves label name to ID', function () {
        $resolver = new LabelResolver;
        $resolver->load([
            ['id' => 'INBOX', 'name' => 'INBOX'],
            ['id' => 'Label_1', 'name' => 'Work'],
            ['id' => 'Label_2', 'name' => 'Personal'],
        ]);

        expect($resolver->resolve('Work'))->toBe('Label_1');
        expect($resolver->resolve('Personal'))->toBe('Label_2');
    });

    it('is case-insensitive', function () {
        $resolver = new LabelResolver;
        $resolver->load([
            ['id' => 'Label_1', 'name' => 'Important'],
        ]);

        expect($resolver->resolve('important'))->toBe('Label_1');
        expect($resolver->resolve('IMPORTANT'))->toBe('Label_1');
        expect($resolver->resolve('Important'))->toBe('Label_1');
    });

    it('returns ID directly if passed as input', function () {
        $resolver = new LabelResolver;
        $resolver->load([
            ['id' => 'INBOX', 'name' => 'INBOX'],
            ['id' => 'Label_1', 'name' => 'Work'],
        ]);

        expect($resolver->resolve('INBOX'))->toBe('INBOX');
        expect($resolver->resolve('Label_1'))->toBe('Label_1');
    });

    it('returns null for unknown labels', function () {
        $resolver = new LabelResolver;
        $resolver->load([
            ['id' => 'Label_1', 'name' => 'Work'],
        ]);

        expect($resolver->resolve('Unknown'))->toBeNull();
        expect($resolver->resolve('NotFound'))->toBeNull();
    });

    it('resolves multiple labels', function () {
        $resolver = new LabelResolver;
        $resolver->load([
            ['id' => 'INBOX', 'name' => 'INBOX'],
            ['id' => 'UNREAD', 'name' => 'UNREAD'],
            ['id' => 'Label_1', 'name' => 'Work'],
        ]);

        $result = $resolver->resolveMany(['INBOX', 'Work', 'Unknown']);

        expect($result['resolved'])->toBe(['INBOX', 'Label_1']);
        expect($result['notFound'])->toBe(['Unknown']);
    });

    it('handles system labels', function () {
        $resolver = new LabelResolver;
        $resolver->load([
            ['id' => 'INBOX', 'name' => 'INBOX'],
            ['id' => 'UNREAD', 'name' => 'UNREAD'],
            ['id' => 'STARRED', 'name' => 'STARRED'],
            ['id' => 'IMPORTANT', 'name' => 'IMPORTANT'],
            ['id' => 'TRASH', 'name' => 'TRASH'],
            ['id' => 'SPAM', 'name' => 'SPAM'],
        ]);

        expect($resolver->resolve('inbox'))->toBe('INBOX');
        expect($resolver->resolve('unread'))->toBe('UNREAD');
        expect($resolver->resolve('starred'))->toBe('STARRED');
    });
});
