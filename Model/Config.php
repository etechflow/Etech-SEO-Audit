<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    private const P = 'etechflow_seoaudit/general/';
    private const C = 'etechflow_seoaudit/canonical/';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::P . 'enabled');
    }

    public function titleMin(): int
    {
        return (int) ($this->scopeConfig->getValue(self::P . 'title_min') ?: 20);
    }

    public function titleMax(): int
    {
        return (int) ($this->scopeConfig->getValue(self::P . 'title_max') ?: 60);
    }

    public function descriptionMin(): int
    {
        return (int) ($this->scopeConfig->getValue(self::P . 'description_min') ?: 70);
    }

    public function descriptionMax(): int
    {
        return (int) ($this->scopeConfig->getValue(self::P . 'description_max') ?: 160);
    }

    public function thinDescription(): int
    {
        return (int) ($this->scopeConfig->getValue(self::P . 'thin_description') ?: 150);
    }

    public function canonicalCheckEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::C . 'enabled');
    }

    public function canonicalSampleSize(): int
    {
        return max(1, min(200, (int) ($this->scopeConfig->getValue(self::C . 'sample_size') ?: 25)));
    }

    public function canonicalFetchBaseUrl(): string
    {
        return trim((string) $this->scopeConfig->getValue(self::C . 'fetch_base_url'));
    }

    public function canonicalBasicAuth(): ?string
    {
        $v = trim((string) $this->scopeConfig->getValue(self::C . 'basic_auth'));
        return $v !== '' ? $v : null;
    }
}
