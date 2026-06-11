#!/usr/bin/env bash
set -euo pipefail

WORKER_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEB_DIR="$(cd "${WORKER_DIR}/.." && pwd)"
SERVICE_NAME="${CLIO_EMAIL_SERVICE_NAME:-clio-email-worker}"
RUN_USER="${CLIO_EMAIL_RUN_USER:-www-data}"
NODE_BIN="$(command -v node || true)"
NPM_BIN="$(command -v npm || true)"

if [[ "${EUID}" -ne 0 ]]; then
  echo "Run dit script met sudo/root, zodat dependencies en systemd service geinstalleerd kunnen worden." >&2
  exit 1
fi

if [[ -z "${NODE_BIN}" || -z "${NPM_BIN}" ]]; then
  if command -v apt-get >/dev/null 2>&1; then
    apt-get update
    apt-get install -y nodejs npm
  else
    echo "NodeJS/NPM ontbreken en dit script kent alleen automatische installatie via apt-get." >&2
    exit 1
  fi
fi

NODE_BIN="$(command -v node)"
NPM_BIN="$(command -v npm)"

cd "${WORKER_DIR}"
if [[ -f package-lock.json ]]; then
  "${NPM_BIN}" ci --omit=dev
else
  "${NPM_BIN}" install --omit=dev
fi

if [[ ! -f "${WORKER_DIR}/config.json" ]]; then
  cp "${WORKER_DIR}/config.example.json" "${WORKER_DIR}/config.json"
  chmod 600 "${WORKER_DIR}/config.json"
  echo "Aangemaakt: ${WORKER_DIR}/config.json. Vul IMAP/SMTP instellingen in voordat de service gebruikt wordt." >&2
fi

mkdir -p "${WEB_DIR}/data/emails"
chown -R "${RUN_USER}:${RUN_USER}" "${WEB_DIR}/data/emails" "${WORKER_DIR}/config.json"

cat >"/etc/systemd/system/${SERVICE_NAME}.service" <<SERVICE
[Unit]
Description=Clio email archive worker
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=${RUN_USER}
WorkingDirectory=${WORKER_DIR}
ExecStart=${NODE_BIN} ${WORKER_DIR}/worker.js
Restart=always
RestartSec=30
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
SERVICE

systemctl daemon-reload
systemctl enable "${SERVICE_NAME}.service"
systemctl restart "${SERVICE_NAME}.service"

echo "Service geinstalleerd en gestart: ${SERVICE_NAME}.service"
echo "Status: systemctl status ${SERVICE_NAME}.service"
