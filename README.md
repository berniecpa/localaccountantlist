# Local Accountant List

Setup and customization tooling for [localaccountantlist.com](https://localaccountantlist.com) — a directory of CPAs, tax preparers, bookkeepers, and accounting firms, built on WordPress with the ListingPro theme.

## What's in this repo

| Path | Purpose |
| --- | --- |
| `setup/apply.sh` | One-command deployment: backs up the database, installs the child theme, runs the seeder, flushes caches |
| `setup/seed.php` | Idempotent converter: accounting categories/features/cities, drafts the ListingPro demo listings, adds sample firm listings, rewrites the homepage copy (with backups), sets tagline and pretty permalinks |
| `wp-content/themes/listingpro-child/` | Child theme for site-specific CSS/PHP so ListingPro updates never wipe customizations |
| `AUDIT.md` | Launch checklist: what's automated, what's manual (logo, images, pricing plans, Stripe) |

## Deploy to the live site

SSH into the VPS and run:

```bash
curl -sL https://raw.githubusercontent.com/berniecpa/localaccountantlist/main/setup/apply.sh | bash -s -- --path /home/SITEUSER/htdocs/localaccountantlist.com
```

Safe to re-run. Every run takes a database backup first; revert anytime with `wp db import <backup file>` (the path is printed at the end of each run).

See `AUDIT.md` for the full launch checklist and the monetization setup (free + featured pricing plans, Stripe).
