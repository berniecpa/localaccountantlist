# LocalAccountantList.com — Launch Audit (2026-09-09)

Site: ListingPro theme + listingpro-plugin, Elementor, CubeWP on WordPress.
The ListingPro license is registered to this domain — unlike the old limo domain,
the "Select Your Plan" page renders correctly here (no license warning), so
submissions and paid plans are available. Verified against the live site.

## 🔴 High priority (the "it's still the demo" problems)

1. **Plain permalinks.** URLs are `?cat=1` / `?listing-category=restaurant` style —
   bad for SEO. Fixed automatically by `setup/apply.sh` (sets `/%postname%/`).
2. **All 33 listings are demo businesses** (restaurants, barbershops, museums…)
   across generic categories (Restaurant, Beauty & Spa, Hotels…) and demo cities.
   `setup/seed.php` drafts the demo listings and creates the accounting structure:
   - Categories: Tax Preparation, Bookkeeping, CPA Firm, Payroll Services,
     Audit & Assurance, Business Advisory, Forensic Accounting
   - Features: IRS Representation, QuickBooks ProAdvisor, Free Consultation,
     Virtual Appointments, Small Business Specialist, Individual Tax Returns,
     Spanish Speaking, Year-Round Service
   - Cities: New York, LA, Chicago, Houston, Miami, Dallas, Atlanta, Phoenix
     (edit the list at the top of seed.php before running)
   - 3 sample firm listings (Summit Tax & Accounting, Ledger & Main Bookkeeping,
     Hartwell CPA Group)
3. **Homepage is stock ListingPro copy** — "Explore Your City", "eat, drink, and
   shop", restaurant hero collage. The seeder rewrites the text to accounting copy
   (original backed up in post meta). The hero/city **images** still need swapping
   in Elementor — professional/office imagery instead of restaurants.
4. **ListingPro branding**: header logo + "Copyright © ListingProWP" footer.
   Change in ListingPro Theme Options → General/Footer. (Send me a logo, or I can
   generate one for Local Accountant List.)

## 🟡 Medium priority

5. **Page-width overflow** on Elementor pages after demo import →
   Elementor → Tools → Regenerate CSS & Data (apply.sh also clears Elementor cache).
6. **Favicon** missing → Customizer → Site Identity.
7. **Demo blog posts** — replace with content that earns accountant-directory
   traffic: filing deadline calendars, "CPA vs EA vs bookkeeper", state tax guides.
8. **XML sitemap** — enable in the SEO plugin once permalinks are pretty.
9. **Search placeholder** ("Ex: food, service, barber, hotel") — the seeder swaps
   the homepage instance; check Theme Options → Header for other spots.

## 💰 Monetization — Featured firm listings (built into ListingPro)

1. wp-admin → **Pricing Plans → Add New**: e.g. "Featured Firm", **$99/month**
   (or your price), enable the **Featured/Ad** flag so the listing gets the badge
   and ranks first. Add a **Free** basic plan so firms can list free and upgrade.
2. **Theme Options → Payments**: enable **Stripe**, paste publishable + secret keys
   (test mode first), currency USD.
3. **Theme Options → Submission**: require a plan on submit; enable **claims** so
   you can pre-load real firms and let owners claim + upgrade their listing.
   (Pre-loading real local firms is the classic directory cold-start move —
   I can help build that list per city when you're ready.)
4. Test with Stripe test card 4242 4242 4242 4242, then switch to live keys.

## What runs automatically

On the VPS:

```bash
curl -sL https://raw.githubusercontent.com/berniecpa/localaccountantlist/claude/wordpress-customization-x8wyf5/setup/apply.sh | bash
```

DB backup → install listingpro-child theme (inactive unless `--activate-child`) →
run seed.php (accounting categories/features/cities, demo listings drafted,
3 sample firms, homepage copy rewrite with backup, tagline, pretty permalinks) →
flush caches. Revert anytime: `wp db import <backup file printed at the end>`.
