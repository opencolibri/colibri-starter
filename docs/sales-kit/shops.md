---
title: Demo shops
description: The sylius:shop:* tasks of the sales kit, its extensions, and what each demo shop gets.
sidebar:
  order: 2
---

`opencolibri/starter-sales-kit` runs the demo shops of a sales kit: the admin calls its `sylius:shop:*` tasks
through castor-api, and they also work from the command line.

## A shop

A shop is created from a [dataset](../datasets/index.md): the dataset is copied into `.castor/import/var/<shop>/`, so
its settings and images can change without touching the dataset, and deleting or rebuilding the dataset leaves it
unchanged. Its `project.yaml` keeps its name, the channel it is loaded into, the password of its demo customer and
what the extensions keep.

```bash
castor sylius:shop:create cellar                                         # shop "cellar"
castor sylius:shop:create cellar --name="Cave du Port" --description="…" # shop "cave-du-port"
castor sylius:shop:generate cave-du-port                                 # writes its fixtures
castor sylius:shop:load cave-du-port                                     # loads them into Sylius
castor sylius:shop:list                                                  # JSON when not run in a terminal
castor sylius:shop:delete cave-du-port                                   # its channel, catalog and files
```

`sylius:shop:generate` decides which channel receives the catalog, kept in `project.yaml` (`shop` key):

| `--subdomain`              | Channel                              | Hostname                | On load                                                     |
|----------------------------|--------------------------------------|-------------------------|-------------------------------------------------------------|
| omitted (the shop slug), or e.g. `cave` | a channel of its own (`CAVE_DU_PORT`) | `cave.{domain}`         | the channel is reset and recreated                          |
| `""`                       | the default `WEB_STORE` channel      | the app domain          | only the shop catalog is replaced; the channel, its orders and admins are kept |

`--limit` caps the number of products.

## What each shop gets

- **A demo customer**: `<shop>@shop.local`, with the password of `project.yaml` (`shop_password`), shown in the admin.
- **Its images**: the logo, header and interstice images uploaded in the admin (`media/image_*` of the shop). They are
  copied to the Sylius app as static files, `public/media/shops/<CHANNEL>/<role>.<hash>.<ext>`, and
  `config/import/shop_images.php` lists their URLs per channel; the storefront feeds them to the shared
  [hero](../storefront/hero.md) and [brand](../storefront/brand.md) slots. The hash changes the URL when an image
  changes, so browsers cache them for good. On Upsun, `public/media` is a storage mount: upload `public/media/shops/`
  to it (`upsun mount:upload`).

## Extensions

The package registers these [fixtures extensions](../datasets/extending.md#attach-data-to-the-loaded-catalogs): they
only handle the demo shops, not the catalog `sylius:dataset:load` puts into the default channel.

### One theme per shop

Each shop gets its own storefront theme, among the [themes installed with the theme-bundle target](../commands/themes.md#one-theme-per-channel):

```bash
castor sylius:shop:theme cave-du-port cellar     # "default" for the Sylius look
```

The theme is applied to the channel right away (`castor sylius:theme:channel`), kept in `project.yaml` (`theme`, the
theme of the dataset until one is picked), and set again by the `import_channel_theme` fixture each time the channel
is recreated.

### Private shops

```bash
castor sylius:shop:visibility cave-du-port private   # or public
```

Only the demo customer of a private shop gets into its storefront and shop API, for a demo shared with one customer.
The scaffold adds to the app a private shop field on the channel (`ImportPrivateShopAwareTrait`, wired onto
`src/Entity/Channel/Channel.php`, and its migration), a voter and its `access_control` rules in
`config/packages/security.yaml`, a login page, the `import_private_shop` fixture and the
`sylius:import:channel:private` command. The visibility is kept in `project.yaml` (`private`). A shop on the default
channel stays public.

### One admin per shop

Each shop on a channel of its own gets a Sylius admin restricted to that channel: it only sees and edits the catalog
of its shop. Its login is the shop slug, its password is generated on the first generation and kept in
`project.yaml` (`admin_password`). The scaffold adds a channel scope on the admin user (`ImportChannelAdminAwareTrait`,
wired onto `src/Entity/User/AdminUser.php`, and its migration), the voter, access and menu listeners and product form
extension that enforce it, product, variant and taxon repositories filtering by channel
(`config/sylius/import_channel_admin.yaml`), a listener removing the admins of a reset channel, and the
`import_admin_user` fixture.

## Push a shop to a server

To load a shop on a remote stack without building it there, copy its directory over SSH with rsync:

```bash
castor sylius:shop:push cave-du-port --dry-run   # show what would be copied
castor sylius:shop:push cave-du-port             # copy it
castor sylius:shop:push cave-du-port --delete    # also delete remote files missing locally
```

Configure the server in the `.env.local` of the project root:

| Variable                        | Default         | Description                           |
|---------------------------------|-----------------|---------------------------------------|
| `IMPORT_VAR_PUSH_SSH_HOST`      | —               | SSH host (required)                   |
| `IMPORT_VAR_PUSH_REMOTE_DIR`    | —               | Root of the remote project (required) |
| `IMPORT_VAR_PUSH_SSH_USER`      | `debian`        | SSH user                              |
| `IMPORT_VAR_PUSH_SSH_PORT`      | `22`            | SSH port                              |
| `IMPORT_VAR_PUSH_IDENTITY_FILE` | SSH default key | Private key (`~/` is expanded)        |
