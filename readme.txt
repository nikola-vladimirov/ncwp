=== Nashenski Commerce ===
Contributors: pristadigital
Tags: woocommerce, bulgaria, shipping, invoice, econt
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.2
Requires Plugins: woocommerce
Stable tag: 0.21.10
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WooCommerce for Bulgaria: Econt, Speedy, BOX NOW and Pigeon shipping, invoices and Ordinance N-18 documents.

== Description ==

Manual (Bulgarian and English): https://nashenski.com/manual/

Nashenski Commerce adds what a Bulgarian online store needs to WooCommerce. Everything runs on your own site: prices, labels and documents are made by your site and sent only to the services you turn on.

**Shipping**

* Econt, Speedy, BOX NOW and Pigeon Express shipping methods: to an office, a locker or an address.
* Waybills for many orders at once, courier requests for Econt and Speedy (holidays skipped), the order number on the waybill and a tracking link for the customer.
* Order statuses that follow the shipment (delivered or returned) for Econt, Speedy and BOX NOW.
* Live shipping prices with your courier account, or from the courier's built-in public price list when you have no account yet.
* Labels, label printing and shipment status updates from the order screen.
* Who pays the courier, per method and payment method: you collect it, or the customer pays the courier, outside your total and documents.
* Works with classic, block and page builder checkouts, multi-step ones included.
* Office, locker and address pickers with search, and the courier's map for Econt and Speedy.
* Shipping options as cards with courier logos, styled by your theme or a token-based CSS framework such as TokenForge.

**Documents and Ordinance N-18**

* Order documents, invoices and credit notes as PDF, in Bulgarian and in English. Only the Bulgarian PDF goes with the order emails.
* The order document is made for every order. Invoices are made automatically, or by hand with your own invoice number.
* The audit XML file (Annex 38 of Ordinance N-18) for any number of orders, checked against the NRA schema, and optionally emailed to your accountant every month.
* Invoice archive and Microinvest Delta export for a month. Documents and exports stay in a private folder, never in the Media Library.
* A custom export builder (CSV, Excel, JSON, XML) and a checkout field editor for every checkout type.
* Company invoice fields at checkout with the EU VAT number check (VIES), the Article 113(9) setting, the total in words in euro and a check for "mathematically impossible" prices with VAT.

**Quick order**

* A "Quick order" button on the product page and the cart opens a short form. Only Bulgarian phone numbers are accepted. The order waits for your confirmation; no stock is reserved and no payment link is sent until then.

**Abandoned carts**

* Off by default. Keeps carts that were not ordered and sends reminders through an editable WooCommerce email, with an optional single-use coupon and a link that refills the cart.

**EU withdrawal function**

* The "withdraw from contract here" function of Directive 2011/83/EU, Article 11a (from 19 June 2026), on WooCommerce's own withdrawal form (WooCommerce 11.1 or newer): a link on every page, in My Account and in the order emails, a shortcode and Bulgarian texts.

**Customer checks**

* Optional checks with nekorekten.com, Connectix and NepoStop before allowing cash on delivery, at most 20 an hour from one visitor address.

**Languages**

* English and Bulgarian.

**Moving from another plugin**

Nashenski Commerce is based on Bulgarisation for WooCommerce 4.0.13 by Autopolis.bg (GPLv3). If you used it, deactivate it, activate Nashenski Commerce and press "Import" on the Help tab: your settings, shipping zones and order data come along.

It also imports from the mreja.net plugins (Ordinance N-18 included), Drusoft Shipping, Econt Delivery and Speedy Shipping. After each import, deactivate the old plugin. The import runs on your site; nothing is sent anywhere.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the plugin and activate it.
3. Go to WooCommerce > Settings > Nashenski Commerce, choose the couriers and documents you need and save.
4. Add the courier shipping methods to your shipping zones in WooCommerce > Settings > Shipping.

== Frequently Asked Questions ==

= Does my data go through a third-party server? =

No. Your site talks directly to the couriers and services you turn on. The NRA XML file is built on your site.

= Do I need a courier account? =

No. Without an account, shipping prices come from the courier's public price list. With your account, the prices are live.

= Who pays the courier? =

You choose on each courier method, per payment method. What the customer pays the courier is shown for information and stays out of the total, the documents and the NRA file.

= Can I move from Bulgarisation? =

Yes, see "Moving from another plugin". Import the mreja.net N-18 plugin while it is off; documents may wait for it. Turned on again, it can reuse numbers, so documents pause until a new import.

== Screenshots ==

1. Couriers: turn on the couriers you work with, each with its account and sender.
2. Checkout: countries, courier logos, address transliteration and the company invoice fields.
3. Invoice numbering: website invoices go into the NRA audit file. Your own invoicing program keeps its orders out of it.
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

== Changelog ==

All versions: CHANGELOG.md in the plugin folder (Plugins screen > Changelog).

= 0.21.10 =
* Changes: Security Audit: nonces checked first in every request, output escaped where printed, no global file permission settings, only standard file types.
