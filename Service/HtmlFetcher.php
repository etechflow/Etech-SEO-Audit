<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Service;

use Etechflow\SeoAudit\Model\Config;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Shared HTTP fetcher for the audit's rendered-HTML checks (canonical,
 * indexability, social tags, schema). Fetches a path or absolute URL via native
 * curl, sending the store domain as the Host header. Same-host absolute URLs are
 * routed through an optional internal "fetch base" so origins behind Varnish /
 * basic-auth / an edge gate can still be read. Captures response headers too
 * (needed for X-Robots-Tag).
 */
class HtmlFetcher
{
    private ?string $publicBase = null;
    private ?string $host = null;

    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function isAvailable(): bool
    {
        return function_exists('curl_init');
    }

    public function defaultStoreId(): int
    {
        $store = $this->storeManager->getDefaultStoreView();
        return $store ? (int) $store->getId() : 0;
    }

    public function host(): string
    {
        $this->init();
        return (string) $this->host;
    }

    private function init(): void
    {
        if ($this->publicBase !== null) {
            return;
        }
        $store = $this->storeManager->getDefaultStoreView();
        $base  = $store ? (string) $store->getBaseUrl(UrlInterface::URL_TYPE_LINK, true) : '';
        $this->publicBase = rtrim($base, '/');
        $this->host       = (string) parse_url($this->publicBase, PHP_URL_HOST);
    }

    private function fetchBase(): string
    {
        $this->init();
        return rtrim($this->config->fetchBaseUrl() ?: $this->publicBase, '/');
    }

    /** Turn a "/path" or absolute URL into the URL we actually request. */
    public function resolve(string $pathOrUrl): string
    {
        $this->init();
        if ($pathOrUrl === '') {
            return $this->fetchBase() . '/';
        }
        if ($pathOrUrl[0] === '/') {
            return $this->fetchBase() . $pathOrUrl;
        }
        $h = (string) parse_url($pathOrUrl, PHP_URL_HOST);
        if ($h !== '' && $this->host !== '' && strcasecmp($h, (string) $this->host) === 0) {
            $path  = (string) parse_url($pathOrUrl, PHP_URL_PATH);
            $query = (string) parse_url($pathOrUrl, PHP_URL_QUERY);
            return $this->fetchBase() . $path . ($query !== '' ? '?' . $query : '');
        }
        return $pathOrUrl;
    }

    /**
     * @return array{status:int,body:string,headers:array<string,string>}
     */
    public function get(string $pathOrUrl, bool $followRedirects = true): array
    {
        if (!function_exists('curl_init')) {
            return ['status' => 0, 'body' => '', 'headers' => []];
        }
        $this->init();
        $url = $this->resolve($pathOrUrl);

        $headers = ['X-Forwarded-Proto: https', 'Accept: text/html,application/xhtml+xml,application/xml'];
        if ($this->host !== '') {
            $headers[] = 'Host: ' . $this->host;
        }
        $bust = (strpos($url, '?') !== false ? '&' : '?') . '_seoaudit=' . substr(md5($url), 0, 8);

        $respHeaders = [];
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url . $bust,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'Etechflow-SeoAudit/1.1',
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$respHeaders) {
                $len   = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $len;
            },
        ]);
        $auth = $this->config->fetchBasicAuth();
        if ($auth !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, $auth);
        }
        $body   = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => $followRedirects ? $body : '', 'headers' => $respHeaders];
    }
}
