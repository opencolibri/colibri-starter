---
title: Extending
description: Ship datasets from a package, and attach your own data to the catalogs loaded into Sylius.
sidebar:
  order: 3
---

## Ship datasets

A package ships its own datasets by registering their directory from an autoloaded file, like
`opencolibri/starter-datasets` does:

```php
use OpenColibri\Starter\Core\Dataset\DatasetDirectories;

DatasetDirectories::register('my-datasets', __DIR__ . '/../datasets');
```

Each sub-directory holding a `products.yaml` is then a dataset (see [the format](index.md#the-format)). These
datasets are built-in: read only, and they win over a built dataset of the same slug. Register a directory with
`builtIn: false` for editable ones, and a closure when it depends on the project:

```php
DatasetDirectories::register('built', static fn(): string => Workspace::importDir() . '/datasets', builtIn: false);
```

## Attach data to the loaded catalogs

Loading a catalog adds its taxons, products, prices and, for a dedicated channel, the channel: it does not change your
database schema. A package needing more registers a fixtures extension from an autoloaded file:

```php
use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Fixtures\CatalogFixtures;
use OpenColibri\Starter\Fixtures\CatalogTarget;
use OpenColibri\Starter\Fixtures\Extension\FixturesExtensionInterface;
use OpenColibri\Starter\Fixtures\Extension\FixturesExtensions;

FixturesExtensions::register('demo_admins', new class implements FixturesExtensionInterface {
    public function scaffold(App $app): void
    {
        // Copy your own files into the Sylius app, patch entities, run migrations…
    }

    public function buildFixtures(CatalogTarget $target): array
    {
        // Written to config/sylius/fixtures/import/<key>/extensions/<name>.php, loaded after the catalog.
        return ['demo_admin' => CatalogFixtures::suite('demo_admin', 'admin_user', [/* … */], priority: -100)];
    }
});
```

| Hook            | Called when                                                                                      |
|-----------------|--------------------------------------------------------------------------------------------------|
| `scaffold`      | the fixtures make sure their files are in the Sylius app (each load, and the `app` suite)        |
| `buildFixtures` | the fixtures of a catalog are generated                                                          |

The `CatalogTarget` tells where the catalog goes: its `key` (naming its fixtures, images and code prefix), its
`channelCode` (`WEB_STORE`, or a dedicated channel) and the `hostname` of a dedicated channel. Return no fixture for
the targets you do not handle: the extensions of the [sales kit](../sales-kit/shops.md#extensions) only handle the
demo shops. A negative priority runs a fixture after the channel the catalog recreates.

To remove what you attached to a catalog, listen to the `import.channel_reset` Symfony event in the Sylius app,
dispatched by `sylius:import:channel:reset` (before a catalog is loaded again or removed) with the channel code as
subject and the `prefix` and `keep_channel` arguments.

## Load a catalog from your own task

```php
use OpenColibri\Starter\Core\Dataset\Datasets;
use OpenColibri\Starter\Fixtures\CatalogLoader;
use OpenColibri\Starter\Fixtures\CatalogTarget;

$loader = new CatalogLoader($app);
$target = new CatalogTarget('outlet', 'Outlet', 'OUTLET', 'outlet.app.test');
$loader->generate(Datasets::get('cellar'), $target);
$loader->load($target);
```

A target other than the default channel gets a channel of its own, created on load and removed on reset
(`$loader->unload($target)`).

## Progress of the long steps

Generating or downloading images takes a while, and a tool running the tasks in the background (castor-api) only gets
their output once they finish. The image steps of `sylius:dataset:build` and `sylius:dataset:images` therefore write
their progress to `.castor/import/var/.progress/dataset-<slug>.json`:

```json
{"label": "Product images", "done": 3, "total": 18, "current": "Baguette fraîche", "updatedAt": 1792744000}
```

The file is removed when the step ends. Report the progress of your own steps with
`OpenColibri\Starter\Core\Progress\TaskProgress` (`start()`, `current()`, `advance()`, `finish()`).
