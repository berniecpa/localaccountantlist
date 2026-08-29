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

## Quick start on a CloudPanel VPS (recommended)

1. In CloudPanel, **Add Site → Create a WordPress Site** for your domain and finish the WordPress installer (site title + admin account).
2. SSH into the VPS and run the one-command installer:

   ```bash
   curl -sL https://raw.githubusercontent.com/berniecpa/limolisthone/main/deploy/install.sh | bash -s -- --path /home/SITEUSER/htdocs/YOURDOMAIN.com
   ```

   (Omit `--path` if there's only one WordPress site on the server — it auto-detects. If the repo is private, clone it on the server and run `bash deploy/install.sh` from the clone instead.)

That's it. The installer activates the plugin and theme, sets pretty permalinks, and the plugin's activation setup creates the **Get Listed** submission page and starter vehicle types automatically. Then connect Stripe (next section).

## Manual installation (any host)

1. Copy the two directories into your WordPress install:
   - `wp-content/plugins/limolisthone-core` → `wp-content/plugins/`
   - `wp-content/themes/limolisthone` → `wp-content/themes/`
2. In wp-admin: **Plugins → activate "LimoListHone Core"**, then **Appearance → Themes → activate "LimoListHone"**.
3. Go to **Settings → Permalinks** and click *Save Changes* once (flushes rewrite rules so `/limos/`, `/limo/...`, `/limo-area/...` resolve).

Activation auto-creates the **Get Listed** page (slug `get-listed`, containing `[limo_submit_listing]`) and seeds default vehicle types. Add your **Service Areas** under **Listings → Service Areas** (the cities/regions you cover) — the submission form and filters are driven by these terms.

## Stripe boost setup

Boosted listings appear first in every directory view and carry a gold **Featured** badge. Companies buy boosts themselves via the *Boost this listing* button on their listing page.

1. In Stripe, create a **Product** (e.g. "Boosted Listing") with a **recurring monthly Price**, and copy the `price_…` ID. (Skip if one was already created for you — check Products in the Stripe dashboard.)
2. In wp-admin, open **Listings → Boost Settings**, paste your **Stripe secret key** (`sk_test_…` first; swap to `sk_live_…` when going live) and the **Boost price ID**, and Save.
3. Click **"Create webhook automatically"** on the same page — the site registers its own webhook endpoint with Stripe and stores the signing secret. (Manual alternative: in Stripe **Developers → Webhooks**, add `https://YOUR-SITE.com/wp-json/limolisthone/v1/stripe-webhook` with events `checkout.session.completed`, `invoice.paid`, `customer.subscription.deleted`, and paste the `whsec_…` into the settings page.)
4. Test the flow with Stripe's test card `4242 4242 4242 4242`, or locally with the Stripe CLI:
   ```
   stripe listen --forward-to https://YOUR-SITE.com/wp-json/limolisthone/v1/stripe-webhook
   stripe trigger checkout.session.completed
   ```

Each paid invoice extends the boost ~35 days; if a subscription is cancelled or payments stop, the webhook (or the daily cron safety net) clears the boost automatically. Admins can grant/revoke boosts manually in the **Boosted Placement** box on any listing's edit screen.

## Moderation workflow

- Front-end submissions arrive as **Pending** listings; the admin email links straight to the edit screen. Publish to approve.
- Reviews follow your normal comment moderation settings (**Settings → Discussion**). A star rating is required on listing reviews.
- Quote requests are emailed to the listing's contact email (falling back to the admin email) and archived under **Listings → Quote Requests**.
