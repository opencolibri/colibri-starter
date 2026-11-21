---
title: Your own plugins
description: Register your own plugins in the sylius:add and sylius:remove commands.
sidebar:
  order: 6
---

The [plugin list](plugins.md) is not closed: you can add your own plugins to `sylius:add` and `sylius:remove`, from
your own `castor.php` (or from any file it `import()`s). Once registered, your plugin is offered in the interactive
prompt of the commands **and** is callable by name on the command line, exactly like a built-in one.

Themes work the same way, see [Your own themes](../themes/custom-themes.md).

## Available attributes

The attributes live in `OpenColibri\Starter\Plugins\Attribute`, take a required `name` argument, and can be put on a
function or on a class.

| Attribute                                           | Command                       | Called when                       |
|-----------------------------------------------------|-------------------------------|-----------------------------------|
| `#[AsPluginInstaller(name: '…', description: '…')]` | `castor sylius:add <name>`    | `sylius:add` picks the plugin     |
| `#[AsPluginRemover(name: '…', description: '…')]`   | `castor sylius:remove <name>` | `sylius:remove` picks the plugin  |

`name` is the identifier you type on the command line, so keep it shell friendly: lowercase letters and underscores.

The optional `description` is displayed next to the name in the interactive prompt of `sylius:add` and
`sylius:remove`. Omit it and the plugin is listed by its name alone.

## Declaring a plugin

### As a function

The most direct option, and a good fit for a plugin living in your `castor.php`:

```php
<?php

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Plugins\Attribute\AsPluginInstaller;
use OpenColibri\Starter\Plugins\Attribute\AsPluginRemover;
use OpenColibri\Starter\Core\Util\Assets;
use OpenColibri\Starter\Core\Util\Composer;
use OpenColibri\Starter\Core\Util\Database;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Symfony;

use function Castor\io;

#[AsPluginInstaller(name: 'acme_loyalty', description: 'Acme loyalty programme for Sylius')]
function install_acme_loyalty(App $app): void
{
    io()->title('Adding the Acme Loyalty plugin');

    Composer::allowContribRecipes($app);
    Docker::run($app, 'composer require acme/sylius-loyalty-plugin');
    Docker::run($app, 'yarn install');
    Database::migrate($app);
    Assets::build($app);
    Symfony::cacheClear($app);
}

#[AsPluginRemover(name: 'acme_loyalty', description: 'Acme loyalty programme for Sylius')]
function remove_acme_loyalty(App $app): void
{
    io()->title('Removing the Acme Loyalty plugin');

    Composer::allowContribRecipes($app);
    Database::rollbackPluginMigrations($app, 'Acme\SyliusLoyaltyPlugin\Migrations');
    Docker::run($app, 'composer remove acme/sylius-loyalty-plugin');
    Assets::build($app);
    Symfony::cacheClear($app);
}
```

It is now part of the plugin commands:

```bash
castor sylius:add acme_loyalty
castor sylius:remove acme_loyalty
```

### As a class

Handy when the plugin needs a bit of logic, or when you would rather not pollute the global function namespace. The
class is instantiated once, without arguments, and called on install or remove:

```php
<?php

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Util\Database;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Symfony;
use OpenColibri\Starter\Plugins\Attribute\AsPluginInstaller;

#[AsPluginInstaller(name: 'acme_loyalty', description: 'Acme loyalty programme for Sylius')]
final class AcmeLoyaltyInstaller
{
    public function __invoke(App $app): void
    {
        Docker::run($app, 'composer require acme/sylius-loyalty-plugin');
        Database::migrate($app);
        Symfony::cacheClear($app);
    }
}
```

The plugin receives the targeted application as an [`App` instance](../getting-started/toolbox.md), and can use the
[helpers](../getting-started/toolbox.md#helpers) of the core.

## Good to know

- **Plugins are discovered at boot.** Castor scans the functions and classes it has loaded, once, when the
  application starts. Put them in your `castor.php`, or in a file added with `import(__DIR__ . '/…')`: a class living in
  a file nobody imports is never seen.
- **Names are unique.** The `name` is the CLI argument and the label of the interactive prompt. Registering it twice,
  or reusing a built-in name, replaces the previous plugin.
- **Pair your installers with removers.** `sylius:remove` warns when the remover is missing.
- **Classes must be constructible and callable.** They are instantiated with no arguments and must expose an
  `__invoke()` method; a constructor with required arguments, or a class without `__invoke()`, fails at boot with a
  configuration error.
- **A plugin can also be a task.** Nothing prevents you from adding `#[AsTask]` next to the attribute, if you want
  the very same code to be reachable as a standalone command.
