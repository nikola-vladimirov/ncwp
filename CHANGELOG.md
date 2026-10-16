# Nashenski Commerce changelog

## 0.16.4 (2026-10-16)

### Admin scripts
- No compiled code is left: the old `dist/` folder is gone, and every script and style in the plugin is readable. The admin styles moved to `assets/css/`.
- Speedy: the label box shows again on orders (it could stay empty).
- Making a label with no office or locker chosen shows the courier's answer instead of a server error (Speedy, CVC, Econt, Pigeon).

### Help
- Quick order: for Econt, the label button in the orders list also makes the label once the delivery is saved.

### Updates
- "Tested up to" is 7.1 again, as WordPress.org asks.

## 0.16.3 (2026-10-16)

### Admin scripts
- The CVC, BOX NOW, Pigeon, Speedy and Econt label boxes now run on readable scripts instead of the old compiled files.
- Econt: the order list label button builds a label from the order when there is no checkout draft, and shows the courier message when no label was made.
- Courier settings no longer lock or show fields twice after saving.

## 0.16.2 (2026-10-16)

### Help
- The Help screen covers every part of the plugin, old and new, with short how-to steps: couriers and prices, checkout, waybills and statuses, quick order, abandoned carts, invoices and documents, the NRA file and exports, the EU VAT check, the withdrawal function, customer checks, impossible prices, moving from another plugin and updates.

### Admin scripts
- The order list "Generate label" button, the BOX NOW and Pigeon notices and the impossible VAT price check now run on a new readable script instead of the old compiled one. The notices check a security token when dismissed. The price check texts are translated.
- Unused compiled files were removed (about 350 KB smaller).

### Updates
- "Tested up to" is WordPress 7.1.3, so the update details no longer warn on 7.1.x sites.

## 0.16.1 (2026-10-16)

### Security
- The courier choice saved in a cookie is checked value by value.
- Only shop managers can hide the BOX NOW and Pigeon notices.
- The quick order limit counts the visitor's real address, not one a visitor can make up.
- Parcel numbers are checked before they go to BOX NOW or Speedy, and export errors are shown as plain text.
- The import from older plugins no longer turns stored data into PHP objects.

### Speed
- The courier cache jobs and the weekly price check are checked from the admin, not on every page of the store.
- When the BOX NOW or Pigeon office list cannot be fetched, the checkout waits 5 minutes before trying again instead of trying on every search.
- The withdrawal link style loads only where the link shows.
- Order, label and price notice scripts load only on the order and product screens.

### Removing the plugin
- Deactivating stops the plugin's scheduled jobs. Deleting it also removes the abandoned carts table and its temporary data. Settings, orders and documents stay.

## 0.16.0 (2026-10-16)

### Updates
- Stable versions now come from WordPress.org, through Dashboard > Updates like any other plugin. The plugin no longer checks GitHub itself.

## 0.15.4 (2026-10-16)

### Customer checks
- A report sent to nekorekten.com carries the first name, last name, phone and email of the order, as the service asks for. The box lists what goes with the report. An order with neither a phone nor an email cannot be reported.
- A report sent from an order shows in the box: change its text and press "Save report", or press "Delete report" (with a confirmation) to remove it from nekorekten.com. Each change leaves a note on the order.

## 0.15.3 (2026-10-16)

### Quick order
- Fixed: the Delivery box did not show on the order screen in 0.15.2.
- Fixed: the Econt label box stayed empty on a quick order, because only orders from the checkout had its starting data. It now starts from the order: receiver, office, weight, one parcel and the cash on delivery amount.
- Fixed: a quick order paid on delivery now gets the "Cash on delivery" payment method when the delivery is saved, so the label collects the amount and the shipping price comes onto the order.
- After a label is made, the order totals on the screen update without reloading the page (all couriers).
- The customer gets the WooCommerce "Order details" email with the delivery price and the new total once a label sets the price, and again only if the price changes. A note on the order says so.
- The Delivery box has its own "Save delivery" button, and the town and street lists open above the boxes below it.

### Customer checks
- "Send report" on the order screen: write what happened and send it to nekorekten.com with the phone number and the name of the order. A note on the order keeps the text. Before, the box could only refresh the data.

### Documents
- New setting "Delete order documents after (days)" under Documents > Invoices and documents: the order document PDFs (Bulgarian and English) of older orders are deleted once a day to save disk space. Invoices, credit notes and refund documents stay. The document number stays on the order and in exports, so the document can be made again. 0 keeps them all (default).
- "Delete all order documents now" under the same setting, with a confirmation.

## 0.15.2 (2026-10-16)

### Quick order
- A "Delivery" box on the order screen of a quick order without courier shipping. Choose the shipping option (for example Econt: To an office), then the town and the office, locker or address, with the same pickers as the checkout. Saving the order adds the courier shipping line with the choice, puts the town into the shipping address and leaves a note; the courier box for the label then appears. The shipping price comes when the label is made.
- The store owner gets the WooCommerce "New order" email for a quick order, the same as for any order (its recipients and on/off setting apply). It is not sent again when the quick order is confirmed.
- Quick orders count in the number next to WooCommerce > Orders in the menu, together with the orders that are processing.

## 0.15.1 (2026-10-16)

### Changed
- Quick order and Abandoned carts have their own sections in the Nashenski Commerce settings menu (WooCommerce > Settings > Nashenski Commerce). Each shows its on/off switch, and its settings appear as soon as it is on. Before, they were inside Checkout and only showed after turning them on in Overview.

### Fixed
- Abandoned carts: logging out no longer stops the reminders for a saved cart.
- Abandoned carts: orders from the block checkout count as ordered.
- Abandoned carts: "Send reminder" by hand works only for open carts, so a shopper who ordered gets no reminder or coupon.
- Quick order from the cart empties the cart, so it is not ordered twice or reminded as abandoned.
- Quick order on a product page keeps the quantity the shopper chose and refuses products out of stock.
- Quick order no longer writes a database error when abandoned carts were never turned on.

## 0.15.0 (2026-10-16)

### Quick order
- A "Quick order" button on the product page and/or the cart, for shoppers who do not want to go through checkout. It opens a short form as a dialog window, inline under the button, or a sliding panel from the right.
- Ready-made form templates (Minimal, Callback request, Standard, Full order) or a Custom one where you tick the fields one by one. Name, phone and email are always asked.
- Bulgarian phone numbers only, with a local (0888 123 456) or international (+359 88 123 4567) mask and a check on the page and again on the server.
- The submission becomes an order with the status "Quick order". No stock is reserved and no payment link is sent until you change the status. You get an email, and the shopper can get a confirmation (off by default).
- Spam protection with a nonce, a hidden honeypot field and a rate limit per IP address. The consent checkbox links to the privacy policy.

### Abandoned carts
- Off by default. Keeps the carts that were not ordered and records the email as soon as the shopper types it, even for a logged-out shopper at checkout.
- Reminders at the times you set (default 1, 24 and 72 hours after the last change), at most three, only between 08:00 and 22:00 site time, sent through WooCommerce.
- The reminder is a WooCommerce email, "Abandoned cart reminder", with an editable subject and body and placeholders, under WooCommerce > Settings > Emails.
- An optional single-use coupon for each cart, created when the first reminder goes out; the link in the email refills the cart and applies the coupon. Or a manual coupon code you create yourself in WooCommerce.
- A one-click unsubscribe link. A shopper who unsubscribes gets no more reminders.
- WooCommerce > Abandoned carts: totals, the newest carts, a status filter, paging, edit and send per cart, and bulk send and delete.
- Carts older than the retention period (default 90 days) are deleted daily.

## 0.14.2 (2026-10-15)

### Fixed
- CVC "to an address": the street and address search at checkout stopped with a server error while CVC was not connected yet (no token saved). It now answers like the other couriers, and the shipping price check no longer trips over it.
- Speedy offices and lockers at checkout no longer write PHP notices to the log while Speedy is not connected yet.

## 0.14.1 (2026-10-14)

### Fixed
- "View version details" on an update shows the whole changelog, every version, with headings and lists that wrap to the window. Before, it showed only the new version's notes as long lines you had to scroll sideways.
- The "Changelog" link on the Plugins screen shows the same headings and lists instead of raw text.
- Update details show the Nashenski Commerce banner, the developer PristaDigital with a link to pristadigital.com, and the WordPress and PHP versions the update needs. The plugin site link goes to pristadigital.com instead of the GitHub release, which a private repository does not show.
- On the Plugins screen the developer name and "Visit plugin site" link to pristadigital.com.

## 0.14.0 (2026-10-14)

### Settings screens
- Every group of settings now sits in its own box, and on wide screens the boxes flow in two columns, so long pages like Documents and Checkout need much less scrolling. Rows are tighter and fields fit their column. On narrower screens the boxes stack in one column.
- The courier switches on the Overview are one row, and so are the customer check services, instead of one tall row each with the same note repeated.
- Courier options no longer wait for a working login to appear. The sender details, label options, where you send from and the BOX NOW prices are always shown, greyed out until the courier accepts the login. Greyed-out values are kept when you save.
- The courier logos above the courier settings all sit in the same size box, so the row looks even.
- Wide tables (price lists, checkout fields) use the full width, and their columns no longer push the page sideways.

### Fixed
- The Pigeon Express "Extra services on labels" note with the link to the price page was never shown.

## 0.13.0 (2026-10-12)

### Checkout with the town or region hidden
- When the store hides the town or the region field, the courier fields ask for the town themselves: the customer starts typing and picks the town or village from a list with its region and postcode, so places with the same name (there are six called Nikolaevo) are told apart. Latin letters work too ("Varna", "Ruse"). Before, office and address delivery waited for a town the customer could not enter, and the order failed with "Choose a delivery option".
- When only the region is hidden and the customer types the town, the town is matched by itself when only one place has that name, so nothing is typed twice. This fixes Econt's "invalid settlement" error for the receiver.
- The chosen town, region and postcode are saved on the order in every checkout: classic, block and page builder checkouts. In the block checkout they also go into the customer's address.
- The town list is Econt's public list of towns, so this needs Econt switched on.

### Block checkout
- The office, locker or address fields now open right under the chosen delivery option, joined to it, instead of below the whole list.
- The field widths from the field layout now apply to the address in the block checkout too (for example phone on its own line and first and last name side by side). On phones WooCommerce still shows one field per line.
- "Complete the fields to calculate" no longer runs over the "Shipping" label in the order summary or over the courier name on narrow screens.

### Fixed
- PHP warnings "Trying to access array offset on false" from the weekly courier cache clean-up when a courier's cache folder did not exist yet.
## 0.12.1 (2026-10-12)

### Checkout
- A customer who typed the street number (or chose an office or locker) and pressed "Place order" straight away was told to choose a delivery option, although everything was filled in. The order now prices the choice that is on screen at that moment. This was most visible in the block checkout with the street address field hidden, and it is fixed for every courier and every checkout: classic, block and page builder checkouts.
- An address changed right before "Place order" could be saved with its old street number on the order and on the waybill. The order now always gets the latest choice.
- When the street address field is hidden, the chosen office or address always becomes the order address. Before, a street saved from an earlier order could stay on the new order. The customer types the address only once, in the courier fields.
- If the shipping price was calculated only when the order was placed, the customer now sees the new total and places the order again, so nobody orders at a price they have not seen. The block checkout already did this through WooCommerce.

### Courier price lists
- The Econt price list can be read again. Econt's server now ends the file in a way that the usual connection method reports as an error, so Nashenski Commerce reads it another way when that happens.

## 0.12.0 (2026-10-10)

### Updates from the dashboard
- New versions now show up in Dashboard > Updates and on the Plugins screen, and install with "Update now" like any other plugin. Automatic updates can be turned on too. "View version details" shows what changed.
- Every new version on the main branch of the GitHub repository is published as a release by itself.
- While the repository is private, each site needs a read-only GitHub token in wp-config.php. See Help > Updates.

### Fixed
- PHP warnings at checkout ("Trying to access array offset on null" in the Econt shipping method) when the shipping price had to be calculated again, for example after the payment method changed. The same was fixed for Speedy, BOX NOW, CVC and Pigeon Express. The courier checks now always run on the rates of the current checkout.

## 0.11.1 (2026-10-10)

Security release. Please update.

### Customer documents are private
- Invoices, order documents, credit notes, pro formas and the NRA and Delta export files are kept in a private folder and are no longer public files. Before, anyone could find and download them through the WordPress media list.
- Every document link now checks who opens it. Shop staff can open all documents. A customer with an account opens their own documents when logged in. A guest gets a link on the order page that works for 24 hours.
- Documents made before this version are moved to the private folder in the background after the update. Their old direct addresses stop working; the links on the order screens keep working.
- While an invoice with its copy was being made, the two halves were briefly public files with fixed names. They are now made in the server's temporary folder and deleted right after.

### Checkout
- Cash on delivery is checked when the order is placed, not only when the payment methods are shown. A customer flagged by Customer checks can no longer place a cash on delivery order by sending the order directly. In the block checkout, cash on delivery is now also hidden for them.
- The order is refused when the payment method does not match the one the shipping price was calculated with. Before, a changed browser cookie could leave out the cash on delivery amount from the Econt, Speedy, CVC and Pigeon waybill and lower the shipping price. The customer sees the updated total and places the order again.

### Exports
- CSV exports escape every text cell that a spreadsheet would run as a formula, also when it starts with + or - followed by a number (for example a company name like "+1+1"). Amounts and numbers are not changed.

### Fixed
- A PHP warning when making a PDF before the VAT group is set.

## 0.11.0 (2026-10-09)

### Nothing from the old plugins is lost
- The import now keeps everything an earlier plugin saved on each order: all its order fields and its own records for that order. For Econt Express (mreja.net) that is the full waybill record with the tracking history and the checkout choices; for the N-18 plugin (mreja.net) every document with its products and the links to its PDF files; for BOX NOW, Drusoft, Econt Delivery and Speedy Shipping their waybill and locker details.
- A new box on the order, "From earlier plugins", shows all of it, with the PDF and waybill links.
- Customers still see the documents the N-18 plugin made for them (order document, invoice, credit note, in Bulgarian and English) on their order page.
- Bulgarisation for WooCommerce needs nothing extra: its settings, order data, documents and product data come over one to one.

### Import notice
- The notice about other plugins is now one line with "Review and import" (the Help page) and "Dismiss". After Dismiss it comes back only when another plugin is found. It shows on the Dashboard, the Plugins screen and the plugin settings.
- Bulgarisation for WooCommerce is listed in the same notice instead of its own block.

### Name
- In Bulgarian the plugin name is written in Cyrillic everywhere (Nashenski Komers): the settings tab, the plugin list, notices and texts.

## 0.10.0 (2026-10-09)

### Moving from another plugin
- New import from the other Bulgarian shipping and N-18 plugins: the mreja.net plugins (Econt Express, BOX NOW Delivery, Ordinance N-18 light regime, Change Speedy and Econt Shipping Methods), Drusoft Shipping for Econt and for Speedy, Econt Delivery (also OneCheckout) and Speedy Shipping, and WooCommerce Speedy Offices.
- What comes along: courier logins (only when Nashenski Commerce has none yet), sender name and phone, the waybill numbers of your old orders (so tracking, status checks and courier requests work for them), the shipping zone methods (one Nashenski method per delivery type is added and the old method is turned off) and, from the N-18 plugin, your company details and the numbers of order documents and invoices, with the numbering continuing after the highest.
- After the import the old plugin is switched off. Its own data stays in the database.
- The import from Bulgarisation for WooCommerce now switches Bulgarisation (and its Pro add-on) off too.
- Plugins that are found are listed on the Help page with an "Import and switch off" button, and in a notice on the Dashboard and Plugins screens.

### Help everywhere
- Every setting has a short description under it and a tooltip with more detail, also the shipping method settings in your shipping zones.
- The Help page starts with the recommended settings, then the import from other plugins, getting started and "How to use it", one part of the plugin per section.

### Fixed
- Automatic order statuses: only Speedy's "delivered back to the sender" counts as returned. Destroyed, lost, cancelled or closed parcels no longer count as returned.
- Econt waybills without a PDF address (for example imported ones) link to Econt's tracking page.
- A PHP warning on the CVC settings page without a CVC login.

## 0.9.0 (2026-10-09)

Ideas taken from other Bulgarian courier plugins and the couriers' own guides, built for all five couriers at once.

### Waybills and order statuses (Overview settings)
- Order number on the waybill: Econt and Speedy get it in their reference field, BOX NOW on the label, Pigeon Express in the note for the courier and CVC in front of the contents.
- Order statuses that follow the shipment: choose a status for "delivered" (for example Completed) and one for "goes back to you" (refused or returned). Open orders with an Econt, Speedy, BOX NOW or CVC waybill are checked every hour and each change is noted on the order. Pigeon Express does not publish its statuses yet. Both are "Do not change" until you choose.
- New actions on each order (Order actions) and for many orders at once (orders list): "Create waybill" makes the same waybill as "Generate label after checkout", "Request a courier" books a pickup with Econt or Speedy for all the chosen waybills in one request, "Check the shipment now" reads the status straight away.
- Econt courier requests skip weekends and public holidays: when Econt says the day is a holiday, the next working day is asked.
- Customers see "Track your parcel" with the waybill number linked to the courier's tracking page on their order page (Econt, Speedy, BOX NOW, Pigeon Express).

### Documents
- New option: email the NRA audit file (Ordinance N-18) of the month before to the accountant on the 1st of every month.

### Fixed
- Making an Econt waybill automatically crashed for an order without the customer's checkout choices (for example an order made in the admin).

## 0.8.2 (2026-10-09)

### Order screen no longer breaks for couriers without a login
- Opening an order sent with Speedy crashed the order screen when Speedy had no login ("There has been a critical error"). Fixed. The same kind of crash is fixed for BOX NOW and CVC orders, and warnings are gone from the Econt box.
- When a town is not found, the Speedy label no longer takes the first town of the region by mistake, and CVC no longer takes the first country of its list.

### Pigeon Express offices and lockers without a login
- Pigeon Express offers no test login, so until you have a real one, customers now choose a Pigeon office or locker from the list Pigeon Express publishes on its website. The price comes from Pigeon's public price list, as before. Labels still need your Pigeon login.
- CVC does not publish its offices anywhere, so "CVC to an office" stays hidden until you enter your CVC login. "CVC to an address" works without it.

## 0.8.1 (2026-10-09)

### BOX NOW lockers at checkout
- Customers always get the list of BOX NOW lockers. It now comes from the public locker list that BOX NOW publishes, so it no longer depends on your BOX NOW login working. Your login is still needed to make labels.
- In demo mode BOX NOW has only two test lockers, both in Sofia. That is BOX NOW's test environment, not a fault in your settings.
- The locker search also looks at the town of each locker, so typing the town finds every locker there.
- When the cart needs a certain locker size, the size filter still asks BOX NOW with your login.

### Your own checkout fields in page builder checkouts
- Builder checkouts made of single field elements now show your own fields after the billing fields, as they already did for the company invoice fields. If you place a field yourself in the builder, it stays where you put it.

## 0.8.0 (2026-10-09)

### Checkout fields: order, width and your own fields
- Checkout > Checkout fields is now one table: move each field up or down, choose Default, Required, Optional or Hidden, and Full width or Half width. Two half-width fields next to each other share a line, so you can have one or two columns.
- Email and phone can be required and placed at the top, before the name and address.
- Add your own fields: text, text area, list, checkbox, email, phone, number or date. They show in the billing section of the classic checkout and page builder checkouts, under the email in the block checkout, on the order screen and in the order emails.
- The block checkout follows the order of the address fields and the phone; the email always comes first there.
- Your earlier show, require and hide choices are kept.

### Invoices: make one by hand with your own number
- The PDF Files box on the order screen has "Make invoice" with an invoice number box, filled in with the next number. Change it if you need to and press the button. The invoice uses your logo, colours and other PDF settings, like the automatic ones.
- A number that another order already uses is refused. The next automatic number continues after the one you typed.
- Automatic invoices work as before.

### Courier price notice
- "New published prices were found" now has a Dismiss link, and it goes away for a courier once you save that courier's prices. It only comes back when the courier publishes a newer price list.

## 0.7.2 (2026-10-09)

### EU withdrawal function: see the requests
- WooCommerce only marks the order and adds a short note when a customer withdraws; the details are only in the emails. Nashenski Commerce now keeps on the order what the customer submitted: the date and time (the same second as in the customer's acknowledgement), name, email, order number, full order or specific items, and the details.
- Orders list: a "Withdrawal" column after the status with a pill, the date and time, the choice and the start of the details.
- Order screen: a pill under the customer (like the reports pill) and a "Withdrawal from contract" box with everything the customer submitted.
- Withdrawals sent before this version show the date and the choice from WooCommerce's order note.
- Withdrawals stay visible on their orders if the withdrawal function is turned off later.

## 0.7.1 (2026-10-09)

### EU withdrawal function
- The My Account page shows "Ordered without an account?" and the "Withdraw from contract here" button under the login form for visitors who are not signed in. It works with WooCommerce's login form and with page builder login form elements that use WooCommerce's login form hooks.

## 0.7.0 (2026-10-09)

### EU withdrawal function
Directive 2011/83/EU, Article 11a (added by Directive (EU) 2023/2673, applied from 19 June 2026) asks every shop with an online checkout for a "withdraw from contract here" function. Nashenski Commerce builds on WooCommerce's own withdrawal form (WooCommerce 11.1 or newer), which already has the online statement (name, order number, email), the "confirm withdrawal" step, the acknowledgement email with the date and time, an email to the shop and an order note.

- Turn it on in Checkout > EU withdrawal function (it is WooCommerce's own "Order withdrawal" switch).
- "Withdraw from contract here" link at the bottom of every page, whatever the theme or builder, or placed by you: shortcode [nashenski_withdrawal_link] (button="yes" for a button) or the form address in a menu.
- On each order during the withdrawal period: a button in My Account > Orders, a link on the order page and on the order received page, and a link in the customer order emails. The withdrawal period is set in days after the order is completed (14 or more). The form itself is always open.
- The order number is filled in from the link; signed-in customers also get their name and email filled in.
- The form takes the site's look: the same colours, corners and spacing as the checkout (design tokens of TokenForge or another token-based CSS framework, or the theme). Steps as tabs, choices as cards, the review as a clear list.
- Bulgarian for all of WooCommerce's withdrawal texts while WooCommerce's own language pack has none, with the exact wording of the Bulgarian text of the directive for the link and the confirm button.

## 0.6.1 (2026-10-09)

### Fixed
- Reports box on the order screen: Connectix showed "Error:" with nothing after it, and NepoStop said "No data for this phone number", when they were turned on without a token or API key. Such a service is no longer asked (at checkout either); the box names it as "Not checked" with a link to Customer checks.
- When Connectix gives no answer, the box now says so instead of an empty error.

## 0.6.0 (2026-10-09)

### Order document for every order
- The order document ("Order", the sale document with the QR code) is now made for every order, also when invoices or documents are turned off in the settings or Ordinance N-18 is off. The N-18 setting now only turns on the NRA audit file in Exports.
- The Documents column in the orders list and the PDF box on the order screen are always shown.
- The refund document of a refunded order is made the same way.

### Bulgarian and English
- Every document (order document, invoice, invoice copy, credit note, refund document, pro forma) is made twice: in Bulgarian and in English, whatever the language of the site or of the user who placed or opened the order.
- The Bulgarian file stays the main one: it is attached to the emails and used by the exports. The English file has an "EN" link next to it in the orders list and on the order screen, and an "English" button on the customer's order page.

### PDF look
- The supplier and customer boxes are much smaller: one line per detail, address and town on one line, no empty lines.
- Empty header lines (for example "Place of transaction" without a town) are left out.
- New options in Documents > PDF appearance: spacing (compact, normal, spacious), date format (02.10.2026, 2026-10-02 or 2 October 2026), phone and email in the boxes, and the "Received" box.
- Dates use the chosen format instead of "Oct 02, 2026".

### Fixed
- The refund document showed a "Compiled by" column value even when no compiler was set, so its last row did not line up with the headings.
- The credit notice saved its date in the site language, which could not be read back in Bulgarian. It is now saved as 2026-10-09.

## 0.5.3 (2026-10-09)

### Fixed
- The NRA audit file (Annex 38) was built without the company EIK when Documents had none, and the check then reported a schema error. Now the button asks for the EIK (9 or 13 digits), with a link to Documents, before building the file.
- The first document of a store with an empty "Next NRA Document Number" got number 0000000000. Numbering now starts at 1.

## 0.5.2 (2026-10-07)

### Leaner code, same features
- About 1,900 fewer lines of code (33,166 to 31,247). Everything works as before; Plugin Check still reports no errors and no warnings.
- Code that was copied for every courier is now written once and shared: the label box on the order screen, its scripts, the checkout label saving, the courier login helpers, the cache clean-up and the shipping method setup.
- Invoices and credit notes build the "original and copy in one PDF" file in one shared place.
- The courier list and courier names are kept in one place.
- One security check for every request helper (label tools, checkout lists, place order).
- Removed code nothing used: an old CSV reader (replaced by PHP's own), the old settings app leftovers, an old help form, an image upload helper, unused helpers and hooks, and a check for the paid version that no longer exists.
- The office search box uses React's own unique IDs.

### Fixed
- The custom export showed no waybill number for Pigeon Express orders. It now uses the same number as the orders list.
- CVC orders did not keep the office or address chosen at checkout (a wrong argument when saving it).

### Changed
- The Changelog window shows this file as plain text.
- The NRA export reads the payment settings directly from the saved settings (same result in the file).

## 0.5.1 (2026-10-02)

### WordPress.org Plugin Check
- The plugin now passes Plugin Check with no errors and no warnings (it reported 129 errors and 649 warnings for 0.5.0).

### Security
- Every office, locker and street list at checkout now checks a security code (nonce) sent by the checkout page.
- All label tools on the order screen read their data through one function that checks the label security code first, then cleans every value.
- Label print links (Speedy, Pigeon Express, BOX NOW) now carry a security code, on the order screen and in the orders list.
- "Refresh data" in the Reports box now carries a security code before it asks the services again.
- The checkout company and VAT checks read the order form only after checking WooCommerce's own security code again.
- Removed an old "Clear cache" link that deleted the courier cache without a security code (the "Reload offices and cities" button replaced it in 0.3.0).

### Fixed
- A company customer with a valid VAT number could not place an order when the VIES check was on: an empty error was added at checkout. Now an error only shows for an invalid number or when the check itself fails.
- Text in documents, the help page and the orders list is escaped before it is printed.
- Translator notes for every text with placeholders, and numbered placeholders where a text has more than one.

### Under the hood
- Removed code no longer used since 0.2.0 and 0.5.0: the old settings pages and their save handler, the old checkout picker scripts and their styles, and the per-courier picker holders.
- Bundled translations are loaded with load_textdomain(); a language pack from WordPress.org, when there is one, still wins.
- File downloads (custom export, invoice archive) go through one helper that reads the temporary file with the WordPress file system class and deletes it.
- Asset versions on every script and style, so browsers load new files after an update.

## 0.5.0 (2026-10-02)

### Checkout: one picker for every checkout
- A new office, locker and address picker replaces the old one, the same in the classic checkout, the block checkout and page builder checkouts (also step-by-step ones). Search by town, name or address, use the arrow keys, and see the chosen office with its address and a "Change" button.
- "Choose on the map" opens the courier's own office map for Econt and Speedy.
- Speedy parcel lockers and offices can be chosen on Speedy's map without a Speedy login, and are priced from the public price list. Before, picking a Speedy parcel locker without a login did nothing.
- Speedy, Pigeon Express and CVC delivery to an address works without a login too: the customer types the street and number, and the price comes from the public price list.
- Office and locker options of a courier that has no login and no public office list (BOX NOW, Pigeon Express, CVC) are hidden, so customers never get an empty list.
- A choice is remembered in the browser, and typing in the address fields no longer jumps or loses focus.
- When the store shows the street field, the chosen office or address is written into it. Orders without a street get the chosen office or address in their address.

### Checkout: prices and looks
- Options that still need an office, locker or address say "Complete the fields to calculate" instead of "Free", in the options and in the order summary.
- Each option shows its delivery type under the name: "To an office", "To a parcel locker" or "To your address".
- New settings in Checkout > Shipping options look: cards or the plain look of the theme, accent colour, backgrounds, borders, corners, spacing and logo height. Empty settings follow the design tokens of TokenForge or another token-based CSS framework when one is active, and your theme otherwise.

### Company invoices
- Checkout > Company invoice turns the company fields on or off: "I want an invoice for a company", then company name, EIK, VAT number, MOL, town and address.
- They now work in the block checkout too, and in builder checkouts made of single field elements they appear after the billing fields by themselves, in the same style.

### Exports
- Custom export builder: choose orders, products sold or documents; filter by dates, status, payment method, courier and document type; pick about 70 columns (or any order meta field), order and rename them; choose CSV (separator and encoding), Excel, JSON or XML, the date format, the decimal mark and the file name. Preview the first rows, download, and save the export to run it again.
- Three ready-made starting points: sales for the accountant, products sold and shipments.
- The ready-made files (NRA audit file, invoice archive, Microinvest Delta) are now shown as cards above the builder.

### Documents
- "Total in words" works in euro, written in Bulgarian with euro and euro cents. Older orders in leva stay in leva.

### Orders list
- The Reports column shows a small status label in the WooCommerce style ("No reports" or "2 reports") instead of the large icons.

### Everything else
- Bulgarian translation of the whole plugin.
- The plugin is called Nashenski Commerce everywhere, also in shipping method names and messages.
- Speedy is not called without a login any more, so no error pages end up in its cached city list.

## 0.4.0 (2026-10-01)

### Block checkout
- Office, locker and address pickers under the shipping options of the WooCommerce block checkout, for Econt, Speedy, BOX NOW, Pigeon Express and CVC. The price updates after each choice, and the order is checked and saved the same way as in the classic checkout.

### Public prices
- Each courier has a built-in price list from its published prices (Econt from 01.10.2026, Speedy from 01.10.2026, Pigeon Express for October 2026, CVC from 01.09.2025), with office, locker and address prices by weight, the price above the heaviest row, VAT, surcharges and the cash on delivery fee.
- When a courier is not connected or does not answer, orders are priced with this list, so customers can always order.
- Edit the lists in Couriers, or press "Check the courier website": Nashenski Commerce reads the courier price list, shows what changed and fills in the new prices only when you choose to. Once a week it checks by itself and shows a notice when there are new prices.

### Fixed
- The courier checks before an order is placed ("Please choose an office", missing street number, courier errors) did not run since 0.1.0, because the shipping method names changed. They work again, in the classic and the block checkout.
- Warnings when an Econt order was placed without a working Econt login.

## 0.3.0 (2026-10-01)

### Settings: fewer sections, clearer wording
- Seven sections instead of eleven: Overview, Couriers, Checkout, Documents, Customer checks, Exports and Help.
- Couriers has one switcher with the courier logos, a connection status and a "Reload offices and cities" button (it used to say "Clear cache").
- New wording and short explanations for every group of settings.
- Old links to the previous sections still open the right place.

### Help page
- Step-by-step getting started, how shipping, offices, labels, documents and customer checks work, and how to move from Bulgarisation for WooCommerce.
- Recommended settings: a table with the current and the recommended value of each setting and why. Tick the rows you want and apply them; nothing else changes.

### Checkout
- Office and locker pickers are now found by their shipping rate, not by the page layout, so they work in the classic checkout and in page builder checkouts (Bricks, Elementor and others).
- Block checkout: courier logos, the country setting and the field editor work. Office and locker pickers in the block checkout came in 0.4.0.
- Courier logos next to each shipping option, in every checkout.
- Customer countries: Bulgaria only (the country field is hidden and every order is for Bulgaria), Bulgaria, Greece and Romania, or the WooCommerce settings.
- Checkout field editor: make address fields required, optional or hidden. Hidden fields also stay hidden in page builders that place fields one by one.
- Transliterate addresses: Bulgarian addresses typed in Latin letters become Cyrillic, and addresses abroad become Latin (Bulgarian Transliteration Act). Orders also get a "Transliterate addresses" action.
- Econt cities, offices and lockers load without an Econt login (they are public), so the office picker works before you connect.

### Customer checks
- nekorekten.com: reports about the customer phone number on the order, in the orders list, and to hide cash on delivery. Answers are remembered for 10 minutes (the service allows 10 checks a minute).

### Documents
- Logo and website details (name, address, email, phone) at the top of every PDF. The N-18 QR code moved under the document title.
- PDF appearance: logo size, title, accent, text and table colours, font, text size and paper size.
- With TokenForge active, the colours come from its palette and keep following it.

### Security
- The PDF generator no longer runs PHP code or loads remote files.

## 0.2.0 (2026-10-01)

### Settings now live inside WooCommerce
- The settings moved to **WooCommerce > Settings > Nashenski Commerce**. They look and save like every other WooCommerce settings tab: the same fields, help tips, section links and Save button.
- Sections: General, Documents, Export, Shipping, one section per courier you turn on (Econt, Speedy, BOX NOW, Pigeon Express, CVC), Connectix and NepoStop when turned on, and Help.
- All saved settings stay as they are. Old links to the previous Nashenski Commerce page open the new tab.
- Courier sections show login problems at the top and have a "Clear cache" button.
- BOX NOW price tables are now simple rows you can add and remove.
- The Export section creates the NRA XML file, the invoice archive and the Microinvest Delta file for a chosen month.
- The logo is now a small mark above the section links.

### Removed
- The "Enable BGN/EUR dual price" option and everything it switched on (dual prices in the shop, cart, emails and invoices). Bulgaria uses the euro and the dual price period has ended.

### Fixed
- Saving settings and running exports now check that the user may manage WooCommerce.

### Other
- A "Changelog" link next to the plugin on the Plugins screen opens this list.

## 0.1.0 (2026-10-01)

First build of Nashenski Commerce, based on Bulgarisation for WooCommerce 4.0.13 (GPLv3).

- Everything runs on your site. Shipping prices and labels go straight to Econt, Speedy and BOX NOW, and the NRA N-18 XML file is built on your site with no limit on the number of orders. Nothing goes through a third-party server.
- Econt now uses a secure (https) connection.
- Import of settings, shipping zones and order data from Bulgarisation for WooCommerce, with one button.
- Nashenski Commerce stays paused while Bulgarisation for WooCommerce is active, so the two never run together.
- Office, locker and address pickers now appear on the multi-step checkout of the builder.
- Customers see the courier name ("Econt", "Speedy") as the shipping method title.
- Fixed a crash when the BOX NOW locker list was empty.
- Removed: usage statistics sent to the original author, the paid add-on tab, the contact form, the donation box and partner advertisements.
