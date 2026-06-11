# Clio email worker

Deze map moet mee naar productie, omdat productie alleen de inhoud van `web/` krijgt.

Behoud bij deploys of uploads altijd deze serverbestanden:

- `web/email-worker/config.json`
- `web/data/emails/`

`config.json` bevat mailboxconfiguratie en staat bewust niet in Git. `web/data/emails/` bevat het gearchiveerde emailarchief en wordt door de worker aangemaakt als de map nog niet bestaat.

Installeer of update de systemd service op de server met:

```sh
sudo web/email-worker/install-email-worker-service.sh
```
