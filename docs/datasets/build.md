---
title: Build a dataset
description: Build a dataset with AI, from a description or from the catalog of an existing website, write one by hand, or import a zip.
sidebar:
  order: 2
---

`opencolibri/starter-dataset-builder` builds datasets with AI, or [by hand](#by-hand) and [from a zip](#import-a-zip),
into `.castor/import/datasets/<slug>/`. A built dataset
is final: loading it ([`sylius:dataset:load`](index.md#load-a-dataset), or a [demo shop](../sales-kit/shops.md)) needs
neither the AI nor the source website.

## Configure the AI

Set the AI in `.castor/.env.local` (ignored by git), over the defaults of `.castor/.env` (see `.castor/.env.example`).
The `.env` and `.env.local` of the project root are read too. `.castor/.env` picks Ollama (`qwen2.5:latest`, images
with `x/flux2-klein`); without it, the defaults below apply:

| Variable         | Default                  | Description                            |
|------------------|--------------------------|----------------------------------------|
| `AI_PROVIDER`    | `openrouter`             | `ollama` (local) or `openrouter`       |
| `AI_MODEL`       | provider-specific        | Text / structured-output model         |
| `AI_IMAGE_MODEL` | provider-specific        | Image generation model                 |
| `AI_BASE_URL`    | `http://127.0.0.1:11434` | Ollama URL (ignored for OpenRouter)    |
| `AI_API_KEY`     | —                        | Required when `AI_PROVIDER=openrouter` |

## From a description

```bash
castor sylius:dataset:build --name="Tea House" --description="Loose leaf teas and tea ware"
```

The AI writes the catalog (20 products at most, `--limit` to ask for fewer): products with their collection, price and
a prompt of their image, then generates the images.

## From an existing website

```bash
castor sylius:dataset:build --source=existing --name="Tea House" --url=https://www.example-tea.com
```

:::caution[Scraping and the law]
The `existing` source scrapes the website you point it to: it reads its sitemap, its product and collection pages, and
downloads its images. Scraping a website may be forbidden by its terms of use, by copyright and database rights
(photos, descriptions, the catalog as a whole), or by personal data law, depending on the website and your country.

It is meant to **migrate a website you own** to Sylius, or a website whose owner gave you their written permission to
copy its content (a client whose shop you migrate, for example). Do not use it to copy a third-party catalog. You are
responsible for the websites you fetch and for what you do with the data.
:::

The builder discovers the sitemap (from `robots.txt`, with dedicated handling for Shopify and Magento), picks the
product and collection pages, and uses the AI to extract the collections. The AI then selects the products to keep
(`--limit`, 100 by default), assigns each one its collection and a price, and the images are downloaded.

## What the build does

1. The source writes `products.yaml` and `collections.yaml`. Building a dataset again replaces its catalog, and keeps
   the previous one when the source fails.
2. A fetched catalog is curated by the AI (above).
3. When more than eight collections hold products, the AI groups them into a two-level menu (e.g. Men > T-shirts).
4. `dataset.yaml` records the name, description, URL and source.
5. The image of every product and collection is downloaded or generated into `media/`.

```bash
castor sylius:dataset:images tea-house   # store the images still missing (after a failed download, for example)
castor sylius:dataset:delete tea-house   # built datasets only: catalogs loaded from it are kept
```

The dataset slug is the slug of the name, or `--dataset`. A built-in dataset cannot be built or deleted.

## By hand

Write a catalog without AI: create an empty dataset, then add its collections and its products, each with its image.
The [dev-kit admin](../dev-kit/index.md#your-own-dataset) does it from forms; the tasks do it from the command line:

```bash
castor sylius:dataset:create --name="Tea House" --description="Loose leaf teas" --theme=teahouse
castor sylius:dataset:collection tea-house --name=Green
castor sylius:dataset:collection tea-house --name=Sencha --parent=Green          # shown under Green
castor sylius:dataset:product tea-house --title="Gyokuro" --collection=Sencha --price=24.5 --image=gyokuro.jpg
castor sylius:dataset:product tea-house --index=0 --expected="Gyokuro" --price=22  # change the first product
castor sylius:dataset:product tea-house --index=0 --remove
castor sylius:dataset:update tea-house --name="Tea Garden"                      # name, description, theme
```

- `--index` is the position of the product or collection in its YAML file (from 0); without it, one is added.
  `--expected` (its current title or name) refuses the change when the file changed in between.
- `--image` is a file of `.castor/import/var/.uploads/` (where the admin puts its uploads), converted to WebP into
  `media/` and removed afterwards. The image it replaces is removed.
- Renaming a collection moves its products and sub-collections along. Removing one keeps its products, without a
  collection. The menu has two levels: a parent is a top-level collection.
- Any dataset built here can be changed this way, not a ready-made one: export it and import the zip under another
  name to change a copy.
- A dataset already loaded into the shop gets the changes once loaded again (`castor sylius:dataset:load <slug>`).

## Import a zip

```bash
castor sylius:dataset:import ~/Downloads/cellar.zip --dataset=my-cellar
castor sylius:dataset:import tea.zip --replace      # replaces the dataset of the same slug built here
```

The zip holds `products.yaml`, and optionally `dataset.yaml`, `collections.yaml` and `media/`, at its root or in a single
directory: the layout of an [exported dataset](#export-a-dataset). Other files are ignored. The slug is `--dataset`,
the directory of the zip, or the slug of the name of `dataset.yaml`; a ready-made slug is refused.

## Export a dataset

```bash
castor sylius:dataset:export cellar                   # .castor/import/exports/cellar.zip
castor sylius:dataset:export tea-house --output=tea.zip
```

The zip holds a `<slug>/` directory: unzipped in `src/Datasets/datasets/`, it becomes a ready-made dataset.

## Other sources

A project can register its own source, e.g. a CSV export of its current shop. It writes `products.yaml` and
`collections.yaml` of the slug it receives; products without a collection or a price are curated by the AI, like a
fetched catalog:

```php
use OpenColibri\Starter\DatasetBuilder\DatasetSources;

use function OpenColibri\Starter\DatasetBuilder\castor_host_dir;

DatasetSources::register('csv', static function (string $slug, string $name, string $description, ?string $url, int $limit): void {
    // Write castor_host_dir($slug) . '/products.yaml' and '/collections.yaml'.
});
```

```bash
castor sylius:dataset:build --source=csv --name="My shop"
```
