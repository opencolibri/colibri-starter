---
title: Overview
description: Load a ready-made or built catalog, with its images, into the Sylius shop.
sidebar:
  order: 1
---

A **dataset** is a catalog ready to be loaded into Sylius: its products and collections (`products.yaml`,
`collections.yaml`), **their images** (`media/`) and a `dataset.yaml` describing it. Loading one makes no AI call and
downloads nothing.

| Package                                                                | Tasks                                                                  |
|------------------------------------------------------------------------|------------------------------------------------------------------------|
| `opencolibri/starter-datasets`                                         | none: [ready-made datasets](#ready-made-datasets)                      |
| `opencolibri/starter-fixtures`                                         | `sylius:dataset:list`, `sylius:dataset:load`, `sylius:dataset:unload`  |
| [`opencolibri/starter-dataset-builder`](build.md)                      | `sylius:dataset:build`, `sylius:dataset:images`, `sylius:dataset:export`, `sylius:dataset:delete` |
| [`opencolibri/starter-sales-kit`](../sales-kit/shops.md)        | `sylius:shop:*`: demo shops created from datasets                      |

## Load a dataset

```bash
castor sylius:dataset:list            # the datasets, with their number of products and images
castor sylius:dataset:load cellar     # load it into the default channel
castor sylius:dataset:unload          # remove it
```

`sylius:dataset:load` puts the catalog into the default channel (`WEB_STORE`, created by the `app` fixture suite):
taxons under a root taxon used as the channel menu, products with their image, prices (a few of them on promo). Loading
another dataset replaces it, and the rest of the channel (its orders, its admins, the products you created) is kept.
`--limit` caps the number of products.

Start from a store without products with `castor app:db:fixtures app` first: the default fixtures add clothing to the
channel.

Under the hood, the task writes the fixtures of the catalog into the app (`config/sylius/fixtures/import/catalog/`),
mirrors the images of the dataset into `var/import/catalog/media/` (the containers loading the fixtures only mount the
app directory), points the `import` fixture suite at them, removes the catalog loaded before
(`sylius:import:channel:reset`, a command of the app) and loads the suite.

## The format

```
cellar/
├── dataset.yaml       name, description, url, source, theme (suggested), created_at
├── products.yaml
├── collections.yaml
└── media/             the images, in WebP
```

```yaml
# products.yaml
products:
    -   title: 'Château des Aulnes — Saint-Émilion Grand Cru 2019'
        collection_name: Bordeaux      # the name of its collection
        price_eur: 34.9
        image: media/chateau_des_aulnes.webp
        description: 'Merlot and cabernet franc from limestone slopes…'

# collections.yaml
collections:
    -   name: Bordeaux
        image: media/collection_bordeaux.webp   # optional, shown on the homepage category tiles
```

- A product belongs to the collection named by `collection_name`; one of an unknown collection goes to the root taxon.
- A collection with a `parent` (the name or code of another collection) is shown under it: a two-level menu (e.g.
  Men > T-shirts). Its collections then have a `code`, as a child name may repeat across groups (Men > T-shirts,
  Women > T-shirts), and products point at it with `collection: <code>`.
- An image is a path relative to the dataset directory. A URL or an `image_prompt` is only the leftover of a built
  dataset whose images were not stored: `sylius:dataset:images` stores them.
- A product `code` (optional) keeps its Sylius code stable; it is derived from the title otherwise.
- A flat menu shows the twelve collections holding the most products.

The format is read by `OpenColibri\Starter\Core\Dataset\Dataset`, and the datasets are listed by `Datasets`.

## Ready-made datasets

`opencolibri/starter-datasets` ships ready-made datasets, images included:

| Dataset        | Catalog                                                       | Suggested theme                           |
|----------------|---------------------------------------------------------------|-------------------------------------------|
| `automotive`   | Eighteen car parts, tyres, tools and workshop equipment       | [Torque](../themes/torque.md)             |
| `bloom`        | Eighteen beauty products and tools, on six shelves            | [Bloom](../themes/bloom.md)               |
| `cellar`       | Seventeen wines from fictional estates, in seven regions      | [Cellar](../themes/cellar.md)             |
| `culture`      | Eighteen books, games, instruments and collectibles           | [Culture](../themes/culture.md)           |
| `depot`        | Eighteen trade supplies and equipment, in six aisles          | [Depot](../themes/depot.md)               |
| `groove`       | Sixteen fictional vinyl records, in six genres                | [Groove](../themes/groove.md)             |
| `home`         | Eighteen pieces of furniture, lighting, linen and tableware   | [Hearth](../themes/hearth.md)             |
| `outdoor`      | Eighteen pieces of hiking, climbing and camping gear          | [Summit](../themes/summit.md)             |
| `pets`         | Eighteen foods, treats, toys, beds and care products for pets | [Paws](../themes/paws.md)                 |
| `retro-gaming` | Sixteen retro consoles, games and accessories, on six shelves | [Retro Gaming](../themes/retro_gaming.md) |
| `roastery`     | Sixteen specialty coffees and brewing tools, on six shelves   | [Roastery](../themes/roastery.md)         |
| `supermarket`  | Nine everyday groceries, one per supermarket aisle            | [Drive](../themes/drive.md)               |
| `teahouse`     | Sixteen fine teas and pieces of teaware, on six shelves       | [Teahouse](../themes/teahouse.md)         |
| `tech`         | Sixteen devices from fictional brands, in six departments     | [Tech](../themes/tech.md)                 |
| `video-games`  | Sixteen fictional video games, in six genres                  | [Arena](../themes/arena.md)               |

To add one, drop its directory in `src/Datasets/datasets/` (an [exported dataset](build.md#export-a-dataset) has that
layout). Every product needs a local image, a collection may have one: the package tests check them. Another package
can ship its own datasets, see [Extending](extending.md#ship-datasets).
