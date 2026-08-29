# LimoListHone

A WordPress limo service directory: a custom theme plus a companion plugin that together provide searchable listings, company self-submission, quote requests, star reviews, and paid "boost" placement powered by Stripe subscriptions.

## What's in this repo

```
wp-content/
  plugins/limolisthone-core/   Directory engine (data + business logic)
  themes/limolisthone/         Presentation (black/gold responsive theme)
```

**Plugin (`limolisthone-core`)**

- `limo_listing` post type with **Service Area**, **Vehicle Type**, and **Amenities** taxonomies, plus contact/fleet/pricing meta fields and a photo gallery
- Search + filters on the directory archive (keyword, area, vehicle type, minimum capacity) with **boosted listings always sorted first**
- `[limo_submit_listing]` shortcode — front-end submission form that creates a *pending* listing for moderation (admin gets an email)
- Quote request form on every listing — emails the company and stores each lead under **Listings → Quote Requests**
- Star reviews built on native comments (1–5 rating required, average cached per listing)
- **Boost subscriptions via Stripe Checkout** — a recurring subscription flags the listing as boosted; webhooks + a daily cron keep it in sync. Admins can also boost manually from the listing edit screen.

**Theme (`limolisthone`)**

- Front page: hero search bar, featured (boosted) listings, browse-by-vehicle tiles, get-listed CTA
- Directory archive with a filter sidebar; listing detail pages with gallery, company facts, quote form, reviews, and the boost button
- No build step — plain CSS and vanilla JS

## Installation

1. Copy (or symlink/deploy) the two directories into your WordPress install:
   - `wp-content/plugins/limolisthone-core` → `wp-content/plugins/`
   - `wp-content/themes/limolisthone` → `wp-content/themes/`
2. In wp-admin: **Plugins → activate "LimoListHone Core"**, then **Appearance → Themes → activate "LimoListHone"**.
3. Go to **Settings → Permalinks** and click *Save Changes* once (flushes rewrite rules so `/limos/`, `/limo/...`, `/limo-area/...` resolve).
4. Create taxonomy terms under **Listings → Service Areas / Vehicle Types** (e.g. cities you cover; Stretch Limo, Party Bus, SUV, Sedan…). The submission form and filters are driven by these terms.
5. Create the submission page: **Pages → Add New**, title it **Get Listed** (slug `get-listed` — the theme links to this slug), and put `[limo_submit_listing]` in the content.
6. (Optional) Set a static front page: **Settings → Reading → A static page** — any page will do; the theme's `front-page.php` renders the directory home regardless.

## Stripe boost setup

Boosted listings appear first in every directory view and carry a gold **Featured** badge. Companies buy boosts themselves via the *Boost this listing* button on their listing page.

1. In Stripe, create a **Product** (e.g. "Boosted Listing") with a **recurring monthly Price**. Copy the `price_…` ID.
2. In wp-admin, open **Listings → Boost Settings** and enter:
   - **Stripe secret key** (`sk_live_…` or `sk_test_…`)
   - **Boost price ID** (`price_…`)
   - **Webhook signing secret** (next step)
3. In Stripe **Developers → Webhooks**, add an endpoint pointing at:
   ```
   https://YOUR-SITE.com/wp-json/limolisthone/v1/stripe-webhook
   ```
   subscribed to: `checkout.session.completed`, `invoice.paid`, `customer.subscription.deleted`. Copy its `whsec_…` signing secret into the settings page.
4. Test locally with the Stripe CLI:
   ```
   stripe listen --forward-to https://YOUR-SITE.com/wp-json/limolisthone/v1/stripe-webhook
   stripe trigger checkout.session.completed
   ```

Each paid invoice extends the boost ~35 days; if a subscription is cancelled or payments stop, the webhook (or the daily cron safety net) clears the boost automatically. Admins can grant/revoke boosts manually in the **Boosted Placement** box on any listing's edit screen.

## Moderation workflow

- Front-end submissions arrive as **Pending** listings; the admin email links straight to the edit screen. Publish to approve.
- Reviews follow your normal comment moderation settings (**Settings → Discussion**). A star rating is required on listing reviews.
- Quote requests are emailed to the listing's contact email (falling back to the admin email) and archived under **Listings → Quote Requests**.
