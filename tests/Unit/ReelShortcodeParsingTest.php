<?php

use App\Models\Reel;

test('extracts shortcode from /reel/ urls', function () {
    expect(Reel::shortcodeFromUrl('https://www.instagram.com/reel/DZQZEs_qb0b/'))->toBe('DZQZEs_qb0b');
});

test('extracts shortcode from /reels/ urls', function () {
    expect(Reel::shortcodeFromUrl('https://www.instagram.com/reels/AbC123-_xyz/'))->toBe('AbC123-_xyz');
});

test('extracts shortcode from /p/ urls', function () {
    expect(Reel::shortcodeFromUrl('https://www.instagram.com/p/DXwpWpPs6E3/'))->toBe('DXwpWpPs6E3');
});

test('returns null for a non-matching url', function () {
    expect(Reel::shortcodeFromUrl('https://www.example.com/not-instagram'))->toBeNull();
});
