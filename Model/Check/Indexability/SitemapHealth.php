<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Indexability;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;
use Etechflow\SeoAudit\Model\Config;
use Etechflow\SeoAudit\Service\HtmlFetcher;
use Magento\Framework\App\ResourceConnection;

/**
 * Checks the XML sitemap is reachable and clean: referenced from robots.txt,
 * returns 200, and the URLs it lists actually return 200 (not 404 / redirect).
 * A sitemap full of dead or redirecting URLs wastes crawl budget and signals
 * low quality. Follows one level of sitemap-index. Samples a handful of URLs.
 */
class SitemapHealth extends AbstractCheck
{
    private const MAX_URL_SAMPLES = 12;

    public function __construct(
        ResourceConnection $resource,
        Config $config,
        private readonly HtmlFetcher $fetcher
    ) {
        parent::__construct($resource, $config);
    }

    public function getCode(): string { return 'indexability_sitemap_health'; }
    public function getLabel(): string { return 'XML sitemap problems (missing / dead or redirecting URLs)'; }
    public function getCategory(): string { return 'links'; }
    public function getSeverity(): string { return 'warning'; }
    public function getFixHint(): string { return 'Review sitemap'; }

    /** @return Result[] */
    public function run(): array
    {
        if (!$this->config->indexabilityCheckEnabled() || !$this->fetcher->isAvailable()) {
            return [];
        }
        $storeId = $this->fetcher->defaultStoreId();
        if (!$storeId) {
            return [];
        }

        $out = [];

        $robots         = $this->fetcher->get('/robots.txt', true);
        $sitemapFromTxt = $this->sitemapFromRobots($robots['status'] === 200 ? $robots['body'] : '');
        if ($robots['status'] === 200 && $sitemapFromTxt === '') {
            $out[] = new Result('url', null, '/robots.txt', 'robots.txt does not reference the XML sitemap (add a "Sitemap:" line).', $storeId);
        }

        $sitemapUrl = $sitemapFromTxt !== '' ? $sitemapFromTxt : '/sitemap.xml';
        $sm         = $this->fetcher->get($sitemapUrl, true);
        if ($sm['status'] !== 200 || trim($sm['body']) === '') {
            // only report a hard miss when we guessed the default location
            if ($sitemapFromTxt !== '' || $sm['status'] !== 0) {
                $out[] = new Result('url', null, $sitemapUrl, "XML sitemap not reachable (HTTP {$sm['status']}).", $storeId);
            }
            return $out;
        }

        $body = $sm['body'];
        // Follow one level of <sitemapindex>
        if (stripos($body, '<sitemapindex') !== false) {
            $child = $this->firstLoc($body);
            if ($child !== '') {
                $childResp = $this->fetcher->get($child, true);
                if ($childResp['status'] === 200) {
                    $body = $childResp['body'];
                }
            }
        }

        $urls = $this->sampleLocs($body, self::MAX_URL_SAMPLES);
        foreach ($urls as $url) {
            $status = $this->fetcher->get($url, false)['status'];
            if ($status === 0 || $status === 200) {
                continue;
            }
            $kind = in_array($status, [301, 302, 303, 307, 308], true) ? 'REDIRECTS' : 'is dead';
            $out[] = new Result('url', null, $url, "Sitemap lists a URL that {$kind} (HTTP {$status}). Sitemaps should only contain live 200 URLs.", $storeId);
        }
        return $out;
    }

    private function sitemapFromRobots(string $robotsBody): string
    {
        if ($robotsBody === '') {
            return '';
        }
        if (preg_match('/^\s*sitemap\s*:\s*(\S+)/im', $robotsBody, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    private function firstLoc(string $xml): string
    {
        if (preg_match('/<loc>\s*(.*?)\s*<\/loc>/is', $xml, $m)) {
            return html_entity_decode(trim($m[1]));
        }
        return '';
    }

    /** @return string[] */
    private function sampleLocs(string $xml, int $max): array
    {
        if (!preg_match_all('/<loc>\s*(.*?)\s*<\/loc>/is', $xml, $m)) {
            return [];
        }
        $all = array_map(static fn ($u) => html_entity_decode(trim($u)), $m[1]);
        $all = array_values(array_filter($all));
        if (count($all) <= $max) {
            return $all;
        }
        // even spread across the sitemap
        $step = (int) ceil(count($all) / $max);
        $out  = [];
        for ($i = 0; $i < count($all) && count($out) < $max; $i += $step) {
            $out[] = $all[$i];
        }
        return $out;
    }
}
