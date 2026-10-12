# CLAUDE.md - Llunio Studios

High-end lamp e-commerce platform built with Symfony 7.4.

## Design merge (in progress): read `docs/merge-plan.md` first

The site is being reshaped to the Claude Design prototype "Llunio Site v4 Unified". `docs/merge-plan.md` has everything needed to continue: where the prototype is, the working rules (small steps, commit only when asked, match the prototype, real photos only, British English, "Dorset Studio"), every decision, each step's status and commits, the open questions, and the local setup (database commands run in the PHP container, Mailpit at http://localhost:52374, admin sign-in by emailed code). Work happens on branch `design-foundation`; never push to `main` or merge unless asked.

## Tech Stack

- **PHP 8.2+** / Symfony 7.4
- **PostgreSQL 16** with PostGIS
- **Doctrine ORM 3.3** (attribute-based mapping)
- **EasyAdmin 4** for admin panel
- **Stimulus.js** + Swup for frontend
- **Docker** (PHP, Caddy, PostgreSQL)
- **Vich Uploader** for image handling

## Development Commands

```bash
# Start containers
docker compose up -d

# Run tests
composer test

# Code style
composer check-cs    # Check
composer fix-cs      # Fix

# Static analysis
composer phpstan

# Database migrations
php bin/console doctrine:migrations:migrate
```

## Project Structure

```
src/
├── Entity/              # Doctrine entities (UUID-based)
├── Repository/          # Query repositories
├── Controller/
│   ├── Controller/      # Frontend (ConfiguratorController, CartController, ReserveController)
│   └── Admin/           # EasyAdmin CRUD controllers
├── Security/            # Authenticators (Email, Fingerprint)
├── Service/             # Business logic (CartHelper, FingerPrintService)
└── Form/                # Symfony forms
```

## Product EAV Pattern

Products use **Entity-Attribute-Value** for flexible customization without schema changes:

```
Product (Entity)
  └── ProductOption (Attribute) - e.g., "Size", "Color", "Finish"
        └── ProductOptionValue (Value) - e.g., "Small", "Brass", "Matte"
```

- Options are per-product (not global) - each lamp can have different configurable attributes
- CartItemOption stores the selected option values when added to cart
- Lamps are configured in the Lumen Forma configurator (`/lighting-designs`); the old product pages and `CartItemForm` are retired (`/products` and `/collections` redirect there)

## Key Entities

- **User** (email-keyed account; `password` nullable until the customer sets one)
- **Product** -> **ProductOption** -> **ProductOptionValue** (EAV)
- **Cart** -> **CartItem** -> **CartItemOption** (stores selected EAV values)
- **Order** -> **OrderItem** (snapshotted at purchase); Order belongs to a User
- **Image** (Vich uploaded, linked to Product)

## Conventions

- **Prices**: Stored in pence (integer), use `getPriceInGbp()` for display
- **IDs**: All entities use UUID with custom generator
- **Dates**: CarbonImmutable for all timestamps
- **Routing**: Attribute-based, products use slug routing
- **Cart dedup**: SHA256 hash of product+options prevents duplicates

## Authentication

Note: this section is out of date. Sign-in uses emailed 6-digit codes (`LoginCode`, `LoginCodeService`), not passwords; it is to be rewritten in merge Step 12 (accounts and guest checkout are open questions 6 and 7 in `docs/merge-plan.md`).

Simple email/password (no fingerprinting or guest Identity).

- Anonymous visitors browse and hold a **session-based cart** (`CartResolver`,
  cart id in the session).
- At checkout an account is created from the entered **email** (passwordless).
  If that email already has a password, the visitor is asked to log in first.
  On success the session cart is attached to the user (`CartResolver::attachToUser`)
  and they're logged in programmatically (`Security::login`).
- The `/account` area (order history, set password) is guarded by `ROLE_USER`.
  Customers set a password after checkout to sign back in.
- `/admin` requires `ROLE_ADMIN`; grant it with `php bin/console app:user:promote <email>`.

## Admin Panel

Access at `/admin` - Full CRUD for Products, Options, Users, Cart management.

## File Uploads

Images upload to `/public/uploads/images` via Vich Uploader with SmartUniqueNamer.

## Pending content

- **Mobile homepage photos:** a portrait pair (one dim, one bright) will be taken specifically for the homepage on upright phones. Phones will use them; desktop, tablet and sideways phones keep the current landscape pair (`public/images/home/hero-dim-*.jpg`, `hero-bright-*.jpg`). The template is already set up for them: see the "PENDING CONTENT" note in `templates/app/landing.html.twig` (add the files, set `portraitPhotos` to true) and fill in the photo's measurements in `assets/styles/app.css` under "Portrait photos for upright phones".
- **Known phone limits until then:** with the landscape pair, upright phones with a tall visible screen (no browser bars, e.g. 390×844) get a small homepage heading (about 10.5–12.5px), and screens shorter than about 609px (e.g. 320×568) scroll slightly. Both are accepted for now and will be fixed by the portrait photos.
- **Bespoke Commissions hero photos (`/bespoke-commissions`):** the prototype's eight, cross-fading behind the title, to be replaced by the edited versions. Listed in `templates/commissions/index.html.twig` (`heroPhotos`, each with its crop) and stored in `public/images/commissions/` as `hero-NN-2000.jpg` and `hero-NN-1000.jpg` (longest edge, all metadata including GPS removed): hero-01 APC_0055, hero-02 APC_0116, hero-03 APC_0112, hero-04 APC_0058-1, hero-05 APC_0117, hero-06 APC_0057, hero-07 APC_0054, hero-08 APC_0109. All are dark behind the title, so it stays cream; if a replacement is light there, the title needs the dark version (as the configurator's title-colour settings do).
- **Lighting Designs introduction photos (the seven-stage story, Step 7b):** the design's dim/lit pair, the lit one fading in over the dim one as the visitor scrolls: `public/images/lumen-forma/story-dim-2000.jpg` / `-1000.jpg` (APC_0263-3f798540, only the bulb lit) and `story-lit-2000.jpg` / `-1000.jpg` (APC_0267-84a5529c), converted to sRGB, resized (longest edge) and re-encoded with all metadata (including GPS) removed. A new pair will be shot. The two must stay exactly aligned, and the framing (lamp left of centre) is set in `assets/styles/app.css` (`.ll-story-frame`). The text stays amber because, with this pair, the background behind it never gets light; **revisit the amber/near-black colour switch when the new pair is shot** (decided 12 October 2026).
- **Lumen Forma photos (configurator, `/lighting-designs`):** all or most of the current photos will be replaced with more finely edited versions. Real photos only; anything without one shows "Image Coming Soon" with its name, and one size's photos are never shown for another.
  - **How to add or replace:** photos are listed in `src/Catalogue/LumenFormaPhotos.php` (`BY_SIZE` per size, `BY_OPTION` per option value) and stored in `public/images/lumen-forma/` as `<name>-2000.jpg` and `<name>-1000.jpg` (longest edge), re-encoded so all metadata (including GPS) is stripped. Each photo has its own desktop crop, phone framing (upright and sideways) and title-colour settings; recheck these when a photo is replaced, since a new edit may frame the lamp differently.
  - **Sizes:**
    - Gallery: 17 photos (`gallery-01` … `gallery-17`), in the prototype's brightest-to-darkest order: Re-Size APC_0182, APC_0145-1, Re-size APC_0170, APC_0041, APC_0069-1, APC_0172, APC_0050, Re-size APC_0199, Re-sized APC_0186, APC_0198, APC_0117, APC_0116, APC_0112, APC_0054, APC_0191, APC_0057, APC_0259. They are also the photos that cross-fade behind the title before the options open.
    - Studio, Atelier, Estate, Monument: no photos yet ("Image Coming Soon").
  - **Options** (one photo per value, shown on that option's stage): none in use yet, so every value shows "Image Coming Soon":
    - The Pigment: White, Grey, Black, Custom.
    - Cable Colour: Black Braided Cotton, Slate Grey, Raw Jute, Orange, Forest Green, Natural Linen, Black & White, Custom Textile / Length. **Parked:** real photos exist for Black & White and Orange (`uploads/Black & White-e27c1f7f.jpg` and `uploads/Orange.jpg` in the design zip) but are held back until the edited versions arrive.
    - Footing Material: Brushed Copper, Brushed Greened Copper, Polished Copper, Brushed Aluminium, Brushed Blackened Aluminium, Polished Aluminium, Brushed Stainless Steel, Polished Stainless Steel, Custom Metal Finish.
    - Bulb Style: Soft-Filament Amber Globe, XXL Spiral Filament, Tubular Amber LED, Smoked Glass LED, Crown Gold LED, Clear Vintage Edison, Custom Specification.
    - Bulb Count (Atelier, Estate and Monument only): Single Array, Dual Array, Triple Array.

## For my brother

- **Rector suggestions:** the GitHub "rector" check is advisory (it reports without failing). On 10 October 2026 it suggested changes to 54 files (code tidying such as removing unneeded casts). Whether to apply them is your call (`composer rector` applies, `composer rector-dry` shows them).

- **`Product::$price` type mismatch:** the column is an `integer` (pence) but the property, getter and setter are typed `?string`, so `doctrine:schema:validate` reports the mapping as invalid. Left as it is during the design merge because changing it ripples into the basket code (e.g. `CartItem::setUnitPrice($product->getPrice())`). Fix by typing it `?int` throughout and updating the callers.
