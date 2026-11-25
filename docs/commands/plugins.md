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

| Plugin         | Description                                        |
|----------------|----------------------------------------------------|
| 2fa            | Two-factor authentication for admin and shop users |
| ai_dev_tools   | Dev-only AI tooling for Sylius                     |
| altcha         | ALTCHA captcha to customer registration            |
| bugsnag        | Official BugSnag notifier for Symfony applications |
| cms            | CMS plugin for Sylius applications                 |
| gdpr           | Synolia sylius GDPR plugin                         |
| invoicing      | Invoicing plugin for Sylius                        |
| media          | Media management bundle for Symfony applications   |
| product_bundle | Product bundle for Sylius                          |
| refund         | Basic refunds functionality for Sylius             |
| recaptcha      | Google reCAPTCHA v3 to customer registration       |
| wishlist       | Wishlist plugin for Sylius                         |

These descriptions are the ones displayed next to each plugin in the interactive prompt.

### Payment gateways

The payment gateways are plugins too, added and removed with the same commands:

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
| stripe       | Stripe payment gateway                             |
| wishlist     | Wishlist plugin for Sylius                         |

## Your own plugins

You can [register your own plugins](custom-plugins.md) with the `AsPluginInstaller` and `AsPluginRemover`
attributes.
