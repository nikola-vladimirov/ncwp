# Nashenski Commerce

Everything a WooCommerce store needs to sell in Bulgaria, in one free plugin: Econt, Speedy, BOX NOW,
Pigeon Express shipping, invoices and Ordinance N-18 documents, the NRA audit file, quick
orders, abandoned carts, the EU withdrawal function and customer checks. English and Bulgarian.

Everything runs on the store's own site. Data goes only to the services the store owner turns on
(listed under "External services" in [readme.txt](readme.txt)).

Based on Bulgarisation for WooCommerce 4.0.13 by Autopolis.bg (GPLv3 or later). Made by
[PristaDigital](https://pristadigital.com/). Licence: GPLv3 or later ([license.txt](license.txt)).

## Contents

- [Requirements](#requirements)
- [Install](#install)
- [Updates](#updates)
- [Getting started](#getting-started)
- [Features and how to use them](#features-and-how-to-use-them)
- [Moving from another plugin](#moving-from-another-plugin)
- [Documentation](#documentation)
- [Troubleshooting](#troubleshooting)
- [For developers](#for-developers)

## Requirements

- WordPress 6.5 or newer (tested up to 7.1), PHP 8.2 or newer.
- WooCommerce 9.4 or newer (tested up to 11.1). The EU withdrawal function needs WooCommerce 11.1.
- For live courier prices and labels: an account with each courier you use. Without one, checkout
  prices come from the courier's public price list, which is built in.

## Install

1. Open the [latest release](https://github.com/nikola-vladimirov/nashenski-commerce/releases/latest)
   and download `nashenski-commerce-<version>.zip` from its Assets.
2. In WordPress go to Plugins > Add New Plugin > Upload Plugin, choose the zip and activate it.

Use the release zip, not GitHub's green "Code > Download ZIP" button. That one unpacks into a folder
called `nashenski-commerce-main` with the repository files, so WordPress sees it as a different
plugin and updates do not reach it. If you installed it that way, delete that plugin (settings stay
in the database) and upload the release zip.

## Updates

Updates come through Dashboard > Updates like any other plugin, once the plugin is on
WordPress.org. Turn on automatic updates on the Plugins screen if you want them.


**Updates never show up?** WordPress checks for updates in the background with WP-Cron. If
`wp-config.php` has `define( 'DISABLE_WP_CRON', true );`, the server must run `wp-cron.php` on a
schedule (a system cron job), or WordPress only checks when someone opens Dashboard > Updates. The
plugin's own scheduled jobs (documents, abandoned cart reminders, shipment status) need WP-Cron too.

## Getting started

1. Install and activate WooCommerce, then this plugin.
2. Run the setup from the notice (or Help > "Run the setup again"): company details, VAT (20%, some
   goods at 9%, or not registered), invoices, couriers and delivery types with a free shipping total,
   and the checkout look (cards, side by side or plain; free shipping hint and progress bar). It adds
   the tax rates, the zone "Bulgaria" and the shipping methods, never twice. Moving from another
   plugin? Import first (step 6). The steps below then become checks.
3. Go to WooCommerce > Settings > Nashenski Commerce. The Overview turns each part on or off:
   couriers, invoices and N-18, quick order, abandoned carts, customer checks.
4. For each courier you use, open its section, enter your courier login and save. The other courier
   settings unlock once the courier accepts the login. Then set "Send from" (your office or address).
5. Add the courier shipping methods to your zones in WooCommerce > Settings > Shipping.
6. Open WooCommerce > Settings > Nashenski Commerce > Help: it has the recommended settings (one
   click to apply), the import from other plugins and how to use every part.

## Features and how to use them

### Shipping: Econt, Speedy, BOX NOW, Pigeon Express

- Delivery to an office, a locker or an address, with search pickers at checkout and the courier's
  map for Econt and Speedy. Works with the classic checkout, the block checkout and page builder
  checkouts, including multi-step ones.
- Live prices from your courier account, or from the built-in public price lists (editable, and
  checked weekly against the courier website).
- Labels from the order screen or the order list, label printing in two sizes, shipment status,
  courier requests for Econt and Speedy, the order number on the waybill and a tracking link for the
  customer. Order statuses can follow the shipment (delivered or returned).
- Review or test before payment, set on each courier method in the shipping zone and changeable per
  label (Pigeon Express: review or test, one of the two).
- Who pays the courier: "Customer pays the courier" on each Econt, Speedy and Pigeon Express method
  lists the payment methods for which the customer pays the delivery to the courier on delivery. The
  price is then shown for information only, not in the order total, on the documents or in the NRA
  file, and the waybill is paid by the receiver. Any other payment method: you collect the delivery
  and the sender pays. The total follows the payment method chosen at checkout. Free shipping,
  deliveries abroad, a Speedy or Pigeon Express fixed price and BOX NOW are always yours. Quick and
  admin orders are decided on the order screen once they have a shipping method and a payment
  method, and again when the payment method changes, until they have documents. On update to 0.21.8 every
  Econt and Speedy method marked as a courier and every Pigeon Express method get cash on delivery
  in their list, so the cash on delivery money flow stays as it was. New methods start with cash on
  delivery in the list. Shipping methods of other plugins keep "Is ... courier?" in Documents >
  Shipping on documents.
- Each order screen shows the courier, the delivery type and the chosen office, locker or address.
- Ship abroad (off by default): Econt to Romania and Greece, Speedy to nine countries on its price
  list. Prices abroad come only from the courier. Free shipping over a total can be set per country,
  for example `RO=150, GR=200`.

How to use: set up the courier (see Getting started), add its methods to a shipping zone, then on an
order open the courier box, check the details and press "Generate label".

### Invoices and Ordinance N-18

- Order documents, invoices and credit notes as PDF, in Bulgarian and English, attached to the order
  emails. The order document is made for every order.
- Invoices automatically or by hand from the order screen, with your own number if needed.
- The NRA audit XML file (Annex 38) for any period, checked against the NRA schema, and optionally
  emailed to your accountant on the 1st of every month.
- Invoice archive and Microinvest Delta exports, and an export builder for orders, products sold or
  documents (CSV, Excel, JSON, XML; documents are picked by their own date). Export files are deleted
  after 7 days; documents and export files never show in the Media Library.
- Company invoice fields at checkout and the EU VAT number check (VIES). The VAT number is required
  only when "VAT number required on checkout?" is ticked in Documents.
- Article 113(9) stores (not VAT registered): the invoice, its copy and the pro forma print without
  a VAT column.
- A delivery with no VAT, on an order with no VAT at all, prints at 0% with its full price (invoices,
  credit notes and the NRA audit file).
- A delivery the customer paid to the courier is not on the documents or in the NRA audit file. Each
  order keeps what was decided at checkout or on the order screen; credit notes follow their order.
- Import the checkout fields and rules of WooCommerce Checkout Field Editor (free or Pro) in one click.
- Optional clean-up of order documents (not invoices) of older orders.

How to use: turn on "Invoices and N-18" in the Overview, fill in your company details in Documents
and choose "When to generate documents?".

### Quick order

A "Quick order" button on the product page and in the cart opens a short form. The order waits for
your confirmation. On the order screen the Delivery box sets the courier, town and office, locker or
address; "Save delivery" adds the shipping line, and a label can then be made as usual (for Econt,
also from the label button in the orders list). When the
label sets the delivery price, the customer gets the order details email with the new total.

### Abandoned carts

Off by default. Keeps carts that were not ordered, records the email as the shopper types it, and
sends reminders through an editable WooCommerce email, with an optional single-use coupon and a link
that refills the cart. The list is under WooCommerce > Abandoned carts. Check the consent rules that
apply to your store before you turn reminders on. Tools > Export Personal Data and Erase Personal
Data cover the saved carts, and the plugin suggests text for your privacy policy.

### EU withdrawal function

The "withdraw from contract here" function of Directive 2011/83/EU, Article 11a (applies from
19 June 2026), on WooCommerce's own withdrawal form: a link on every page, in My Account, on the
order page and in the order emails during the withdrawal period, and a shortcode for any theme.

### Customer checks

nekorekten.com, Connectix and NepoStop look up a phone number's history of refused parcels. The
order shows a warning, and cash on delivery can be hidden for such customers. In the NepoStop and
nekorekten.com settings you can also tick payment methods to hide for a flagged customer. From an order
you can send a report to nekorekten.com, then edit or delete it. Each visitor address gets at most 20
checks an hour; over that, the check is skipped and the checkout goes on.

### Prices with VAT

A scan finds prices that cannot come from a whole net price plus VAT and offers the corrected price.

## Moving from another plugin

The plugin imports from Bulgarisation for WooCommerce, the mreja.net plugins (Econt Express, BOX NOW
Delivery, Speedy shipping method, Pigeon Express shipping, Ordinance N-18), Drusoft Shipping for Econt and for Speedy, Econt Delivery and Speedy
Shipping: courier logins, sender details, waybill numbers, shipping zone methods with the mreja.net free
shipping totals and, from the N-18 plugin, company details and document numbers. Bulgarisation's order documents and invoices move into
the private documents folder and still open from the order. Go to WooCommerce > Settings > Nashenski Commerce >
Help and press "Import", then deactivate the old plugin (Nashenski Commerce asks, with a link to the Plugins
screen; WordPress.org plugins never change other plugins). Its data stays in the database.
Only users who can manage plugins see the import button. If an old document number clashes with a
number already in use, it is skipped and the summary says "N document numbers skipped (conflict)".

While Bulgarisation for WooCommerce (4.x or 5.x) is active, Nashenski Commerce is paused; deactivate
it before the import. Import the mreja.net Ordinance N-18 plugin while it is off (its import is refused
while it is active). Until its documents are imported, Nashenski Commerce makes no invoices or other
documents and takes no numbers while a new number could clash with one of them (the plugin is active, or
one of our counters has not passed its numbers). A store without its documents, or whose counters are
already past them, is not paused; the import is still offered. If it is turned on again after the import, it can reuse numbers Nashenski Commerce already
gave out: documents pause until its import is run again from the Help page (also after it is off again).
That run takes over its new numbers, continues the counters after its highest number (order documents
and cancellations; invoices, credit and debit notes) and notes and lists each order whose number clashes,
to correct. An error notice on the Dashboard, the Plugins screen, the orders and the Nashenski settings
says so (with a link to Plugins while it is active, to the import while it is off), the PDF Files box of an
order and the setup explain it, and orders whose documents were skipped get a note. Then use
"Regenerate PDF's" for orders without documents.

If a document cannot be made, the refund, subscription renewal or checkout still goes through and the
order gets the note "Some documents were not generated" with the reason. Fix the cause and make the
document again from the order.

## Documentation

- **In the plugin:** WooCommerce > Settings > Nashenski Commerce > Help, and a tooltip on every
  setting.
- **What changed:** [CHANGELOG.md](CHANGELOG.md), also shown on the Plugins screen ("Changelog") and
  in "View version details".
- **WordPress.org page:** [readme.txt](readme.txt), with the list of external services.
- **Plans:** [docs/plans](docs/plans) (repository only, not in the zip).

## Troubleshooting

- **"Nothing found" under a courier:** check the courier login in its section. In BOX NOW demo mode
  there are only two test lockers, both in Sofia.
- **No courier options at checkout:** add the courier methods to the shipping zone of the customer's
  address, and give your products a weight.
- **"Complete the fields to calculate" stays:** the customer has not chosen an office, a locker or an
  address yet.
- **No documents:** turn on "Invoices and N-18" in the Overview and check "When to generate
  documents?" in Documents.
- **No updates:** see [Updates](#updates), above all the part about WP-Cron.

## For developers

- PHP 8.2+, namespace `Nashenski\` in `app/`, front-end templates in `templates/`, readable JS and CSS
  in `assets/`, bundled PHP libraries in `vendor/` (do not edit). There is no compiled code: the old `dist/` bundles were rewritten as readable JS ([plan](docs/plans/dist-rewrite.md)).
- Options are `nashenski_settings_{group}_{name}`, read and written with `nashenski_get_option()` and
  `nashenski_set_option()`.
- Texts are in English; the Bulgarian translation is in `languages/` (PO, MO and the PHP translation
  file). Every new text needs its translation. The WordPress.org zip leaves them out (WordPress.org
  delivers translations as language packs); import the PO on translate.wordpress.org.
- **Releases:** bump `Version:` in `nashenski-commerce.php` and `Plugin::VERSION`, add a `CHANGELOG.md`
  entry, replace the `readme.txt` changelog with the new version's line (the readme keeps only the
  current release, to stay under the 10 KB WordPress.org asks for), update the Help page, and push to `main`. The
  [Release workflow](.github/workflows/release.yml) publishes `vX.Y.Z` with two zips: the plugin, `nashenski-commerce-wporg-X.Y.Z.zip` (for WordPress.org, no bundled translation). A version with
  a dash is published as a pre-release.
- The release zip is `git archive` of the tag: everything marked `export-ignore` in
  [.gitattributes](.gitattributes) (docs, this README, repository files, the demos,
  docs and development files of the libraries in `vendor/`) stays out.
