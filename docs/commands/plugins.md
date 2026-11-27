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
Their descriptions are the ones displayed next to each plugin in the interactive prompt, and end with the vendor
of the plugin, to credit its authors.

### Payment gateways

Added and removed with the same commands:

```bash
castor sylius:add stripe
castor sylius:remove paypal
```

| Plugin | Package                        | Description                                                                      |
|--------|--------------------------------|----------------------------------------------------------------------------------|
| mollie | `sylius/mollie-plugin`         | Mollie payment gateway (cards, iDEAL, Bancontact, Klarna...), by Sylius          |
| paypal | `sylius/paypal-plugin`         | PayPal payment gateway (PayPal Commerce Platform), by Sylius                     |
| stripe | `flux-se/sylius-stripe-plugin` | Stripe payment gateway (Checkout, Payment Element, express checkout), by FLUX-SE |

Once a gateway is added, create its payment method in the admin (**Configuration > Payment methods**) with your
account credentials. `castor docker:service:install sylius` also asks which payment gateways the new shop should have
(all of them by default): Sylius-Standard 2.3+ already requires Stripe, Mollie and PayPal, so the ones you do not pick
are removed with `sylius:remove`, and the picked ones it lacks are added with `sylius:add`.

### Security and privacy

Protect the accounts and the forms, and handle personal data.

| Plugin    | Description                                                                                   |
|-----------|-----------------------------------------------------------------------------------------------|
| 2fa       | Two-factor authentication (authenticator app or email) for admin and shop users, by bitExpert |
| altcha    | Add ALTCHA (self-hosted, privacy-friendly captcha) to customer registration, by tito10047     |
| gdpr      | GDPR plugin for Sylius, by Synolia                                                            |
| recaptcha | Add Google reCAPTCHA v3 to customer registration, by karser                                   |

### Catalog and content

Pages, media, SEO and product bundles around the catalog.

| Plugin         | Description                                                           |
|----------------|-----------------------------------------------------------------------|
| cms            | CMS plugin for Sylius applications, by Sylius                         |
| media          | Media management bundle for Symfony applications, by JoliCode         |
| product_bundle | Product bundle for Sylius, by Sylius                                  |
| seo            | SEO metadata, OpenGraph, noindex rules and rich snippets, by Dedi     |
| sitemap        | XML sitemap of the products, taxons and static pages, by Stefan Doorn |

`seo` installs `dedi/sylius-seo-plugin`: metadata and OpenGraph for the channels, products, taxons and any shop URL
(**Marketing > SEO**), noindex rules, canonical and hreflang links, breadcrumb and product rich snippets, and Google
Analytics or Tag Manager per channel. It wires its interfaces and traits onto the `Product`, `ProductVariant`, `Taxon`
and `Channel` entities (the product and taxon descriptions and the first product image are the default metadata),
migrates its tables and columns, and overrides the shop layout
(`templates/bundles/SyliusShopBundle/shared/layout/base.html.twig`) for the SEO title: pages keep their own title when
no SEO content gives one.

`sitemap` installs `stefandoorn/sitemap-plugin`: `/sitemap.xml` redirects to `/sitemap_index.xml`, which links one
sitemap per provider (products and taxons with their hreflang alternates and images, the homepage and the contact
page). It adds no table: its contrib recipe registers the bundle and imports its config and routes. The sitemaps are
files, generated per channel in `var/sitemap` by the install, then by `bin/console sylius:sitemap:generate`: run it on
a schedule (once a day, say) in production, so new products show up. Their URLs take the channel hostname, and the
scheme of `DEFAULT_URI` (`http://localhost` in `.env`): set it to your `https://` URL in production, or the sitemaps
list `http://` URLs. `sylius:remove sitemap` also deletes the generated files.

### Orders and customers

What happens after the checkout, and what customers keep.

| Plugin    | Description                                                                             |
|-----------|-----------------------------------------------------------------------------------------|
| eu_vat    | EU VAT number on addresses, checked with VIES, and the reverse charge rule, by FLUX-SE  |
| invoicing | Invoicing plugin for Sylius, by Sylius                                                  |
| refund    | Basic refunds functionality for Sylius, by Sylius                                       |
| wishlist  | Wishlist plugin for Sylius, by Sylius                                                   |

`eu_vat` installs `flux-se/sylius-eu-vat-plugin`: a VAT number field on the addresses (checkout, address book, admin
order), checked against the European Commission VIES service, and the reverse charge rule: a customer of another
member state with a valid VAT number pays no VAT. It wires its interfaces and traits onto the `Channel` and `Address`
entities and runs the migration the plugin ships. Each channel gets a base country, the country of its billing data,
and a European zone, the `EU` zone of the member states, created with the missing member states (disabled) when the
shop has none: change them in the admin channel form (**Configuration > Channels**). The VIES client needs the PHP
`soap` extension, part of the image since this plugin: on an older stack, run `castor docker:build` then `castor up`
first. `sylius:remove eu_vat` drops the VAT numbers and the channel settings, and keeps the `EU` zone and the countries.

### Developer tools

Tooling and monitoring for the people who build the shop.

| Plugin       | Description                                                    |
|--------------|----------------------------------------------------------------|
| ai_dev_tools | Dev-only AI tooling for Sylius, by Sylius                      |
| bugsnag      | Official BugSnag notifier for Symfony applications, by Bugsnag |

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

| Plugin       | Description                                                                      |
|--------------|----------------------------------------------------------------------------------|
| 2fa          | Remove two-factor authentication from admin and shop users, by bitExpert         |
| ai_dev_tools | Dev-only AI tooling for Sylius, by Sylius                                        |
| altcha       | Remove ALTCHA from customer registration, by tito10047                           |
| api          | Sylius API and its test tooling, by Sylius                                       |
| bugsnag      | Official BugSnag notifier for Symfony applications, by Bugsnag                   |
| cms          | CMS plugin for Sylius applications, by Sylius                                    |
| eu_vat       | Remove the EU VAT plugin and the VAT numbers, by FLUX-SE                         |
| gdpr         | GDPR plugin for Sylius, by Synolia                                               |
| invoicing    | Invoicing plugin for Sylius, by Sylius                                           |
| mollie       | Mollie payment gateway (cards, iDEAL, Bancontact, Klarna...), by Sylius          |
| paypal       | PayPal payment gateway (PayPal Commerce Platform), by Sylius                     |
| recaptcha    | Remove Google reCAPTCHA v3 from customer registration, by karser                 |
| seo          | Remove the SEO plugin and its SEO contents, by Dedi                              |
| sitemap      | Remove the sitemap plugin and its generated sitemaps, by Stefan Doorn            |
| stripe       | Stripe payment gateway (Checkout, Payment Element, express checkout), by FLUX-SE |
| wishlist     | Wishlist plugin for Sylius, by Sylius                                            |

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
    A plugin whose migrations only roll back on MySQL (`eu_vat`) is rolled back with a migration generated from the
    mapping instead, run then deleted.

  Outside a git repository, nothing proves the plugin was not deployed: it gets a migration.

## Your own plugins

You can [register your own plugins](custom-plugins.md) with the `AsPluginInstaller` and `AsPluginRemover`
attributes.
