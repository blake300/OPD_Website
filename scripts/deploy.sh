#!/usr/bin/env bash
# One-way deploy: local -> Bluehost (never pulls changes back)
# Usage: bash scripts/deploy.sh

set -euo pipefail

LOCAL_DIR="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$LOCAL_DIR/.env"

read_env_var() {
  local key="$1"
  local file="${2:-$ENV_FILE}"
  if [[ ! -f "$file" ]]; then
    return 1
  fi
  local line
  line="$(grep -m1 "^${key}=" "$file" || true)"
  if [[ -z "$line" ]]; then
    return 1
  fi
  local value="${line#*=}"
  value="${value%$'\r'}"
  value="${value#\"}"
  value="${value%\"}"
  value="${value#\'}"
  value="${value%\'}"
  printf '%s' "$value"
}

HOST="${OPD_DEPLOY_HOST:-${Bluehost_SSH_Host:-$(read_env_var 'Bluehost_SSH_Host' || true)}}"
PORT="${OPD_DEPLOY_PORT:-${Bluehost_SSH_Port:-$(read_env_var 'Bluehost_SSH_Port' || true)}}"
USER="${OPD_DEPLOY_USER:-${Bluehost_SSH_Username:-$(read_env_var 'Bluehost_SSH_Username' || true)}}"
SSH_KEY="${OPD_DEPLOY_SSH_KEY:-$HOME/.ssh/id_ed25519_bluehost}"
DB_HOST="${OPD_DEPLOY_DB_HOST:-${OPD_DB_HOST:-$(read_env_var 'OPD_DB_HOST' || true)}}"
DB_NAME="${OPD_DEPLOY_DB_NAME:-${OPD_DB_NAME:-$(read_env_var 'OPD_DB_NAME' || true)}}"
DB_USER="${OPD_DEPLOY_DB_USER:-${OPD_DB_USER:-$(read_env_var 'OPD_DB_USER' || true)}}"
DB_PASS="${OPD_DEPLOY_DB_PASS:-${OPD_DB_PASS:-$(read_env_var 'OPD_DB_PASS' || true)}}"

required_vars=(HOST PORT USER DB_NAME DB_USER DB_PASS)
for var_name in "${required_vars[@]}"; do
  if [[ -z "${!var_name:-}" ]]; then
    echo "Missing required deploy setting: ${var_name}" >&2
    exit 1
  fi
done

SCP="scp -P $PORT -i $SSH_KEY -o IdentitiesOnly=yes"
SSH="ssh -p $PORT -i $SSH_KEY -o IdentitiesOnly=yes"

echo "=== OPD Deploy ==="
echo "Local:  $LOCAL_DIR"
echo "Remote: $USER@$HOST"
echo ""

# Generate schema sync first
echo ">> Generating schema sync..."
php "$LOCAL_DIR/scripts/generate_schema_sync.php"

# Pack everything into a tarball to minimize SSH connections
echo ">> Packing files..."
TMPTAR=$(mktemp /tmp/opd-deploy-XXXXXX.tar.gz)

cd "$LOCAL_DIR"
tar czf "$TMPTAR" \
  --exclude='.git' \
  --exclude='node_modules' \
  --exclude='Plan' \
  --exclude='references' \
  --exclude='tests' \
  --exclude='test-results' \
  --exclude='playwright-report' \
  --exclude='artifacts' \
  --exclude='Agents' \
  --exclude='Memory' \
  --exclude='Providers' \
  --exclude='Rules' \
  --exclude='State' \
  --exclude='Tools' \
  --exclude='_Uploads' \
  --exclude='package.json' \
  --exclude='package-lock.json' \
  --exclude='playwright.config.*' \
  --exclude='CLAUDE.md' \
  --exclude='RUDI.md' \
  --exclude='README.md' \
  public/ api/ config/ database/ scripts/ src/ vendor/ .env

echo ">> Uploading (~$(du -h "$TMPTAR" | cut -f1))..."
$SCP "$TMPTAR" "$USER@$HOST:/tmp/opd-deploy.tar.gz"

echo ">> Extracting on server..."
$SSH "$USER@$HOST" \
  OPD_REMOTE_DB_HOST="$DB_HOST" \
  OPD_REMOTE_DB_NAME="$DB_NAME" \
  OPD_REMOTE_DB_USER="$DB_USER" \
  OPD_REMOTE_DB_PASS="$DB_PASS" \
  bash -s <<'REMOTE'
cd ~
tar xzf /tmp/opd-deploy.tar.gz

# Move public/ contents to public_html/
cp -r public/* public_html/
cp public/.htaccess public_html/.htaccess 2>/dev/null
rm -rf public/

# Run schema sync
mysql_args=(-u "$OPD_REMOTE_DB_USER" "-p$OPD_REMOTE_DB_PASS" "$OPD_REMOTE_DB_NAME")
if [[ -n "${OPD_REMOTE_DB_HOST:-}" && "$OPD_REMOTE_DB_HOST" != "localhost" ]]; then
  mysql_args=(-h "$OPD_REMOTE_DB_HOST" "${mysql_args[@]}")
fi
mysql "${mysql_args[@]}" < ~/database/schema_sync.sql 2>/dev/null

# Cleanup
rm -f /tmp/opd-deploy.tar.gz
echo "Done"
REMOTE

rm -f "$TMPTAR"

echo ""
echo "=== Deploy complete ==="
