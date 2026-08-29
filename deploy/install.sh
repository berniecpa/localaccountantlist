#!/usr/bin/env bash
#
# LimoListHone one-command installer.
#
# Installs (or updates) the limolisthone theme + limolisthone-core plugin into
# an existing WordPress install and activates everything. Designed for
# CloudPanel VPSes (docroot /home/<site-user>/htdocs/<domain>) but works on any
# server with PHP and a WordPress directory. Safe to re-run — it upgrades the
# theme/plugin in place.
#
# Usage (as root or the site user, over SSH):
#   curl -sL https://raw.githubusercontent.com/berniecpa/limolisthone/BRANCH/deploy/install.sh | bash -s -- --path /home/SITEUSER/htdocs/YOURDOMAIN.com
#
# Or from a clone of this repo on the server:
#   bash deploy/install.sh --path /home/SITEUSER/htdocs/YOURDOMAIN.com
#
# Options:
#   --path <dir>     WordPress root (contains wp-config.php). Auto-detected
#                    under /home/*/htdocs/* when omitted and exactly one
#                    install is found.
#   --branch <name>  Git branch to download when not running from a clone.
#                    Default: main (falls back to the development branch).

set -euo pipefail

REPO="berniecpa/limolisthone"
BRANCH="main"
FALLBACK_BRANCH="claude/wordpress-customization-x8wyf5"
WP_PATH=""

say()  { printf '\033[1;33m==>\033[0m %s\n' "$*"; }
fail() { printf '\033[1;31mERROR:\033[0m %s\n' "$*" >&2; exit 1; }

while [ $# -gt 0 ]; do
	case "$1" in
		--path)   WP_PATH="${2:-}"; shift 2 ;;
		--branch) BRANCH="${2:-}"; shift 2 ;;
		*) fail "Unknown option: $1" ;;
	esac
done

command -v php >/dev/null 2>&1 || fail "php not found on this server."

# ---------------------------------------------------------------- find WP root
if [ -z "$WP_PATH" ]; then
	say "No --path given, looking for WordPress installs under /home/*/htdocs/* ..."
	mapfile -t candidates < <(ls -d /home/*/htdocs/*/wp-config.php 2>/dev/null | xargs -r -n1 dirname)
	if [ "${#candidates[@]}" -eq 1 ]; then
		WP_PATH="${candidates[0]}"
	elif [ "${#candidates[@]}" -eq 0 ]; then
		fail "No WordPress install found. Create the WordPress site first (in CloudPanel: Add Site → WordPress), then re-run with --path /home/SITEUSER/htdocs/DOMAIN"
	else
		printf 'Multiple WordPress installs found:\n%s\n' "${candidates[@]}"
		fail "Re-run with --path pointing at the right one."
	fi
fi

[ -f "$WP_PATH/wp-config.php" ] || fail "$WP_PATH does not look like a WordPress root (no wp-config.php)."
say "WordPress root: $WP_PATH"

SITE_OWNER="$(stat -c '%U' "$WP_PATH")"
run_as() {
	if [ "$(id -un)" = "root" ] && [ "$SITE_OWNER" != "root" ]; then
		sudo -u "$SITE_OWNER" -- "$@"
	else
		"$@"
	fi
}

# ------------------------------------------------------------------ get wp-cli
if command -v wp >/dev/null 2>&1; then
	WP_BIN=(wp)
else
	say "wp-cli not found, downloading wp-cli.phar ..."
	WPCLI="/tmp/llh-wp-cli.phar"
	[ -f "$WPCLI" ] || curl -sSL -o "$WPCLI" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
	chmod +r "$WPCLI"
	WP_BIN=(php "$WPCLI")
fi
# wp-cli refuses to run as root without --allow-root. run_as only drops to the
# site owner when that owner isn't root, so the flag is needed exactly when
# both the current user and the site owner are root.
WP_FLAGS=( "--path=$WP_PATH" )
if [ "$(id -un)" = "root" ] && [ "$SITE_OWNER" = "root" ]; then
	WP_FLAGS+=( "--allow-root" )
fi
wp() { run_as "${WP_BIN[@]}" "${WP_FLAGS[@]}" "$@"; }

wp core is-installed || fail "WordPress at $WP_PATH is not installed/configured yet. Finish the WordPress setup first."

# ------------------------------------------------------- locate the theme code
# Prefer a local checkout (script run from the repo); otherwise download the
# branch tarball from GitHub, falling back to the development branch.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]:-$0}")" 2>/dev/null && pwd || true)"
SRC=""
CLEANUP=""

if [ -n "$SCRIPT_DIR" ] && [ -d "$SCRIPT_DIR/../wp-content/plugins/limolisthone-core" ]; then
	SRC="$(cd "$SCRIPT_DIR/.." && pwd)"
	say "Using local repo at $SRC"
else
	TMP="$(mktemp -d)"
	CLEANUP="$TMP"
	for try_branch in "$BRANCH" "$FALLBACK_BRANCH"; do
		url="https://github.com/$REPO/archive/refs/heads/$(printf '%s' "$try_branch" | sed 's|/|%2F|g').tar.gz"
		say "Downloading $REPO@$try_branch ..."
		if curl -fsSL "$url" | tar -xz -C "$TMP" 2>/dev/null; then
			SRC="$(ls -d "$TMP"/*/ | head -1)"
			SRC="${SRC%/}"
			break
		fi
	done
	[ -n "$SRC" ] || fail "Could not download the repo (is it private? clone it to the server and run deploy/install.sh from the clone)."
fi

# --------------------------------------------------------------------- install
say "Installing plugin and theme ..."
mkdir -p "$WP_PATH/wp-content/plugins" "$WP_PATH/wp-content/themes"
rm -rf "$WP_PATH/wp-content/plugins/limolisthone-core.new" "$WP_PATH/wp-content/themes/limolisthone.new"
cp -a "$SRC/wp-content/plugins/limolisthone-core" "$WP_PATH/wp-content/plugins/limolisthone-core.new"
cp -a "$SRC/wp-content/themes/limolisthone" "$WP_PATH/wp-content/themes/limolisthone.new"
rm -rf "$WP_PATH/wp-content/plugins/limolisthone-core" "$WP_PATH/wp-content/themes/limolisthone"
mv "$WP_PATH/wp-content/plugins/limolisthone-core.new" "$WP_PATH/wp-content/plugins/limolisthone-core"
mv "$WP_PATH/wp-content/themes/limolisthone.new" "$WP_PATH/wp-content/themes/limolisthone"
chown -R "$SITE_OWNER":"$(stat -c '%G' "$WP_PATH")" \
	"$WP_PATH/wp-content/plugins/limolisthone-core" "$WP_PATH/wp-content/themes/limolisthone" 2>/dev/null || true

say "Activating ..."
wp plugin activate limolisthone-core
wp theme activate limolisthone
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard

[ -n "$CLEANUP" ] && rm -rf "$CLEANUP"

SITE_URL="$(wp option get siteurl)"
cat <<DONE

------------------------------------------------------------------
  LimoListHone is installed and active. 🎉

  Site:            $SITE_URL
  Directory:       $SITE_URL/limos/
  Submit listings: $SITE_URL/get-listed/
                   (created automatically, with starter vehicle types)

  Last step — connect Stripe for paid boosts:
    1. wp-admin → Listings → Boost Settings
    2. Paste your Stripe secret key + boost price ID, Save
    3. Click "Create webhook automatically"
------------------------------------------------------------------
DONE
