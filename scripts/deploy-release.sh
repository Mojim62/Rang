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
# docs/DEPLOYMENT_PIPELINE_FA.md):
#   - SSH key auth (read-only key acceptable) for $DEPLOY_USER
#   - wp-cli available as `wp` in $DEPLOY_PATH
#   - releases dir: $DEPLOY_PATH/releases/<tag>/
#   - current symlink: $DEPLOY_PATH/current -> releases/<tag>
#
# Usage:
#   bash scripts/deploy-release.sh --target staging|production
#
set -euo pipefail

TARGET="${2:-staging}"
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
REMOTE_TMP="\$HOME/.bamero-deploy-$$"
$SSH "mkdir -p $REMOTE_TMP" 

echo "== upload artifact =="
$SCP "$ARTIFACT" "$DEPLOY_USER@$DEPLOY_HOST:$REMOTE_TMP/bamero-release.zip"

# ---- 3. pre-deploy backup (production only, mandatory) ------------------
if [ "$TARGET" = "production" ]; then
    echo "== production: mandatory DB backup =="
    $SSH "cd $DEPLOY_PATH && wp db export $REMOTE_TMP/pre-deploy-backup.sql --allow-root 2>/dev/null || wp db export $REMOTE_TMP/pre-deploy-backup.sql" \
        || { echo "DEPLOY ABORT: mandatory pre-deploy DB backup failed." >&2; exit 1; }
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
# previous symlink target is the rollback point
readlink current >/dev/null 2>&1 || true
ln -sfn "releases/$RELEASE_TAG" current.tmp
mv -T current.tmp current
echo "switched current -> releases/$RELEASE_TAG"
REMOTE

# ---- 5. post-deploy health check ----------------------------------------
WP_OK=$($SSH "cd $DEPLOY_PATH && wp core is-installed && wp option get siteurl" || true)
if [ -z "$WP_OK" ]; then
    echo "POST-DEPLOY HEALTH FAIL: wp core not reachable on target." >&2
    exit 1
fi
echo "post-deploy wp health: OK ($WP_OK)"
echo "DEPLOY OK: $TARGET @ releases/$RELEASE_TAG"
