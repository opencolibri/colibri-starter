---
title: Setting up
description: What the stack of a sales kit runs, and how to install the admin next to it.
sidebar:
  order: 4
---

## The stack

The stack is a Castor project with the shared Sylius app of the demo shops and
[castor-api](https://github.com/jeromegxj/castor-api). In its `castor.php`, it:

- requires `opencolibri/starter-sales-kit`: its [`sylius:shop:*` tasks](shops.md) and its extensions (a theme,
  a private storefront and an admin per shop) come with it, nothing to enable;
- exposes the tasks the admin calls through castor-api (`#[AsApi]`): the `sylius:shop:*` and `sylius:dataset:*` tasks,
  and two thin `sales-kit:theme:*` tasks over `opencolibri/starter-themes`:

  | Task | Wraps |
  |---|---|
  | `sales-kit:theme:list` | `ThemeBundle::pickable()` and `HeaderLayout::all()`, as `{"themes": [...], "headers": {...}}` |
  | `sales-kit:theme:header <theme> --menu --search` | `castor sylius:theme:header` |

- installs every theme next to each other, once and after each update of `opencolibri/starter-themes`:

  ```bash
  castor sylius:theme:setup all --target=theme-bundle
  ```

## The admin

The admin is a Symfony app with `opencolibri/starter-sales-kit-bundle`, next to the stack: it reaches castor-api over HTTP and
mounts the `.castor/import/var`, `.castor/import/exports` and `.castor/import/datasets` directories of the stack (the
last one read only).

```bash
composer require opencolibri/starter-sales-kit-bundle:^0.1@dev sylius/shadcn-admin-ui:^0.14@dev
```

The [README of opencolibri/starter-sales-kit-bundle](https://github.com/opencolibri/colibri-starter/blob/main/src/SalesKitBundle/README.md)
gives the install steps (hooks, routes, firewall, styles, JavaScript), the configuration and the customization points
(branding, colors, blocks of each page, grids).
