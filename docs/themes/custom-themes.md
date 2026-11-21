---
title: Your own themes
description: Register your own storefront themes in the sylius:theme:setup command.
sidebar:
  order: 100
---

The [theme list](index.md) is not closed: you can add your own themes to `sylius:theme:setup`, from your own
`castor.php` (or from any file it `import()`s). Once registered, your theme is offered in the interactive prompt of the
command **and** is callable by name on the command line, exactly like a built-in one.

Plugins work the same way, see [Your own plugins](../commands/custom-plugins.md).

## Available attributes

The attributes live in `OpenColibri\Starter\Themes\Attribute`, take a required `name` argument, and can be put on a
function or on a class.

| Attribute                        | Command                                     | Called when                 |
|----------------------------------|---------------------------------------------|-----------------------------|
| `#[AsThemeInstaller(name: '…')]` | `castor sylius:theme:setup <name>`          | the theme is selected       |
| `#[AsThemeRemover(name: '…')]`   | `castor sylius:theme:setup <another-theme>` | another theme gets selected |

`name` is the identifier you type on the command line, so keep it shell friendly: lowercase letters and underscores.

## Declaring a theme

A class is handy when the theme needs a bit of logic, or when you would rather not pollute the global function
namespace. It is instantiated once, without arguments, and called on install or remove:

```php
<?php

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Themes\Attribute\AsThemeInstaller;
use OpenColibri\Starter\Themes\Attribute\AsThemeRemover;
use OpenColibri\Starter\Core\Util\Assets;
use OpenColibri\Starter\Core\Util\Javascript;
use OpenColibri\Starter\Core\Util\Symfony;

#[AsThemeInstaller(name: 'acme')]
final class AcmeThemeInstaller
{
    public function __invoke(App $app): void
    {
        Symfony::addBundle($app, 'AcmeShopBundle', ['all' => true]);
        Javascript::addImport($app, 'assets/shop/entrypoint.js', './styles/acme.scss');
        Assets::install($app);
        Assets::build($app);
        Symfony::cacheClear($app);
    }
}

#[AsThemeRemover(name: 'acme')]
final class AcmeThemeRemover
{
    public function __invoke(App $app): void
    {
        Javascript::removeImport($app, 'assets/shop/entrypoint.js', './styles/acme.scss');
        Assets::build($app);
        Symfony::cacheClear($app);
    }
}
```

A function works too, with the attribute on it, like the [plugin example](../commands/custom-plugins.md#as-a-function).

```bash
castor sylius:theme:setup acme
```

The theme receives the targeted application as an [`App` instance](../getting-started/toolbox.md), and can use the
[helpers](../getting-started/toolbox.md#helpers) of the core. It reads the shared [hero](../storefront/hero.md) and
[brand](../storefront/brand.md) slots, so the data other packages contribute (a demo shop's logo, for instance) shows
up in it.

## Good to know

- **Only one theme is active at a time.** Installing a theme removes the others first, then installs it. Keep the
  remover of your theme idempotent, and reverse exactly what the installer did.
- **Pair your installer with a remover.** `sylius:theme:setup` runs the remover of every *other* registered theme: a
  theme without one makes every single theme switch print an `Unknown theme remover` warning.
- **Themes are discovered at boot.** Castor scans the functions and classes it has loaded, once, when the application
  starts. Put them in your `castor.php`, or in a file added with `import(__DIR__ . '/…')`: a class living in a file
  nobody imports is never seen.
- **Names are unique.** Registering a name twice, or reusing a built-in name, replaces the previous theme.
- **Classes must be constructible and callable.** They are instantiated with no arguments and must expose an
  `__invoke()` method, or Castor fails at boot with a configuration error.
- **Follow the styling rules of the built-in themes** if you want light and dark schemes and accessible contrast: see
  [Light and dark schemes](color-schemes.md) and [Header layout](header-layout.md).
