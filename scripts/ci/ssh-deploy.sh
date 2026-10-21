#!/usr/bin/env bash
# Open an SSH session and run a deploy script on the VPS.
#
# Retries only when the connection itself fails (the GitHub-hosted runner
# sometimes cannot dial the VPS). A failure inside the remote script is not
# retried: that script brings the site back up, and running it again would
# repeat a partial release.
#
# Required environment: SSH_HOST, SSH_USER, SSH_PRIVATE_KEY.
# Optional: SSH_PORT (default 22), SSH_DEPLOY_ATTEMPTS (default 4),
# SSH_DEPLOY_DELAY_SECONDS (default 45).
set -euo pipefail

is_connect_failure() {
  grep -Eq \
    'Connection timed out|Connection refused|Connection reset by peer|kex_exchange_identification|No route to host|Network is unreachable|Could not resolve hostname|banner exchange|Operation timed out|dial tcp|i/o timeout|connect to host .* port' \
    "$1"
}

self_test() {
  tmp=$(mktemp)
  printf '%s\n' 'ssh: connect to host 203.0.113.10 port 22: Connection timed out' >"$tmp"
  is_connect_failure "$tmp" || {
    echo "self-test: missed connection timeout" >&2
    exit 1
  }
  printf '%s\n' 'dial tcp 203.0.113.10:22: i/o timeout' >"$tmp"
  is_connect_failure "$tmp" || {
    echo "self-test: missed dial tcp timeout" >&2
    exit 1
  }
  printf '%s\n' 'ERROR: APP_ENV is not staging. Aborting.' >"$tmp"
  if is_connect_failure "$tmp"; then
    echo "self-test: remote abort was treated as a connection failure" >&2
    exit 1
  fi
  printf '%s\n' 'Deploy failed (exit 1). Application was brought back up.' >"$tmp"
  if is_connect_failure "$tmp"; then
    echo "self-test: maintenance recovery was treated as a connection failure" >&2
    exit 1
  fi
  rm -f "$tmp"
  echo "ssh-deploy self-test ok"
}

if [ "${1:-}" = "--self-test" ]; then
  self_test
  exit 0
fi

remote_script=${1:?usage: ssh-deploy.sh <remote-script>}
: "${SSH_HOST:?SSH_HOST is required}"
: "${SSH_USER:?SSH_USER is required}"
: "${SSH_PRIVATE_KEY:?SSH_PRIVATE_KEY is required}"

if [ ! -f "$remote_script" ]; then
  echo "Remote script not found: $remote_script" >&2
  exit 1
fi

key_file=$(mktemp)
known_hosts=$(mktemp)
log_file=$(mktemp)
chmod 600 "$key_file"
cleanup() {
  rm -f "$key_file" "$known_hosts" "$log_file"
}
trap cleanup EXIT

# OpenSSH rejects a private key file that does not end in a newline.
printf '%s\n' "$SSH_PRIVATE_KEY" >"$key_file"

port="${SSH_PORT:-22}"
max_attempts="${SSH_DEPLOY_ATTEMPTS:-4}"
delay_seconds="${SSH_DEPLOY_DELAY_SECONDS:-45}"
attempt=1

while [ "$attempt" -le "$max_attempts" ]; do
  : >"$log_file"
  set +e
  ssh \
    -i "$key_file" \
    -p "$port" \
    -o IdentitiesOnly=yes \
    -o StrictHostKeyChecking=accept-new \
    -o UserKnownHostsFile="$known_hosts" \
    -o ConnectTimeout=180 \
    -o ServerAliveInterval=30 \
    -o ServerAliveCountMax=6 \
    -o BatchMode=yes \
    "${SSH_USER}@${SSH_HOST}" \
    bash -s <"$remote_script" 2>&1 | tee "$log_file"
  code=${PIPESTATUS[0]}
  set -e

  if [ "$code" -eq 0 ]; then
    exit 0
  fi

  if [ "$attempt" -lt "$max_attempts" ] && is_connect_failure "$log_file"; then
    echo "SSH connect failed (attempt ${attempt}/${max_attempts}). Waiting ${delay_seconds}s before retry."
    attempt=$((attempt + 1))
    sleep "$delay_seconds"
    continue
  fi

  if is_connect_failure "$log_file"; then
    echo "Deploy failed (exit ${code}) after ${attempt} connection attempts."
  else
    echo "Deploy failed (exit ${code}) on attempt ${attempt}. A remote script failure is not retried."
  fi
  exit "$code"
done
