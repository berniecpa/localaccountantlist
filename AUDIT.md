# LocalLimoGuide.com — Launch Audit (2026-08-30)

Site: ListingPro theme + listingpro-plugin, Elementor, CubeWP, SEO Repair Kit on WordPress 7.1.
Everything below was verified against the live site.

## 🔴 Blockers (site can't take paying customers until these are done)

1. **ListingPro license not activated.** The Submit Your Listing page renders only
   "Please activate your license" — listing submission, claims, and pricing plans are
   disabled until the ThemeForest purchase code is entered.
   → wp-admin → ListingPro (Theme Options) → license activation, paste your Envato purchase code.
2. **Plain permalinks.** All URLs are `?p=123` / `?listing=slug` style — bad for SEO and
   ugly on shared links. Fixed automatically by `setup/apply.sh` (sets `/%postname%/`).

## 🟠 High priority (the "it's still the demo" problems)

3. **All 27 listings are demo businesses** (Sushi Kashiba, The Mark Hotel, Subway, museums…)
   across 11 generic categories (Restaurant, Beauty & Spa, Real Estate…) and 12 demo cities
   (Seattle, Denver, New York…). `setup/limo-seed.php` drafts the demo listings, adds limo
   categories (Stretch Limo, Party Bus, SUV Limo, Executive Sedan, Sprinter Van, Classic Car),
   limo features (Wet bar, WiFi, Red carpet…), your service cities, and 3 sample limo listings.
4. **Homepage is stock ListingPro copy** — "Explore Your City", "eat, drink, and shop",
   restaurant hero collage, "Happening Cities" with the Golden Gate Bridge. The seeder
   rewrites the text (with a backup of the original); the hero/city **images** must be swapped
   in Elementor (or send me images and I'll wire them once the theme code is in the repo).
5. **ListingPro branding everywhere**: header logo is the ListingPro logo, footer says
   "Copyright © 2023 ListingProWP / Developed by Cridio Studio". Logo + footer text live in
   ListingPro Theme Options → General/Footer. (Send me a logo, or I can generate one.)

## 🟡 Medium priority

6. **Horizontal page overflow** — the homepage scrolls sideways (content column ~2035px wide
   against a 1440px viewport), typical after a demo import.
   → Elementor → Tools → Regenerate CSS & Data (apply.sh also clears the Elementor cache).
7. **No favicon** (`<link rel="shortcut icon" href="">` is empty). Theme Options or
   Customizer → Site Identity.
8. **Demo blog posts** ("Hello world!", "Excited news about arrival fashion.",
   "Reduce Unwanted Wrinkles") — draft or replace with limo content.
9. **No XML sitemap** (`/sitemap.xml` returns the homepage). Enable in your SEO plugin, or
   WordPress core's `/wp-sitemap.xml` works once permalinks are pretty.
10. **Search placeholder** says "Ex: food, service, barber, hotel" — change to
    "Ex: stretch limo, party bus, airport transfer" in Theme Options → Header/Search.

## 💰 Monetization (Phase D — after the license is activated)

ListingPro has this built in; no custom code needed:
1. wp-admin → **Pricing Plans → Add New**: name "Featured", price **99**, billing **Monthly**,
   enable **Featured/Ad** flag (listing shows the Ad badge + ranks first, like the demo's
   "Sauce & Barrel" card). Optionally add a Free basic plan so companies can list free and upgrade.
2. **Theme Options → Payments**: enable **Stripe**, paste your Stripe publishable + secret keys
   (test keys first), set currency USD.
3. **Theme Options → Submission**: require a plan on submit, enable claims ("Claim your business")
   so you can pre-load limo companies and let owners claim + upgrade them.
4. Test: submit a listing, pick Featured, pay with card 4242 4242 4242 4242.

## What runs automatically

`bash setup/apply.sh` on the VPS does: DB backup → install listingpro-child theme →
run limo-seed.php (categories/features/cities/sample listings/homepage copy/tagline,
demo listings drafted) → pretty permalinks → clear Elementor + rewrite caches.
Everything it changes is reversible: DB backup file + `_llh_backup` copies of edited content.
