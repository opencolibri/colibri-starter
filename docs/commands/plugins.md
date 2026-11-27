---
title: Add or remove plugins
description: Install or uninstall Sylius plugins with a single command.
sidebar:
  order: 1
---

Install or uninstall Sylius plugins with simple commands. Provided by `opencolibri/starter-plugins`.

## Add plugins

A single command to add all the Sylius plugins you need (`sylius:add` is an alias of `sylius:plugin:add`):

```bash
castor sylius:add cms invoicing refund
```

![Adding multiple Sylius plugins with the sylius add command](../images/sylius-add-plugins.svg)

The plugins come by category, the ones the [dev-kit](../dev-kit/index.md) **Plugins** page groups and filters them by.
Their descriptions are the ones displayed next to each plugin in the interactive prompt.

### Payment gateways

Added and removed with the same commands:

```bash
castor sylius:add stripe
castor sylius:remove paypal
```

| Plugin | Package                        | Description                                                          |
|--------|--------------------------------|----------------------------------------------------------------------|
| mollie | `sylius/mollie-plugin`         | Mollie payment gateway (cards, iDEAL, Bancontact, Klarna...)         |
| paypal | `sylius/paypal-plugin`         | PayPal payment gateway (PayPal Commerce Platform)                    |
| stripe | `flux-se/sylius-stripe-plugin` | Stripe payment gateway (Checkout, Payment Element, express checkout) |

Once a gateway is added, create its payment method in the admin (**Configuration > Payment methods**) with your
account credentials. `castor docker:service:install sylius` also asks which payment gateways the new shop should have
(all of them by default): Sylius-Standard 2.3+ already requires Stripe, Mollie and PayPal, so the ones you do not pick
are removed with `sylius:remove`, and the picked ones it lacks are added with `sylius:add`.

### Security and privacy

Protect the accounts and the forms, and handle personal data.

| Plugin    | Description                                        |
|-----------|----------------------------------------------------|
| 2fa       | Two-factor authentication for admin and shop users |
| altcha    | ALTCHA captcha to customer registration            |
| gdpr      | Synolia sylius GDPR plugin                         |
| recaptcha | Google reCAPTCHA v3 to customer registration       |

### Catalog and content

Pages, media, SEO and product bundles around the catalog.

| Plugin         | Description                                                    |
|----------------|----------------------------------------------------------------|
| cms            | CMS plugin for Sylius applications                             |
| media          | Media management bundle for Symfony applications               |
| product_bundle | Product bundle for Sylius                                      |
| seo            | Dedi SEO: metadata, OpenGraph, noindex rules and rich snippets |

`seo` installs `dedi/sylius-seo-plugin`: metadata and OpenGraph for the channels, products, taxons and any shop URL
(**Marketing > SEO**), noindex rules, canonical and hreflang links, breadcrumb and product rich snippets, and Google
Analytics or Tag Manager per channel. It wires its interfaces and traits onto the `Product`, `ProductVariant`, `Taxon`
and `Channel` entities (the product and taxon descriptions and the first product image are the default metadata),
migrates its tables and columns, and overrides the shop layout
(`templates/bundles/SyliusShopBundle/shared/layout/base.html.twig`) for the SEO title: pages keep their own title when
no SEO content gives one.

### Orders and customers

What happens after the checkout, and what customers keep.

| Plugin    | Description                            |
|-----------|----------------------------------------|
| invoicing | Invoicing plugin for Sylius            |
| refund    | Basic refunds functionality for Sylius |
| wishlist  | Wishlist plugin for Sylius             |

### Developer tools

Tooling and monitoring for the people who build the shop.

| Plugin       | Description                                        |
|--------------|----------------------------------------------------|
| ai_dev_tools | Dev-only AI tooling for Sylius                     |
| bugsnag      | Official BugSnag notifier for Symfony applications |

### Choosing the plugins interactively

Without arguments, `sylius:add` lists every plugin with the installed ones already checked. Check the plugins you
want: the new ones are installed. Uncheck an installed plugin to remove it: the command lists the unchecked plugins and
asks before removing them, since their data is dropped. Plugins that are not installed are never removed, and plugins
given on the command line are only added.

To declare the exact set of plugins of a shop instead, use the [dev-kit](../dev-kit/index.md): `castor dev-kit:apply`
only adds and removes what differs.

## Remove plugins

A single command to remove the Sylius plugins you do not need anymore (`sylius:remove` is an alias of
`sylius:plugin:remove`):

```bash
castor sylius:remove invoicing cms
```

![Removing multiple Sylius plugins with the sylius remove command](../images/sylius-remove-plugins.svg)

| Plugin       | Description                                        |
|--------------|----------------------------------------------------|
| 2fa          | Two-factor authentication for admin and shop users |
| ai_dev_tools | Dev-only AI tooling for Sylius                     |
| altcha       | ALTCHA captcha to customer registration            |
| api          | Sylius API and its test tooling                    |
| bugsnag      | Official BugSnag notifier for Symfony applications |
| cms          | CMS plugin for Sylius applications                 |
| gdpr         | Synolia sylius GDPR plugin                         |
| invoicing    | Invoicing plugin for Sylius                        |
| mollie       | Mollie payment gateway                             |
| paypal       | PayPal payment gateway                             |
| recaptcha    | Google reCAPTCHA v3 to customer registration       |
| seo          | Remove the Dedi SEO plugin and its SEO contents    |
| stripe       | Stripe payment gateway                             |
| wishlist     | Wishlist plugin for Sylius                         |

## Plugin migrations

A migration that ran in production cannot be taken back by deleting it: commit the migrations the plugins generate
in the `migrations/` folder of the shop, so every environment follows.

- Plugins that add tables or columns without shipping migrations (`2fa`, `media`, `seo`) generate theirs on install,
  with `doctrine:migrations:diff` restricted to the tables they change: it follows the installed plugin version and
  the database platform, and the schema drift of the other tables stays out of it.
- `sylius:remove` first tells whether the plugin may have been deployed, from git: its package is in the committed
  `composer.lock`, or the migration generated by its install is committed.
  - If so, it generates a migration that drops the tables and columns of the plugin, whether it shipped its migrations
    or not. It forgets the migrations the plugin shipped, so a reinstall runs them again, and it is skipped where none
    of these tables and columns exist (a plugin removed before it was deployed there).
  - Otherwise, the plugin never left your machine: its migrations are rolled back and the ones generated by its
    install are deleted, so the shop gets no migration for it at all.

  Outside a git repository, nothing proves the plugin was not deployed: it gets a migration.

## Your own plugins

You can [register your own plugins](custom-plugins.md) with the `AsPluginInstaller` and `AsPluginRemover`
attributes.
