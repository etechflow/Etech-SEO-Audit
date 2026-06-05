<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    private const P = 'etechflow_seoaudit/general/';

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
}
