# rasuvaeff/yii3-metrics-prometheus

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-metrics-prometheus.svg)](https://packagist.org/packages/rasuvaeff/yii3-metrics-prometheus)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-metrics-prometheus.svg)](https://packagist.org/packages/rasuvaeff/yii3-metrics-prometheus)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-metrics-prometheus/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-metrics-prometheus/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-metrics-prometheus/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/yii3-metrics-prometheus/actions)
[![Psalm Level](https://shepherd.dev/github/rasuvaeff/yii3-metrics-prometheus/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-metrics-prometheus)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-metrics-prometheus/php)](https://packagist.org/packages/rasuvaeff/yii3-metrics-prometheus)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-metrics-prometheus.svg)](https://github.com/rasuvaeff/yii3-metrics-prometheus/blob/master/LICENSE.md)
[English version](README.md)

Prometheus backend для [`rasuvaeff/yii3-metrics`](https://github.com/rasuvaeff/yii3-metrics).
Записывает метрики из core `MetricRegistry` в `promphp/prometheus_client_php`
и отдаёт их на endpoint'е `/metrics`.

> Используете AI-ассистента? В [llms.txt](llms.txt) — компактный API-справочник,
> который можно передать как контекст.

## Требования

- PHP 8.3+
- `rasuvaeff/yii3-metrics` ^1.0
- `promphp/prometheus_client_php` ^2.0
- Shared storage backend для php-fpm: `ext-apcu`, `ext-redis`, `predis/predis` или PDO DSN

## Установка

```bash
composer require rasuvaeff/yii3-metrics-prometheus
```

Установка этого пакета биндит сменный `MeterProviderInterface` — core
`MetricRegistry` теперь пишет в Prometheus. **Не** биндьте provider сами
(намеренная ошибка `yiisoft/config` `Duplicate key`).

## Использование

### Multiprocess storage (обязательно для php-fpm)

Storage `InMemory` из `promphp` по умолчанию — **на процесс**, поэтому под php-fpm
`/metrics` отразил бы только worker, обслуживающий scrape. Используйте shared
adapter через env:

| Runtime | `PROMETHEUS_STORAGE` | Требует |
|---|---|---|
| php-fpm (несколько worker'ов) | `apcng` (рекомендуется), `apcu`, `redis`, `predis` или `pdo` | `ext-apcu` / `ext-redis` / `predis/predis` / PDO DSN |
| RoadRunner / Swoole (один долгоживущий процесс) | `in_memory` | — |
| CLI / тесты | `in_memory` | — |

`apcng` — более новый APCu-адаптер promphp со значительно более дешёвым сбором
при scrape на больших registry — для новых деплоев предпочитайте его вместо
`apcu`. `predis` использует pure-PHP клиент (без `ext-redis`).

```php
use Rasuvaeff\Yii3MetricsPrometheus\StorageFactory;

$adapter = (new StorageFactory())->create('apcng');
// pure-PHP Redis client (no ext-redis):
$adapter = (new StorageFactory())->create('predis', ['host' => 'redis', 'port' => 6379]);
// or, without apcu/redis (MySQL, PostgreSQL, SQLite):
$adapter = (new StorageFactory())->create('pdo', [
    'dsn' => 'mysql:host=db;dbname=app',
    'username' => 'app',
    'password' => getenv('DB_PASSWORD'),
]);
```

Неизвестное имя адаптера бросает исключение (без молчаливого fallback), а выбор
`in_memory` под php-fpm поднимает `E_USER_WARNING` — scrape, который молча
показывает счётчики одного worker'а, хуже видимого предупреждения.

### Endpoint `/metrics`

`MetricsEndpoint` (PSR-15) рендерит registry как Prometheus text exposition
(`text/plain; version=0.0.4`). Направьте на него путь `/metrics` — ему нужен
PSR-17 `ResponseFactoryInterface`.

```php
use Rasuvaeff\Yii3MetricsPrometheus\MetricsEndpoint;

$endpoint = new MetricsEndpoint($collectorRegistry, $responseFactory);
```

### Безопасные лейблы (кардинальность)

`SanitizingRouteResolver` сворачивает id-подобные сегменты пути (`/users/123` →
`/users/:id`, UUID → `:uuid`) для лейбла `route` RED middleware. Это
**opt-in** — ядро биндит `PathRouteResolver` по умолчанию, поэтому перевяжите
его в конфиге приложения (override на уровне app):

```php
// config/common/di.php
use Rasuvaeff\Yii3Metrics\RouteResolverInterface;
use Rasuvaeff\Yii3MetricsPrometheus\SanitizingRouteResolver;

return [
    RouteResolverInterface::class => SanitizingRouteResolver::class,
];
```

Держите значения лейблов низкокардинальными — один временной ряд создаётся на
каждую уникальную комбинацию лейблов.

Запись с именем лейбла, которое **не было объявлено** при регистрации, бросает
`InvalidArgumentException` (опечатка в лейбле иначе молча записалась бы под
пустым значением); объявленный, но отсутствующий лейбл рендерится как пустая
строка.

### Namespace метрик

Установите `PROMETHEUS_NAMESPACE` (params `namespace`), чтобы префиксировать
каждую метрику: `checkout_http_server_requests_total`. По умолчанию пусто.

### Классы

| Класс | Назначение |
|---|---|
| `PrometheusMeterProvider` | core `MeterProviderInterface` над promphp `CollectorRegistry` |
| `PrometheusMeter` / `PrometheusCounter` / `PrometheusGauge` / `PrometheusUpDownCounter` / `PrometheusHistogram` | адаптеры |
| `PrometheusRenderer` | рендерит registry как text exposition |
| `MetricsEndpoint` | PSR-15 обработчик `/metrics` |
| `StorageFactory` | строит storage-адаптер (`in_memory`/`apcu`/`apcng`/`redis`/`predis`/`pdo`) |
| `SanitizingRouteResolver` | opt-in низкокардинальный лейбл маршрута |

## Безопасность

- Значения лейблов произвольны — не кладите id/токены в них; используйте
  `SanitizingRouteResolver` для лейбла route.
- `php_info` и другие дефолтные метрики promphp отключены
  (`registerDefaultMetrics: false`).
- **Защитите `/metrics`**: exposition раскрывает маршруты, трафик и rate
  ошибок. Ограничьте его до scrape-сети (firewall / ingress allowlist) либо
  закройте basic auth — не выставляйте публично.

## Примеры

Запускаемые, server-independent скрипты в [`examples/`](examples/). См.
[`examples/README.md`](examples/README.md).

## Разработка

Ядро резолвится через path-repository, поэтому запускайте Docker с корнем
**monorepo**, смонтированным как `/repo`:

```bash
docker run --rm -v /path/to/monorepo:/repo -w /repo/yii3-metrics-prometheus \
  composer:2 composer build
```

См. [AGENTS.md](AGENTS.md).

## Лицензия

BSD-3-Clause. См. [LICENSE.md](LICENSE.md).
