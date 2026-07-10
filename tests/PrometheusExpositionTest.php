<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;
use Rasuvaeff\Yii3Metrics\LabelSet;
use Rasuvaeff\Yii3Metrics\MetricRegistry;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusCounter;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusGauge;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusHistogram;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusMeter;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusMeterProvider;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusRenderer;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusUpDownCounter;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(PrometheusMeter::class)]
#[Covers(PrometheusCounter::class)]
#[Covers(PrometheusGauge::class)]
#[Covers(PrometheusUpDownCounter::class)]
#[Covers(PrometheusHistogram::class)]
#[Covers(PrometheusMeterProvider::class)]
#[Covers(PrometheusRenderer::class)]
final class PrometheusExpositionTest
{
    private CollectorRegistry $registry;
    private MetricRegistry $metrics;

    #[BeforeTest]
    public function setUp(): void
    {
        // Fresh in-memory storage per test — APC/Redis are global and would leak.
        $this->registry = new CollectorRegistry(new InMemory(), false);
        $this->metrics = new MetricRegistry(new PrometheusMeterProvider($this->registry));
    }

    public function rendersCounterWithLabelsInDeclarationOrder(): void
    {
        // Declared order (status, method) is NOT alphabetical — the LabelSet map is
        // sorted, so this proves values are positioned by declared name, not by sort.
        $counter = $this->metrics->counter('http_server_requests_total', 'Total requests', ['status', 'method']);
        $counter->inc(1.0, new LabelSet(['method' => 'GET', 'status' => '200']));

        $text = $this->render();
        Assert::string($text)->contains('# TYPE http_server_requests_total counter');
        Assert::string($text)->contains('http_server_requests_total{status="200",method="GET"} 1');
    }

    public function memoizedCounterAccumulatesOnOneSeries(): void
    {
        $this->metrics->counter('c_total', 'C')->inc();
        $this->metrics->counter('c_total')->inc();

        Assert::string($this->render())->contains("c_total 2\n");
    }

    public function rendersCumulativeHistogramBuckets(): void
    {
        // Non-default bounds — proves the custom buckets are passed through, not
        // promphp's defaults.
        $histogram = $this->metrics->histogram('req_seconds', 'Duration', ['route'], [0.2, 0.7, 3.0]);
        $histogram->observe(0.3, new LabelSet(['route' => '/x']));

        $text = $this->render();
        Assert::string($text)->contains('# TYPE req_seconds histogram');
        Assert::string($text)->contains('req_seconds_bucket{route="/x",le="0.2"} 0');
        Assert::string($text)->contains('req_seconds_bucket{route="/x",le="0.7"} 1');
        Assert::string($text)->contains('req_seconds_bucket{route="/x",le="+Inf"} 1');
        Assert::string($text)->contains('req_seconds_count{route="/x"} 1');
        Assert::string($text)->contains('req_seconds_sum{route="/x"} 0.3');
    }

    public function rendersGauge(): void
    {
        $gauge = $this->metrics->gauge('inflight', 'In flight');
        $gauge->set(5.0);
        $gauge->inc(2.0);
        $gauge->dec(1.0);

        Assert::string($this->render())->contains("inflight 6\n");
    }

    public function doesNotEmitDefaultMetrics(): void
    {
        $this->metrics->counter('x_total')->inc();

        Assert::string($this->render())->notContains('php_info');
    }

    public function counterRejectsNegativeIncrement(): void
    {
        $counter = $this->metrics->counter('c_total');

        try {
            $counter->inc(-1.0);
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('cannot be decremented');
        }
    }

    public function counterAllowsZeroIncrement(): void
    {
        // Zero is a valid (no-op) increment — only a negative amount is rejected.
        $this->metrics->counter('z_total')->inc(0.0);

        Assert::string($this->render())->contains("z_total 0\n");
    }

    public function memoizationKeepsTheFirstDeclarationsLabelNames(): void
    {
        // The second call declares different label names, but memoization returns
        // the cached wrapper — so its values still map onto the first label ('a'),
        // rendering `a="2"` rather than the empty `a=""` a fresh wrapper would give.
        $this->metrics->counter('c_total', 'first', ['a'])->inc(1.0, new LabelSet(['a' => '1']));
        $this->metrics->counter('c_total', 'second', ['b'])->inc(1.0, new LabelSet(['a' => '2']));

        $this->metrics->gauge('g_val', 'first', ['a'])->set(7.0, new LabelSet(['a' => '2']));
        $this->metrics->gauge('g_val', 'second', ['b'])->set(9.0, new LabelSet(['a' => '3']));

        $this->metrics->histogram('h_sec', 'first', ['a'])->observe(0.1, new LabelSet(['a' => '1']));
        $this->metrics->histogram('h_sec', 'second', ['b'])->observe(0.1, new LabelSet(['a' => '2']));

        $text = $this->render();
        Assert::string($text)->contains('c_total{a="2"} 1');
        Assert::string($text)->contains('g_val{a="3"} 9');
        Assert::string($text)->contains('h_sec_count{a="2"} 1');
    }

    public function upDownCounterAggregatesSignedDeltasOnOneSeries(): void
    {
        $upDown = $this->metrics->upDownCounter('inflight_requests', 'In flight', ['pool']);
        $again = $this->metrics->upDownCounter('inflight_requests', 'In flight', ['pool']);
        Assert::same($again, $upDown);

        $labels = new LabelSet(['pool' => 'web']);
        $upDown->add(5.0, $labels);
        $again->add(-2.0, $labels);

        $text = $this->render();
        Assert::string($text)->contains('# TYPE inflight_requests gauge');
        Assert::string($text)->contains('inflight_requests{pool="web"} 3');
    }

    public function undeclaredLabelThrowsInsteadOfSilentEmptyValue(): void
    {
        $counter = $this->metrics->counter('orders_total', 'Orders', ['channel']);

        try {
            $counter->inc(1.0, new LabelSet(['chanel' => 'web'])); // typo'd label name
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('Undeclared label');
            Assert::string($e->getMessage())->contains('chanel');
        }
    }

    public function missingDeclaredLabelRendersEmptyValue(): void
    {
        $this->metrics->counter('orders_total', 'Orders', ['channel'])
            ->inc(1.0, new LabelSet([]));

        Assert::string($this->render())->contains('orders_total{channel=""} 1');
    }

    public function namespacePrefixesEveryMetricName(): void
    {
        $metrics = new MetricRegistry(new PrometheusMeterProvider($this->registry, 'checkout'));
        $metrics->counter('orders_total', 'Orders')->inc();

        Assert::string($this->render())->contains('checkout_orders_total 1');
    }

    public function providerReturnsTheSameMeter(): void
    {
        $provider = new PrometheusMeterProvider($this->registry);

        Assert::same($provider->getMeter(), $provider->getMeter());
    }

    private function render(): string
    {
        return (new PrometheusRenderer())->render($this->registry);
    }
}
