<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Test\Unit\Model;

use Etechflow\SeoAudit\Model\ScoreCalculator;
use Magento\Framework\App\ResourceConnection;
use PHPUnit\Framework\TestCase;

class ScoreCalculatorTest extends TestCase
{
    private function calculatorWithEntities(int $entities): ScoreCalculator
    {
        $resource = $this->createMock(ResourceConnection::class);
        return new class($resource, $entities) extends ScoreCalculator {
            public function __construct(ResourceConnection $resource, private int $entities)
            {
                parent::__construct($resource);
            }
            protected function entityCount(): int
            {
                return $this->entities;
            }
        };
    }

    public function testPerfectStoreScores100(): void
    {
        $calc = $this->calculatorWithEntities(1000);
        $this->assertSame(100, $calc->calculate(['critical' => 0, 'warning' => 0, 'notice' => 0]));
    }

    public function testWeightedPenaltyReducesScore(): void
    {
        // 100 warnings over 1000 entities: penalty 100, 100/1000*100 = 10 → 90
        $calc = $this->calculatorWithEntities(1000);
        $this->assertSame(90, $calc->calculate(['warning' => 100]));
    }

    public function testCriticalsWeighHeaviest(): void
    {
        // 100 criticals: penalty 300, 300/1000*100 = 30 → 70
        $calc = $this->calculatorWithEntities(1000);
        $this->assertSame(70, $calc->calculate(['critical' => 100]));
    }

    public function testScoreClampedAtZero(): void
    {
        $calc = $this->calculatorWithEntities(10);
        $this->assertSame(0, $calc->calculate(['critical' => 10000]));
    }

    public function testZeroEntitiesDoesNotDivideByZero(): void
    {
        $calc = $this->calculatorWithEntities(0);
        $this->assertSame(0, $calc->calculate(['warning' => 5]));
    }
}
