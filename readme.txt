=== Nashenski Commerce ===
Contributors: pristadigital, milenfrom
Tags: woocommerce, bulgaria, shipping, invoice, econt
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.2
Requires Plugins: woocommerce
Stable tag: 0.21.11
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WooCommerce for Bulgaria: Econt, Speedy, BOX NOW and Pigeon shipping, invoices and Ordinance N-18 documents.

== Description ==

Manual (Bulgarian and English): https://nashenski.com/manual/

Nashenski Commerce adds what a Bulgarian online store needs to WooCommerce. Everything runs on your own site: prices, labels and documents are made by your site and sent only to the services you turn on.

**Shipping**

* Econt, Speedy, BOX NOW and Pigeon Express: to an office, a locker or an address, with search and the courier's map.
* Live prices with your courier account, or the courier's public price list. If the courier does not answer, the list takes over, so checkout keeps working.
* Who pays the courier: you, or the customer at delivery, so courier money never passes through your books.
* Waybills for many orders at once, courier requests, tracking links and order statuses that follow the shipment.
* Classic, block and page builder checkouts, multi-step ones included. Shipping options as cards with courier logos.

**Documents and Ordinance N-18**

* Order documents, invoices and credit notes as PDF, in Bulgarian and English.
* The NRA audit XML file (Annex 38), checked against the schema, optionally emailed to your accountant every month.
* Invoice archive, Microinvest Delta export and a custom export builder (CSV, Excel, JSON, XML). Files stay private, never in the Media Library.
* Company invoice fields with the EU VAT number check (VIES), the total in words in euro and a checkout field editor.

**More**

* Quick order: a short form on the product page and the cart.
* Abandoned cart reminders with an optional single-use coupon (off by default).
* The EU withdrawal function (Directive 2011/83/EU, Article 11a, from 19 June 2026).
* Optional customer checks with nekorekten.com, Connectix and NepoStop before cash on delivery.
* Import from Bulgarisation for WooCommerce, the mreja.net plugins, Drusoft Shipping, Econt Delivery and Speedy Shipping.
* English and Bulgarian.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the plugin and activate it.
3. Go to WooCommerce > Settings > Nashenski Commerce, choose the couriers and documents you need and save.
4. Add the courier shipping methods to your shipping zones in WooCommerce > Settings > Shipping.

== Frequently Asked Questions ==

= Does my data go through a third-party server? =

No. Your site talks directly to the couriers and services you turn on. The NRA XML file is built on your site.

= Do I need a courier account? =

No. Without one, prices come from the courier's public price list, which you can edit. With your account, prices are live.

= What if the courier's system is down? =

For Econt, Speedy and Pigeon Express in Bulgaria, the public price list takes over when the courier does not answer. Checkout keeps working, so no order is lost to an error. Abroad, only the courier's live price is used.

= Who pays the courier? =

You choose per courier method and payment method. If the customer pays the courier at delivery, the fee is shown for information and stays out of the total, the documents and the NRA file. Collecting the courier's money yourself puts it in your turnover, which is an accounting error.

= Can I make waybills for many orders? =

Yes, from the bulk actions of the orders list: make the waybills, then request a courier for all of them.

= Which documents does it make? =

An order document for every order, invoices (automatic, or by hand with your own number) and credit notes for refunds.

= How does the NRA audit file work? =

Choose the month and press "Create XML file". The file is checked against the NRA schema and can go to your accountant by email every month.

= Can my accountant import the documents? =

Yes. Export a month for Microinvest Delta, download the invoice archive, or build your own export in CSV, Excel, JSON or XML.

= Does it check company VAT numbers? =

Yes, with the EU VIES service when a company customer enters a VAT number at checkout.

= What is Quick order? =

A button that opens a short form with a Bulgarian phone number. The order waits for your confirmation; no stock is reserved and no payment link is sent until then.

= How do abandoned cart reminders work? =

Once on, an editable WooCommerce email reminds shoppers who left, with a link that refills the cart and an optional single-use coupon.

= What is the EU withdrawal function? =

The "withdraw from contract here" link required from 19 June 2026: on every page, in My Account and in the order emails, using WooCommerce's withdrawal form (11.1 or newer).

= How do customer checks work? =

Before cash on delivery, the phone number can be checked with nekorekten.com, Connectix or NepoStop. You choose when cash on delivery is held back.

= Can I import from Bulgarisation or another plugin? =

Yes. Deactivate the old plugin and press "Import" on the Help tab: settings, shipping zones and order data come along. Import the mreja.net N-18 plugin while it is off. The import runs on your site.

== Screenshots ==

1. Couriers: turn on the couriers you work with, each with its account and sender.
2. Checkout: countries, courier logos and address transliteration.
3. Invoices: on request for companies, in the NRA audit file or left out of it.
4. Customer checks: nekorekten.com, Connectix and NepoStop, and when cash on delivery is held back.
5. Quick order: a short form for shoppers who do not want to check out. The order waits for your confirmation.
6. Order screen: the courier line on the order, with the customer's history and checks beside it.

== External services ==

The plugin contacts a service only for a feature you turn on (a courier, a customer check, the VAT number check). Most need your account; the public price lists and BOX NOW and Pigeon Express location lists do not. A courier's public price list is read only when you press "Check the courier website" and once a week, with a normal page request that sends nothing else.

* Econt (shipping): prices, labels, offices, cities, shipment status and courier requests. Sends the receiver's name, phone, address or office, the parcel details and the cash on delivery amount when prices or labels are made. Hosts: ee.econt.com, demo.econt.com. Price list: admin-ee.econt.com (tariff PDF). Terms: https://www.econt.com/econt-express/terms-of-use Privacy policy: https://www.econt.com/econt-express/privacy-policy
* Econt office map: shown at checkout only when the customer presses "Choose on the map". The map page loads from officelocator.econt.com with the customer's town and the store address. Terms: https://www.econt.com/econt-express/terms-of-use Privacy policy: https://www.econt.com/econt-express/privacy-policy
* Speedy office map: like the Econt map, from services.speedy.bg, with Google Maps. Only the Speedy town number is sent. Terms: https://www.speedy.bg/en/terms-and-conditions-cookies Privacy policy: https://www.speedy.bg/en/gdpr Google Maps terms: https://maps.google.com/help/terms_maps/ Google privacy: https://policies.google.com/privacy
* Speedy (shipping): same purpose and data. Hosts: api.speedy.bg, services.speedy.bg. Price list: services.speedy.bg/pricelist. Terms: https://api.speedy.bg/api/docs/#href-terms Privacy policy: https://www.speedy.bg/en/gdpr
* BOX NOW (locker shipping): lockers, labels and status. Sends the receiver's name, phone, email and locker, the parcel details and the cash on delivery amount when a label is made. Hosts: api-production.boxnow.bg, api-stage.boxnow.bg, locationapi-production.boxnow.bg, locationapi-stage.boxnow.bg (locker list, nothing sent). Terms: https://boxnow.bg/terms-of-use-for-shipping-services Privacy policy: https://boxnow.bg/personal-data-processing-notice
* Pigeon Express (shipping): offices, lockers, prices, labels and status. Hosts: api.pigeonexpress.com, api-demo.pigeonexpress.com. Without a login, checkout loads the public office and locker list from pigeonexpress.com/api/locations (nothing about the customer is sent). Price list: pigeonexpress.com/prices-and-taxes. Terms: https://pigeonexpress.com/terms Privacy policy: https://pigeonexpress.com/privacy
* nekorekten.com (customer check): sends the customer's phone number to look up reports, and, only when you click "Send report" on an order, the report with that order's name, phone and email (later edits and deletes too). Host: api.nekorekten.com. Terms: https://nekorekten.com/terms Privacy policy: https://nekorekten.com/privacy
* Connectix (customer check): sends the customer's phone number to check their delivery record, and shipment data when tracking is on. Hosts: api.connectix.bg, api-sandbox.connectix.bg. Terms: https://connectix.bg/bg/terms Privacy policy: https://connectix.bg/bg/privacy
* NepoStop (customer check): sends the customer's phone number, and labels when that option is on. Host: nepostop.com. Terms: https://nepostop.com/terms Privacy policy: https://nepostop.com/gdpr
* EU VIES (VAT number check): sends the VAT number a company customer enters at checkout. Host: ec.europa.eu. Website: https://ec.europa.eu/taxation_customs/vies/ Legal notice: https://commission.europa.eu/legal-notice_en Privacy policy: https://commission.europa.eu/privacy-policy-websites-managed-european-commission_en

== Third-party libraries ==

Vendor libraries: LGPL, MIT or Apache-2.0. DejaVu fonts: Bitstream Vera licence. All GPL-compatible. List and font licence: assets/licenses/third-party-licenses.txt; each library has its licence in its folder.

Code based on Bulgarisation for WooCommerce 4.0.13 by Autopolis.bg (GPLv3).

== Changelog ==

All versions: CHANGELOG.md in the plugin folder (Plugins screen > Changelog).

= 0.21.11 =
* Fixes: subscription renewals keep the earlier order documents, a courier reply without a price is never free shipping, cart reminders without shopper links, no outside IP lookup, batched exports.
