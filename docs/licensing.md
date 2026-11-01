# Licensing

MooreLife is sold as an **annual licence per property** after a **30-day trial**.
One licence covers the property's server and every terminal and staff client that
pairs with it.

## How it works

```
customer buys at /pricing  ──▶  licence order (pending)
                                    │
                     gateway checkout (Flutterwave/Paystack)
                                    │
              callback + webhook ──▶ order paid, licence key issued
                                    │
                                    ▼
        desktop app POSTs key + machine id to /licence/activate
                                    │
                                    ▼
        licence server signs a payload with its RSA private key
                                    │
                                    ▼
        app verifies the signature offline with the embedded public key
        and stores the licence; it never needs the network again
```

| Piece | Where it lives |
|---|---|
| Signing private key | Central licence server only: `LICENCE_PRIVATE_KEY` (base64 PEM) |
| Verification public key | `desktop/src-tauri/src/licence_public.pem`, compiled into every build |
| Licence records | `licences` table (key stored only as a SHA-256 hash) |
| Sales | `licence_orders` table (one row per checkout) |
| Prices | `licence_plans` table, editable under **Admin → Licences → Pricing** |
| Trial markers | `HKCU\Software\MooreLife\TrialStart` **and** `<app-data>/trial-start`; earliest wins |

The app does not hold the private key, so an installed copy cannot mint a licence
for itself. Anything it accepts must verify against the public key it carries.

## Pricing

A licence is priced by the size of the property, quoted **per month** and charged
**once for the whole term** (12 months by default). The buyer chooses NGN or USD.

| Tier | Rooms | Monthly (NGN) | Monthly (USD) | Charged for 12 months |
|---|---|---|---|---|
| Small hotel | up to 25 | ₦15,000 | $50 | ₦180,000 / $600 |
| Mid-size hotel | 26–50 | ₦25,000 | $70 | ₦300,000 / $840 |
| Large hotel / multi-property | 51+ | ₦40,000 | $100 | ₦480,000 / $1,200 |

These are **rows**, not config: the superuser edits the bands and both currency
prices at any time from the licence desk, with no deployment. Existing licences
keep the amount they were sold at; only new checkouts use new rates.

Overlapping or missing bands are refused at checkout rather than silently
charging the wrong tier: a room count with no matching active band returns a
validation error asking the buyer to contact you.

## Selling a licence online

The shop is the **platform's own**, not the hotel's website. It lives at
`/pricing` and `/licence/checkout` and is enabled by `LICENCE_CHECKOUT_ENABLED`
(off by default, so a half-configured deployment cannot take money).

1. Buyer enters the property name, room count and currency. The price is computed
   **server-side** from the room count — a tampered form cannot buy a large hotel
   at the small-hotel rate.
2. An order is recorded as `pending` and the buyer is sent to the gateway's
   hosted checkout.
3. On return, the callback requires the gateway's own `tx_ref`/`reference` to
   match the order, then verifies the charge with the provider. Requiring the
   reference means guessing an order id reveals nothing.
4. The webhook completes the same order if the buyer closes the tab before
   returning. Whichever arrives first issues the key; the second sees the order is
   paid and does nothing. **One payment always produces exactly one licence.**
5. The key is shown once on a **signed** success page and emailed to the buyer.
   Between the two, it is held encrypted on the order (APP_KEY) and cleared the
   moment it is displayed, so a webhook that beats the browser redirect still
   leaves the buyer able to see their key.

Failed or abandoned checkouts appear in **Admin → Licences → Orders**. They never
reach the hotel ledgers or reports: a licence sale is not hotel revenue.

`docs` note: the hotel's own public site does **not** link to `/pricing`. Link it
from your marketing site instead.

## Selling a licence by hand

For a bank transfer or a reseller, use **Admin → Licences → Issue a licence**, or:

```powershell
php artisan licence:issue --property="Lagos Harbour Hotel" --rooms=30 \
  --currency=NGN --name="Ada Obi" --email="ada@example.com" \
  --payment="BANK-7788" --months=12
```

Output:

```
Licence issued.
Key:       ML-7K3Q-9XPT-M2WD-RH4N
Property:  Lagos Harbour Hotel
Tier:      Mid-size hotel (26–50 rooms)
Price:     NGN 300,000.00
Term:      12 months from activation
Licence #: 41
```

The key is shown **once**; the database stores only its hash. Send it to the
customer.

## What is enforced

- **Trial**: 30 days from the first marker. Deleting the app-data folder does not
  reset it (the registry copy remains) and deleting the registry value does not
  reset it either (the file remains). Reinstalling the app does not reset it.
- **Expiry**: the window shows an activation screen instead of the wizard, and
  `setup_run_server` refuses to start the server with the trial-end date in the
  error. Terminals and staff clients do not hold their own licence — they rely on
  their server, which is the machine that was activated.
- **Term**: the months start at **first activation**, not at purchase, so a key
  bought in advance is not consumed sitting in an inbox.
- **Machine binding**: a key activates on one machine. Re-activating the same
  machine (restart, reinstall, restore) returns a fresh token and does not extend
  the term. A second machine is refused until support clears the binding.

## The licence desk

**Admin → Licences** exists only where `LICENCE_PRIVATE_KEY` is set (elsewhere the
route is a 404 and the sidebar entry is hidden) and only for the `superuser`
role. A hotel manager cannot reach it.

- **Overview**: active, expiring within 30 days, not yet activated, expired and
  revoked counts — each card filters the list. Revenue is shown per currency;
  adding naira to dollars would be meaningless.
- **List**: search by property, customer or reference; filter by status and
  currency.
- **Detail**: plan, room count, amount, payment reference, the bound machine id
  and activation date, plus the order it came from.
- **Actions**:
  - *Revoke* — blocks activation and renewal (an installed copy runs until its
    signed expiry).
  - *Restore* — undo a revocation; the expiry does not change.
  - *Move to another PC* — clears the machine binding so the same key activates on
    a replacement server, without extending the term.
  - *Re-issue key* — for a lost key: revokes the old one and issues a replacement
    with the same commercial terms, shown once so you can send it.
- **Pricing**: edit the bands and both currency prices, with a live preview of the
  12-month total and a warning when bands overlap.
- **Orders**: every checkout including abandoned ones, filterable by status.

## Deployment

Only the **central licence server** sets `LICENCE_PRIVATE_KEY`. On every other
deployment — including a customer's own hotel server — the variable must be
absent: there activation answers **503** rather than issuing a licence it cannot
sign, and the licence desk does not exist.

Generate the keypair once (RSA-3072, SHA-256). System PHP on Windows needs the
bundled OpenSSL config, so use the vendored binary:

```powershell
./resources/bin/x86_64-pc-windows-msvc/php.exe -r "
  \$cnf = 'resources/bin/x86_64-pc-windows-msvc/extras/ssl/openssl.cnf';
  \$key = openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => \$cnf]);
  openssl_pkey_export(\$key, \$pem, null, ['config' => \$cnf]);
  file_put_contents(getenv('USERPROFILE').'/.tauri/licence-private-key.pem', \$pem);
  file_put_contents('desktop/src-tauri/src/licence_public.pem', openssl_pkey_get_details(\$key)['key']);
"
```

Then put `base64_encode()` of the private PEM into `LICENCE_PRIVATE_KEY`.

**Back the private key up offline.** If it is lost, no existing installation can
ever receive a new or renewed licence without shipping a build with a different
public key. If it leaks, anyone can mint licences; rotate by generating a new
pair, shipping a release with the new public key, and re-issuing.

### Environment

| Variable | Default | Meaning |
|---|---|---|
| `LICENCE_PRIVATE_KEY` | empty | Base64 PEM of the signing key. **Licence server only.** |
| `LICENCE_CHECKOUT_ENABLED` | `false` | Turns the public shop on. |
| `LICENCE_CURRENCY_DEFAULT` | `NGN` | Currency preselected on the checkout form. |
| `LICENCE_TRIAL_DAYS` | `30` | Mirrors the desktop trial; documentation only. |
| `LICENCE_TERM_MONTHS` | `12` | Term of a new licence, in months. |
| `LICENCE_PRODUCT` | `MooreLife` | Shown in the signed payload and emails. |

The app points at `https://licence.moorelife.app/licence/activate` by default.
Override at build time with `MOORELIFE_LICENCE_ENDPOINT`.

Gateway keys come from the existing `FLUTTERWAVE_*` / `PAYSTACK_*` settings, and
the enabled providers and default come from **Admin → Settings → Payments**. Set
each provider's webhook URL to `/api/webhooks/flutterwave` and
`/api/webhooks/paystack` on the licence server.

### Enabling checkout

1. Deploy the licence server with `LICENCE_PRIVATE_KEY` set.
2. Add the gateway keys and confirm a test payment on each provider you enable.
3. Confirm the webhook URLs point at the licence server.
4. Set `LICENCE_CHECKOUT_ENABLED=true` and clear the config cache.
5. Open `/pricing` and buy one licence end to end with a live card, then activate
   it on a test machine.

## Offline behaviour

After activation the app needs no network: it verifies the stored token against
its embedded public key on every start. Consequences worth knowing:

- Revocation is not instant. It stops activation and renewal, but an installed
  copy keeps running until its signed expiry date.
- The clock is the machine's clock. Moving the system clock forward past the
  expiry blocks the app; moving it back does not extend a licence because the
  signed expiry is absolute.
- A tampered or foreign `licence.json` is ignored, and the app falls back to the
  trial/expiry decision rather than trusting it.

## Not implemented

- Automated refunds: revoke the licence in the desk and refund in the gateway
  dashboard. There is no reverse-sync from the gateway.
- Self-service transfer: moving a licence to a new server is a support action
  (*Move to another PC*), not something the customer can do.
