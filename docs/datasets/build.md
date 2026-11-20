---
title: Build a dataset
description: Build a dataset with AI, from a description or from the catalog of an existing website.
sidebar:
  order: 2
---

`opencolibri/starter-dataset-builder` builds datasets with AI, into `.castor/import/datasets/<slug>/`. A built dataset
is final: loading it ([`sylius:dataset:load`](index.md#load-a-dataset), or a [demo shop](../sales-kit/shops.md)) needs
neither the AI nor the source website.

## Configure the AI

Set the AI in the `.env.local` of the project root (or `.env`):

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
