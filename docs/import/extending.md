---
title: Extending the import
description: Attach your own data to imported shops with an import extension.
sidebar:
  order: 6
---

The import only adds the catalog, its channel and a demo customer: it does not change your database schema. A project
needing more (e.g. a sales kit giving each demo shop its own admin) registers an import extension from its `castor.php`:

```php
<?php

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Import\Extension\ImportExtensionInterface;
use OpenColibri\Starter\Import\Extension\ImportExtensions;

ImportExtensions::register('demo_admins', new class implements ImportExtensionInterface {
    public function scaffold(App $app): void
    {
        // Copy your own templates into the Sylius app, patch entities, run migrations…
    }

    public function buildFixtures(string $projectSlug, array $target): array
    {
        // Written to config/sylius/fixtures/import/{slug}/extensions/{name}.php, loaded after the catalog.
        return ['demo_admin' => ['sylius_fixtures' => ['suites' => ['import' => ['fixtures' => [/* … */]]]]]];
    }

    public function describeShop(string $projectSlug, ?array $config): array
    {
        // Extra fields of the shop in `castor sylius:import:list`.
        return [];
    }
});
```

| Hook             | Called when                                                                                |
|------------------|--------------------------------------------------------------------------------------------|
| `scaffold`       | each import task makes sure the import scaffold is in the Sylius app                       |
| `buildFixtures`  | `sylius:import:fixtures:generate` writes the project's fixtures (`$target`: `channel`, `subdomain`, `shared`) |
| `describeShop`   | `sylius:import:list` lists the shops (`$config` is the project's `project.yaml`)           |

Keys you add to `project.yaml` are kept when the import rewrites it. To remove what you attached to an imported shop,
listen to the `import.channel_reset` Symfony event in the Sylius app, dispatched by `sylius:import:channel:reset`
(on `fixtures:load` and `sylius:import:delete`) with the channel code as subject and the `prefix` and `keep_channel`
arguments.

## Extensions shipped with the import

The import ships extensions for several shops imported on their own channel in one Sylius app. None is enabled by
default: an app built with the starter gets none of their files, fixtures or fields until its `castor.php` registers
them.

### One theme per shop

```php
use OpenColibri\Starter\Import\Extension\ImportExtensions;
use OpenColibri\Starter\Import\Extension\ShopThemeExtension;

ImportExtensions::register(ShopThemeExtension::NAME, new ShopThemeExtension());
```

Each shop then gets its own storefront theme, among the [themes installed with the theme-bundle target](../commands/themes.md#one-theme-per-channel):

- the theme is kept in the `project.yaml` of the shop (`theme`), the theme of its dataset until one is picked;
- `ShopThemeExtension::set($project, $theme)` picks one (`default` for the Sylius look): it applies it to the channel
  of the shop right away (`castor sylius:theme:channel`), keeps it, and writes it in the fixtures when they are
  generated. Call it from a task of your project;
- the `import_channel_theme` fixture, copied into the app (`src/Fixture/ImportChannelThemeFixture.php`), sets it again
  each time `sylius:import:fixtures:load` recreates the channel. A shop with no theme and no dataset theme keeps its
  channel as it is;
- `sylius:import:list` gives the `theme` of each shop (`default` when it has none).

### Private shops

```php
use OpenColibri\Starter\Import\Extension\ImportExtensions;
use OpenColibri\Starter\Import\Extension\PrivateShopExtension;

ImportExtensions::register(PrivateShopExtension::NAME, new PrivateShopExtension());
```

A shop imported on its own channel can then be private: only its demo customer (its storefront login and password,
`<slug>@shop.local`) gets into its storefront and shop API, for a demo shared with one customer. The default channel,
which hosts the shops imported on it, stays public.

- Its scaffold adds to the app a private shop field on the channel (`ImportPrivateShopAwareTrait`, wired onto
  `src/Entity/Channel/Channel.php`, and its migration), a voter and its `access_control` rules in
  `config/packages/security.yaml`, a login page, the `import_private_shop` fixture and the
  `sylius:import:channel:private` command;
- the visibility is kept in the `project.yaml` of the shop (`private: true`);
- `PrivateShopExtension::set($project, $private)` changes it right away, keeps it, and writes it in the fixtures when
  they are generated. Call it from a task of your project;
- `sylius:import:list` gives `private` and `canBePrivate` for each shop.

### One admin per shop

```php
use OpenColibri\Starter\Import\Extension\ChannelAdminExtension;
use OpenColibri\Starter\Import\Extension\ImportExtensions;

ImportExtensions::register(ChannelAdminExtension::NAME, new ChannelAdminExtension());
```

Each shop imported on its own channel then gets a Sylius admin restricted to that channel: it only sees and edits
the catalog of its shop. The default channel keeps its own admins.

- Its scaffold changes the app, which is why it is opt-in: a channel scope on the admin user
  (`ImportChannelAdminAwareTrait`, wired onto `src/Entity/User/AdminUser.php`, and its migration adding
  `import_channel_code` and `import_code_prefix` to `sylius_admin_user`), the voter, access and menu listeners and
  product form extension that enforce it, product, variant and taxon repositories filtering by channel
  (`config/sylius/import_channel_admin.yaml` replaces the Sylius ones), a listener removing the admins of a channel
  `sylius:import:channel:reset` flushes, and the `import_admin_user` fixture;
- the admin of a shop is `<slug>`; its password is generated on the first fixtures generation and kept in the
  `project.yaml` of the shop (`admin_password`);
- `sylius:import:list` gives the `adminPassword` of each shop.

## Progress of the long steps

Generating or downloading images takes a while, and a tool running the tasks in the background (castor-api) only gets
their output once they finish. The image steps of `sylius:import:dataset:build`, `sylius:import:dataset:images` and
`sylius:import:fixtures:generate` therefore write their progress to `.castor/import/var/.progress/<key>.json`, where
the key is `dataset-<dataset slug>` or `shop-<project slug>`:

```json
{"label": "Product images", "done": 3, "total": 18, "current": "Baguette fraîche", "updatedAt": 1792744000}
```

`current` is the item being processed. The file is removed when the step ends. A task locks its shop or dataset, so a
key has a single writer at a time. Report the progress of your own steps with `import_progress_start()`,
`import_progress_current()`, `import_progress_advance()` and `import_progress_finish()`.
