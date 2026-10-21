<?php

use App\Domain\Extraction\Parsing\SitemapParser;
use App\Domain\Extraction\Support\Html;
use App\Domain\Extraction\Support\Price;

it('converts Store API minor units exactly', function ($amount, int $minor, ?string $expected) {
    expect(Price::fromMinor($amount, $minor))->toBe($expected);
})->with([
    ['45000', 2, '450.00'],
    ['1999', 2, '19.99'],
    [5, 2, '0.05'],
    ['1500', 0, '1500'],
    ['123456', 3, '123.456'],
    ['99999999999999999999', 2, '999999999999999999.99'],
    [null, 2, null],
    ['abc', 2, null],
]);

it('parses human and schema.org prices', function ($input, ?string $expected) {
    expect(Price::parse($input))->toBe($expected);
})->with([
    ['19.99', '19.99'],
    ['1,234.50', '1234.50'],
    ['1.234,50', '1234.50'],
    ['25,00&nbsp;€', '25.00'],
    ['€ 49,9', '49.9'],
    ['$1,200', '1200'],
    ['1.200', '1200'],
    [450, '450'],
    [12.5, '12.5'],
    ['', null],
    ['Free', null],
]);

it('compares decimal prices without floats', function () {
    expect(Price::lessThan('19.99', '25.00'))->toBeTrue()
        ->and(Price::lessThan('25.00', '25'))->toBeFalse()
        ->and(Price::lessThan(null, '1'))->toBeFalse();
});

it('decodes entities and whitespace in names', function () {
    expect(Html::decode("  Wool Beanie &#8211; Winter&nbsp;\n"))->toBe('Wool Beanie – Winter')
        ->and(Html::decode('   '))->toBeNull();
});

it('sanitizes descriptions to a safe allow-list', function () {
    $html = '<h2 style="color:red">Title</h2><p onclick="x()">Hi <strong>there</strong><img src=x onerror=alert(1)></p>'
        .'<script>alert(1)</script><iframe src="https://evil"></iframe><a href="javascript:alert(1)">bad</a>'
        .'<a href="https://good.example.org/" target="_blank">good</a><div><span>kept text</span></div><!-- comment -->';

    $clean = Html::sanitize($html);

    expect($clean)->toContain('<h2>Title</h2>')
        ->toContain('<strong>there</strong>')
        ->toContain('<a href="https://good.example.org/" rel="nofollow noopener">good</a>')
        ->toContain('kept text')
        ->not->toContain('script')
        ->not->toContain('iframe')
        ->not->toContain('onclick')
        ->not->toContain('onerror')
        ->not->toContain('style=')
        ->not->toContain('javascript:')
        ->not->toContain('<img')
        ->not->toContain('comment');
});

it('turns HTML into readable text', function () {
    expect(Html::toText('<p>One &amp; two</p><ul><li>A</li><li>B</li></ul><script>x</script>'))->toBe("One & two\nA\nB");
});

it('parses sitemap indexes and url sets', function () {
    $index = SitemapParser::parse('<?xml version="1.0"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc>https://a.com/s1.xml</loc></sitemap></sitemapindex>');
    $set = SitemapParser::parse('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://a.com/product/x/?a=1&amp;b=2</loc></url><url><loc>javascript:alert(1)</loc></url></urlset>');

    expect($index)->toBe(['type' => 'index', 'locs' => ['https://a.com/s1.xml']])
        ->and($set)->toBe(['type' => 'urlset', 'locs' => ['https://a.com/product/x/?a=1&b=2']]);
});

it('refuses sitemaps with DTDs or entities (XXE)', function () {
    $xxe = '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><urlset><url><loc>https://a.com/&e;</loc></url></urlset>';

    expect(SitemapParser::parse($xxe))->toBe(['type' => 'unknown', 'locs' => []])
        ->and(SitemapParser::parse('not xml at all'))->toBe(['type' => 'unknown', 'locs' => []]);
});
