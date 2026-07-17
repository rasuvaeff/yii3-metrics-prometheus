# rasuvaeff/yii3-метрики-прометей
[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-metrics-prometheus.svg)](https://packagist.org/packages/rasuvaeff/yii3-metrics-prometheus)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-metrics-prometheus.svg)](https://packagist.org/packages/rasuvaeff/yii3-metrics-prometheus)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-metrics-prometheus/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-metrics-prometheus/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-metrics-prometheus/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/yii3-metrics-prometheus/actions)
[![Psalm Level](https://shepherd.dev/github/rasuvaeff/yii3-metrics-prometheus/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-metrics-prometheus)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-metrics-prometheus/php)](https://packagist.org/packages/rasuvaeff/yii3-metrics-prometheus)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-metrics-prometheus.svg)](https://github.com/rasuvaeff/yii3-metrics-prometheus/blob/master/LICENSE.md)
Prometheus backend for [`rasuvaeff/yii3-metrics`](https://github.com/rasuvaeff/yii3-metrics).
Он записывает основные метрики MetricRegistry в promphp/prometheus_client_php
 и предоставляет их в конечной точке /metrics.

 > Используете помощника по программированию с искусственным интеллектом? [llms.txt](llms.txt) имеет компактную ссылку на API
 > которую можно передать в качестве контекста. @@ЛИНИЯ@@
## Требования
- PHP 8.3+
 - `rasuvaeff/yii3-metrics` ^1.0
 - `promphp/prometheus_client_php` ^2.0
 - Серверная часть общего хранилища для php-fpm: `ext-apcu`, `ext-redis`, `predis/predis` или PDO DSN

## Установка
```bash
composer require rasuvaeff/yii3-metrics-prometheus
```
Установка этого пакета привязывает заменяемый `MeterProviderInterface` — ядро ​​
 `MetricRegistry` теперь записывает в Prometheus. **Не** также самостоятельно привязывайте провайдера
 (намеренно `yiisoft/config` `Дублировать ключ`). @@ЛИНИЯ@@
## Использование
### Многопроцессное хранилище (требуется для php-fpm)
По умолчанию в `promphp` хранилище `InMemory` предназначено **на каждый процесс**, поэтому в php-fpm
 `/metrics` будет отражать только работника, который обслуживал очистку. Используйте общий адаптер
 через env:

 | Время выполнения | `PROMETHEUS_STORAGE` | Потребности |
 |---|---|---|
 | php-fpm (несколько рабочих) | `apcng` (рекомендуется), `apcu`, `redis`, `predis` или `pdo` | `ext-apcu` / `ext-redis` / `predis/predis` / PDO DSN |
 | RoadRunner/Swoole (один длительный процесс) | `в_памяти` | — |
 | CLI/тесты | `в_памяти` | — |

 `apcng` — это новый адаптер APCu от promphp с гораздо более дешевым сбором данных
 в больших реестрах — для новых развертываний предпочтите его вместо `apcu`. `predis` использует
 клиент чистого PHP (без `ext-redis`). @@ЛИНИЯ@@
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
Неизвестное имя адаптера выдает ошибку (без молчаливого возврата), а выбор `in_memory`
 в php-fpm вызывает `E_USER_WARNING` — очистка, которая молча показывает счетчики одного рабочего
, хуже, чем видимое предупреждение. @@ЛИНИЯ@@
### Конечная точка `/metrics`
`MetricsEndpoint` (PSR-15) отображает реестр как текстовую экспозицию Prometheus
 (`text/plain; version=0.0.4`). Направьте к нему путь `/metrics` — ему нужен
 PSR-17 `ResponseFactoryInterface`. @@ЛИНИЯ@@
```php
use Rasuvaeff\Yii3MetricsPrometheus\MetricsEndpoint;

$endpoint = new MetricsEndpoint($collectorRegistry, $responseFactory);
```
### Безопасные метки (мощность)
`SanitizingRouteResolver` сворачивает сегменты пути, подобные идентификатору (`/users/123` →
 `/users/:id`, UUIDs → `:uuid`) для метки `route` промежуточного программного обеспечения RED. Это
 **согласие** — ядро ​​привязывает PathRouteResolver по умолчанию, поэтому повторно привяжите его в конфигурации приложения
 (переопределение на уровне приложения):

```php
// config/common/di.php
use Rasuvaeff\Yii3Metrics\RouteResolverInterface;
use Rasuvaeff\Yii3MetricsPrometheus\SanitizingRouteResolver;

return [
    RouteResolverInterface::class => SanitizingRouteResolver::class,
];
```
Сохраняйте низкую мощность значений меток — для каждой уникальной комбинации меток
 создается один временной ряд.

 При записи с именем метки, которое **не было объявлено** при регистрации, выдается
 `InvalidArgumentException` (в противном случае метка с опечаткой автоматически записывала бы под
 пустое значение); объявленная, но отсутствующая метка отображается как пустая строка. @@ЛИНИЯ@@
### Пространство имен метрик
Установите `PROMETHEUS_NAMESPACE` (параметры `namespace`) для префикса каждой метрики:
 `checkout_http_server_requests_total`. По умолчанию пусто. @@ЛИНИЯ@@
### Классы
| Класс | Цель |
 |---|---|
 | `PrometheusMeterProvider` | ядро `MeterProviderInterface` через promphp `CollectorRegistry` |
 | `PrometheusMeter` / `PrometheusCounter` / `PrometheusGauge` / `PrometheusUpDownCounter` / `PrometheusHistogram` | адаптеры |
 | `ПрометейРендерер` | визуализировать реестр в виде текстового представления |
 | `MetricsEndpoint` | PSR-15 обработчик `/metrics` |
 | `StorageFactory` | построить адаптер хранилища (`in_memory`/`apcu`/`apcng`/`redis`/`predis`/`pdo`) |
 | `SanitizingRouteResolver` | метка маршрута с низкой мощностью подписки | @@ЛИНИЯ@@
## Безопасность
— Значения меток произвольны — не допускайте попадания в них идентификаторов/токенов; используйте
 `SanitizingRouteResolver` в качестве метки маршрута.
 - `php_info` и другие метрики promphp по умолчанию отключены
 (`registerDefaultMetrics: false`).
 - **Защита `/metrics`**: в экспозиции показаны маршруты, трафик и частота ошибок
. Ограничьте его своей очищающей сетью (брандмауэр/список разрешений входящего трафика) или
 поставьте перед собой базовую аутентификацию — не раскрывайте ее публично. @@ЛИНИЯ@@
## Примеры
Запускаемые, независимые от сервера сценарии в [`examples/`](examples/). См.
 [`examples/README.md`](examples/README.md). @@ЛИНИЯ@@
## Разработка
Ядро разрешается через хранилище путей, поэтому запустите Docker с корнем **monorepo
**, смонтированным как `/repo`:

```bash
docker run --rm -v /path/to/monorepo:/repo -w /repo/yii3-metrics-prometheus \
  composer:2 composer build
```
См. [AGENTS.md](AGENTS.md). @@ЛИНИЯ@@
## Лицензия
BSD-3-пункт. См. [LICENSE.md](LICENSE.md).
