# Offline security regressions

From the plugin directory:

```sh
php tests/import-permissions.php
php -d memory_limit=64M tests/boxnow-volume.php
php -d memory_limit=64M tests/speedy-resources.php
php -d memory_limit=64M tests/econt-resources.php
php -d memory_limit=64M tests/pigeon-resources.php
```

These scripts execute production classes with small WordPress/WooCommerce and
courier fixtures. They do not contact couriers, mutate a WordPress installation,
place orders or prove live checkout compatibility. Verification currently uses
PHP 8.5; a PHP 7.4/WooCommerce integration run remains a release check.

## Speedy calculation limits

Speedy opts into `Packing_Budget` for APS and carton packing. A calculation may
expand at most 1,000 product units, retain at most 2,048 carton candidates per
search and spend 2,000,000 work units across its packing calls. Work units count
unit expansion and candidate generation plus estimates for sorting and fixed
orientation checks. They are deterministic accounting units, not elapsed time
or CPU instructions.

The absolute parcel ceiling is 1,000. The existing `nashenski_speedy_max_parcels`
filter still supplies the commercial limit (10 by default); a nonpositive value
disables that commercial limit, but not the absolute safety ceiling. Both the
initial content generator and the later label-normalization filter validate
split counts before allocating output.

Exceeding any limit hides Speedy for that package. No partial product list,
truncated parcel list or approximate quote is sent to the courier or restored
through public/fixed/free pricing. A rejected calculation clears its prepared
session label. Ordinary courier failures can still use public-price fallback.
Large legitimate orders may therefore need another shipping option or a manual
quote. The same packers behave as before for callers that supply no budget;
this change does not claim to fix every courier's resource-exhaustion paths.

## Econt calculation limits

Econt now opts into the same unit, candidate and work budgets. Its early locker
fit check, office/address automatic sizing, and later locker label filter share
one calculation budget. The shared `APSPackage::pack_package` helper accepts an
optional budget; callers that omit it retain their previous behavior.

Automatic locker sizing no longer creates unused carton-product objects before
the locker-specific packing pass. Resource-limit exceptions escape the locker's
ordinary packing-failure fallback and hide Econt for that package. A rejected
calculation clears `nashenski-econt-label` and cannot be restored by public,
free or fixed pricing. Ordinary courier/packing failures keep their existing
handling. A fresh calculation starts with a fresh budget.

Limits apply to actual per-unit packing, rather than imposing a general order
quantity cap on weight-only calculation. This patch does not change Econt's
merchant service-policy handling or claim to fix unrelated findings.

## Pigeon calculation limits

Pigeon uses one calculation budget for unit expansion and locker checks/label
packing. Office/address carton search (whole-cart size and per-parcel grouping)
has its own work budget: when it runs out, the weight split prices the cart and
Pigeon stays available. The 1,000-unit and parcel caps still reject.
Office/address expansion is checked before pricing or connection fallbacks;
direct package and locker filters also receive a budget. Shared APS conversion
checks its input row count before allocating product descriptors.

The early locker check remains a per-product fit test, not a requirement that
the whole order fit in one box. Without automatic locker packing, it does not
introduce an aggregate order-quantity cap. Ordinary multi-box output, weight
fallbacks and existing fractional/default quantity interpretation are retained.

Both generic packing-error catches propagate resource-limit exceptions. Weight
fallback splitting validates finite weight and the projected package count
before allocation, while retaining the existing 30kg parcel split, 400kg
commercial shipment check and 30kg locker check. Safety limits remain effective
when a filter overrides a commercial eligibility check.

Exhausting the shared budget or a hard cap hides Pigeon and clears `nashenski-pigeon-label`; public,
fixed and free pricing cannot revive that rejected calculation. Ordinary
courier/login/packing failures retain their existing fallback behavior. A new
calculation resets the budget. This does not modify unrelated Pigeon policy,
cache, address, or administrator-label findings.

## Document-number concurrency integration

`tests/document-numbers.php` requires a disposable WordPress installation with
WooCommerce active and site title `Numbering tests`. It creates/deletes orders,
changes numbering settings, switches legacy/HPOS storage, and creates/drops a
synthetic `notasupto_documents` table. **Use a dedicated empty test database.**

```sh
NASHENSKI_NUMBERING_WP=/absolute/path/to/disposable/wordpress php -d memory_limit=512M tests/document-numbers.php
```

Workers use separate PHP processes and database connections. They exercise
concurrent checkout, stale instances of one order, refunds, separate invoices,
renewals, manual numbering and actual N-18 import. Controls cover independent
sequences, stable existing references, pending metadata, failed persistence,
database-lock contention, reservation errors and bulk-prefix migration.

All active number issuers share a site-specific MySQL/MariaDB advisory lock with
a five-second acquisition timeout. Failure stops issuance. Counter and metadata
reads bypass caches in the authoritative legacy/HPOS table; metadata writes
still use WooCommerce. Counters advance before order persistence, so a failed
save can leave a gap. Refreshing a saved reference leaves that metadata clean,
and a failed assignment cannot remain queued for an unrelated later save.

Imports retain available historical numbers and existing order references. An
invalid or conflicting incoming reference is skipped, counted and reported
("N document numbers skipped (conflict)") instead of silently renumbering it
or stopping the import. An empty separate invoice counter (shared numbering) is
never advanced by an import. Existing duplicate
historical numbers are not rewritten. Ordinary administrative counter edits
remain available; issuance checks stored references before using a reset value.

Validated locally with WordPress 7.1.4, WooCommerce 11.2.1, MySQL 26.10.0 and
PHP 8.5.12. This is not a deployed-site test or a PHP 7.4 compatibility run.

## Courier PDF navigation

```sh
node tests/courier-pdf.js
NASHENSKI_NUMBERING_WP=/absolute/path/to/disposable/wordpress php -d memory_limit=512M tests/courier-pdf.php
```

The PHP integration suite uses the dedicated `Numbering tests` site described
above. It creates/deletes orders and mocks courier clients; it does not send a
shipment request. It exercises Econt create/update/refresh, CVC creation, stored
status localization, legacy waybill import, order-list links and script loading.
The JavaScript suite executes the actual shipped iframe URL computations.

The preview guard accepts absolute remote HTTP(S) URLs, upgrades HTTP to HTTPS,
and rejects executable schemes, relative URLs, credentials, control characters,
backslashes, malformed URLs and the administrator site's hostname. Econt print
size is set through URL query APIs, retaining existing tokens and fragments.
Unknown provider/CDN hosts are retained: no complete courier PDF host allowlist
is established by the repository. This fix prevents URL-based script execution;
it does not authenticate arbitrary remote content or constrain remote redirects.

Both lazy components call the readable `assets/js/courier-pdf.js` helper and
fail closed if it is absent. Their changed chunks/loaders have new content-based
filenames, linked by `dist/manifest.json`, so existing caches cannot keep the old
computation. Original Vue/build sources are absent; the compiled changes are
limited to those two URL getters and their loader references. A future rebuild
must preserve the guarded computations and helper dependency.

Browser validation reproduced parent-page execution before the fix and rejected
the same payload afterward for both couriers. An intercepted valid PDF response
opened Chromium's native PDF viewer with the expected size query and fragment.
This is a synthetic browser control, not a live courier-label or physical-print
test. No iframe sandbox was added, preserving the native PDF viewer behavior.

## Econt service policy

```sh
php -d memory_limit=64M tests/econt-policy.php
NASHENSKI_NUMBERING_WP=/absolute/path/to/disposable/wordpress php -d memory_limit=512M tests/econt-policy-integration.php
```

These reuse the existing Econt-resource and courier-PDF fixtures, respectively;
their output reports those existing assertions separately from the new checks.
The integration script requires the dedicated `Numbering tests` site described
above and mocks all courier calls.

The shopper's `selectedOfficeIsAPS` flag does not determine service eligibility
when the catalogue can be read. Only a catalogue record matching the actual office
**code** authorizes the APS exemption. Raw and `officeID-` codes are supported;
the distinct numeric office ID does not count as a match. Address delivery is
not APS, and offices missing from a readable catalogue retain configured
protection. When the city search or catalogue cannot be read, the flag is used
only to remove services, never to add them.
Genuine recipient APS and sender APS retain their existing service restrictions.

Old prepared session labels are repaired at order save. Uncreated automatic
labels are checked again before creation, using the order's shipping instance
for review/test and order line/fee totals plus tax for missing declared value.
Shipping charges are excluded; the existing fee-inclusion filter is honored.
This works without a cart. Manual label creation and existing shipment updates
keep explicit staff choices.

Changes made by trusted `calculate_label` and `label_before_save` hooks are
recorded for four policy fields in a private session/order wrapper record.
That preserves explicit values, false flags and deliberate field omissions
across automatic creation. Shopper cookies cannot populate that record, and
it is not sent to the courier. Older labels without provenance use merchant
defaults when fields are missing; historical intentional omissions cannot be
distinguished from the original cookie-induced omissions.

Tests cover forged flags, ordinary/address/actual APS destinations, unknown and
malformed catalogue inputs, sender exclusions, separate instance choices,
quote/session/order/automatic paths, declared-value arithmetic, cart-free
automation, explicit staff overrides, and trusted filter values/omissions.
Live Econt API acceptance and a browser checkout walkthrough are not tested.

## Pigeon payment authority

```sh
php -d memory_limit=64M tests/pigeon-payment.php
node tests/pigeon-payment.js
NASHENSKI_NUMBERING_WP=/absolute/path/to/disposable/wordpress php -d memory_limit=512M tests/pigeon-payment-integration.php
```

The PHP scripts reuse the existing Pigeon-resource and courier-PDF fixtures and
report those existing assertions separately. Integration requires the dedicated
test installation described above and mocks all Pigeon requests.

Pigeon address cookies now keep only picker/address fields. Monetary fields are
discarded before rate metadata, checkout persistence and staff localization,
including for older saved cookies. The staff form gets its fixed-price default
from the first Pigeon shipping line displayed by its order panel. Its amount is
the WooCommerce shipping-line **net** amount, retaining existing staff-edit
semantics; WooCommerce calculates tax. Other shipping-line net amounts are not
changed by that adjustment.

Manual requests carry fixed price and explicit COD edits separately from cookie
data. Scalar, finite, nonnegative amounts (including zero) are required. Parsing
and projected shipping/tax/COD validation happen before order/item saves. The
projection uses cloned WooCommerce items and honors inherited tax classes and
VAT exemption. Amounts beyond safe numeric precision are rejected.

Untouched COD is derived from saved product/fee totals and tax, honoring the fee
inclusion filter. Sender-paid Pigeon shipping adds the saved gross shipping
charge once; receiver-paid courier shipping is collected separately. Prepaid
orders have no COD collection. Repeated Generate requests do not add a fee to an
already increased label value.

Per the chosen business rule, an explicit staff COD edit is the **final amount
collected**, including fixed shipping. Successful overrides are stored as
staff-owned order metadata and remain until changed. Failed courier creation
does not record a new COD override. As before, an approved fixed shipping-line
edit can remain saved if the courier later rejects label creation.

The Pigeon component records whether staff actually edited COD, sends a dedicated
override only for that edit, and clears the dirty flag after success. The changed
chunk/loader have new content-based names in the manifest. Older compiled forms
that lack the new fixed-price request field fail closed and need a page reload.
Future rebuilds must retain these separate staff inputs and edited-state logic.
Manual forms also carry a protocol version, so a stale cached form cannot have
an explicit COD edit silently treated as an untouched default.

Tests cover cookie/rate/order/default boundaries, actual Generate and automatic
creation, repeated requests, zero/final overrides, invalid native/string values,
pre-write projection failures, prepaid orders, other courier lines, standard and
inherited VAT, VAT exemption, and courier rejection. Live Pigeon API acceptance,
physical label printing and PHP 7.4 were not exercised. Existing invalid saved
financial data is not rewritten automatically.
