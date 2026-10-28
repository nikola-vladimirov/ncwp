=== Nashenski Commerce ===
Contributors: pristadigital
Tags: woocommerce, bulgaria, shipping, invoice, econt
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 0.17.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WooCommerce for Bulgaria: Econt, Speedy, BOX NOW and Pigeon shipping, invoices and Ordinance N-18 documents.

== Description ==

Nashenski Commerce ("нашенски" means "ours, done the local way") adds what a Bulgarian online store needs to WooCommerce. Everything runs on your own site: prices, labels and documents are made by your site and sent only to the services you turn on.

**Shipping**

* Econt, Speedy, BOX NOW and Pigeon Express shipping methods: to an office, a locker or an address.
* Waybills for many orders at once, courier requests for Econt and Speedy (holidays skipped), the order number on the waybill, a tracking link on the customer's order page, and order statuses that follow the shipment (delivered or returned).
* Live shipping prices at checkout with your courier account, or from the courier's public price list when you have no account yet. The price lists are built in, editable and can be checked against the courier website.
* Labels from the order screen, label printing and shipment status updates.
* Works with the classic checkout, the block checkout and checkout pages built in a page builder, including multi-step checkouts.
* Office, locker and address pickers with search, the courier's map for Econt and Speedy, and "Complete the fields to calculate" until the customer has chosen.
* Shipping options as cards with courier logos and the delivery type, styled with the design tokens of TokenForge or another token-based CSS framework, or with your theme.

**Documents and Ordinance N-18**

* Order documents, invoices and credit notes as PDF, sent with the order emails. Every document is made in Bulgarian and in English.
* The order document is made for every order, also with invoices turned off.
* Invoices made automatically, or by hand from the order screen with your own invoice number.
* The standardised audit XML file (Annex 38 of Ordinance N-18) for any number of orders, checked against the NRA schema.
* Invoice archive and Microinvest Delta export for a chosen month.
* The NRA audit file of the month before, emailed to your accountant on the 1st of every month (optional).
* Custom export builder: orders, products sold or documents, with your filters, columns, order and file format (CSV, Excel, JSON, XML). Save exports to run them again.
* Checkout field editor: order, one or two columns, required, optional or hidden for each field, and your own extra fields, in the classic, block and page builder checkouts.
* Company invoice fields at checkout (on or off), in every checkout type, and EU VAT number check (VIES).
* The total in words on documents, in euro.
* A check for "mathematically impossible" prices with VAT.

**Quick order**

* A "Quick order" button on the product page and the cart: a short form (a dialog, inline under the button, or a sliding panel) with ready-made or custom fields. Only Bulgarian phone numbers are accepted, local or international. The order waits for your confirmation; no stock is reserved and no payment link is sent until you confirm.

**Abandoned carts**

* Off by default. Keeps carts that were not ordered, records the email as soon as the shopper types it (even a logged-out shopper at checkout), and sends reminders through an editable WooCommerce email, with an optional single-use coupon and a link that refills the cart. A list with totals is under WooCommerce > Abandoned carts.

**EU withdrawal function**

* The "withdraw from contract here" function required by Directive 2011/83/EU, Article 11a (added by Directive (EU) 2023/2673, applied from 19 June 2026), built on WooCommerce's own withdrawal form (WooCommerce 11.1 or newer): a link on every page, on each order in My Account, on the order page and in the order emails during the withdrawal period, a shortcode for any theme or builder, prefilled fields, your site's look and Bulgarian texts.

**Customer checks**

* Optional checks with nekorekten.com, Connectix and NepoStop before allowing cash on delivery.

**Languages**

* English and Bulgarian.

**Moving from another plugin**

Nashenski Commerce is based on Bulgarisation for WooCommerce 4.0.13 by Autopolis.bg (GPLv3). If you used it, activate Nashenski Commerce and press "Import now": your settings, shipping zones and order data come along, and Bulgarisation is switched off.

It also imports from the mreja.net plugins (Econt Express, BOX NOW Delivery, Ordinance N-18 light regime), Drusoft Shipping for Econt and for Speedy, Econt Delivery and Speedy Shipping: courier logins, sender details, the waybill numbers of your orders, shipping zone methods and, from the N-18 plugin, your company details and document numbers. Each import switches the old plugin off. The import runs on your site; nothing is sent anywhere.

**Help inside the plugin**

Every setting has a short description and a tooltip. WooCommerce > Settings > Nashenski Commerce > Help has the recommended settings, the import from other plugins, getting started and how to use every part of the plugin.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the plugin and activate it.
3. Go to WooCommerce > Settings > Nashenski Commerce, choose the couriers and documents you need and save.
4. Add the courier shipping methods to your shipping zones in WooCommerce > Settings > Shipping.

== Frequently Asked Questions ==

= Where are the settings? =

In WooCommerce > Settings > Nashenski Commerce.

= Does my data go through a third-party server? =

No. Your site talks directly to the couriers and services you turn on. The NRA XML file is built on your site.

= Do I need a courier account? =

No. Without an account, shipping prices come from the courier's public price list. With your account, the prices are live.

= Does it work with the block checkout and page builders? =

Yes. It works with the classic checkout, the block checkout and checkout pages built in a page builder, including multi-step checkouts.

= Can I move from Bulgarisation? =

Yes. Activate Nashenski Commerce, then press "Review and import" in WooCommerce > Settings > Nashenski Commerce > Help. Your settings, shipping zones and order data come along, and Bulgarisation is switched off.

== Screenshots ==

1. Overview: turn on the couriers you work with and the documents you need. Each one adds its own settings.
2. Checkout: countries, courier logos, address transliteration and the company invoice fields.
3. Documents: company details for invoices and the NRA audit file, VAT group and refund method.
4. Customer checks: choose the checks from nekorekten.com, Connectix and NepoStop, and when cash on delivery is held back.
5. Quick order: a short form for shoppers who do not want to check out. The order waits for your confirmation.
6. Order screen: the courier line on the order, with the customer's history and checks beside it.

== External services ==

The plugin only contacts a service after you turn it on and enter your own account details for it.

* Courier websites (public price lists, only when you press "Check the courier website" and once a week): admin-ee.econt.com (Econt tariff PDF), services.speedy.bg/pricelist, pigeonexpress.com/prices-and-taxes. Nothing is sent except a normal page request.
* Econt (shipping): prices, labels, offices, cities, shipment status and courier requests. Sends the receiver's name, phone, address or office, the parcel details and the cash on delivery amount when prices or labels are made. Hosts: ee.econt.com, demo.econt.com. Terms: https://www.econt.com/econt-express/terms-of-use
* Econt office map: shown at checkout only when the customer presses "Choose on the map". The map page loads from officelocator.econt.com with the customer's town and the store address. Terms: https://www.econt.com/econt-express/terms-of-use
* Speedy office map: shown at checkout only when the customer presses "Choose on the map". The map page loads from services.speedy.bg (it uses Google Maps). Only the Speedy town number is sent. Website: https://www.speedy.bg/
* Speedy (shipping): same purpose and data. Hosts: api.speedy.bg, services.speedy.bg. Terms: https://api.speedy.bg/api/docs/#href-terms
* BOX NOW (locker shipping): lockers, labels and status. Hosts: api-production.boxnow.bg, api-stage.boxnow.bg, locationapi-production.boxnow.bg, locationapi-stage.boxnow.bg (public locker list), map.boxnow.bg. Website: https://boxnow.bg/ Terms: https://boxnow.bg/terms-of-use-for-shipping-services Privacy policy: https://boxnow.bg/personal-data-processing-notice
* Pigeon Express (shipping): offices, lockers, prices, labels and status. Hosts: api.pigeonexpress.com, api-demo.pigeonexpress.com. Without a Pigeon login, the checkout loads the public list of offices and lockers from pigeonexpress.com/api/locations (a normal page request, nothing about the customer is sent). Website: https://pigeonexpress.com/ Terms: https://pigeonexpress.com/terms Privacy policy: https://pigeonexpress.com/privacy
* nekorekten.com (customer check): sends the customer phone number to look up reports about it, and, only when the store owner clicks "Send report" on an order, the report text with the name, phone number and email of that order (and edits or deletes a report sent from an order when the owner asks). Host: api.nekorekten.com. Terms: https://nekorekten.com/terms
* Connectix (customer check): sends the customer's phone number to check their delivery record, and shipment data when tracking is on. Hosts: api.connectix.bg, api-sandbox.connectix.bg. Website: https://connectix.bg/ Terms: https://connectix.bg/bg/terms Privacy policy: https://connectix.bg/bg/privacy
* NepoStop (customer check): sends the customer's phone number, and labels when that option is on. Host: nepostop.com. Website: https://nepostop.com/ Terms: https://nepostop.com/terms Privacy policy: https://nepostop.com/gdpr
* EU VIES (VAT number check): sends the VAT number a company customer enters at checkout. Host: ec.europa.eu. Website: https://ec.europa.eu/taxation_customs/vies/ Legal notice: https://commission.europa.eu/legal-notice_en

== Third-party libraries ==

The plugin ships these libraries in its vendor folder: dompdf (LGPL-2.1), dompdf php-font-lib (LGPL-2.1 or later) and php-svg-lib (LGPL-3.0 or later), which make the PDF documents; and Pimple, Masterminds HTML5, sabberworm PHP-CSS-Parser, setasign FPDI, chillerlan php-qrcode and php-settings-container, clegginabox PDF merger, ddeboer VATIN and PSR container (all MIT). Their licences are in each library's folder.

== Changelog ==

= 0.17.0 =
* CVC courier removed. Its settings, labels, shipping method and cron jobs are gone.
* Courier pages are shorter: less used options sit under "More options", and the account and "Where you send from" sit side by side.
* "Public prices" shows only when "Use public prices" is ticked.
* Checkout settings regrouped into clear sections.
* New "Invoice numbering" for company invoices: "Own invoicing program" keeps the invoice out of the NRA audit file.
* The "Alternative shipping options layout" option is removed. It did not work in the block checkout.

= 0.16.9 =
* The "Withdraw from contract here" link no longer shows inside page-builder editors (Bricks, Oxygen, Breakdance, Elementor, Etch and others).

= 0.16.8 =
* New WP-CLI commands for every setting: `wp nashenski setting list`, `get`, `set` and `reset`, and `wp nashenski courier reload` and `check`.
* WP-CLI `setting set` runs the plugin's own checks for custom fields directly.

= 0.16.7 =
* Pigeon Express: labels for a region given by name, and for a new pack with its sizes.
* Speedy and Pigeon Express (mreja.net) can be imported: logins, sender details and shipping zone methods.
* NepoStop and nekorekten.com: choose the payment methods to hide for a flagged customer.
* Courier logos on the Couriers tab have the same height.

= 0.16.6 =
* Speedy: the city and region on a waybill come from the order's address, and street search works when typing in Bulgarian letters.

= 0.16.5 =
* BOX NOW: a refused label shows BOX NOW's reason instead of a server error.

= 0.16.4 =
* No compiled code left: all scripts and styles are readable; Speedy label box shows again; no server error when no office is chosen for a label.

= 0.16.3 =
* All courier label boxes run on readable scripts; Econt labels from the orders list for quick orders; courier settings no longer lock after saving.

= 0.16.2 =
* Help covers every part of the plugin with how-to steps; the order list label button, notices and the VAT price check use new readable scripts; tested up to WordPress 7.1.3.

= 0.16.1 =
* Security and speed fixes from a review; clean uninstall and deactivation.

= 0.16.0 =
* Updates: new versions come through Dashboard > Updates like any other plugin; the plugin no longer checks GitHub itself.

= 0.15.4 =
* Customer checks: a report to nekorekten.com carries the name, phone and email of the order, and a report sent from an order can be edited or deleted there.

= 0.15.3 =
* Quick order: the Econt label works from the Delivery box (the Delivery box now shows, the label box gets its starting data and cash on delivery), the totals update after a label, and the customer gets an email with the delivery price and the new total.
* Customer checks: send a report to nekorekten.com from the order screen.
* Documents: delete order document PDFs older than a set number of days, or all at once, to save disk space. Invoices stay.

= 0.15.2 =
* Quick order: a Delivery box on the order screen to choose the courier, the town and the office, locker or address with the checkout pickers; saving adds the courier shipping line, so the label can be made.
* Quick order: the owner gets the WooCommerce "New order" email, and quick orders count in the Orders menu bubble.

= 0.15.1 =
* Quick order and Abandoned carts have their own sections in the Nashenski Commerce settings menu, each with its on/off switch, so their settings are easy to find.
* Review fixes: a cart stays tracked after logout, block checkout orders count as ordered, reminders by hand only for open carts, a quick order from the cart empties the cart, a product quick order keeps the chosen quantity and skips products out of stock.

= 0.15.0 =
* Quick order: a short form on the product page and the cart for shoppers who do not want to check out, with ready-made templates, Bulgarian phone masking and a spam check.
* Abandoned carts: off by default, keeps carts that were not ordered, records the email when the shopper types it and sends reminders through an editable WooCommerce email, with an optional single-use coupon and a link that refills the cart. A list with totals is under WooCommerce > Abandoned carts.

= 0.14.2 =
* Checkout no longer fails for CVC addresses, and Speedy offices show no PHP notices, while a courier is not connected yet.

= 0.14.1 =
* Update details show the whole changelog with headings and lists that wrap, instead of one version scrolling sideways.
* Update details show the banner, the developer PristaDigital (pristadigital.com) and the WordPress and PHP versions needed.

= 0.14.0 =
* Settings in boxes that flow in two columns on wide screens, tighter rows, one row for the courier switches and one for the check services.
* Courier options are always shown, greyed out until the courier accepts the login; greyed-out values are kept on save.
* Even courier logo row; wide tables no longer push the page sideways.

= 0.13.0 =
* With the town or region field hidden, the courier fields ask for the town (with region and postcode) and save it on the order, in classic, block and page builder checkouts. Needs Econt switched on.
* Block checkout: office and address fields open under the chosen delivery option, field widths from the layout apply, and the pending price text no longer overlaps.
* Fixed PHP warnings from the weekly courier cache clean-up.

= 0.12.1 =
* Checkout: pressing "Place order" right after typing the street number no longer asks to choose a delivery option, and the order always gets the latest office or address. Works with the street field hidden, in classic, block and page builder checkouts.
* The Econt price list can be read again.

= 0.12.0 =
* New versions show up in Dashboard > Updates and can be installed from there, also automatically.
* Fixed PHP warnings at checkout when the shipping price had to be calculated again.

= 0.11.1 =
* Security: invoices, order documents and export files are private and open only for shop staff, the customer who owns the order, or a guest through a 24-hour link. Existing documents are moved after the update.
* Security: cash on delivery and the waybill's cash amount are checked when the order is placed.
* Security: CSV exports escape every text that a spreadsheet would run as a formula.

= 0.11.0 =
* The import keeps everything an earlier plugin saved on each order (waybill records, tracking, N-18 documents and their PDF files) and shows it in "From earlier plugins" on the order. Customers keep seeing their old documents.
* The import notice is one line and can be dismissed.
* In Bulgarian the plugin name is written in Cyrillic.

= 0.10.0 =
* Import from the mreja.net plugins, Drusoft, Econt Delivery and Speedy Shipping, and the old plugin is switched off (Bulgarisation too).
* Every setting has a description and a tooltip; the Help page has the recommended settings at the top and explains every part of the plugin.

= 0.9.0 =
* Order number on the waybill, order statuses that follow the shipment, "Create waybill", "Request a courier" and "Check the shipment now" for one order or many, and a tracking link on the customer's order page.
* The NRA audit file can be emailed to the accountant every month.
* Fixed: automatic Econt waybills for orders without checkout choices.

= 0.8.2 =
* Fixed: the order screen crashed for Speedy, BOX NOW and CVC orders while that courier had no working login.
* Pigeon Express offices and lockers at checkout without a login, from Pigeon's public list.

= 0.8.1 =
* BOX NOW: customers always get the locker list (BOX NOW's public list, no login needed), and the search finds lockers by town.
* Your own checkout fields now show in page builder checkouts made of single field elements.

= 0.8.0 =
* Checkout fields: one table to set the order, width (one or two columns), required, optional or hidden for every field, and to add your own fields (text, list, checkbox and more).
* Invoices: "Make invoice" on the order screen with your own invoice number, next to the automatic invoices.
* The courier price notice can be dismissed and goes away when you save that courier's prices.

= 0.7.2 =
* EU withdrawal function: the details the customer submitted are kept on the order and shown in a "Withdrawal" column in the orders list, a pill on the order screen and a "Withdrawal from contract" box.

= 0.7.1 =
* EU withdrawal function: a "Withdraw from contract here" button under the login form on the My Account page, for customers who ordered without an account.

= 0.7.0 =
* EU withdrawal function (Directive 2011/83/EU, Article 11a): WooCommerce's withdrawal form turned on from Checkout settings, with "Withdraw from contract here" links on every page, on orders, in order emails and as a shortcode, in your site's look and in Bulgarian.

= 0.6.1 =
* Customer checks: a service that is turned on without its API key or token is no longer asked and no longer shows an empty "Error:" or "No data". The Reports box names it, with a link to the settings.

= 0.6.0 =
* The order document is made for every order, whatever the invoice, documents or N-18 settings.
* Every document is made in Bulgarian and in English. The English file has an "EN" link next to it.
* Smaller supplier and customer boxes on the PDF, without empty lines. New PDF options: spacing, date format, phone and email, the "Received" box.

= 0.5.3 =
* NRA audit file: asks for the company EIK first instead of building a file the NRA rejects.
* Document numbers start at 1 when "Next NRA Document Number" is empty (it started at 0).

= 0.5.2 =
* Leaner code: about 1,900 fewer lines, with the same features. Copied code is shared, and unused code is removed.
* The export file now shows the Pigeon Express waybill number, and CVC orders keep the chosen office after checkout.
* The changelog window shows the plain text file.

= 0.5.1 =
* Passes WordPress.org Plugin Check with no errors or warnings.
* Security codes (nonces) on the checkout office and street lists, label tools, label print links and the Reports refresh link.
* Fixed: company customers with a valid VAT number could not order when the VIES check was on.
* Removed old unused code; asset versions on all scripts and styles.

= 0.5.0 =
* One new office, locker and address picker for every checkout, with search, the courier's map and saved choices.
* Speedy parcel lockers and offices can be chosen on Speedy's map and are priced without a Speedy login.
* "Complete the fields to calculate" instead of "Free" until the customer has chosen.
* Shipping options look settings: cards or theme look, colours and sizes from your design tokens.
* Company invoice fields with their own on/off switch, also in the block checkout.
* Custom export builder with saved exports.
* Bulgarian translation.
* Total in words in euro; clearer Reports column; the full name Nashenski Commerce everywhere.

= 0.4.0 =
* Office, locker and address pickers in the block checkout.
* Built-in public price lists for Econt, Speedy, Pigeon Express and CVC, used when a courier is not connected, with a check against the courier website.
* The courier checks before an order is placed work again.

= 0.3.0 =
* Settings in seven sections with clearer wording, a Help page with getting started and recommended settings.
* Courier logos, Bulgaria-only checkout, checkout field editor and address transliteration for every checkout.
* nekorekten.com customer checks.
* PDF logo, website details and appearance options (colours from TokenForge when it is active).

= 0.2.0 =
* Settings moved to WooCommerce > Settings > Nashenski Commerce and now look and save like the rest of WooCommerce.
* Removed the BGN/EUR dual price option.
* Saving settings and exports now check the user's permissions.
* A "Changelog" link on the Plugins screen.

= 0.1.0 =
* First build, based on Bulgarisation for WooCommerce 4.0.13. Everything runs on your site; no third-party server.
