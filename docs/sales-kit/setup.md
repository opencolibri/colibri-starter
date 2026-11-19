---
title: Setting up
description: What the stack of a sales kit runs, and how to install the admin next to it.
sidebar:
  order: 3
---

## The stack

The stack is a Castor project with the shared Sylius app of the demo shops and
[castor-api](https://github.com/jeromegxj/castor-api). In its `castor.php`, it:

- enables the import extensions of `opencolibri/starter-import` the demo shops need
  (see [Extending the import](../import/extending.md#extensions-shipped-with-the-import)):

  ```php
  use OpenColibri\Starter\Import\Extension\ChannelAdminExtension;
  use OpenColibri\Starter\Import\Extension\ImportExtensions;
  use OpenColibri\Starter\Import\Extension\PrivateShopExtension;
  use OpenColibri\Starter\Import\Extension\ShopThemeExtension;

  ImportExtensions::register(ShopThemeExtension::NAME, new ShopThemeExtension());
  ImportExtensions::register(PrivateShopExtension::NAME, new PrivateShopExtension());
  ImportExtensions::register(ChannelAdminExtension::NAME, new ChannelAdminExtension());
  ```

- exposes the tasks the admin calls through castor-api (`#[AsApi]`): the `sylius:import:*` tasks, and four thin
  `sales-kit:*` tasks over the packages:

  | Task | Wraps |
  |---|---|
  | `sales-kit:theme:list` | `ThemeBundle::pickable()` and `HeaderLayout::all()`, as `{"themes": [...], "headers": {...}}` |
  | `sales-kit:theme:header <theme> --menu --search` | `castor sylius:theme:header` |
  | `sales-kit:shop:theme <project> <theme>` | `ShopThemeExtension::set()` |
  | `sales-kit:shop:visibility <project> <public\|private>` | `PrivateShopExtension::set()` |

- installs every theme next to each other, once and after each update of `opencolibri/starter-themes`:

  ```bash
  castor sylius:theme:setup all --target=theme-bundle
  ```

## The admin

The admin is a Symfony app with `opencolibri/starter-sales-kit`, next to the stack: it reaches castor-api over HTTP and
mounts the `.castor/import/var`, `.castor/import/exports` and `.castor/import/datasets` directories of the stack (the
last one read only).

```bash
composer require opencolibri/starter-sales-kit:^0.1@dev sylius/shadcn-admin-ui:^0.14@dev
```

The [README of opencolibri/starter-sales-kit](https://github.com/opencolibri/colibri-starter/blob/main/src/SalesKit/README.md)
gives the install steps (hooks, routes, firewall, styles, JavaScript), the configuration and the customization points
(branding, colors, blocks of each page, grids).
