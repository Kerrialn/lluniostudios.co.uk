# CLAUDE.md - Llunio Studios

High-end lamp e-commerce platform built with Symfony 7.3.

## Tech Stack

- **PHP 8.2+** / Symfony 7.3
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
│   ├── Controller/      # Frontend (ProductController, CartController)
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
- CartItemForm dynamically builds form fields from product's options

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

## For my brother

- **`Product::$price` type mismatch:** the column is an `integer` (pence) but the property, getter and setter are typed `?string`, so `doctrine:schema:validate` reports the mapping as invalid. Left as it is during the design merge because changing it ripples into the basket code (e.g. `CartItem::setUnitPrice($product->getPrice())`). Fix by typing it `?int` throughout and updating the callers.
