<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Import;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Service\SyliusService;
use OpenColibri\Starter\Core\Task\TaskProviderRegistry;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Fixtures;
use OpenColibri\Starter\Import\Dataset\DatasetBuilders;
use OpenColibri\Starter\Import\Tasks\FetchTasks;
use OpenColibri\Starter\Import\Tasks\ImportTasks;

use function Castor\io;
use function OpenColibri\Starter\Import\Fetch\fetch_import_data;

TaskProviderRegistry::register(
    'import',
    static fn(SyliusService $service): iterable => (new ImportTasks($service->getName(), $service->getDirectory(), $service->getDomains()[0] ?? null))(),
);

DatasetBuilders::register(
    'ai',
    static function (string $slug, string $name, string $description, ?string $url, int $limit): void {
        build_ai_import_data($name, $description, $url, min($limit, IMPORT_AI_PRODUCT_LIMIT), $slug);
    },
);

DatasetBuilders::register(
    'existing',
    static function (string $slug, string $name, string $description, ?string $url): void {
        io()->warning(FetchTasks::SCRAPING_WARNING);
        fetch_import_data($url, $name, $description, $slug);
    },
);

Fixtures::beforeLoad(static function (App $app, array $suites): void {
    if (!\in_array('app', $suites, true)) {
        return;
    }

    $wasDeployed = is_import_scaffold_deployed($app);
    ensure_import_scaffold($app, $app->name());

    if (!$wasDeployed) {
        Docker::run($app, 'php bin/console cache:clear --no-warmup -n');
    }
});
