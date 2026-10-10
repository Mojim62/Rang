#!/usr/bin/env bash
#
# Bamero release deployment — SSH transport contract (fail-closed).
#
# Promotes the ALREADY-BUILT artifact (dist/bamero-release.zip) to a target
# host. Does NOT build, does NOT fetch plugins at runtime, does NOT toggle
# DISALLOW_FILE_MODS. Staging/production credentials come exclusively from
# GitHub environment-scoped secrets.
#
# Contract on the target host (must exist; documented in
# docs/DEPLOYMENT_PIPELINE_FA.md and GO_LIVE_PHP_HOSTING_FA.md):
#   - SSH key auth (read-only key acceptable) for $DEPLOY_USER
#   - wp-cli available as `wp` for $DEPLOY_USER
#   - releases dir: $DEPLOY_PATH/releases/<tag>/
#   - each release dir is a COMPLETE WordPress docroot: WP core + the artifact
#     overlay (wp-config.php, .htaccess, wp-content/). The artifact zip does
#     NOT contain WP core — bootstrap the first release by extracting the WP
#     fa_IR core into the release dir BEFORE this script switches to it
#     (GO_LIVE_PHP_HOSTING_FA.md, section 2). This script verifies core
#     presence and ABORTS if it is missing (fail-closed: a docroot without
#     wp-admin/wp-includes must never go live).
#   - current symlink: $DEPLOY_PATH/current -> releases/<tag>
#   - wp-cli commands run inside $DEPLOY_PATH/current (where wp-config.php
#     and WP core actually live), NOT inside $DEPLOY_PATH itself.
#
# Usage:
#   bash scripts/deploy-release.sh --target staging|production
#
set -euo pipefail

# M5 remediation: real argument parsing — `--target` (and `--target=x`) both work.
TARGET=""
while [ $# -gt 0 ]; do
    case "$1" in
        --target)  TARGET="${2:-}"; shift 2 ;;
        --target=*) TARGET="${1#--target=}"; shift ;;
        *) echo "DEPLOY ABORT: unknown argument '$1' (expected --target staging|production)" >&2; exit 1 ;;
    esac
done
if [ -z "$TARGET" ]; then
    echo "DEPLOY ABORT: --target is required (staging|production)." >&2
    exit 1
fi
case "$TARGET" in
    staging|production) ;;
    *) echo "DEPLOY ABORT: invalid target '$TARGET' (expected staging|production)." >&2; exit 1 ;;
esac

ARTIFACT="${ARTIFACT:-dist/bamero-release.zip}"
RELEASE_TAG="${RELEASE_TAG:-$TARGET-$(date -u +%Y%m%d%H%M%S)}"

# ---- fail-closed preflight -------------------------------------------
for var in SSH_KEY DEPLOY_HOST DEPLOY_USER DEPLOY_PATH; do
    if [ -z "${!var:-}" ]; then
        echo "DEPLOY ABORT: missing required env '$var' (transport not configured — refusing to fake deployment)." >&2
        exit 1
    fi
done
if [ ! -f "$ARTIFACT" ]; then
    echo "DEPLOY ABORT: artifact $ARTIFACT not found. Build once with scripts/build-release.sh first." >&2
    exit 1
fi

# B2 remediation: the wp docroot is the `current` release dir, not $DEPLOY_PATH.
DOCROOT="$DEPLOY_PATH/current"

KEYFILE="$(mktemp)"
printf '%s\n' "$SSH_KEY" > "$KEYFILE"
chmod 600 "$KEYFILE"
trap 'rm -f "$KEYFILE"' EXIT

SSH="ssh -i $KEYFILE -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=15 $DEPLOY_USER@$DEPLOY_HOST"
SCP="scp -i $KEYFILE -o StrictHostKeyChecking=accept-new"

echo "== Deploy: target=$TARGET tag=$RELEASE_TAG host=$DEPLOY_HOST =="

# ---- 1. verify checksum of the artifact we promote --------------------
if [ -f dist/artifact.sha256 ]; then
    sha256sum -c dist/artifact.sha256
else
    echo "NOTE: dist/artifact.sha256 missing; checksum not verified."
fi

# ---- 2. upload artifact ------------------------------------------------
# B6 remediation: resolve the remote HOME once into a LITERAL path. The old
# "\$HOME" placeholder relied on remote-side shell expansion, which breaks in
# two places: OpenSSH >= 9 scp defaults to the SFTP protocol, which performs
# NO shell expansion on remote paths, and the production DB-backup path was
# single-quoted (also no expansion). A literal absolute path works for ssh,
# scp (both protocols) and the remote heredoc alike.
REMOTE_HOME="$($SSH 'printf %s "$HOME"')"
if [ -z "$REMOTE_HOME" ]; then
    echo "DEPLOY ABORT: could not resolve remote HOME over SSH." >&2
    exit 1
fi
REMOTE_TMP="$REMOTE_HOME/.bamero-deploy-$"
$SSH "mkdir -p $REMOTE_TMP"

echo "== upload artifact =="
$SCP "$ARTIFACT" "$DEPLOY_USER@$DEPLOY_HOST:$REMOTE_TMP/bamero-release.zip"

# ---- 3. pre-deploy backup (production only, mandatory) ------------------
if [ "$TARGET" = "production" ]; then
    echo "== production: mandatory DB backup (from $DOCROOT) =="
    $SSH "if [ ! -d '$DOCROOT' ]; then echo 'DEPLOY ABORT: no existing release under $DOCROOT — bootstrap the first release per GO_LIVE_PHP_HOSTING_FA.md before production deploys.' >&2; exit 1; fi"
    $SSH "cd '$DOCROOT' && wp db export '$REMOTE_TMP/pre-deploy-backup.sql'" \
        || { echo "DEPLOY ABORT: mandatory pre-deploy DB backup failed." >&2; exit 1; }
    # L2 hardening: a "successful" export of 0 bytes is not a backup. Refuse to
    # switch the symlink on an empty or missing dump.
    BACKUP_SIZE="$($SSH "stat -c %s '$REMOTE_TMP/pre-deploy-backup.sql' 2>/dev/null" || echo 0)"
    if [ "$BACKUP_SIZE" -lt 1024 ]; then
        echo "DEPLOY ABORT: pre-deploy DB backup is empty or missing ($BACKUP_SIZE bytes)." >&2
        exit 1
    fi
    echo "production: DB backup OK ($BACKUP_SIZE bytes)"
fi

# ---- 4. stage release dir + atomic-ish symlink switch --------------------
echo "== stage release dir and switch symlink =="
$SSH bash -s <<REMOTE
set -euo pipefail
cd "$DEPLOY_PATH"
mkdir -p "releases/$RELEASE_TAG" backups
if [ -f "releases/$RELEASE_TAG/bamero-release.zip" ]; then
    echo "DEPLOY ABORT: release dir already exists (refusing overwrite)." >&2
    exit 1
fi
mv "$REMOTE_TMP/bamero-release.zip" "releases/$RELEASE_TAG/bamero-release.zip"
cd "releases/$RELEASE_TAG"
unzip -qo bamero-release.zip
sha256sum -c SHA256SUMS
# B2 remediation: fail-closed core check — the release dir must be a COMPLETE
# docroot. The artifact ships wp-config/.htaccess/wp-content but NOT WP core.
if [ ! -f wp-config.php ] || [ ! -f wp-includes/wp-load.php ]; then
    echo "DEPLOY ABORT: release dir is not a complete WordPress docroot (missing wp-config.php or wp-includes/). Extract the WP fa_IR core into releases/$RELEASE_TAG first (GO_LIVE_PHP_HOSTING_FA.md section 2), or copy it from the previous release dir. Refusing to switch current to an incomplete docroot." >&2
    exit 1
fi
ln -sfn "releases/$RELEASE_TAG" current.tmp
mv -T current.tmp current
echo "switched current -> releases/$RELEASE_TAG"
REMOTE

# ---- 5. post-deploy health check ----------------------------------------
# B2 remediation: wp-cli runs where wp-config.php actually lives (current).
WP_OK=$($SSH "cd '$DOCROOT' && wp core is-installed && wp option get siteurl" || true)
if [ -z "$WP_OK" ]; then
    echo "POST-DEPLOY HEALTH FAIL: wp core not reachable in $DOCROOT." >&2
    exit 1
fi
# L2 hardening: flush the object cache after the symlink switch so no visitor
# can hit a mix of old/new code paths through stale cache entries.
$SSH "cd '$DOCROOT' && wp cache flush" || echo "WARN: wp cache flush failed (non-fatal)."
echo "post-deploy wp health: OK ($WP_OK)"
echo "DEPLOY OK: $TARGET @ releases/$RELEASE_TAG"
