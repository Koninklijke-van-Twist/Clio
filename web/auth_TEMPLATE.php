<?php
/**
 * Auth-template voor Clio. Niet de productie-auth.php (die staat niet in git).
 *
 * Zet Mímir aan en laat het Business Central-blok ernaast staan. Fetches proberen
 * Mímir eerst en vallen terug op deze BC-variabelen als Mímir faalt (web én CLI/cron).
 * Zonder $mimirApi wordt alleen het BC-blok gebruikt.
 *
 *   $mimirApi  = 'mimir_…';  // verplicht om Mímir te activeren
 *   $mimirBase = 'https://sleutels.kvt.nl/mimir/api'; // optioneel
 *
 * Kopieer dit bestand niet over een bestaande auth.php heen; voeg $mimirApi toe
 * en laat $auth_list / $environment / $auth / $baseUrl staan.
 */

// --- Mímir (aanbevolen) ---
// $mimirApi  = 'mimir_…';
// $mimirBase = 'https://sleutels.kvt.nl/mimir/api';

// --- Business Central (directe route, én fallback als Mímir faalt) ---
$auth_list = [
    'Production' => ['mode' => 'basic', 'user' => 'USERNAME', 'pass' => 'PASSWORD'],
];
$environment = 'Production';
$auth = $auth_list[$environment];
$baseUrl = 'https://my-bc-domain.com:7148/';

// Wie odata.php?action=… mag aanroepen (cache_status, cache_delete, cache_clear).
// Zonder deze lijst weigert Clio die acties, ook voor een ingelogde gebruiker.
// Staat de lijst er wel, dan geldt hij ook voor de UI-acties (download, upload, favoriet).
// $allowedUsers = [
//     "user@kvt.nl",
// ];

// Optioneel, alleen als een HTTP-runner odata.php?action=… moet aanroepen.
// Header X-Clio-Secret moet exact dit geheim zijn. Weglaten: geen anonieme toegang.
// CLI (php web/odata.php) heeft dit niet nodig.
// $clioRunnerSecret = '';
