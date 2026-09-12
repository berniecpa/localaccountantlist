#!/usr/bin/env bash
#
# Local Accountant List — apply the accounting-directory conversion to the live site.
#
# Run on the VPS (SSH), either of:
#   curl -sL https://raw.githubusercontent.com/berniecpa/localaccountantlist/claude/wordpress-customization-x8wyf5/setup/apply.sh | bash -s -- --path /home/SITEUSER/htdocs/localaccountantlist.com
#   git clone ... && bash setup/apply.sh --path /home/SITEUSER/htdocs/localaccountantlist.com
#
# What it does, in order:
#   1. Takes a full database backup (wp db export) next to wp-config.php
#   2. Installs the listingpro-child theme (does NOT activate it unless --activate-child)
#   3. Runs setup/seed.php: accounting categories/features/cities, demo listings
#      drafted, 3 sample listings, homepage copy rewrite (with backups),
#      tagline, pretty permalinks
#   4. Flushes rewrite + Elementor caches
#
# Safe to re-run. Revert path: wp db import <backup file>.

set -euo pipefail

REPO="berniecpa/localaccountantlist"
BRANCH="claude/wordpress-customization-x8wyf5"
WP_PATH=""
ACTIVATE_CHILD=0

say()  { printf '\033[1;33m==>\033[0m %s\n' "$*"; }
fail() { printf '\033[1;31mERROR:\033[0m %s\n' "$*" >&2; exit 1; }

while [ $# -gt 0 ]; do
	case "$1" in
		--path)   WP_PATH="${2:-}"; shift 2 ;;
		--branch) BRANCH="${2:-}"; shift 2 ;;
		--activate-child) ACTIVATE_CHILD=1; shift ;;
		*) fail "Unknown option: $1" ;;
	esac
done

command -v php >/dev/null 2>&1 || fail "php not found on this server."

if [ -z "$WP_PATH" ]; then
	mapfile -t candidates < <(ls -d /home/*/htdocs/*/wp-config.php 2>/dev/null | xargs -r -n1 dirname)
	if [ "${#candidates[@]}" -eq 1 ]; then
		WP_PATH="${candidates[0]}"
	else
		fail "Pass --path /home/SITEUSER/htdocs/DOMAIN (found ${#candidates[@]} candidates)."
	fi
fi
[ -f "$WP_PATH/wp-config.php" ] || fail "$WP_PATH does not look like a WordPress root."
say "WordPress root: $WP_PATH"

SITE_OWNER="$(stat -c '%U' "$WP_PATH")"
run_as() {
	if [ "$(id -un)" = "root" ] && [ "$SITE_OWNER" != "root" ]; then
		sudo -u "$SITE_OWNER" -- "$@"
	else
		"$@"
	fi
}

if command -v wp >/dev/null 2>&1; then
	WP_BIN=(wp)
else
	WPCLI="/tmp/llh-wp-cli.phar"
	[ -f "$WPCLI" ] || { say "Downloading wp-cli ..."; curl -sSL -o "$WPCLI" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar; chmod +r "$WPCLI"; }
	WP_BIN=(php "$WPCLI")
fi
WP_FLAGS=( "--path=$WP_PATH" )
[ "$(id -un)" = "root" ] && [ "$SITE_OWNER" = "root" ] && WP_FLAGS+=( "--allow-root" )
wp() { run_as "${WP_BIN[@]}" "${WP_FLAGS[@]}" "$@"; }

wp core is-installed || fail "WordPress at $WP_PATH is not installed."

# Locate repo files: local clone or download the branch tarball.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]:-$0}")" 2>/dev/null && pwd || true)"
SRC=""
CLEANUP=""
if [ -n "$SCRIPT_DIR" ] && [ -f "$SCRIPT_DIR/seed.php" ]; then
	SRC="$(cd "$SCRIPT_DIR/.." && pwd)"
	say "Using local repo at $SRC"
else
	TMP="$(mktemp -d)"; CLEANUP="$TMP"
	url="https://github.com/$REPO/archive/refs/heads/$(printf '%s' "$BRANCH" | sed 's|/|%2F|g').tar.gz"
	say "Downloading $REPO@$BRANCH ..."
	curl -fsSL "$url" | tar -xz -C "$TMP" || fail "Could not download the repo."
	SRC="$(ls -d "$TMP"/*/ | head -1)"; SRC="${SRC%/}"
fi

say "Backing up database ..."
BACKUP="$WP_PATH/llh-backup-$(date +%Y%m%d-%H%M%S).sql"
wp db export "$BACKUP"
say "Backup: $BACKUP  (revert any time with: wp db import $BACKUP)"

say "Installing listingpro-child theme ..."
cp -a "$SRC/wp-content/themes/listingpro-child" "$WP_PATH/wp-content/themes/listingpro-child.new"
rm -rf "$WP_PATH/wp-content/themes/listingpro-child"
mv "$WP_PATH/wp-content/themes/listingpro-child.new" "$WP_PATH/wp-content/themes/listingpro-child"
chown -R "$SITE_OWNER":"$(stat -c '%G' "$WP_PATH")" "$WP_PATH/wp-content/themes/listingpro-child" 2>/dev/null || true

if [ "$ACTIVATE_CHILD" -eq 1 ]; then
	say "Activating child theme (carrying over menus/customizer settings) ..."
	wp eval '
		$mods = get_option( "theme_mods_listingpro" );
		if ( $mods && ! get_option( "theme_mods_listingpro-child" ) ) {
			update_option( "theme_mods_listingpro-child", $mods );
		}
	'
	wp theme activate listingpro-child
else
	say "Child theme installed but NOT activated (re-run with --activate-child when you want it)."
fi

say "Running the accounting-directory seeder ..."
wp eval-file "$SRC/setup/seed.php"

say "Flushing caches ..."
wp rewrite flush --hard 2>/dev/null || wp rewrite flush
wp cache flush 2>/dev/null || true

[ -n "$CLEANUP" ] && rm -rf "$CLEANUP"

SITE_URL="$(wp option get siteurl)"
cat <<DONE

------------------------------------------------------------------
  Accounting-directory conversion applied. 📊

  Check the site:  $SITE_URL

  Still manual (see AUDIT.md in the repo):
    1. Logo + favicon (Theme Options)
    2. Hero/city images in Elementor
    3. Pricing Plans + Stripe keys (Featured \$99/mo)

  Revert everything:  wp db import $BACKUP
------------------------------------------------------------------
DONE
