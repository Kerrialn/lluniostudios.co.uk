# Browser checks

End-to-end checks for the design merge (`docs/merge-plan.md`, Steps 6–10 and the old shop redirects), run against the **local** site in a headless Google Chrome. They click through the pages as a customer would and print `PASS` / `FAIL` for each check, then `ALL PASSED` or `SOME CHECKS FAILED`.

They are not part of `composer test` (they need the running site, Chrome and Mailpit) and only ever touch your local site and database.

## Before you run them

- The local site is up: `docker compose up -d`, with the database migrated and the Lumen Forma sizes created (`docker compose exec -T php php bin/console app:lumen-forma:setup`).
- Assets are built: `npm run build`.
- Google Chrome is installed, and Node.js 22 or newer (they use the built-in `fetch` and `WebSocket`).

## Running

From the project folder:

```bash
node tests/browser/run-all.mjs            # every check, with a summary at the end
node tests/browser/run-all.mjs --clean    # ...and then remove the test data the run left
node tests/browser/run-all.mjs reserve configurator-edit    # just these
node tests/browser/reserve.mjs            # one check on its own, with every PASS/FAIL line
```

A full run takes about 15 minutes. A few checks take a size: `configurator-photos.mjs`, `configurator-custom-notes.mjs`, `configurator-edit.mjs`, `commissions.mjs` and `basket-gift.mjs` accept `1440 900` (desktop, the default) or `390 844` (phone); `run-all` runs both.

| Check | What it covers |
|---|---|
| `configurator-loads.mjs` | The configurator starts with no script errors (normal visit, old session, unknown edit link). |
| `configurator-page.mjs` | Nav link, no footer, the photo cross-fade and title colour, the reveal, panel width, moving between stages. |
| `configurator-stages.mjs` | Sizes, specifications, quantity (1–10), every option required with its "Please choose…" message, Bulb Count rules. |
| `configurator-customise.mjs` | Customise All / Individually, lamp tabs, a new stage opening on Lamp 1, per-lamp required choices. |
| `configurator-photos.mjs` | The looping gallery (arrows, side zones, swipe, keys), "Image Coming Soon", photos loading only as needed. |
| `configurator-add-to-basket.mjs` | The review, "Cast Your Lamp", the purchase route notes, the nav basket count, and the server refusing bypassed or invalid designs. |
| `configurator-custom-notes.mjs` | The line under a Custom choice, and the purchase route note under the review's button. |
| `configurator-edit.mjs` | "Edit configuration" from the basket: loaded design, updates in place, per-lamp lines, Reserve leaves the line unchanged; links to the configurator. |
| `configurator-hover-and-headings.mjs` | Which option headings show, the hand pointer and hover feedback. |
| `commissions-saving.mjs` | Bespoke Commissions enquiries saved with their files kept privately; "Commission submitted" with each form's message and the reference; email 03 (both forms) and the studio notification in Mailpit; the server's own checks with the page's bypassed (file type, 50MB a file, 100MB in total, over 110MB at once, the phone rule); the spam trap; the limit of 5 an hour; admin: the Commissions list, an enquiry's page, file downloads (admin only), deleting an enquiry deleting its files. Signs in to admin as `lluniostudios@gmail.com` (code from Mailpit) and clears the enquiry limit before and after. |
| `basket-gift.mjs` | The basket's "This is a gift": the date and its note, the earliest date allowed, both messages, the choice saved on the basket and kept, unticking clearing it; a single "Proceed to Payment"; no gift box when the basket goes by request; hover feedback. |
| `reserve.mjs` | Reserve Your Design end to end: what it carries, the form and its messages, the saved request, the emptied basket, email 02 and the studio notification in Mailpit. |
| `old-shop-redirects.mjs` | `/products` and `/collections` (and anything under them) permanently redirect to Lighting Designs, and no page links to them; the basket's purchase route and 2-lamp limit (Step 6), with the basket filled through the configurator. |
| `commissions.mjs` | Bespoke Commissions: the nav and homepage links, the hero (eight photos, title visible on load, reveal), the For Your Home / For Your Project tabs, each form's fields and required-field messages, the phone becoming required when Phone is chosen, the file chooser's checks (type, 50MB a file, 100MB in total), hover feedback. |
| `commissions-saving.mjs` | Bespoke Commissions enquiries saved with their files kept privately; "Commission submitted" with each form's message and the reference; email 03 (both forms) and the studio notification in Mailpit; the server's own checks with the page's bypassed (file type, 50MB a file, 100MB in total, over 110MB at once, the phone rule); the spam trap; the limit of 5 an hour; admin: the Commissions list, an enquiry's page, file downloads (admin only), deleting an enquiry deleting its files. Signs in to admin as `lluniostudios@gmail.com` (code from Mailpit) and clears the enquiry limit before and after. |
| `basket-gift.mjs` | The basket's "This is a gift": the date and its note, the earliest date allowed, both messages, the choice saved on the basket and kept, unticking clearing it; a single "Proceed to Payment"; no gift box when the basket goes by request; hover feedback. |

### Comparing with the prototype

`compare/` holds side-by-side measurements against the Claude Design prototype: the position, size and colour of each piece of text, with screenshots. They need the prototype unzipped (from `/Users/theonewham/Llunio Studios/# Website Wireframing Brief.zip`; see `docs/merge-plan.md`):

```bash
PROTOTYPE_DIR="/path/to/unzipped/brief" node tests/browser/compare/stages.mjs 1440 900
PROTOTYPE_DIR="/path/to/unzipped/brief" node tests/browser/compare/review.mjs 390 844 two
PROTOTYPE_DIR="/path/to/unzipped/brief" node tests/browser/compare/commissions.mjs 1440 900
PROTOTYPE_DIR="/path/to/unzipped/brief" node tests/browser/compare/basket-gift.mjs 390 844
```

They print only the differences (often expected ones, such as "Gallery" where the prototype says "Gallery (S)").

## Settings

Environment variables, all optional:

| Variable | Default | |
|---|---|---|
| `BASE_URL` | `https://localhost` | The local site. |
| `MAILPIT_URL` | `http://localhost:52374` | Mailpit, where the local site's emails land. |
| `OUT_DIR` | `var/browser-checks` in the project (ignored by git) | Screenshots and saved emails. |
| `CHROME` | `/Applications/Google Chrome.app/Contents/MacOS/Google Chrome` | The Chrome to drive. |
| `PROTOTYPE_DIR` | none | The unzipped prototype (for `compare/` only). |
| `PROJECT_DIR` | this project | Where `docker compose` runs the database commands. |

Chrome profiles are kept in the system temp folder (`llunio-chrome-profile-*`), one per check.

## Test data they leave

The checks create real data on the local site: anonymous baskets, "Ada Test" requests and commission enquiries (`ada.test@example.test`, with the enquiries' files in `var/commission-uploads/`), and emails in Mailpit. Remove them with `--clean` on `run-all`, or afterwards with:

```bash
node tests/browser/clean-up.mjs "2026-10-07 08:00"    # everything since this time (UTC)
```

This removes baskets without an account created since that time (including any basket of your own made then), requests and commission enquiries (and their stored files) from `@example.test` addresses, and test emails, studio notifications for those requests and sign-in codes sent since then. Accounts, orders and other requests are never touched.

## Writing new checks

Start from an existing one: `lib.mjs` gives `open(width, height, profileName)` (a browser with `ev()` to run code in the page, `send()` for DevTools commands, `shot()`), `checker()` for `PASS`/`FAIL` lines, `sql()` for the local database, `out()` for output files, and the settings above. Add the new file to `CHECKS` in `run-all.mjs`.
