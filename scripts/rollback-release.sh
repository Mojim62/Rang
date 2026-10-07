#!/usr/bin/env bash
#
# Bamero rollback — switch `current` symlink back to the previous release.
# Rollback = promote the previous KNOWN-GOOD artifact directory. NO rebuild,
# NO re-download, NO database changes.
#
# Limitation (explicit, per SRE practice): if the release shipped DB
# migrations, rolling back code does NOT roll back migrated data. Bamero
# seeds are idempotent (re-running them is safe), but a rollback after real
# orders were placed must be reviewed manually before being declared clean.
#
set -euo pipefail

for var in SSH_KEY DEPLOY_HOST DEPLOY_USER DEPLOY_PATH; do
    if [ -z "${!var:-}" ]; then
        echo "ROLLBACK ABORT: missing required env '$var'." >&2
        exit 1
    fi
done

KEYFILE="$(mktemp)"
printf '%s\n' "$SSH_KEY" > "$KEYFILE"
chmod 600 "$KEYFILE"
trap 'rm -f "$KEYFILE"' EXIT

SSH="ssh -i $KEYFILE -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=15 $DEPLOY_USER@$DEPLOY_HOST"

$SSH bash -s <<REMOTE
set -euo pipefail
cd "$DEPLOY_PATH"
CURRENT=\$(readlink current || echo "")
if [ -z "\$CURRENT" ]; then
    echo "ROLLBACK ABORT: no current symlink." >&2
    exit 1
fi
# \$CURRENT is like releases/<tag>/
CUR_DIR=\${CURRENT#releases/}
PREV=\$(ls -1t releases/ | grep -v "\$CUR_DIR" | head -1 || true)
if [ -z "\$PREV" ]; then
    echo "ROLLBACK ABORT: no previous release found to roll back to." >&2
    exit 1
fi
ln -sfn "releases/\$PREV" current.tmp
mv -T current.tmp current
echo "rolled back: current -> releases/\$PREV (was \$CURRENT)"
cd current && wp core is-installed && echo "post-rollback wp health: OK"
REMOTE

echo "ROLLBACK OK"
