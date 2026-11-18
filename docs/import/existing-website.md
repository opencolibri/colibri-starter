---
title: Existing website
description: Import the catalog of an existing website into Sylius.
sidebar:
  order: 3
---

Fetch the products and collections of an existing shop into YAML under `.castor/import/var/{project-slug}/`, then turn
them into Sylius fixtures.

:::caution[Scraping and the law]
`sylius:import:existing:fetch` scrapes the website you point it to: it reads its sitemap and its product and collection
pages, and the fixtures generated from them download its images. Scraping a website may be forbidden by its terms of
use, by copyright and database rights (photos, descriptions, the catalog as a whole), or by personal data law,
depending on the website and your country.

This command is meant to **migrate a project you own** to Sylius, or a website whose owner gave you their written
permission to copy its content (a client whose shop you migrate, for example). Do not use it to copy a third-party
catalog. You are responsible for the websites you fetch and for what you do with the data.
:::

## Fetch the website

```bash
castor sylius:import:existing:fetch https://shop.example.com --project=example
```

| Option          | Description                                                        |
|-----------------|--------------------------------------------------------------------|
| `url`           | URL of the website (normalized to its base URL)                    |
| `--project`     | Project slug (asked, or derived from the name, when omitted)       |
| `--name`        | Shop name                                                          |
| `--description` | Shop description                                                   |

The command discovers the sitemap (from `robots.txt`, with dedicated handling for Shopify and Magento), picks the
product and collection pages, and uses the AI configured in [the prerequisites](index.md#prerequisites) to extract the
collections.
The project is configured for an existing website import, so later commands reuse its URL.

You can also prepare the YAML yourself, without fetching a website.

## Generate and load the fixtures

```bash
castor sylius:import:fixtures:generate --project=example --limit=100
castor sylius:import:fixtures:load --project=example
```

See [Fixtures](fixtures.md) for the options of these commands.

## Push the fetched data to a server

To load the fixtures on a remote server without fetching the website from it, copy the import data of a project over
SSH with rsync:

```bash
castor sylius:import:var:push --project=example --dry-run   # show what would be copied
castor sylius:import:var:push --project=example             # copy it
castor sylius:import:var:push --project=example --delete    # also delete remote files missing locally
```

Configure the server in `.castor/.env.local`:

| Variable                        | Default         | Description                                         |
|---------------------------------|-----------------|-----------------------------------------------------|
| `IMPORT_VAR_PUSH_SSH_HOST`      | —               | SSH host (required)                                 |
| `IMPORT_VAR_PUSH_REMOTE_DIR`    | —               | Root of the remote project (required)               |
| `IMPORT_VAR_PUSH_SSH_USER`      | `debian`        | SSH user                                            |
| `IMPORT_VAR_PUSH_SSH_PORT`      | `22`            | SSH port                                            |
| `IMPORT_VAR_PUSH_IDENTITY_FILE` | SSH default key | Private key (`~/` is expanded)                      |
