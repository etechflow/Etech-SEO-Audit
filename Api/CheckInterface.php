<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Api;

use Etechflow\SeoAudit\Model\Check\Result;

/**
 * One SEO check. Detects a single class of issue and returns the offending
 * entities. Checks are registered into the scanner's pool via di.xml, so a
 * merchant or third party can add their own without touching core.
 */
interface CheckInterface
{
    public function getCode(): string;

    public function getLabel(): string;

    /** meta | content | links | schema */
    public function getCategory(): string;

    /** critical | warning | notice */
    public function getSeverity(): string;

    /** Human hint naming the suite module that fixes this (shown in the grid). */
    public function getFixHint(): string;

    /** @return Result[] */
    public function run(): array;
}
