---
title: Packages
description: The opencolibri/starter-* packages, their namespace and the tasks they provide.
sidebar:
  order: 2
---

| Package                                                                              | Namespace               | Tasks                                                                 |
|--------------------------------------------------------------------------------------|-------------------------|-----------------------------------------------------------------------|
| [`opencolibri/starter-core`](https://github.com/opencolibri/starter-core)       | `OpenColibri\Starter\Core`    | Castor plugin: `SyliusService`, installer, `sylius:fixtures`, helpers |
| [`opencolibri/starter-plugins`](https://github.com/opencolibri/starter-plugins) | `OpenColibri\Starter\Plugins` | [`sylius:plugin:add`, `sylius:plugin:remove`](../commands/plugins.md) |
| [`opencolibri/starter-themes`](https://github.com/opencolibri/starter-themes)   | `OpenColibri\Starter\Themes`  | [`sylius:theme:setup`](../commands/themes.md)                         |
| [`opencolibri/starter-menu`](https://github.com/opencolibri/starter-menu)       | `OpenColibri\Starter\Menu`    | [`sylius:menu:remove`](../commands/admin-menu.md)                     |
| [`opencolibri/starter-datasets`](https://github.com/opencolibri/starter-datasets) | `OpenColibri\Starter\Datasets` | [ready-made datasets](../datasets/index.md#ready-made-datasets) |
| [`opencolibri/starter-fixtures`](https://github.com/opencolibri/starter-fixtures) | `OpenColibri\Starter\Fixtures` | [`sylius:dataset:list`, `sylius:dataset:load`](../datasets/index.md) |
| [`opencolibri/starter-dataset-builder`](https://github.com/opencolibri/starter-dataset-builder) | `OpenColibri\Starter\DatasetBuilder` | [`sylius:dataset:build`](../datasets/build.md) (AI) |
| [`opencolibri/starter-sales-kit`](https://github.com/opencolibri/starter-sales-kit) | `OpenColibri\Starter\SalesKit` | [`sylius:shop:*`](../sales-kit/shops.md): the demo shops of a sales kit |
| [`opencolibri/starter-upsun`](https://github.com/opencolibri/starter-upsun)     | `OpenColibri\Starter\Upsun`   | [`sylius:upsun:*`](../commands/upsun.md)                              |
| [`opencolibri/starter-sales-kit-bundle`](https://github.com/opencolibri/starter-sales-kit-bundle) | `OpenColibri\Starter\SalesKitBundle` | Symfony bundle: the sales kit admin (demo shops, datasets), on Sylius Shadcn Admin UI |
| `opencolibri/starter`                                                      | —                       | All of the above                                                      |

The core is the Castor plugin itself. Every other package only contributes tasks: it registers them in the core
from a file autoloaded by Composer, so installing a package is enough to get its tasks on your `SyliusService`.

`opencolibri/starter-sales-kit-bundle` is the exception: a Symfony bundle, installed in the admin app of a sales kit (it
drives the `sylius:shop:*` and `sylius:dataset:*` tasks through castor-api). See the [sales kit](../sales-kit/index.md) section and its [README](https://github.com/opencolibri/colibri-starter/blob/main/src/SalesKitBundle/README.md).
