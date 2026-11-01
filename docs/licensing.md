# Licensing

MooreLife is sold as an **annual licence per property** after a **30-day trial**.
One licence covers the property's server and every terminal and staff client that
pairs with it.

## How it works

```
customer pays  ──▶  licence key (ML-XXXX-XXXX-XXXX-XXXX)
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
| Trial markers | `HKCU\Software\MooreLife\TrialStart` **and** `<app-data>/trial-start`; earliest wins |

The app does not hold the private key, so an installed copy cannot mint a
licence for itself. Anything it accepts must verify against the public key it
carries.

## What is enforced

- **Trial**: 30 days from the first marker. Deleting the app-data folder does not
  reset it (the registry copy remains) and deleting the registry value does not
  reset it either (the file remains). Reinstalling the app does not reset it.
- **Expiry**: the window shows an activation screen instead of the wizard, and
  `setup_run_server` refuses to start the server with the trial-end date in the
  error. Terminals and staff clients do not hold their own licence — they rely on
  their server, which is the machine that was activated.
- **Term**: the 12 months start at **first activation**, not at issue, so a key
  bought in advance is not consumed sitting in an inbox.
- **Machine binding**: a key activates on one machine. Re-activating the same
  machine (restart, reinstall, restore) returns a fresh token and does not extend
  the term. A second machine is refused until support clears the binding.

## Deployment

Only the **central licence server** sets `LICENCE_PRIVATE_KEY`. That deployment
is the one the desktop app calls. On every other deployment — including a
customer's own hotel server — the variable must be absent: there the activation
endpoint answers **503** rather than issuing a licence it cannot sign.

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

The endpoint is `POST /licence/activate`, throttled to 10 requests per minute per
client. It is called by the desktop app, not by a browser.

The app points at `https://licence.moorelife.app/licence/activate` by default.
Override at build time with `MOORELIFE_LICENCE_ENDPOINT`.

## Selling a licence

Until checkout automation exists, payment and key issuing are separate:

```powershell
php artisan licence:issue --property="Lagos Harbour Hotel" \
  --name="Ada Obi" --email="ada@example.com" \
  --payment="FLW-REF-123" --months=12
```

Output:

```
Licence issued.
Key:       ML-7K3Q-9XPT-M2WD-RH4N
Property:  Lagos Harbour Hotel
Term:      12 months from activation
Licence #: 41
```

The key is shown **once**; the database stores only its hash. Send it to the
customer. They paste it into the activation screen, and the term starts.

`--payment` records the gateway reference so a licence can be traced to a
transaction. `--months` overrides the default term.

## Support operations

All of these are `php artisan tinker` on the licence server:

```php
$licence = App\Models\Licence::find(41);

// Move a licence to a replacement PC (the customer's old machine died).
app(App\Services\Licensing\LicenceService::class)->clearMachine($licence);

// Refund / chargeback: block any future activation.
app(App\Services\Licensing\LicenceService::class)->revoke($licence);
```

Clearing the machine lets the same key activate once on a new PC; the expiry date
is unchanged, so transferring does not extend the term. Revoking keeps an
already-installed copy working until its stored licence expires (verification is
offline), but blocks re-activation and renewals on that key.

To extend a renewal, issue a new key for the property with a fresh term; the old
one can be revoked. The customer activates the new key on the same machine.

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

## Not implemented yet

- **Checkout / payment automation**: there is no public purchase page and no
  webhook that issues a key after a Flutterwave/Paystack payment. Sales are
  manual via `licence:issue`.
- **Admin licence screen**: no UI to list, search, revoke or re-issue licences.
  Operations are the commands above.

Both are additive: the verification path, schema and enforcement are final.
