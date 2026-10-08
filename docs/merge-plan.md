# Merge plan: "Llunio Site v4 Unified" design into the live site

The design made in Claude Design ("Llunio Site v4 Unified") is the visual and UX spec for what this Symfony site becomes: one Lumen Forma configurator, a Reserve (request) route for larger or custom lamps, Commissions, Workshop, gift orders and a 6-email customer journey. The aim is to reskin and reshape the existing site to match the design, reusing what already works (orders, Stripe payments, shipping quotes, admin) rather than rebuilding it. The prototype is a spec only; its code is not copied in.

This file is the single source for the merge: decisions, step statuses, open questions and how to continue. Read it with `CLAUDE.md` (project conventions, pending content, notes for Kerrial).

Last updated: 8 October 2026 (Step 10b decisions recorded; 10b.1 in progress).

---

## Start here (for a new session)

- **Branch:** all design work is on `design-foundation`, made from `symfony-7.4-upgrade` (which is made from `main`). Nothing has been pushed yet. Never push to `main` or merge anything unless asked. To push: `git push -u origin symfony-7.4-upgrade design-foundation`.
- **Next step:** Step 10b (quotes for requests), 10b.1 first. See "Steps" below.
- **The prototype:** `/Users/theonewham/Llunio Studios/# Website Wireframing Brief.zip`. Inside it:
  - `Llunio Site v4 Unified.dc.html`: the prototype itself (with `support.js` next to it). Unzip and open it in Chrome. To jump to a screen, set `localStorage['llunio-site-v4'] = '{"v":7,"screen":"product","basket":[]}'` and reload; screens include `home`, `product` (the configurator), `cart`, `reserve`, `reserved`, `commissions`, `commissioned`, `workshop`, `terms`, `contact`, `payment`, `card`, `confirmed`.
  - `Llunio Email Templates/01-…06-*.html`: the six customer emails (02 is built; see Step 8).
  - `uploads/`: the real photo originals (15–20MB). `assets/email/`: email header images.
  - Older wireframes and concepts (`Llunio Site v3`, `Llunio Wireframes*`, `Lumen Forma Product Concepts*`) are history, not the spec.
- **Local environment:**
  - `docker compose up -d`; the site is at https://localhost, admin at https://localhost/admin.
  - Database commands must run inside the PHP container: `docker compose exec -T php php bin/console …` (the host name `database` only resolves there).
  - Mailpit (catches every email locally): http://localhost:52374.
  - Admin sign-in is by emailed 6-digit code; the local admin is `lluniostudios@gmail.com` (the code arrives in Mailpit). Promote others with `php bin/console app:user:promote <email>`.
  - The Lumen Forma sizes are created by `php bin/console app:lumen-forma:setup` (only what is missing). They are deliberately **unpublished**: the configurator doesn't need publishing; the old product pages do, and those pages are being retired.
- **Checks after every step:** `composer test`, `composer phpstan`, `composer check-cs` (`composer fix-cs` to fix), `php bin/console lint:twig templates`, `npm run build`. Then click through at desktop (1440) and phone (390) widths against the matching prototype screen.
  - **Browser checks:** `node tests/browser/run-all.mjs` runs the end-to-end checks for Steps 6–8 and the old shop redirects against the local site in headless Chrome (about 15 minutes; `--clean` removes the test baskets, requests and emails afterwards). `tests/browser/compare/` measures our pages against the prototype (set `PROTOTYPE_DIR` to the unzipped brief). See `tests/browser/README.md`; add a check there for each new step.

### How the work is done (standing rules)

- Small steps: build one step (or sub-step), stop, say what to check, and **commit only when asked**.
- **Never move or discard uncommitted work without asking first**: no `git stash`, `git reset`, `git checkout` / `git restore` of files, `git clean`, or any other command that sets aside, overwrites or deletes uncommitted changes. Ask before each use, even to compare against the last commit (use `git diff` or `git show` instead). Decided 7 October 2026.
- **Match the prototype exactly**, comparing against it (screenshots and measured positions), not working from memory. Report any intended differences.
- **Real product photography only**: never generated, mocked up or substituted, and never another size's photos. Anything without a real photo shows "Image Coming Soon" (`templates/partials/_image_coming_soon.html.twig`). No filler or stretched edges across photos. Resizing and stripping metadata (including GPS) for the web is fine; the photo list and how to replace photos are in `CLAUDE.md` (Pending content).
- **British English** everywhere, including flash messages and emails (Basket, colour, aluminium, enquiry, Postcode, "7 October 2026", £).
- **"Dorset Studio"**, never "Bournemouth studio" (the design's text says Bournemouth).
- **No size abbreviations** (XS, S, M, L, XL) anywhere: sizes are Studio, Gallery, Atelier, Estate, Monument.
- Migrations are **hand-written** (only the step's own changes; `doctrine:migrations:diff` picks up unrelated drift) and tested up, down and up again.
- Anything touching payments or the database gets a note on how to undo it.

---

## Status at a glance

| Step | What | Status | Commits |
|---|---|---|---|
| Upgrade | Symfony 7.4, Vich ^3.0 | Done (`symfony-7.4-upgrade`) | `5a4b1ed`, `b242bac` |
| 0–1 | Design branch; theme, nav, footer, Image Coming Soon | Done | `639d4c4` |
| 2 | Workshop, Contact, Terms & Conditions | Done | `1eafde7`, `cb55aee`, `47b0ce9` |
| 3 | Homepage hero (Test 2 bulb dimmer) | Done | `c7026a5` |
| 4 | Product data (sizes, options, admin) | Done | `2cc81ae`, `4b5d9e9` |
| 5 | Shipping: DPD only, one box per lamp | Done (mock DPD) | `dd0c26b` |
| 6 | Basket: one line per add, per-lamp configurations, 2-lamp limit | Done | `30ae978` |
| 7 | Configurator (`/lighting-designs`), 7.1–7.6 | Done | `574bf32`, `45c4635`, `6770e92`, `93a019f`, `27b1ae3`, `b024fde` |
| 8 | Reserve Your Design (requests, email 02, admin) | Done | `44b4408` |
| — | `/products`, `/collections` redirect to Lighting Designs; old pages removed | Done | `2418e07` |
| 9 | Bespoke Commissions (9.1 page and forms, 9.2 saving, uploads and admin, 9.3 thank-you page, email 03, notification) | Done (deferred full browser run for 9.2 and 9.3 done 7 October 2026: 17 of 17 passed) | `73bdb27` (9.1), `74ce773` (9.2), `2a3e0f9` (9.3) |
| 10 | Checkout reshape (10.1 basket and gift, 10.2 Card Payment page, 10.3 paying by card, 10.4 Apple Pay / Google Pay, 10.5 confirmation and gift details, 10.6 sign-in and order history) | Done (10.1–10.6; Apple Pay and Google Pay still to be tested by hand at launch) | `c1bdd31` (10.1), `8b54643` (10.2), `042aa0d` (10.3), `570274d` (10.4), `10d5537` (10.5), `3db5174` (10.6); footer fix `ae621bb` |
| 10b | Quotes (10b.1 quote form, 10b.2 sending, 10b.3 paying, 10b.4 balance) | In progress (10b.1, 10b.2 done; 10b.3 next) | `482b26f` (10b.1), 10b.2: see Step 10b |
| 11 | Order journey emails 01, 03–06 | Not started | — |
| 12 | Launch checks | Not started | — |

---

## Decisions

| Topic | Decision | What it means |
|---|---|---|
| Symfony upgrade | Done on `symfony-7.4-upgrade` | Design work branches from it, not `main`. |
| Who builds | Claude Code at your direction; Kerrial (your brother) steps in if needed | Small, separately reviewable commits, each with its checks run, so any step can be picked up cold. |
| Payments | **Stripe** | Existing `StripePaymentClient` and `assets/controllers/stripe_checkout_controller.js` (Payment Element + Express Checkout Element). Revolut code stays, switched off (`PAYMENT_PROVIDER=stripe`). |
| DPD | Mock until the DPD business account exists | `MockDpdProvider` (per box). Live later with `SHIPPING_MODE=live` through the same provider interface. |
| Delivery | DPD only at checkout; up to 2 lamps as **one consignment, one box per lamp** | `ShippingCalculator::consignmentForCart`; `SHIPPING_PICKUP_ENABLED=0`, `SHIPPING_PALLET_ENABLED=0` (kept in code, switched off). |
| Delivery wording | Keep "DPD Next Day Delivery", always shown with the 7–14 day dispatch time | e.g. "Dispatched within 7–14 days, then DPD Next Day Delivery" (Step 10). |
| Configurator stage 2 | **"The Pigment"**: White, Grey, Black, Custom | Used everywhere (configurator, basket, orders, admin, emails); replaces "Form" / "Finish Colour". |
| Prices | Studio £399, Gallery £799, Atelier from £1,699, Estate from £3,499, Monument from £7,999 | In pence per size product. "From" prices are display only: those sizes always go to Reserve. |
| Option prices | None for now | `ProductOptionValue::$priceDifference` exists (default 0); nothing reads it. |
| Bulb Count | Atelier, Estate and Monument only | Required there; cleared when switching to Studio or Gallery. |
| Checkout limit | **At most 2 lamps in the whole basket**, Studio/Gallery only, no Custom values | Everything else is "Reserve Your Design". Enforced on the server (`BasketRules`) as well as the page. |
| Configurator quantity | 1–10 lamps per configuration | `LumenFormaCatalogue::MAX_LAMPS_PER_CONFIGURATION`. |
| Starting choices | Gallery pre-selected; **every option starts empty and is required** | Moving past a stage without a choice returns to it with "Please choose a … to continue." (for every lamp when customised individually). |
| Purchase route notes | Request size: "Hand cast to order. Reserve now to secure your casting slot. Delivery will be quoted for individually." Custom: "… Custom options are developed with you and quoted individually." Both: "… Custom options are developed with you, and delivery quoted individually." Then, if it applies, "Quantities of three or more proceed through the Reserve request process, not direct checkout." | `BasketRules::routeNote()` (mirrored in `configurator_controller.js`). Shown on The Scale and under the review's button; a short line under any Custom choice. |
| Homepage | "Test 2" hero with an amber/black colour switch | Done in Step 3. Portrait photo pair for phones is pending content. |
| Lamp sizes (placeholders) | Studio 15×15×40cm 15kg; Gallery 22×22×60cm 20kg; Atelier 30×30×90cm; Estate 38×38×140cm; Monument 45×45×200cm | Lamp sizes, not packed boxes; heavier weights so mock delivery isn't understated. Replace with measured packed boxes. |
| Notifications | **lluniostudios@gmail.com** | Request (and later commission) notifications; setting `STUDIO_NOTIFICATION_EMAIL` in `.env`. |
| Reserve page | **One page, "Reserve Your Design"** | The design's "Reserve Your Casting Slot" variant is dropped. |
| Old shop pages | **`/products` and `/collections` redirect to Lighting Designs** | Every address under them (any product or collection, even unknown ones) is a permanent (301) redirect to `/lighting-designs`. Lumen Forma is the only product line at launch and the site isn't live yet (7 October 2026), so nothing else needs its own page. |
| Old shop code | **Removed** (7 October 2026) | The old product and collection pages, their form and their scripts are deleted. The Products and Collections sections in admin and the database are kept as they are. |
| Request follow-up | **Quotes in admin (Step 10b)** | Nothing is sent without pressing "Send quote". |
| Email 02 opening | One sentence per combination of reasons (see Step 8) | Wording approved 7 October 2026. |
| Hover feedback | Hand pointer on every button; muted items brighten to cream, cream items dim slightly, cream filled buttons dim a touch | Site-wide convention (`app.css`, `.ll-cta`). |
| Banners | No "Added to your basket" / "Your basket has been updated" | The nav basket count shows the change. Error messages stay. |
| Commission uploads | **Both forms (Home and Project): several files, up to 50MB each and 100MB in total per enquiry; PDF, DWG, JPG, PNG and HEIC** | Decided 7 October 2026 (answers question 15). The Home form is not limited to one photo. The local server's upload limit is raised to match; the live server must match too (Step 12). |
| Commission files | **Private; downloaded from the enquiry in admin** | Never in a public folder. The studio notification lists the file names with a link to the enquiry; files are not attached. |
| Commissions hero photos | **The prototype's eight** (APC_0055, APC_0116, APC_0112, APC_0058-1, APC_0117, APC_0057, APC_0054, APC_0109) | Resized, metadata (including GPS) removed; listed in `CLAUDE.md` (Pending content) for replacement by the edited versions. |
| Commissions intro text | **No fade-in**: "Your bespoke design / in our signature brutalist style, / custom-built for you." is visible on load in its staggered line positions | Decided 7 October 2026; the prototype matches (its three lines are always fully visible). |
| Commission required fields | Home: name, email, preferred contact, idea; phone too when Phone is chosen. Project: company, contact name, email, project type, location & schedule; the rest optional, uploads included | Decided 7 October 2026. **Project Type starts empty ("Please choose") and is required**, so nothing is pre-selected (the prototype starts on High-End Residential). |
| Email 03 contact line | Home: "by email" or "by phone", as chosen; Project: "by email" | Decided 7 October 2026. |
| Thank-you pages | **"Reservation received" and "Commission submitted" laid out like the site's other text pages** (Contact, Terms): heading ("Reservation Received" / "Commission Submitted"), then the prototype's message under a rule in 15px muted text, then "Confirmation has been sent to the email address provided." (wording decided 7 October 2026) **No reference numbers on these pages** (they are in the confirmation emails). | Decided 7 October 2026; shared template `templates/partials/_received.html.twig`. Differs from the prototype's centred message screen. |
| Accounts and sign-in | **Checkout never asks anyone to sign in; accounts stay in the background** | Decided 7 October 2026 (answers question 6). Every order is linked automatically to the account for its email. Sign-in email: a one-click "Sign in" button with the 6-digit code underneath as a backup. `/account` (order history) kept and restyled to match the design, reached by a small "Your orders" link on the Contact page instead of the footer. Existing accounts kept. |
| "View your order" | **Every order email has a "View your order" link** (built in Step 11) | Decided 7 October 2026 (answers question 7). No separate "Find my order" feature. |
| Delivery area | **UK mainland only at checkout** | Decided 7 October 2026 (answers question 2). Postcodes in the Scottish Highlands and Islands, Northern Ireland, the Isle of Man, the Isles of Scilly or the Channel Islands switch "Pay" to "Reserve Your Design" with a short note; the order goes through the request route with the basket and address carried over. The same rule applies to Apple Pay / Google Pay addresses. |
| Reserve Your Design page | **Styled as the Card Payment page, with the address in separate fields** | Decided 8 October 2026, before committing 10b.1. Fonts, sizes, spacing and boxed fields as Card Payment (its field partial, `checkout/_field.html.twig`), replacing the prototype's underlined fields (so `compare/reserve.mjs`'s field rows now differ on purpose). Typed text, placeholders and dropdowns in the page's light colour (they were black, unreadable on the dark background); this applies to Card Payment too. The single "Delivery Address & Postcode" box is replaced by Address line 1, Address line 2, Town/City, County and Postcode with Card Payment's limits (35; postcode 8), plus Country: United Kingdom (default), Isle of Man, Guernsey or Jersey; a postcode must be a full UK-style postcode and match the country (e.g. "That postcode is on the Isle of Man: please choose it as the country."). An outside-mainland address from checkout or a wallet is carried into these fields (country from the postcode; a company name goes in the notes, as Reserve has no company field). Admin and the quote form show the address in these fields so the quote step (10b.3) can use them directly. Requests received before this keep their single address box, shown as it was. |
| Reference numbers | **Prefix, the date as DDMMYY, then a random 4-digit number**, e.g. LR-081026-4821 | Decided 8 October 2026. LR requests, LC commission enquiries, LS orders (prefixes unchanged). The date is the UK date; the 4 digits are 1000–9999, so always four. A number already taken is replaced with another before saving (`ReferenceNumberChecker`; the database also refuses duplicates). Earlier numbers (e.g. LR-20261008-A0A403) stay as they are. |
| Quote email opening | **No heading**: it opens "Dear {first name}," (the first word of the name, as the sign-in email: "Dear Ada,"), then "Thank you for your patience. Here is our quote for your Lumen Forma request {reference}.", then the breakdown | Decided 8 October 2026 (replacing "Your quote" and "Your quote is ready"). |
| Studio email address | **lluniostudios@gmail.com until info@lluniostudios.co.uk exists** | Decided 8 October 2026: the domain has no mail servers yet, so info@ would bounce. One setting, `STUDIO_EMAIL` (`.env`), is used wherever the site shows or replies to the studio's address: the reply-to on customer emails (the quote), the email footers, the Contact page and the commission rate-limit message. Switched back to info@lluniostudios.co.uk at launch once it is set up (Step 12). Studio notifications already go to lluniostudios@gmail.com (`STUDIO_NOTIFICATION_EMAIL`). |
| Remembered choices | **The configurator and the Reserve form remember choices until used** | Decided 8 October 2026, before committing 10b.1. Leaving the configurator or Reserve Your Design and coming back keeps the size, quantity, every option, Customise All / Individually and each lamp's choices, and anything typed on the Reserve form. The hero reveal and the first stage still start fresh on every visit, with one exception (decided 8 October 2026): a returning visitor with a finished design (every required choice made, for every lamp) skips the hero intro and the configurator opens with the panel already revealed on the review (stage 06), one click from "Reserve Your Design" or "Cast Your Lamp" (on phones, where the panel is below the photo, the page scrolls straight down to it); every stage can still be opened to change things. An unfinished design keeps the hero intro, then stage 01. Forgotten once the lamp is added to the basket, or once the reservation is sent (the configurator's choices only if its lamp was in the request; a basket-only reservation leaves them). A small "Start again" link beside the stage number (shown once something is chosen) clears everything, including the Reserve form's details. Kept in the visitor's own browser: the configurator's choices (no personal details) until used or cleared; the Reserve form's details (name, email, phone, address) only until the tab is closed, as the device may be shared (confirmed 8 October 2026). Editing a basket line isn't remembered and leaves the remembered design alone. If the browser blocks storage, nothing is remembered and the pages work as before. |
| Line under the button (configurator review) | **No line under "Reserve Your Design" on the review** | Decided 8 October 2026: "Small-batch studio capacity. Reserving holds your build slot while we review your specifications and confirm exact lead times." is removed under the configurator's "Reserve Your Design" button, as the purchase route note says it; it stays on the Reserve Your Design page (and under the basket's "Reserve Your Design"). The line under "Cast Your Lamp" stays, with "our studio" changed to "our Dorset Studio" (8 October 2026): "Limited weekly batch capacity. Individually poured, cured, and hand-finished in our Dorset Studio." (`BasketRules::SUB_DIRECT`). |
| Express Payment | **Apple Pay and Google Pay at the top of the Card Payment page**; no separate Express Payment page | Decided 7 October 2026 (answers question 1). The basket has a single "Proceed to Payment" button. |
| Gift date | **Earliest arrive-by date: today + 14 calendar days, then 2 working days for DPD** | Decided 7 October 2026. |
| Gift details | **No recipient's mobile; gift orders ask for the same details as any order** | Decided 7 October 2026 (replaces the recipient's name and mobile). The delivery name is simply "Full name"; DPD's delivery phone is the customer's own mobile; Apple Pay and Google Pay are allowed for gifts. "This is a gift" and the arrive-by date stay. |
| Order confirmation page | **Like the other thank-you pages**: heading "Order & Slot Confirmed", then "Added to Studio Casting Schedule", "Gift · Arrive by [date]" if a gift, and "Confirmation has been sent to the email address provided." | Decided 7 October 2026. |
| Quotes: payment | **"Full payment" or "Deposit" per quote** (deposit 50% by default, adjustable per quote) | Decided 8 October 2026. The customer pays the deposit through the quote; later "Request balance" in admin emails a link to pay the rest (card, Apple Pay or Google Pay). Each order shows what has been paid and what is still owed. Studio and Gallery checkout stays full payment. |
| Quotes: VAT | **No VAT line**: quotes show the total only (not VAT-registered) | Decided 8 October 2026. **Revisit if you register for VAT** (quotes, order emails, receipts and T&Cs would need VAT shown). |
| Quotes: extra lines | **Separate named lines** (e.g. "Custom pigment development £150") alongside the lamps and delivery | Decided 8 October 2026. |
| Quotes: casting date | **An estimated casting date** on each quote, filled in by you | Decided 8 October 2026. |
| Quotes: delivery address | **Confirmed by the customer in proper fields before paying**, pre-filled from the request where possible | Decided 8 October 2026. |
| Quotes: delivery estimates | **Mock prices, clearly marked as estimates, always adjustable** | Decided 8 October 2026 (until the Palletways account and packed sizes exist: questions 25, 26). |
| Browser check output | **`var/browser-checks/` in the project** (ignored by git) | Decided 7 October 2026: screenshots and saved emails stay inside the project, not the system temp folder (`OUT_DIR` in `tests/browser/lib.mjs`). |
| Commission details | Reference `LC-YYYYMMDD-XXXXXX` (like requests' `LR-…`); admin statuses New / Contacted / Closed; a hidden spam-trap field and a limit on repeat sends | Defaults accepted 7 October 2026. |

---

## Steps

### Steps 0–5: done
- **0–1 Foundation:** theme tokens in `assets/styles/app.css` (`ll-bg #15120e`, `ll-ink #ece3d3`, `ll-mute #a89b86`, Archivo), nav and mobile menu, footer, "Image Coming Soon".
- **2 Static pages:** Workshop (origin story, slideshow), Contact (studio address "Hogsmeade, Higher Merley Ln, Wimborne BH21 3EG"), Terms & Conditions.
- **3 Homepage:** Test 2 bulb-dimmer hero (`bulb_dimmer_controller.js`), colour switch, one-screen phone layout.
- **4 Product data:** `Product::directOrder`, option `stage`/`position`, value `goesToRequest`/`position`/`priceDifference`/photo; `LumenFormaCatalogue`; `app:lumen-forma:setup`.
- **5 Shipping:** one parcel per lamp, one DPD consignment, DPD only, mock prices per box.

### Step 6: Basket (done)
One line per Add to Basket (no merging), per-lamp configurations (`cart_item_option.lamp`), `/basket` restyled ("Your Studio Order"), 2-lamp limit with the purchase route. The gift option and both payment buttons wait for Step 10.

### Step 7: Configurator (done)
`/lighting-designs` (`ConfiguratorController`, `configurator_controller.js`, `templates/configurator/`), data from `LumenFormaConfigurator` and `LumenFormaPhotos`.
- 7.1 layout and hero: Gallery photos cross-fading behind the title, per-photo phone framing, reveal on first interaction, panel 25% wide (min 300px).
- 7.2 stages: sizes, option lists, specifications, quantity.
- 7.3 photos: each size's own gallery, looping both ways; option photos (none in use; the two cable photos are parked).
- 7.4 Customise All / Individually with Lamp 1, Lamp 2… tabs; a new stage opens on Lamp 1.
- 7.5 review, "Cast Your Lamp" (adds to the basket via `lighting_designs_add`, which re-checks everything on the server) / "Reserve Your Design".
- 7.6 "Edit configuration" from the basket opens the configurator with the line loaded and updates it in place; shop links moved to the configurator; hover feedback; banners removed; stage headings removed except on The Light.

### Step 8: Reserve Your Design (done, `44b4408`)
- `/reserve` (`ReserveController`, `templates/reserve/`): the prototype's Reserve page. Carries every basket lamp plus the lamp being configured (held in the session by `DesignRequestLamps` when the configurator or the old product page sends someone to Reserve; an edited line is replaced, not duplicated). The basket's own link (`/reserve?basket=1`) sends just the basket.
- Fields as the prototype; "Custom Details" (one box) appears when any lamp has any Custom choice.
- On sending: a `DesignRequest` (status New; lamps snapshotted as `DesignRequestItem`, like order lines) is saved with no payment, the basket is emptied, "Reservation received" is shown (restyled in 9.3 like the site's text pages, with "Confirmation has been sent to the email address provided." and no request number), email 02 goes to the customer and a notification to `STUDIO_NOTIFICATION_EMAIL` (reply goes to the customer).
- Email 02 (`templates/email/request/received.html.twig`): the design's template with "The Pigment" and "Dorset Studio". Its opening sentence (`RequestReceivedCopy`) depends on why the design was reserved:
  1. Custom option only: "Your design includes a custom option, so we'll be in touch to develop it with you and confirm the details before anything is cast."
  2. More than two lamps only: "Your order contains more than two lamps, so we'll be in touch to confirm lead time according to our casting schedule."
  3. Larger size only: "Your design is one of our larger sizes, so we'll be in touch to confirm lead time according to our casting schedule, including a bespoke delivery quote."
  4. Custom + more than two: "Your order is for more than two lamps and includes a custom option, so we'll be in touch to develop them with you and confirm the details before anything is cast."
  5. Custom + larger size: "Your design is one of our larger sizes and includes a custom option, so we'll be in touch to develop it with you and to confirm lead time according to our casting schedule, including a bespoke delivery quote."
  6. More than two + larger size: "Your order is for more than two lamps and includes one of our larger sizes, so we'll be in touch to confirm lead time according to our casting schedule, including a bespoke delivery quote."
  7. All three: "Your order is for more than two lamps, includes one of our larger sizes and has a custom option, so we'll be in touch to develop them and confirm lead time according to our casting schedule, including a bespoke delivery quote."
  8. None (only by opening the page directly): "We review every request by hand before confirming."

  Then, in every version: "Your casting slot is now secured. No payment has been taken." The studio phone line is left out of the footer until a number is given (question 18).
- Admin "Requests" (`DesignRequestCrudController`): newest first, status badge (New / Quoted / Paid) and filter, full details and lamps; only the status can be changed (by hand until Step 10b). Times in UK time.
- Migration `Version20261007120000` (two new tables; undo with `doctrine:migrations:migrate prev`).

### Old shop pages: redirects (done)
- `OldShopController`: `/products`, `/collections` and every address under them answer with a permanent (301) redirect to `/lighting-designs` (query strings dropped; any method). `/productsx` and the like are not caught.
- Removed: `ProductController` (product list and product page), `CollectionController`, `templates/products/`, `templates/collections/`, `CartItemForm`, `CartItemFormModel`, the quantity spinner used only by that form (`IntegerType`, `form/type/integer_type.html.twig`, `integer_spinner_controller.js`), `purchase_route_controller.js` and `product_gallery_controller.js`. The basket's "Edit configuration" now only links to the configurator (the product-page fallback is gone); the nav no longer lists the old routes.
- Kept: the Products and Product Collections admin sections, the entities and the database (no migration).
- Browser check `old-shop-redirects.mjs` replaces `product-page-and-basket.mjs`. The purchase-route notes it checked on the product page are covered by `configurator-add-to-basket.mjs`; its basket checks (2-lamp limit, over-limit basket, checkout bounce) are kept, filling the basket through the configurator.
- To undo: revert the commit (nothing in the database changes).

### Step 9: Bespoke Commissions (done)
Two forms (Home: name, email, phone, preferred contact, idea, files; Project: company, contact, email, phone, project type, location & schedule, project, integration, files), an enquiry record and admin list, "Commission submitted" page (prototype message), email 03 (design template), notification to `STUDIO_NOTIFICATION_EMAIL`. Decisions are in the table above (uploads, files, hero photos, intro text, required fields, email 03 line).
- **9.1 (done):** `/bespoke-commissions` (`CommissionController`, `templates/commissions/`, `commission_controller.js`; forms `CommissionHomeForm`, `CommissionProjectForm`, shared `CommissionFields`).
  - Hero: the eight photos cross-fading every 5 seconds (`slideshow`), the title visible on load in its staggered positions, "Scroll to begin", reveal on first interaction (`hero-reveal`). Matched to the prototype by measurement (`tests/browser/compare/commissions.mjs`).
  - Tabs For Your Home / For Your Project; no form until one is chosen, then it fades in. `?for=home` / `?for=project` opens on that form; after a refused send the page reopens on the same form with the messages and what was typed.
  - Required fields as decided, with a message for each; choosing Phone turns the phone's "(optional)" into "*". A complete enquiry is accepted but **not yet saved or emailed** (9.2, 9.3).
  - File boxes take several files; the page lists them and refuses other types, files over 50MB and more than 100MB in total (the server checks come in 9.2).
  - Nav "Bespoke Commissions" and the homepage's "Commission Your vision" now link to the page.
  - Intended differences from the prototype: on phones the title is scaled to fit (the prototype's lines run off the screen); "*" added to the Home form's "Preferred contact method" and "Tell us about your idea" and to the Project form's "Project Type" (now required); Project Type starts on "Please choose" instead of High-End Residential; the file box text states the upload rules ("Choose photos or files — PDF, DWG, JPG, PNG or HEIC, up to 50MB each, 100MB in total" on Home instead of "Choose a photo"; ZIP replaced by the decided types on Project).
  - The forms use Symfony's plain form layout, so the site theme's default field classes don't apply.
  - Browser check `commissions.mjs` (desktop and phone).
- **9.2 (done; final full browser run deferred, see below):** saving enquiries, private files, spam protection and admin.
  - `CommissionEnquiry` (number `LC-YYYYMMDD-XXXXXX`, type Home/Project, status New/Contacted/Closed, the form's details) and `CommissionEnquiryFile` (the customer's file name, size, stored name). Migration `Version20261007150000` (two new tables; undo with `doctrine:migrations:migrate prev`, which drops both tables and every saved enquiry).
  - Files: `CommissionFileStorage` keeps them in `var/commission-uploads/` (outside `public/`, random names, folder only readable by the server). Downloaded only from admin (`/admin/commission-file/{id}`, admin sign-in required), always as a download under the customer's own file name. Deleting an enquiry in admin deletes its files (`CommissionEnquiryFileRemover`). **The live server must keep this folder between deploys and back it up** (Step 12).
  - The server repeats the page's file checks (type by extension, 50MB a file, 100MB in total) in the same words (`CommissionFields::uploadProblem`), and shows the same "more than 100MB" message when more than the server takes at once (110MB) is sent.
  - Spam: a hidden "website" field (an enquiry with it filled is accepted on screen but not saved) and at most 5 complete enquiries an hour from one connection (`config/packages/rate_limiter.php`, new package `symfony/rate-limiter`), after which: "You've sent several enquiries in a short time. Please try again in an hour, or email us at info@lluniostudios.co.uk."
  - Local PHP limits (`docker/php/zz-uploads.ini`, mounted in `docker-compose.yml`): 50MB a file, 110MB a request, 20 files. Locally the debug toolbar is switched off for an oversize send (it would read the whole request into memory); the live site has no toolbar.
  - Admin "Commissions" (`CommissionEnquiryCrudController`): newest first with number, received (UK time), status badge, type, name, company, email and file count; filters by status and type; an enquiry's page shows every detail and its files as download links; only the status can be changed.
  - After a complete enquiry the page still returns to the form; the "Commission submitted" page and emails come in 9.3.
  - Browser check `commissions-saving.mjs`; `clean-up.mjs` also removes test enquiries and their files.
  - Committed without a final clean full browser run, at your request (7 October 2026), because the machine was busy ingesting video (EditAssist). The last full run passed 16 of 17 (the one failure: Chrome didn't start; that check passed on its own); the run before had two pages not finish loading in time (both passed on their own). The full run is to be repeated when the machine is free, before 9.3 is committed.
- **9.3 (done; final full browser run deferred, see below):** finishing an enquiry.
  - "Commission submitted" (`/bespoke-commissions/received`): heading "Commission Submitted", the prototype's message for the form used (Home: "Your vision is in our hands. …"; Project: "We are reviewing the architectural scope and structural parameters …") and "Confirmation has been sent to the email address provided.", laid out like the Contact page; no reference number. "Reservation received" restyled the same way (heading "Reservation Received", same confirmation line, request number removed). Both use `templates/partials/_received.html.twig`. A spam-trapped enquiry sees the same page (nothing saved or sent).
  - Email 03 (`templates/email/commission/received.html.twig`, subject "Your commission enquiry" as the design's title): "Dear <name>", "We'll be in touch by phone/email …" as decided, the enquiry reference, the commission type ("For Your Home" / "For Your Project"), "Dorset Studio"; no studio phone line until question 18 is answered.
  - Studio notification to `STUDIO_NOTIFICATION_EMAIL` ("New commission enquiry LC-… from <name or company>"): every detail, the file names and sizes (not attached) and a link to the enquiry in admin; replies go to the customer. A mail problem is logged and never stops the enquiry being saved.
  - Unit test `tests/Form/CommissionFieldsTest.php` (the upload rules). Browser checks extended (`commissions-saving.mjs`: the page and its layout against Contact, email 03 for both forms, the notification; `reserve.mjs`: the restyled page, request number now read from the database; `commissions.mjs`; `clean-up.mjs` removes the test notifications). At your request (7 October 2026, machine busy ingesting video), only the unit tests, phpstan, code style and the relevant browser checks (`commissions-saving.mjs`, `reserve.mjs`, both passing) were run while building; 9.3 was committed without the full browser run at your request (7 October 2026: the machine was busy ingesting video and couldn't be paused). The deferred full browser run for 9.2 and 9.3 was done on 7 October 2026 once the machine was free: 17 of 17 passed. Under load, `reserve.mjs` can fail at its first page ("Reserve Your Design opens the request form") because it waits a fixed time; it passes when re-run.

### Step 10: Checkout reshape (done)
Decisions of 7 October 2026 are in the table above (Accounts and sign-in, "View your order", Delivery area, Express Payment, Gift date, Order confirmation page). The deferred full browser run for 9.2 and 9.3 passed (17 of 17) before 10.1 started.
- **10.1 Basket (done):** the "This is a gift" tick box with "When does it need to arrive by?" (earliest: today + 14 calendar days, then 2 working days for DPD; "Please choose a date…" messages as the prototype), and a single "Proceed to Payment" button (no Express Payment button). Delivery wording as in Decisions.
  - "Proceed to Payment" is now a small form (`BasketProceedForm`, handled by `CartController::index`): the server checks the date and saves the choice on the basket (`Cart::$gift`, `Cart::$giftArriveBy`), then goes on to checkout. Unticking clears it. Works without JavaScript (the date shows once the box is ticked, by CSS). Shown only when the basket can check out.
  - Earliest date: `GiftDate::earliest()` (UK time). Working days skip weekends and England and Wales bank holidays (Christmas and New Year substitutes included; one-off holidays such as a coronation are not). Unit tests `tests/Catalogue/GiftDateTest.php`.
  - Matched to the prototype's basket with the gift ticked (`tests/browser/compare/basket-gift.mjs`): same positions and sizes, except the intended ones (no Express Payment button, so "Proceed to Payment" and Terms sit higher; "Dorset Studio" and "DPD Next Day Delivery" wording, so the phone layout wraps one line less). Also brought "Proceed to Payment" and the Terms link to the prototype's letter spacing and line height.
  - Migration `Version20261007180000` (two columns on `cart`; undo with `doctrine:migrations:migrate prev`, which forgets any gift choices on baskets).
  - Browser check `basket-gift.mjs` (desktop and phone); `old-shop-redirects.mjs` now looks for the "Proceed to Payment" button instead of the old payment links.
- **10.2 Card Payment page (done):** one page replacing the address and delivery steps: Contact (email; phone for delivery updates), Delivery address (DPD character limits: name, company, address lines, town and county 35; postcode 8; notes 50; phone 15, to confirm with DPD; for a gift, the recipient's name and mobile, dropped since: see Gift details), Billing address ("Same as delivery address"), the order summary with the DPD price worked out from the postcode, "Pay £X", the "processed securely" line and a Terms & Conditions link. Never asks anyone to sign in. Postcodes outside the UK mainland (Scottish Highlands and Islands, Northern Ireland, Isle of Man, Isles of Scilly, Channel Islands) switch "Pay" to "Reserve Your Design" with a short note, and send the order through the request route with the basket and address carried over.
  - Built: `/checkout` (`CheckoutController::checkout`, `CardPaymentForm`, `templates/checkout/index.html.twig`, `checkout_controller.js`) replaces the old address and delivery steps (removed: `AddressForm`, `checkout/address`, `checkout/shipping`, `checkout/_summary`). Matched to the prototype by measurement (`tests/browser/compare/card-payment.mjs`): identical on desktop apart from the "Card details" section (10.3) and the decided wording ("DPD Next Day Delivery"; sizes without abbreviations).
  - The delivery line, total and button follow the postcode as it is typed (`/checkout/delivery`): "Calculated from postcode", then the DPD price and "Pay £X". Required fields left empty are outlined in cream with the prototype's one message; other problems (bad email, phone or postcode) say so under the field.
  - Outside the UK mainland (`DeliveryArea`, unit-tested; the Highlands and Islands districts follow DPD's usual list, to confirm with DPD): "Quoted individually", "Reserve Your Design" and the note "This address is outside the UK mainland, delivery will be quoted for individually. Reserve now to secure your casting slot." (wording decided 7 October 2026). Sending goes to Reserve Your Design with the basket and the details filled in (address lines, notes; for a gift, "Gift · Arrive by …" in the notes).
  - A gift: the same details as any order ("Full name"; DPD's delivery phone is the customer's "Your Mobile (for order and delivery updates)"), plus "Gift · Arrive by [date]" in the summary. (Built first with the recipient's name and mobile; dropped on 7 October 2026, see Gift details.) A gift date that has become too soon sends the customer back to the basket.
  - Until 10.3: "Pay" saves the order (status awaiting payment, with the contact phone, delivery instructions, company and any billing address) and continues to the existing separate payment page (old styling), which is now open to the visitor who started the order without signing in. Orders are not yet linked to an account (10.3).
  - Migration `Version20261007190000` (address company; order billing address, contact phone, delivery notes; undo with `doctrine:migrations:migrate prev`, which drops those details from any orders placed since).
  - Browser check `checkout-page.mjs` (desktop and phone); `clean-up.mjs` also removes test orders.
  - Basket brought in line with the prototype (decided 7 October 2026): each line's title is "Lumen Forma" with a separate "Size" row (e.g. Size: Gallery), as on the Card Payment summary (it showed "Lumen Forma Gallery" with no Size row since Step 6). Browser checks that read the basket now look for the Size row.
- **10.3 Paying by card (Stripe test mode) (done):** Stripe's secure card box in "Card details", the order created and paid, every order linked automatically to the account for its email (created quietly if new; no sign-in asked).
  - "Card details" as the prototype: "Name on card" (our field) and Stripe's own boxes for the card number, expiry and security code, styled like the page's fields with the prototype's placeholders (Stripe's "Link" saved-card button switched off). Card details go straight to Stripe. Stripe's messages in British English.
  - "Pay" sends the form to the server first (JSON). Anything missing (card included) is outlined with the page's one message and nothing is saved. Once complete: the order is saved (linked to the account for its email via `OrderAccounts`, created quietly if new), a card-only Stripe payment is created for it, and the page confirms it with the card (Stripe's 3D Secure pop-up if the bank asks). A declined card shows Stripe's message; retrying reuses the same order while the basket, gift date and postcode are unchanged, otherwise the earlier unpaid order is cancelled (and its Stripe payment).
  - The completion page asks Stripe whether the payment went through (works without the webhook, e.g. locally; the webhook still confirms too), marks the order paid (the existing confirmation email goes out), empties the basket and clears the gift. Still the old completion page until 10.5.
  - The old separate payment page is now used only by Revolut (switched off; kept); its Stripe part and `stripe_checkout_controller.js` are removed. Without JavaScript the page says "Please turn on JavaScript in your browser to pay by card."
  - No database changes. To undo: revert the commit.
  - Label "Your Mobile (for order and delivery updates) *" for the customer's phone (decided 7 October 2026: first "Your Mobile (for order updates)", reworded once it also became DPD's delivery phone for every order; the gift "Recipient's mobile" it was paired with was dropped, see Gift details).
  - Browser check `checkout-pay.mjs` (desktop and phone; Stripe test cards, typed into Stripe's frames); `checkout-page.mjs` now covers the page and its messages only; `clean-up.mjs` also removes test accounts (`@example.test`).
- **10.4 Apple Pay and Google Pay (done; wallets to be tested by hand at launch):** Stripe's wallet buttons at the top of the Card Payment page; the wallet's address goes through the same UK mainland rule (outside it: the request route instead of payment).
  - Stripe's Express Checkout buttons ("Buy with Apple Pay" / "Buy with Google Pay", white on the dark page, 48px) above Contact, shown only when the visitor's device offers a wallet (otherwise nothing shows).
  - The wallet sheet asks for name, email, phone, billing address and a UK delivery address; the DPD price ("DPD Next Day Delivery") is added once an address is chosen. Wallets share only the first half of the postcode until the customer approves, so `DeliveryArea::isMainlandOutward` decides from that.
  - Outside the UK mainland: the wallet sheet refuses the address (wallets show their own "address not supported" message), and when the sheet closes the page shows the outside-mainland note; the customer can type the address in the form and press "Reserve Your Design". (Approved details with an outside address are also sent to Reserve Your Design, with the address carried over, as the decision says.) Confirmed 7 October 2026: refused in the wallet pop-up, with the note on the page (rather than letting the customer approve a payment that would not be taken).
  - Approved: `/checkout/express` checks the wallet's details exactly as the card form's (DPD limits included; anything that doesn't fit: "Please pay by card using the form below"), then the same order handling as cards (account link, reuse, completion page).
  - Gifts: wallet buttons as for any order (decided 7 October 2026, with the recipient's mobile dropped; see Gift details).
  - Trying the real buttons locally: see "Testing Apple Pay and Google Pay" below. **Not tested by hand yet** (you don't use Google Pay): both wallets are to be tested by hand at launch, Apple Pay in Safari on your iPhone once the live domain is registered with Stripe (Step 12).
  - No database changes. To undo: revert the commit.
  - Browser check `checkout-wallet.mjs`.
- **10.5 Confirmation and gift details (done):** "Order & Slot Confirmed" laid out like the other thank-you pages ("Added to Studio Casting Schedule", "Gift · Arrive by [date]" if a gift, "Confirmation has been sent to the email address provided."); gift details saved on the order and shown in admin (in emails from Step 11).
  - `templates/checkout/complete.html.twig` uses the shared thank-you layout (`partials/_received.html.twig`, which now takes extra lines). If the payment is still being confirmed when the page opens (rare for cards; the webhook finishes it), it says "Order Received" and "Thank you. We're just confirming your payment and will email you shortly." instead (wording decided 7 October 2026).
  - Orders keep "This is a gift" and the arrive-by date from the basket (`Order::$gift`, `Order::$giftArriveBy`). Migration `Version20261007200000` (two columns on `orders`; undo with `doctrine:migrations:migrate prev`, which forgets which orders were gifts).
  - Admin Orders: a "Gift" column ("Arrive by [date]" or "No"); an order's page also shows the customer's mobile, delivery instructions and billing address ("Same as delivery address" when not given), saved since 10.2 but not shown until now.
  - Browser check `checkout-pay.mjs` extended (confirmation wording and layout, gift line, gift saved, admin).
- **10.6 Sign-in and order history (done):** the sign-in email gets a one-click "Sign in" button with the 6-digit code underneath as a backup; `/account` (order history) restyled to match the design; a small "Your orders" link on the Contact page instead of the footer's "Sign in".
  - Sign-in email ("Sign in to Llunio Studios", `templates/email/security/login_code.html.twig`) in the style of emails 02 and 03: "Dear [first name], press the button below to sign in and see your orders.", the "Sign in" button, "Or enter this code on the sign-in page" and the code, "The button and the code work for 10 minutes." (wording approved 8 October 2026, with the Sign In page's).
  - The button is a Symfony login link (`config/packages/security.php`, `login_link`): signed, valid for 10 minutes, and stops working if the account's email changes. It can be opened more than once within the 10 minutes, because some email services open links to check them before the customer clicks (a single-use link would be used up by them). A tampered or expired link returns to Sign In with "That sign-in link has expired. Please enter your email for a new one."
  - Sign In page restyled like the site's form pages (email, then the code step with the code boxes, "Keep me signed in", "Send a new email", "Use a different email"); an unknown email gets the same message and no email.
  - "Your Orders" (`/account/orders`) and each order's page restyled: placed orders only (paid or later; unfinished checkout attempts are left out), each with its date, lamps, total and status; an order's page shows the lamps as on the Card Payment summary, the totals, the gift line and the delivery address.
  - The footer's "Sign in" / "Your orders" link is gone; the Contact page has "Your orders" under Terms & Conditions (asking to sign in first if needed). The old checkout's "already have an account" step (`SESSION_PENDING_EMAIL`) is removed.
  - No database changes. To undo: revert the commit.
  - Browser check `account.mjs`; the checks that sign in to admin look for the new subject.
- Tested end to end with Stripe test keys before anything goes live; each sub-step notes how to undo it. No size abbreviations anywhere.

### Fixed: the wrong footer after moving between pages (found 8 October 2026)
- **Symptoms:** the homepage sometimes showed a black footer band at the bottom instead of its icons pinned over the photo; Lighting Designs sometimes showed the footer icons (it has none); the other pages could lose their footer.
- **Cause:** Swup page transitions swapped only `#main`, `#navbar` and `#flashes`. The footer sat outside them, so it stayed as the first page loaded left it: arriving on the homepage from another page kept that page's normal footer (the black band), arriving on Lighting Designs kept the footer, and arriving on any other page from Lighting Designs left none. A direct visit (or reload) was always right, which is why it seemed intermittent.
- **Fix:** the footer is in its own container (`<div id="footer">` in `templates/base.html.twig`, present on every page even when empty) and Swup swaps it with the page (`containers` in the same file). Back and forward use the same swap.
- **Check:** `tests/browser/page-footers.mjs` (desktop and phone): direct visits, then every page to every other page and back and forward. With the old setting it fails 60 times.

### Testing Apple Pay and Google Pay
In Stripe test mode no wallet card is ever charged; Stripe swaps in a test card.
- **Locally neither wallet shows (found 8 October 2026):** Google Pay doesn't appear at https://localhost because Chrome marks the site "Not secure" (the local certificate isn't trusted), and wallets only appear on secure pages; Apple Pay needs a registered domain (below). So Google Pay and Apple Pay will be tested by hand on the live site at launch (Step 12). The notes below are kept in case local testing is wanted later.
- **Google Pay:** Chrome (desktop, or Android) signed into a Google account with a card saved in Google Pay (pay.google.com). Open https://localhost/checkout with a lamp in the basket; "Buy with Google Pay" appears above Contact. Google Pay is on by default in Stripe (Dashboard, test mode: Settings → Payment methods).
- **Apple Pay:** Safari on a Mac with Touch ID (or an iPhone) with Apple Pay set up. Apple only shows it on a domain registered with Stripe, and `localhost` can't be registered, so it needs a temporary public HTTPS address for the local site (e.g. a tunnel such as ngrok or Cloudflare Tunnel), added in the Stripe Dashboard (test mode: Settings → Payment method domains), then opened in Safari. A tunnel makes the local site reachable from the internet while it runs: stop it afterwards. The live domain is registered at launch (Step 12).

### Step 10b: Quotes (in progress)
Decisions of 8 October 2026 are in the table above (Quotes: payment, VAT, extra lines, casting date, delivery address, delivery estimates). Studio and Gallery checkout stays full payment.
- **10b.1 Quote form in admin (nothing sent) (done, `482b26f`):** opening a request shows a quote form: one price per lamp design (pre-filled from the size's price), any number of extra named lines (e.g. "Custom pigment development £150"), delivery for the whole order as one consignment (DPD for parcels, Palletways for pallets; mock prices marked as estimates, always adjustable), "Full payment" or "Deposit" (50% by default, adjustable), an estimated casting date, and a short personal note. Saved as a draft with the totals; nothing is sent.
  - A "Quote" action on each request (its page and the list) opens `/admin/design-request/{id}/quote` (`DesignRequestCrudController::quote`, `templates/admin/quote.html.twig`, `QuoteForm`). A new draft starts with each design at its size's price as snapshotted on the request (e.g. Atelier £1,699 each) and the delivery estimate (`QuoteDelivery`: the request's lamps as one consignment to the postcode found in its address, priced by DPD or Palletways, whichever can carry it and is cheapest; marked as a mock estimate, with notes when there is no postcode, the address is outside the UK mainland, or a size has no packed weight yet). Everything can be changed; the totals (lamps, extra lines, delivery, total, due now, balance later; no VAT) follow as prices are typed and are worked out again on saving. The delivery stays marked as an estimate only while it is the estimate's price and description.
  - Saved as a draft ("Quote saved as a draft. Nothing has been sent to the customer."); the request stays New. Casting date optional on a draft (required before sending, 10b.2).
  - `Quote` and `QuoteLine` (migration `Version20261008100000`, two new tables; undo with `doctrine:migrations:migrate prev`, which deletes all quotes). Deleting a request deletes its quote.
  - Note: with no packed weights yet, an Estate counts as weightless and fits DPD's size limit, so it is priced as a parcel; Monuments (200cm, over DPD's 175cm) go by pallet. Real packed weights (questions 8, 26) will change this.
  - The quote form uses an ordinary session security token: the site's "stateless" form tokens rely on the public pages' script, which admin doesn't load.
  - Browser check `quote-form.mjs`; unit tests `tests/Entity/QuoteTest.php` (totals and deposit rounding).
  - Also before committing (8 October 2026): remembered choices and the review's button line (see "Remembered choices" and "Line under the button" in Decisions). `assets/saved_choices.js`, `forget_choices_controller.js` (the server's `forget_choices` session list, rendered by `base.html.twig`), `remember_form_controller.js`; browser check `configurator-remember.mjs`. The browser checks now clear the site's storage when they open Chrome (`lib.mjs`, `clearStorage()`), and the configurator checks that expect an empty configurator clear it before each visit.
  - Before committing (8 October 2026), the Reserve Your Design page was restyled as Card Payment, with the address in separate fields and a country (see "Reserve Your Design page" in Decisions). Migration `Version20261008120000` adds the address fields to requests and makes the old single box optional; undoing it (`doctrine:migrations:migrate prev`) first writes newer requests' addresses back into the single box, so none are lost. `DesignRequest` keeps `getDeliveryAddress()` (the whole address as lines, for emails and older requests) and gains the separate fields and `getPostcode()`; the quote's delivery estimate uses that postcode. Checks updated: `reserve.mjs` (fields, styling, colours, country, limits, messages, saved fields, notification), `checkout-page.mjs` and `checkout-wallet.mjs` (address carried into the fields), `quote-form.mjs` (the address in admin and the quote); unit tests for the address and the country of a postcode.
- **10b.2 Sending the quote:** "Send quote" emails a branded quote (breakdown, total, deposit if any, casting date, note) with a "Pay now" button; the request becomes Quoted. Quotes expire after 14 days; one can be changed and sent again (new 14 days). Nothing is ever sent without pressing the button.
  - **Done:** "Send quote" beside "Save draft" on the quote page (asks "Send this quote to …? It will be valid for 14 days." first) saves, then emails the quote and marks the request Quoted. It isn't sent without an estimated casting date ("Quote saved but not sent. Please add the estimated casting date before sending.") or with a £0 total. If the email can't be sent, nothing is marked as sent.
  - The email ("Your Lumen Forma quote, {name}"; no quote email in the prototype, so in the style of email 02): no heading; it opens "Dear {first name}," then "Thank you for your patience. Here is our quote for your Lumen Forma request {reference}." (see "Quote email opening"), the studio's note, 01 Your configuration (each design with its quoted price, e.g. "£1,850 each"), 02 Quote (each design's line, extra lines, "Delivery · {description}", Total; for a deposit "Deposit to pay now (40%)" and "Balance to pay later"), 03 Casting (estimated casting date), a "Pay now · £{amount due now}" button and "This quote is valid until {date}. Reply to this email with any questions." Replies go to the studio's email (`STUDIO_EMAIL`: lluniostudios@gmail.com until info@ exists). No VAT shown.
  - Valid until the end of the 14th day after sending, UK time. "Pay now" opens the customer's private quote page (`/quote/{link}`, a long random link; never indexed): "Your Quote", the request number, "valid until", the note, the configuration and the same breakdown. Paying there comes with 10b.3. Once expired: "This quote expired on {date}. Please contact us or reply to the quote email, and we'll send you an updated quote."
  - What was sent is kept: changes saved afterwards reach neither the page nor the customer until "Send again" (same link, a new 14 days); admin shows "Changed since it was sent…" until then, when it was sent, until when it is valid (or that it expired) and a link to the customer's page.
  - Migration `Version20261008200000` (link, sent and expiry times, the sent copy; undo with `doctrine:migrations:migrate prev`). Browser check `quote-send.mjs`; unit tests for the expiry (UK days, across the clocks changing) and the sent copy.
- **10b.3 Paying the quote:** the private quote page shows the breakdown; the customer confirms the delivery address in proper fields (pre-filled from the request where possible) and pays the deposit or the full amount by card, Apple Pay or Google Pay. The request becomes an order (linked to the account for its email) and Paid; the order shows what has been paid and what is still owed.
- **10b.4 The balance:** "Request balance" in admin emails the customer a link to pay the rest (card, Apple Pay or Google Pay); once paid the order shows nothing owed.
- Needs, later: questions 25 (Palletways account) and 26 (packed sizes and weights) for real delivery prices.

### Step 11: Order journey emails (not started)
Restyle email 01; new order stages In production / Dispatched / Delivered with a tracking-number field and admin buttons sending emails 04–06 (question 16). "Dorset Studio" for every "Bournemouth" in the design's templates. Email images are hosted on the site (`public/images/email/`).

### Step 12: Launch checks
Live server upload limits matched to commissions (as `docker/php/zz-uploads.ini`: 50MB a file, 110MB a request) and the commission files folder (`var/commission-uploads/`) kept between deploys and backed up; info@lluniostudios.co.uk set up (the domain has no mail servers yet: Cloudflare Email Routing forwarding to Gmail, or Google Workspace) and `STUDIO_EMAIL` in the live settings switched back from lluniostudios@gmail.com to info@lluniostudios.co.uk (it is the one setting for the reply-to on customer emails, the email footers, the Contact page and the commission rate-limit message); Apple Pay and Google Pay tested by hand on the live site (Apple Pay in Safari on your iPhone, once the domain is registered with Stripe; neither can be tried locally, as Chrome marks https://localhost "Not secure" so Google Pay doesn't show, see "Testing Apple Pay and Google Pay"); T&Cs reviewed (including the deposit and cancellation terms for quoted orders); VAT revisited if registered; British English copy pass; photo audit (replace with the finely edited photos, see `CLAUDE.md`); real packed-box sizes and weights; Apple Pay domain registered in Stripe; Stripe live keys; update `CLAUDE.md` (its Authentication section describes passwords, but sign-in uses emailed codes).

### Later, when the DPD account exists
Enter the DPD API credentials, confirm the character limits and pricing, check `DpdProvider` against the real API, switch `SHIPPING_MODE=live`.

---

## Design rules that carry over

- Real product photography only; "Image Coming Soon" for anything missing; never the prototype's `scraps/` folder or the workshop stand-ins (`IMG_0745–0751`, marked temporary by the design).
- Routing: Studio or Gallery, no Custom choice, at most 2 lamps in the whole basket → "Cast Your Lamp" → basket → checkout. Everything else → "Reserve Your Design" → request, with no payment taken (stated clearly).
- Lead time 7–14 days is one setting (`LeadTime`, Twig `lead_time`).
- Gift option (Step 10): arrive-by date, earliest today + 14 calendar days, then 2 working days for DPD; otherwise the same details as any order.
- Trading-name line in the footer and emails.

---

## Questions still open

**Checkout and delivery**
None open (questions 1, 2, 6 and 7 answered 7 October 2026).

**Product and configurator**
8. **Atelier weight** (for delivery estimates on requests).
21. **Photos:** which real photos exist for Atelier, Estate, Monument, pigments, footings, bulbs and the workshop? (All or most current photos will be replaced with finely edited versions; the two cable photos are parked.)
25. **Palletways:** does a Palletways account exist? (Live pallet prices in Step 10b.)
26. **Packed sizes and weights** for Atelier, Estate and Monument (delivery prices in Step 10b).

**Commissions and emails**
16. **Order stages for emails 04–06:** marked by hand in admin, or Dispatched/Delivered from DPD tracking once live?
17. **Cancellations:** online before production, or by email only?
18. **Care instructions** for email 06, and a studio phone number for the email footers.

**Content and pages**
20. The newsletter sign-up: keep, move or remove? (Lumen Forma being the only product line at launch is answered.)
23. **Origin story and T&Cs:** final as written? Checked for consumer-law wording? The T&Cs must also state the **deposit and cancellation terms** for quoted orders (added 8 October 2026).
24. **Go-live:** a launch date or gift-season deadline that should change the order of steps?

Answered (for reference): Express Payment layout (1), delivery area (2), accounts (6), "View your order" (7), gift date, order confirmation page; commission uploads (15); Lumen Forma is the only product line at launch (part of 20); remove the old shop code; studio address; "Dorset Studio"; collect from studio (off, kept in code); 3rd-lamp behaviour; upgrade; who builds; Stripe; DPD mock then live per consignment; sizes and weights (placeholders); 2-lamp basket limit; "The Pigment"; quantity rule (1–10 per configuration); bulb count; prices; option prices (none); homepage (Test 2); notifications (lluniostudios@gmail.com); one Reserve page; `/products` and `/collections` redirect; request follow-up by quotes (Step 10b); email 02 wording.
