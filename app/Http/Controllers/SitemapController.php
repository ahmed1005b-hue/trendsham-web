<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * خريطة الموقع لمحركات البحث (sitemap.xml) وملف robots.txt.
 * فقط الصفحات العامة الظاهرة للزوار، وتُخزَّن ساعة لتخفيف الاستعلامات.
 */
class SitemapController extends Controller
{
    public const CACHE_KEY = 'sitemap.xml';

    /** حد بروتوكول sitemap لملف واحد. */
    private const MAX_URLS = 50000;

    public function index(): Response
    {
        $xml = Cache::remember(self::CACHE_KEY, now()->addHour(), fn () => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /seller',
            'Disallow: /employee',
            'Disallow: /customer',
            'Disallow: /sandbox-bank',
            'Disallow: /api/',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function build(): string
    {
        $urls = [];
        $add = function (string $loc, $lastmod = null, string $freq = 'weekly', string $priority = '0.5') use (&$urls) {
            if (count($urls) < self::MAX_URLS) {
                $urls[] = compact('loc', 'lastmod', 'freq', 'priority');
            }
        };

        $add(route('home'), null, 'daily', '1.0');
        foreach (['pages.about', 'pages.help', 'legal.terms', 'legal.privacy', 'legal.refund-policy'] as $name) {
            $add(route($name), null, 'monthly', '0.3');
        }

        Category::active()->orderBy('id')->get(['id', 'slug', 'updated_at'])
            ->each(fn (Category $c) => $add(route('categories.show', $c), $c->updated_at, 'daily', '0.7'));

        SellerProfile::where('status', 'approved')->orderBy('id')->get(['id', 'updated_at'])
            ->each(fn (SellerProfile $s) => $add(route('stores.show', $s), $s->updated_at, 'weekly', '0.6'));

        Product::publiclyVisible()->orderByDesc('updated_at')->limit(self::MAX_URLS)->get(['id', 'slug', 'updated_at'])
            ->each(fn (Product $p) => $add(route('products.show', $p), $p->updated_at, 'weekly', '0.8'));

        $body = '';
        foreach ($urls as $u) {
            $body .= '<url><loc>'.htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>'
                .($u['lastmod'] ? '<lastmod>'.$u['lastmod']->toAtomString().'</lastmod>' : '')
                .'<changefreq>'.$u['freq'].'</changefreq><priority>'.$u['priority'].'</priority></url>'."\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".$body.'</urlset>'."\n";
    }
}
