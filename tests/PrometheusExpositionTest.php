<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests;

use Prometheus\CollectorRegistry;
use Prometheus\MetricFamilySamples;
use Prometheus\Sample;
use Prometheus\Storage\InMemory;
use Rasuvaeff\Understudy\Understudy;
use Rasuvaeff\Yii3Metrics\Buckets;
use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;
use Rasuvaeff\Yii3Metrics\LabelSet;
use Rasuvaeff\Yii3Metrics\MetricRegistry;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\Amount;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\Labels;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusCounter;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusGauge;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusHistogram;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusMeter;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusMeterProvider;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusRenderer;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusUpDownCounter;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

use function Rasuvaeff\Understudy\when;

#[Test]
#[Covers(PrometheusMeter::class)]
#[Covers(PrometheusCounter::class)]
#[Covers(PrometheusGauge::class)]
#[Covers(PrometheusUpDownCounter::class)]
#[Covers(PrometheusHistogram::class)]
#[Covers(PrometheusMeterProvider::class)]
#[Covers(PrometheusRenderer::class)]
#[Covers(Labels::class)]
#[Covers(Amount::class)]
#[Covers(InvalidArgumentException::class)]
final class PrometheusExpositionTest
{
    private CollectorRegistry $registry;
    private MetricRegistry $metrics;

    #[BeforeTest]
    public function setUp(): void
    {
        // Fresh in-memory storage per test — APC/Redis are global and would leak.
        $this->registry = new CollectorRegistry(new InMemory(), registerDefaultMetrics: false);
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
            Assert::string($e->getMessage())->contains('Missing label "channel"');
            Assert::string($e->getMessage())->contains('chanel'); // the typo is visible in the passed list
        }
    }

    public function anUndeclaredExtraLabelStillThrows(): void
    {
        $counter = $this->metrics->counter('orders_total', 'Orders', ['channel']);

        try {
            $counter->inc(1.0, new LabelSet(['channel' => 'web', 'extra' => 'x']));
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            // The exact message pins that only the undeclared labels are named —
            // an unwrapped array_diff would list the declared ones too.
            Assert::same(
                $e->getMessage(),
                'Undeclared label(s) "extra" for this metric; declared: "channel"',
            );
        }
    }

    public function missingDeclaredLabelThrows(): void
    {
        $counter = $this->metrics->counter('orders_total', 'Orders', ['channel']);

        try {
            $counter->inc(1.0, new LabelSet([]));
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('Missing label');
            Assert::string($e->getMessage())->contains('channel');
        }
    }

    public function missingLabelOfAMultiLabelMetricNamesTheLabel(): void
    {
        $counter = $this->metrics->counter('orders_total', 'Orders', ['channel', 'status']);

        try {
            $counter->inc(1.0, new LabelSet(['channel' => 'web']));
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('Missing label "status"');
        }
    }

    /**
     * The core validates metric names with a `\z` anchor; promphp's own regex
     * anchors with `$` and lets a trailing newline through, which then breaks
     * the `# HELP` / `# TYPE` lines of the exposition.
     */
    public function metricNameWithATrailingNewlineIsRejected(): void
    {
        foreach (['counter', 'gauge', 'upDownCounter', 'histogram'] as $kind) {
            try {
                match ($kind) {
                    'counter' => $this->metrics->counter("orders_total\n"),
                    'gauge' => $this->metrics->gauge("queue_depth\n"),
                    'upDownCounter' => $this->metrics->upDownCounter("inflight\n"),
                    'histogram' => $this->metrics->histogram("latency\n"),
                };
                Assert::fail(\sprintf('expected an InvalidArgumentException for %s', $kind));
            } catch (InvalidArgumentException $e) {
                Assert::string($e->getMessage())->contains('Invalid metric name');
            }
        }
    }

    public function nonIncreasingHistogramBoundsAreRejected(): void
    {
        try {
            $this->metrics->histogram('latency', 'Latency', [], [1.0, 0.5]);
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('strictly increasing');
        }
    }

    /**
     * An empty bucket list used to fall through to promphp's own default — 14
     * bounds against the core's 11 — so the identical code produced a different
     * bucket schema in production than in the in-memory tests.
     */
    public function histogramWithoutBucketsUsesTheCoreDefaults(): void
    {
        $this->metrics->histogram('latency', 'Latency')->observe(0.02);

        $rendered = $this->render();

        foreach (Buckets::PROMETHEUS_DEFAULTS as $bound) {
            Assert::string($rendered)->contains('le="' . $this->formatBound($bound) . '"');
        }

        Assert::string($rendered)->contains('le="+Inf"');

        // Exactly the 11 core bounds plus the single overflow bucket: a
        // trailing +Inf left in the list would double the overflow bucket,
        // and promphp's own default layout carries 14 bounds.
        Assert::same(substr_count($rendered, 'le="'), \count(Buckets::PROMETHEUS_DEFAULTS) + 1);
    }

    private function formatBound(float $bound): string
    {
        return rtrim(rtrim(number_format($bound, 3, '.', ''), '0'), '.');
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

    public function providerMemoizesMetersPerScope(): void
    {
        $provider = new PrometheusMeterProvider($this->registry);

        $libA = $provider->getMeter('lib-a');
        $libB = $provider->getMeter('lib-b');

        Assert::same($provider->getMeter('lib-a'), $libA);
        Assert::same($provider->getMeter('lib-b'), $libB);
        Assert::false($libA === $libB);

        // Different scopes, one series: state lives in the shared registry,
        // per the core `(kind, name)` contract.
        $libA->counter('scoped_total', 'Scoped')->inc();
        $libB->counter('scoped_total', 'Scoped')->inc();

        Assert::string($this->render())->contains('scoped_total 2');
    }

    /**
     * A Redis storage can hold samples whose labels no longer match the
     * registered metric. Without silent mode one such row turned the whole
     * scrape into a 500 until the storage was flushed — degrading to a comment
     * keeps every other series visible.
     */
    public function aBrokenSampleIsRenderedAsACommentInsteadOfFailingTheScrape(): void
    {
        $registry = Understudy::for(CollectorRegistry::class);
        when(fn() => $registry->getMetricFamilySamples())->returns([
            new MetricFamilySamples([
                'name' => 'broken_total',
                'type' => 'counter',
                'help' => 'Broken',
                'labelNames' => ['a'],
                'samples' => [
                    [
                        'name' => 'broken_total',
                        'labelNames' => ['a', 'b'],
                        'labelValues' => ['x'],
                        'value' => 1,
                    ],
                ],
            ]),
        ]);

        $rendered = (new PrometheusRenderer())->render($registry);

        Assert::string($rendered)->contains('# HELP broken_total Broken');
        Assert::string($rendered)->contains('# Error:');
    }

    /**
     * Regression: `if ($amount < 0)` is false for `NAN`, so `NAN` and `INF`
     * reached promphp — which does not guard either. Unlike the core's in-memory
     * instruments, a promphp adapter (APCu/Redis/PDO) is shared and durable, so a
     * single poisoned recording survives the request and breaks `rate()` over
     * that series until the storage is flushed.
     *
     * @param \Closure(MetricRegistry, float): void $record
     */
    #[DataProvider('nonFiniteRecordingProvider')]
    public function recordingInstrumentsRejectNonFiniteInput(\Closure $record, float $amount): void
    {
        try {
            $record($this->metrics, $amount);
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('must be finite');
        }

        // Nothing reached the storage: the guard runs before promphp is touched.
        Assert::string($this->render())->notContains('NaN');
    }

    public static function nonFiniteRecordingProvider(): iterable
    {
        $instruments = [
            'counter inc' => static fn(MetricRegistry $m, float $v): mixed => $m->counter('c_total')->inc($v),
            'histogram observe' => static fn(MetricRegistry $m, float $v): mixed => $m->histogram('h_sec')->observe($v),
            'up-down add' => static fn(MetricRegistry $m, float $v): mixed => $m->upDownCounter('u_val')->add($v),
            'gauge inc' => static fn(MetricRegistry $m, float $v): mixed => $m->gauge('g_val')->inc($v),
            'gauge dec' => static fn(MetricRegistry $m, float $v): mixed => $m->gauge('g_val')->dec($v),
        ];

        foreach ($instruments as $label => $record) {
            yield $label . ' rejects NAN' => [$record, NAN];
            yield $label . ' rejects +INF' => [$record, INF];
            yield $label . ' rejects -INF' => [$record, -INF];
        }
    }

    /**
     * `set()` is an absolute write, so `±INF` is fine — promphp has a branch for
     * it. `NAN` does not: it falls through to `(string) $value`, which emits the
     * invalid token `NAN` and raises a PHP warning while coercing, so the guard
     * has to stop it before promphp sees it.
     */
    public function gaugeSetRendersInfinityAndRejectsNan(): void
    {
        $this->metrics->gauge('g_val', 'Gauge')->set(INF);
        Assert::string($this->render())->contains('g_val +Inf');

        $this->metrics->gauge('g_val')->set(-INF);
        Assert::string($this->render())->contains('g_val -Inf');

        try {
            $this->metrics->gauge('g_val')->set(NAN);
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('must not be NaN');
        }

        Assert::string($this->render())->notContains('NAN');
    }

    /**
     * Label values are untrusted strings, and the exposition format delimits with
     * `"`, `\` and newlines. promphp escapes them; this pins that contract, since
     * an unescaped value would let a caller forge extra samples.
     *
     * @param non-empty-string $value
     */
    #[DataProvider('hostileLabelValueProvider')]
    public function hostileLabelValuesCannotForgeExpositionLines(string $value, string $expected): void
    {
        $this->metrics->counter('orders_total', 'Orders', ['channel'])
            ->inc(1.0, new LabelSet(['channel' => $value]));

        $text = $this->render();

        Assert::string($text)->contains('orders_total{channel="' . $expected . '"} 1');
        // Exactly one sample line for this metric — nothing was injected.
        Assert::count(array_filter(
            explode("\n", $text),
            static fn(string $line): bool => str_starts_with($line, 'orders_total{'),
        ), 1);
    }

    public static function hostileLabelValueProvider(): iterable
    {
        yield 'newline' => ["web\nforged_total 999", 'web\nforged_total 999'];
        yield 'double quote' => ['we"b', 'we\"b'];
        yield 'backslash' => ['we\\b', 'we\\\\b'];
        yield 'quote closing the label block' => ['a"} 1' . "\n" . 'forged 5', 'a\"} 1\nforged 5'];
    }

    private function render(): string
    {
        return (new PrometheusRenderer())->render($this->registry);
    }
}
