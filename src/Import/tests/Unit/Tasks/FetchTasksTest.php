<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Import\Tests\Unit\Tasks;

use Castor\Attribute\AsTask;
use OpenColibri\Starter\Import\Dataset\DatasetBuilders;
use OpenColibri\Starter\Import\Tasks\FetchTasks;
use PHPUnit\Framework\TestCase;

final class FetchTasksTest extends TestCase
{
    public function testRegistersFetchAndVarPushTasks(): void
    {
        $tasks = new FetchTasks('app', '/tmp/app');
        $names = [];

        foreach ($tasks() as $task) {
            /** @var AsTask $descriptor */
            $descriptor = $task['task'];
            $names[] = $descriptor->namespace . ':' . $descriptor->name;
            static::assertArrayHasKey('function', $task);
        }

        static::assertContains('sylius:import:existing:fetch', $names);
        static::assertContains('sylius:import:var:push', $names);
    }

    public function testRegistersTheExistingDatasetSource(): void
    {
        static::assertContains('existing', DatasetBuilders::sources());
    }
}
