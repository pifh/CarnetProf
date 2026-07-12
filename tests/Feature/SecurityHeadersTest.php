<?php

it('sends hardened security headers and blocks search engine indexing', function () {
    $response = $this->get('/login');

    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $response->assertHeader('Content-Security-Policy');
});

it('disallows all crawlers via robots.txt', function () {
    expect(file_get_contents(public_path('robots.txt')))->toContain('Disallow: /');
});
