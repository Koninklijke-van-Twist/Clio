# Clio email worker

Deze map moet mee naar productie, omdat productie alleen de inhoud van `web/` krijgt.

Behoud bij deploys of uploads altijd deze serverbestanden:

- `web/email-worker/config.json`
- `web/data/emails/`

`config.json` bevat mailboxconfiguratie en staat bewust niet in Git. `web/data/emails/` bevat het gearchiveerde emailarchief en wordt door de worker aangemaakt als de map nog niet bestaat.

## Microsoft Graph configuratie

Maak in Entra ID een app registration aan met application permissions voor Microsoft Graph:

- `Mail.ReadWrite`
- `Mail.Send`
- `Sites.ReadWrite.All` of `Files.ReadWrite.All` (voor SharePoint projectupload)
- Admin consent voor de tenant

Gebruik daarna client credentials in `config.json`:

```json
{
  "pollIntervalMinutes": 10,
  "archiveRoot": "../data/emails",
  "sharepoint": {
    "enabled": true,
    "siteHostname": "kvtnl.sharepoint.com",
    "sitePath": "/sites/BCDocumentRepository",
    "projectsFolder": "Projects",
    "driveId": ""
  },
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
    "deleteAfterArchive": true,
    "notifications": {
      "enabled": true
    }
  }
}
```

`mailbox` is de mailbox die wordt uitgelezen. `mailFolder` is de Graph folder-id of een well-known folder zoals `Inbox`. Als `allowedSenderDomains` gevuld is, worden mails van andere domeinen direct verwijderd zonder archivering.

## Projectnummers en SharePoint

Als het onderwerp `PRJ` gevolgd door 6-9 cijfers of een 6-cijferig nummer dat begint met `15` bevat, probeert de worker na archivering alleen het `.eml`-bestand te uploaden naar:

`https://kvtnl.sharepoint.com/sites/BCDocumentRepository/Projects/<nr>_<desc>`

De worker zoekt een bestaande map die begint met `<nr>_`. Bestaat die map niet, dan krijgt alleen de afzender een melding dat SharePoint-upload niet lukte maar Clio-archivering wel. Bij succes krijgt alleen de afzender een bevestiging met projectnummer en mapnaam.

### SharePoint configuratie

De transcript-upload in `auth.php` gebruikt een **andere** SharePoint-site (`site_id` / `drive_id` voor meetingtranscripten). Voor projectmails heb je de site en drive nodig van **BCDocumentRepository**, niet de IDs uit `auth.php` kopiëren.

Voorkeur in `config.json`:

1. `driveId` — ID van de **Projects**-documentbibliotheek op BCDocumentRepository
2. `projectsFolder` — leeg (`""`) als `driveId` al naar die bibliotheek wijst; projectmappen staan dan direct in de root (`153703_Omschrijving`)
3. optioneel `siteId` of `siteHostname` + `sitePath` — alleen nodig als je geen `driveId` gebruikt

Zoek de juiste `driveId` via Graph (met dezelfde app-registratie):

`GET https://graph.microsoft.com/v1.0/sites/kvtnl.sharepoint.com:/sites/BCDocumentRepository:/drives`

Kies de drive waar de map `Projects` onder staat en zet die ID in `sharepoint.driveId` op de server.

Voor overige e-mails stuurt de worker alleen naar de afzender een bevestiging dat de mail in Clio gearchiveerd is. CC en andere ontvangers krijgen geen automatische reactie.

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
