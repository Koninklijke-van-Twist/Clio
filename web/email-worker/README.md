# Clio email worker

Deze map moet mee naar productie, omdat productie alleen de inhoud van `web/` krijgt.

Behoud bij deploys of uploads altijd deze serverbestanden:

- `web/email-worker/config.json`
- `web/data/emails/`

`config.json` bevat mailboxconfiguratie en staat bewust niet in Git. `web/data/emails/` bevat het gearchiveerde emailarchief en wordt door de worker aangemaakt als de map nog niet bestaat.

## Microsoft Graph configuratie

Maak in Entra ID een app registration aan met application permissions voor Microsoft Graph:

- `Mail.ReadWrite`
- Admin consent voor de tenant

Gebruik daarna client credentials in `config.json`:

```json
{
  "pollIntervalMinutes": 10,
  "archiveRoot": "../data/emails",
  "graph": {
    "tenantId": "tenant-id",
    "clientId": "app-client-id",
    "clientSecret": "client-secret",
    "mailbox": "clio@example.com",
    "mailFolder": "Inbox",
    "allowedSenderDomains": [
      "kvt.nl"
    ],
    "pageSize": 25,
    "onlyUnread": false,
    "deleteAfterArchive": true
  }
}
```

`mailbox` is de mailbox die wordt uitgelezen. `mailFolder` is de Graph folder-id of een well-known folder zoals `Inbox`. Als `allowedSenderDomains` gevuld is, worden mails van andere domeinen direct verwijderd zonder archivering.

Installeer of update de systemd service op de server met:

```sh
sudo web/email-worker/install-email-worker-service.sh
```

Test zonder wijzigingen met de laatste 3 emails:

```sh
cd web/email-worker
npm run email:dry-run
```

Gebruik `node dry-run.js --limit=5` als je tijdelijk meer of minder berichten wilt bekijken.
