# Lucent Optics

An online optician's shop: browse and configure eyeglasses, buy contact lenses,
keep prescriptions on file, and — with the customer's camera, entirely in their
browser — get frame recommendations matched to their face shape.

Built on Laravel 12, Blade and Tailwind CSS v4, with MediaPipe Tasks Vision for
the face scanner. No JavaScript framework, no external API calls at runtime.

---

## What's in it

**Storefront**

- Frame catalogue with filtering, multiple photos per frame, and stock tracking.
- Eyeglass configurator: frame + lens package + composable add-ons (anti-blue
  light, UV, photochromic, anti-reflective, scratch-resistant, polarised). The
  line price is frame + lens + the selected features, in `PricingService`.
- Contact lens catalogue with its own cart lines.
- Guest cart that merges into the account on sign-in.
- Cash-on-delivery checkout with flat-rate shipping, order history, and a status
  timeline the shop owner drives (`pending → processing → shipped → delivered`,
  plus `paid`, `cancelled` and `refunded`).
- Prescriptions on file: the numbers used to grind lenses plus an optional
  scan/PDF of the paper original, held privately and verified by staff.
- Returns and exchanges, and reviews that go live only after moderation.

**AI Face Match** (`/face-match`)

MediaPipe's face landmarker runs in the browser; only a handful of derived
measurements are POSTed. `FaceShapeClassifier` maps them to one of six shapes
(oval, round, square, heart, diamond, oblong) and ranks frames by optical fit.
The WASM runtime and the model weights are served from this origin, not a CDN,
so no third party ever learns that a customer used the scanner. Customers who
would rather not use the camera can pick their shape from a list instead.

**Admin** (`/admin`)

Inventory (frames, lenses, lens features, contact lenses), order and return
processing, prescription verification, review moderation, promotion campaigns
with queued email, and a dashboard.

---

## Running it locally

Requires PHP 8.2+, Composer, Node 20+, and MySQL 8 / MariaDB 10.6+ (PostgreSQL 14+ also works — see [DEPLOYMENT.md](DEPLOYMENT.md) §2.5).

```bash
git clone https://github.com/10120031-del/optic
cp .env.example .env
```

Create the schema and point `.env` at it:

```sql
CREATE DATABASE lucent_optics CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then:

```bash
composer setup
```

That runs `composer install`, generates `APP_KEY`, migrates, installs npm
packages (which also downloads the ~3.7 MB face-landmarker model into
`public/mediapipe`) and builds the assets.

Seed a development catalogue and two demo accounts:

```bash
php artisan db:seed
```

`test@example.com` (customer) and `admin@example.com` (staff), both with the
password `password`. **Development only** — see the deployment notes below.

Start everything — server, queue worker, log tail and Vite — in one terminal:

```bash
composer dev
```

The shop is then on http://127.0.0.1:8000.

---

## Tests

```bash
composer test
```

51 tests covering the customer/staff boundary, the cash-on-delivery checkout,
owner-driven order status and payment settlement, the face-shape classifier and
its recommendations, frame image management, access control on prescription
scans, and the error pages. They run against an in-memory SQLite database, so
no setup is needed.

---

## Deploying

**[DEPLOYMENT.md](DEPLOYMENT.md)** is the full guide: server requirements,
first deploy, nginx/Apache config, the queue worker, permissions, backups,
rollback and a go-live checklist.

Routine deploys, once that setup exists:

```bash
./deploy.sh --pull
```

Three things are worth knowing before the first one:

- **`public/build` and `public/mediapipe` are not in git.** Run
  `npm ci && npm run build` on the server, or ship both directories from CI.
  Skip it and the site loads unstyled with a scanner that never starts.
- **A queue worker is required** for promotion campaign email, which is queued
  rather than sent inline.
- **Run `php artisan catalog:embed` after migrating.** It builds the vectors the
  recommender ranks on. Skip it and the storefront still works — the recommender
  falls back to attribute matching — but "you may also like" gets noticeably
  worse. Needs Node, like the asset build.
- **Create staff with `php artisan app:create-admin`.** Storefront registration
  only ever produces customers, and `DatabaseSeeder`'s demo admin must not go
  anywhere near a live database — use `--class=ProductionSeeder`, which seeds
  only face shapes and lens features.

---

## Payments: cash on delivery

The shop takes **cash on delivery only**. Checkout asks for a shipping address
and nothing else — no card details are ever requested, and no money moves
online, so there is no gateway, no PCI surface and no webhook to secure.

Placing an order writes a `payments` row for the full total with status
`pending`. That balance is settled by the shop owner, not by a callback:

| Owner sets the order to | The payment becomes |
|---|---|
| `paid` or `delivered` | `completed`, stamped with the time, and the order's `paid_at` is set |
| `cancelled` | `failed` — nothing was ever collected |
| `refunded` | `refunded` |

Everything else is manual staff work in `/admin/orders/{order}`: pick the next
status, add a carrier, tracking number, estimated delivery date and an optional
note. Each change appends to `order_status_history` with the staff member who
made it, which is what the customer's order page renders as a timeline.

The `payments.method` column still carries the wider enum (`card`, `paypal`,
`bank_transfer`) from the original schema, so adding an online gateway later is
a code change rather than a migration. Nothing writes those values today.

---

## Layout

```
app/
  Http/Controllers/        Storefront controllers; Admin/ mirrors them for staff
  Http/Middleware/         EnsureUserIsAdmin / EnsureUserIsCustomer
  Services/                CartService, PricingService, FaceShapeClassifier
  Console/Commands/        app:create-admin
resources/
  views/                   Blade; components/layout.blade.php is the storefront shell
  views/errors/            Branded, dependency-free error pages
  js/face-scan.js          MediaPipe entry, loaded only on /face-match
database/
  migrations/              Schema
  seeders/                 DatabaseSeeder (dev) · ProductionSeeder (reference data only)
scripts/
  copy-mediapipe-assets.mjs  Stages WASM + model into public/mediapipe on npm install
  embed-products.mjs         Runs the sentence transformer for `php artisan catalog:embed`
deploy.sh                  In-place deploy for a single server
```
