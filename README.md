# Cliop

## Mímir (optioneel)

Zet in `web/auth.php` (niet in git) `$mimirApi`, en laat de Business Central-credentials **ernaast** staan. Die zijn de automatische fallback als Mímir uitvalt:

```php
$mimirApi  = 'mimir_…';
// optioneel:
$mimirBase = 'https://sleutels.kvt.nl/mimir/api';

$auth_list = [
    'Production' => ['mode' => 'basic', 'user' => '…', 'pass' => '…'],
];
$environment = 'Production';
$auth = $auth_list[$environment];
$baseUrl = 'https://bc-host:7148/';
```

Met `$mimirApi` proberen OData-fetches en company-discovery (`odata_get_all`, `odata_mimir_list_companies`, `odata_mimir_query`, `odata_mimir_fetch_all`) eerst Mímir. Faalt die aanroep (verbinding/timeout, non-2xx, ongeldige JSON of een Mímir-foutpayload), dan haalt Clio dezelfde data op via de oude Business Central-route (`$baseUrl`, `$auth` / `$auth_list`, `$environment`, lokale odata-filecache) en slaat Mímir voor de rest van dat PHP-proces over. Ontbreken de BC-credentials, dan komt de oorspronkelijke Mímir-fout terug.

Dit geldt voor live webrequests én voor CLI/cron. `web/content/bootstrap.php` laadt `auth.php` bij een webrequest. Een CLI-script dat alleen `$mimirApi` zet, laadt `auth.php` alsnog zodra de fallback de BC-credentials nodig heeft. Webrequests gebruiken een Mímir-timeout van ongeveer 90 seconden (connect-timeout 10 seconden); `PHP_SAPI=cli` houdt de lange timeout van 600 seconden.

De email-worker (Node) leest geen Business Central OData; die gebruikt Microsoft Graph. Zonder `$mimirApi` blijft alleen de directe BC-route actief.

De fallback staat in `web/odata.php`. Dat bestand verder niet wijzigen; deze fallback is een goedgekeurde uitzondering (Tim Falken, 2026-09-28).
