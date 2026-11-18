<?php

use App\Libraries\ReplenishmentAnalysisService as R;
use CodeIgniter\Test\CIUnitTestCase;

final class ReplenishmentAnalysisTest extends CIUnitTestCase
{
    public function testZScoreMatchesServiceLevels(): void
    {
        $this->assertEqualsWithDelta(1.2816, R::zScore(90), 0.0001);
        $this->assertEqualsWithDelta(1.6449, R::zScore(95), 0.0001);
        $this->assertEqualsWithDelta(1.96, R::zScore(97.5), 0.0001);
        $this->assertEqualsWithDelta(2.3263, R::zScore(99), 0.0001);
    }

    public function testInvalidParamsFallBackToDefaults(): void
    {
        $p = R::normalizeParams(['history_days' => 45, 'service_level' => 80, 'review_days' => 500, 'default_lead_time' => 0]);
        $this->assertSame(90, $p['history_days']);
        $this->assertEquals(95, $p['service_level']);
        $this->assertSame(60, $p['review_days']);
        $this->assertSame(1, $p['default_lead_time']);
    }

    public function testStableDemandUsesHoltAndForecastsTheLevel(): void
    {
        $f = R::forecast(array_fill(0, 12, 10));
        $this->assertSame('holt', $f['method']);
        $this->assertEqualsWithDelta(10, $f['weekly'], 0.01);
        $this->assertSame(0.0, $f['cv']);
    }

    public function testGrowingDemandProjectsAboveTheAverage(): void
    {
        $f = R::forecast([2, 4, 6, 8, 10, 12, 14, 16]);
        $this->assertGreaterThan($f['mean'], $f['weekly']);
        $this->assertGreaterThan(0, $f['trend_pct']);
    }

    public function testIntermittentDemandUsesCroston(): void
    {
        $f = R::forecast([0, 0, 6, 0, 0, 0, 6, 0, 0, 6, 0, 0]);
        $this->assertSame('croston', $f['method']);
        $this->assertEqualsWithDelta(1.5, $f['weekly'], 0.6);
    }

    public function testNoDemandForecastsZero(): void
    {
        $f = R::forecast([0, 0, 0, 0]);
        $this->assertSame('sin_demanda', $f['method']);
        $this->assertSame(0.0, $f['weekly']);
    }

    public function testOrderQuantityRespectsMinimumAndMultiple(): void
    {
        $this->assertSame(0.0, R::roundOrderQuantity(-3));
        $this->assertSame(5.0, R::roundOrderQuantity(4.2));
        $this->assertSame(12.0, R::roundOrderQuantity(3, 12));
        $this->assertSame(18.0, R::roundOrderQuantity(13, 0, 6));
        $this->assertSame(24.0, R::roundOrderQuantity(5, 20, 6));
    }

    public function testAbcClassification(): void
    {
        $classes = R::abcClasses(['a' => 700, 'b' => 150, 'c' => 100, 'd' => 40, 'e' => 10, 'f' => 0]);
        $this->assertSame(['a' => 'A', 'b' => 'A', 'c' => 'B', 'd' => 'C', 'e' => 'C', 'f' => 'C'], $classes);
    }

    public function testXyzClassification(): void
    {
        $this->assertSame('X', R::xyzClass(0.3, true));
        $this->assertSame('Y', R::xyzClass(0.8, true));
        $this->assertSame('Z', R::xyzClass(1.4, true));
        $this->assertSame('Z', R::xyzClass(0.0, false));
    }

    private function policy(array $override): array
    {
        return R::policy($override + ['available' => 0, 'on_order' => 0, 'daily' => 2, 'sigma_daily' => 1, 'lead_time' => 7, 'review_days' => 7,
            'z' => 1.6449, 'min_stock' => 0, 'max_stock' => 0, 'min_order_qty' => 0, 'order_multiple' => 0]);
    }

    public function testOutOfStockIsCriticalAndOrdersUpToTarget(): void
    {
        $p = $this->policy([]);
        $this->assertSame('critica', $p['priority']);
        // ROP = 2*7 + 1.6449*1*sqrt(7) ≈ 18.35; S = 2*14 + 1.6449*sqrt(14) ≈ 34.15
        $this->assertEqualsWithDelta(18.35, $p['reorder_point'], 0.01);
        $this->assertEqualsWithDelta(34.15, $p['target_level'], 0.01);
        $this->assertSame(35.0, $p['suggested_qty']);
    }

    public function testOpenOrdersReduceTheSuggestion(): void
    {
        // Posición 30 < ROP + consumo hasta la próxima revisión (32,35): sólo completa hasta el objetivo.
        $p = $this->policy(['available' => 10, 'on_order' => 20]);
        $this->assertSame('media', $p['priority']);
        $this->assertSame(5.0, $p['suggested_qty']);

        $p = $this->policy(['available' => 10, 'on_order' => 30]);
        $this->assertNull($p['priority']);
        $this->assertSame(0.0, $p['suggested_qty']);
    }

    public function testBelowReorderPointIsHighPriority(): void
    {
        $p = $this->policy(['available' => 16]);
        $this->assertSame('alta', $p['priority']);
        $this->assertSame(19.0, $p['suggested_qty']);
    }

    public function testMaxStockCapsTheTarget(): void
    {
        $p = $this->policy(['available' => 5, 'max_stock' => 25]);
        $this->assertSame(25.0, $p['target_level']);
        $this->assertSame(20.0, $p['suggested_qty']);
    }

    public function testMinimumStockActsAsSafetyFloorWithoutDemand(): void
    {
        $p = $this->policy(['available' => 3, 'daily' => 0, 'sigma_daily' => 0, 'min_stock' => 10]);
        $this->assertSame(10.0, $p['safety_stock']);
        $this->assertSame(7.0, $p['suggested_qty']);
        $this->assertSame('alta', $p['priority']);
    }

    public function testSummaryAggregatesBySupplier(): void
    {
        $base = ['status' => 'reponer', 'priority' => 'critica', 'estimated_cost' => 100.0, 'tax_rate' => 21.0, 'sales_at_risk' => 0.0, 'stock_value' => 0.0,
            'excess_value' => 0.0, 'abc' => 'A', 'supplier_id' => 's1', 'supplier_name' => 'Proveedor 1', 'missing_cost' => false];
        $summary = R::summarize([$base, ['priority' => 'media'] + $base, ['supplier_id' => null, 'supplier_name' => null] + $base]);
        $this->assertSame(3, $summary['to_reorder']);
        $this->assertSame(2, $summary['critical']);
        $this->assertSame(1, $summary['without_supplier']);
        $this->assertSame(['name' => 'Proveedor 1', 'lines' => 2, 'amount' => 200.0, 'critical' => 1], $summary['suppliers']['s1']);
        $this->assertEqualsWithDelta(363.0, $summary['investment_with_tax'], 0.001);
    }
}
