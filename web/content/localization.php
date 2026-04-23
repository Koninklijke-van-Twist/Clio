<?php

/**
 * Constants
 */

const FLAG_SVGS = [
    'nl' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 600"><rect width="900" height="600" fill="#AE1C28"/><rect width="900" height="400" fill="#fff"/><rect width="900" height="200" fill="#fff"/><rect width="900" height="200" y="0" fill="#AE1C28"/><rect width="900" height="200" y="200" fill="#fff"/><rect width="900" height="200" y="400" fill="#21468B"/></svg>',
    'en' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 40"><clipPath id="a"><path d="M0 0v40h60V0z"/></clipPath><clipPath id="b"><path d="M30 20h30v20zv20H0zH0V0zV0h30z"/></clipPath><g clip-path="url(#a)"><path d="M0 0v40h60V0z" fill="#012169"/><path d="M0 0l60 40m0-40L0 40" stroke="#fff" stroke-width="8"/><path d="M0 0l60 40m0-40L0 40" clip-path="url(#b)" stroke="#C8102E" stroke-width="5"/><path d="M30 0v40M0 20h60" stroke="#fff" stroke-width="13"/><path d="M30 0v40M0 20h60" stroke="#C8102E" stroke-width="8"/></g></svg>',
    'de' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 5 3"><rect width="5" height="3" y="0" fill="#000"/><rect width="5" height="2" y="1" fill="#D00"/><rect width="5" height="1" y="2" fill="#FFCE00"/></svg>',
    'fr' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 600"><rect width="900" height="600" fill="#ED2939"/><rect width="600" height="600" fill="#fff"/><rect width="300" height="600" fill="#002395"/></svg>',
];
const SUPPORTED_LANGUAGES = [
    'nl' => ['flag' => '🇳🇱', 'label' => 'Nederlands'],
    'en' => ['flag' => '🇬🇧', 'label' => 'English'],
    'de' => ['flag' => '🇩🇪', 'label' => 'Deutsch'],
    'fr' => ['flag' => '🇫🇷', 'label' => 'Français'],
];

const TRANSLATIONS = [
    /* ------------------------------------------------------------------ */
    'nl' => [
        'app.title' => 'Transcript Hub',
        'app.subtitle' => 'Upload meetingtranscripten en bekijk samenvattingen uit SharePoint.',
        'lang.menu_label' => 'Taalkeuze',
        'lang.current' => 'Huidige taal',
        'nav.upload' => 'Upload transcript',
        'nav.summaries' => 'Meeting samenvattingen',
        'upload.title' => 'Upload transcript',
        'upload.description' => 'Sleep een .txt of .docx bestand in het vlak of kies handmatig een bestand.',
        'upload.dropzone' => 'Sleep uw bestand hierheen',
        'upload.dropzone_hint' => 'Ondersteunde bestanden: .txt en .docx',
        'upload.processing_notice' => 'Na uploaden kan het enkele minuten duren voor de samenvatting gereed is.',
        'upload.select_button' => 'Bestand kiezen',
        'upload.warning' => 'De gehele transcript van de meeting, en de samenvatting daarvan, wordt bedrijfsbreed inzichtelijk bij het uploaden van uw transcript. Weet u het zeker?',
        'upload.confirm_label' => 'Ja, ik bevestig dat ik dit transcript bedrijfsbreed wil delen.',
        'upload.submit' => 'Upload naar SharePoint',
        'upload.file_selected' => 'Geselecteerd bestand: %s',
        'upload.drag_active' => 'Laat los om te uploaden',
        'upload.error.file_required' => 'Selecteer eerst een bestand.',
        'upload.error.confirm_required' => 'Bevestig eerst de waarschuwing voor delen binnen het bedrijf.',
        'upload.error.invalid_extension' => 'Alleen .txt en .docx bestanden zijn toegestaan.',
        'upload.error.parse_failed' => 'Het bestand kon niet worden uitgelezen.',
        'upload.error.docx_support' => 'DOCX kan niet worden uitgelezen op deze server (zip-ondersteuning ontbreekt).',
        'upload.error.empty_content' => 'Er is geen bruikbare tekst in het bestand gevonden.',
        'upload.error.drive_item_missing' => 'SharePoint uploadresponse bevat geen bestand-id.',
        'upload.error.http_client' => 'De SharePoint-verbinding kon niet worden gestart.',
        'upload.success' => 'Bestand "%s" is verwerkt en naar SharePoint verzonden.',
        'upload.error.sharepoint' => 'Upload naar SharePoint is mislukt: %s',
        'summary.title' => 'Meeting samenvattingen',
        'summary.description' => 'Overzicht van AI-gegenereerde samenvattingen',
        'summary.empty' => 'Er zijn nog geen samenvattingen gevonden.',
        'summary.open' => 'Openen',
        'summary.download_md' => 'Download als .md',
        'summary.favorite_add' => 'Markeer als favoriet',
        'summary.favorite_remove' => 'Verwijder favoriet',
        'summary.status_ready' => 'Verwerkt',
        'summary.status_unprocessed' => 'Wordt nog verwerkt',
        'summary.unprocessed_eta' => 'Deze meeting wordt nog verwerkt. Geschatte resterende tijd: %s.',
        'summary.eta_inline' => 'Verwachte verwerking: %s',
        'summary.eta_less_than_minute' => 'minder dan een minuut',
        'summary.eta_minutes' => 'ongeveer %d minuten',
        'summary.preview_title' => 'Voorbeeld',
        'summary.preview_placeholder' => 'Kies links een samenvatting om de inhoud te tonen.',
        'summary.source' => 'Bronbestand openen',
        'summary.loaded' => 'Samenvatting geladen: %s',
        'summary.load_failed' => 'Kon samenvatting niet laden: %s',
        'flash.error' => 'Fout: %s',
        'flash.success' => 'Succes: %s',
        'error.sharepoint_config' => 'SharePoint-configuratie ontbreekt. Vul de site, drive en lijst-id in.',
        'error.sharepoint_token' => 'Geen SharePoint toegangstoken gevonden.',
        'error.sharepoint_permissions' => 'De app-token mist Graph-permissies. Geef application permissions en admin consent in Entra.',
        'error.unexpected' => 'Er is een onverwachte fout opgetreden.',
        'api.error.unauthorized' => 'Ongeldige of ontbrekende API key.',
        'api.error.summary_id_required' => 'Parameter "summary_id" is verplicht.',
        'api.error.not_found' => 'Samenvatting niet gevonden of geen toegang.',
        'api.error.invalid_summary_type' => 'Bestand heeft niet de status "Meeting Samenvatting".',
        'api.ok' => 'OK',
    ],

    /* ------------------------------------------------------------------ */
    'en' => [
        'app.title' => 'Transcript Hub',
        'app.subtitle' => 'Upload meeting transcripts and view summaries from SharePoint.',
        'lang.menu_label' => 'Language selection',
        'lang.current' => 'Current language',
        'nav.upload' => 'Upload transcript',
        'nav.summaries' => 'Meeting summaries',
        'upload.title' => 'Upload transcript',
        'upload.description' => 'Drop a .txt or .docx file in the area or select a file manually.',
        'upload.dropzone' => 'Drop your file here',
        'upload.dropzone_hint' => 'Supported files: .txt and .docx',
        'upload.processing_notice' => 'After uploading, it can take a few minutes before the summary is ready.',
        'upload.select_button' => 'Choose file',
        'upload.warning' => 'The full meeting transcript, and the summary derived from it, will become visible company-wide when you upload your transcript. Are you sure?',
        'upload.confirm_label' => 'Yes, I confirm that I want to share this transcript company-wide.',
        'upload.submit' => 'Upload to SharePoint',
        'upload.file_selected' => 'Selected file: %s',
        'upload.drag_active' => 'Release to upload',
        'upload.error.file_required' => 'Please select a file first.',
        'upload.error.confirm_required' => 'Please confirm the company-wide sharing warning first.',
        'upload.error.invalid_extension' => 'Only .txt and .docx files are allowed.',
        'upload.error.parse_failed' => 'The file could not be read.',
        'upload.error.docx_support' => 'DOCX cannot be read on this server (zip support is missing).',
        'upload.error.empty_content' => 'No usable text was found in the file.',
        'upload.error.drive_item_missing' => 'The SharePoint upload response does not contain a file id.',
        'upload.error.http_client' => 'The SharePoint connection could not be initialized.',
        'upload.success' => 'File "%s" was processed and sent to SharePoint.',
        'upload.error.sharepoint' => 'Upload to SharePoint failed: %s',
        'summary.title' => 'Meeting summaries',
        'summary.description' => 'Overview of AI-generated summaries',
        'summary.empty' => 'No summaries were found yet.',
        'summary.open' => 'Open',
        'summary.download_md' => 'Download as .md',
        'summary.favorite_add' => 'Mark as favorite',
        'summary.favorite_remove' => 'Remove favorite',
        'summary.status_ready' => 'Processed',
        'summary.status_unprocessed' => 'Still processing',
        'summary.unprocessed_eta' => 'This meeting is still being processed. Estimated remaining time: %s.',
        'summary.eta_inline' => 'Expected processing: %s',
        'summary.eta_less_than_minute' => 'less than a minute',
        'summary.eta_minutes' => 'about %d minutes',
        'summary.preview_title' => 'Preview',
        'summary.preview_placeholder' => 'Select a summary on the left to display its contents.',
        'summary.source' => 'Open source file',
        'summary.loaded' => 'Summary loaded: %s',
        'summary.load_failed' => 'Could not load summary: %s',
        'flash.error' => 'Error: %s',
        'flash.success' => 'Success: %s',
        'error.sharepoint_config' => 'SharePoint configuration is missing. Fill in the site, drive, and list id.',
        'error.sharepoint_token' => 'No SharePoint access token found.',
        'error.sharepoint_permissions' => 'The app token has no Graph permissions. Configure application permissions and admin consent in Entra.',
        'error.unexpected' => 'An unexpected error occurred.',
        'api.error.unauthorized' => 'Invalid or missing API key.',
        'api.error.summary_id_required' => 'Parameter "summary_id" is required.',
        'api.error.not_found' => 'Summary not found or access denied.',
        'api.error.invalid_summary_type' => 'File does not have status "Meeting Summary".',
        'api.ok' => 'OK',
    ],

    /* ------------------------------------------------------------------ */
    'de' => [
        'app.title' => 'Transcript Hub',
        'app.subtitle' => 'Laden Sie Meeting-Transkripte hoch und sehen Sie Zusammenfassungen aus SharePoint an.',
        'lang.menu_label' => 'Sprachauswahl',
        'lang.current' => 'Aktuelle Sprache',
        'nav.upload' => 'Transkript hochladen',
        'nav.summaries' => 'Meeting-Zusammenfassungen',
        'upload.title' => 'Transkript hochladen',
        'upload.description' => 'Ziehen Sie eine .txt- oder .docx-Datei in das Feld oder waehlen Sie manuell eine Datei.',
        'upload.dropzone' => 'Datei hier ablegen',
        'upload.dropzone_hint' => 'Unterstuetzte Dateien: .txt und .docx',
        'upload.processing_notice' => 'Nach dem Hochladen kann es einige Minuten dauern, bis die Zusammenfassung bereit ist.',
        'upload.select_button' => 'Datei auswaehlen',
        'upload.warning' => 'Das gesamte Meeting-Transkript und die daraus erzeugte Zusammenfassung werden nach dem Upload unternehmensweit sichtbar. Sind Sie sicher?',
        'upload.confirm_label' => 'Ja, ich bestaetige, dass ich dieses Transkript unternehmensweit teilen moechte.',
        'upload.submit' => 'Zu SharePoint hochladen',
        'upload.file_selected' => 'Ausgewaehlte Datei: %s',
        'upload.drag_active' => 'Zum Hochladen loslassen',
        'upload.error.file_required' => 'Bitte waehlen Sie zuerst eine Datei aus.',
        'upload.error.confirm_required' => 'Bitte bestaetigen Sie zuerst den Hinweis zur unternehmensweiten Freigabe.',
        'upload.error.invalid_extension' => 'Nur .txt- und .docx-Dateien sind erlaubt.',
        'upload.error.parse_failed' => 'Die Datei konnte nicht gelesen werden.',
        'upload.error.docx_support' => 'DOCX kann auf diesem Server nicht gelesen werden (Zip-Unterstuetzung fehlt).',
        'upload.error.empty_content' => 'Es wurde kein verwertbarer Text in der Datei gefunden.',
        'upload.error.drive_item_missing' => 'Die SharePoint-Uploadantwort enthaelt keine Datei-ID.',
        'upload.error.http_client' => 'Die SharePoint-Verbindung konnte nicht initialisiert werden.',
        'upload.success' => 'Datei "%s" wurde verarbeitet und an SharePoint gesendet.',
        'upload.error.sharepoint' => 'Upload zu SharePoint fehlgeschlagen: %s',
        'summary.title' => 'Meeting-Zusammenfassungen',
        'summary.description' => 'Uebersicht der KI-generierten Zusammenfassungen',
        'summary.empty' => 'Es wurden noch keine Zusammenfassungen gefunden.',
        'summary.open' => 'Oeffnen',
        'summary.download_md' => 'Als .md herunterladen',
        'summary.favorite_add' => 'Als Favorit markieren',
        'summary.favorite_remove' => 'Favorit entfernen',
        'summary.status_ready' => 'Verarbeitet',
        'summary.status_unprocessed' => 'Wird noch verarbeitet',
        'summary.unprocessed_eta' => 'Dieses Meeting wird noch verarbeitet. Geschaetzte verbleibende Zeit: %s.',
        'summary.eta_inline' => 'Erwartete Verarbeitung: %s',
        'summary.eta_less_than_minute' => 'weniger als eine Minute',
        'summary.eta_minutes' => 'etwa %d Minuten',
        'summary.preview_title' => 'Vorschau',
        'summary.preview_placeholder' => 'Waehlen Sie links eine Zusammenfassung aus, um den Inhalt anzuzeigen.',
        'summary.source' => 'Quelldatei oeffnen',
        'summary.loaded' => 'Zusammenfassung geladen: %s',
        'summary.load_failed' => 'Zusammenfassung konnte nicht geladen werden: %s',
        'flash.error' => 'Fehler: %s',
        'flash.success' => 'Erfolg: %s',
        'error.sharepoint_config' => 'SharePoint-Konfiguration fehlt. Tragen Sie Site-, Drive- und Listen-ID ein.',
        'error.sharepoint_token' => 'Kein SharePoint-Zugriffstoken gefunden.',
        'error.sharepoint_permissions' => 'Das App-Token hat keine Graph-Berechtigungen. Konfigurieren Sie Application Permissions und Admin Consent in Entra.',
        'error.unexpected' => 'Ein unerwarteter Fehler ist aufgetreten.',
        'api.error.unauthorized' => 'Ungueltiger oder fehlender API-Schluessel.',
        'api.error.summary_id_required' => 'Parameter "summary_id" ist erforderlich.',
        'api.error.not_found' => 'Zusammenfassung nicht gefunden oder kein Zugriff.',
        'api.error.invalid_summary_type' => 'Datei hat nicht den Status "Meeting-Zusammenfassung".',
        'api.ok' => 'OK',
    ],

    /* ------------------------------------------------------------------ */
    'fr' => [
        'app.title' => 'Transcript Hub',
        'app.subtitle' => 'Televersez des transcriptions de reunion et consultez les resumes depuis SharePoint.',
        'lang.menu_label' => 'Selection de langue',
        'lang.current' => 'Langue actuelle',
        'nav.upload' => 'Televerser une transcription',
        'nav.summaries' => 'Resumes de reunion',
        'upload.title' => 'Televerser une transcription',
        'upload.description' => 'Deposez un fichier .txt ou .docx dans la zone ou selectionnez un fichier manuellement.',
        'upload.dropzone' => 'Deposez votre fichier ici',
        'upload.dropzone_hint' => 'Fichiers pris en charge : .txt et .docx',
        'upload.processing_notice' => 'Apres televersement, il peut falloir quelques minutes avant que le resume soit pret.',
        'upload.select_button' => 'Choisir un fichier',
        'upload.warning' => 'La transcription complete de la reunion, ainsi que son resume, deviendra visible a toute l entreprise lors du televersement. Etes-vous sur ?',
        'upload.confirm_label' => 'Oui, je confirme vouloir partager cette transcription avec toute l entreprise.',
        'upload.submit' => 'Televerser vers SharePoint',
        'upload.file_selected' => 'Fichier selectionne : %s',
        'upload.drag_active' => 'Relachez pour televerser',
        'upload.error.file_required' => 'Veuillez d abord selectionner un fichier.',
        'upload.error.confirm_required' => 'Veuillez d abord confirmer l avertissement de partage interne.',
        'upload.error.invalid_extension' => 'Seuls les fichiers .txt et .docx sont autorises.',
        'upload.error.parse_failed' => 'Le fichier n a pas pu etre lu.',
        'upload.error.docx_support' => 'DOCX ne peut pas etre lu sur ce serveur (prise en charge zip manquante).',
        'upload.error.empty_content' => 'Aucun texte exploitable n a ete trouve dans le fichier.',
        'upload.error.drive_item_missing' => 'La reponse de televersement SharePoint ne contient pas d identifiant de fichier.',
        'upload.error.http_client' => 'La connexion SharePoint n a pas pu etre initialisee.',
        'upload.success' => 'Le fichier "%s" a ete traite et envoye vers SharePoint.',
        'upload.error.sharepoint' => 'Le televersement vers SharePoint a echoue : %s',
        'summary.title' => 'Resumes de reunion',
        'summary.description' => 'Vue d ensemble des resumes generes par IA',
        'summary.empty' => 'Aucun resume n a ete trouve pour le moment.',
        'summary.favorite_add' => 'Marquer comme favori',
        'summary.favorite_remove' => 'Retirer des favoris',
        'summary.open' => 'Ouvrir',
        'summary.download_md' => 'Telecharger en .md',
        'summary.status_ready' => 'Traite',
        'summary.status_unprocessed' => 'Traitement en cours',
        'summary.unprocessed_eta' => 'Cette reunion est encore en cours de traitement. Temps restant estime : %s.',
        'summary.eta_inline' => 'Traitement estime : %s',
        'summary.eta_less_than_minute' => 'moins d une minute',
        'summary.eta_minutes' => 'environ %d minutes',
        'summary.preview_title' => 'Apercu',
        'summary.preview_placeholder' => 'Selectionnez un resume a gauche pour afficher son contenu.',
        'summary.source' => 'Ouvrir le fichier source',
        'summary.loaded' => 'Resume charge : %s',
        'summary.load_failed' => 'Impossible de charger le resume : %s',
        'flash.error' => 'Erreur : %s',
        'flash.success' => 'Succes : %s',
        'error.sharepoint_config' => 'La configuration SharePoint est manquante. Renseignez les IDs site, drive et liste.',
        'error.sharepoint_token' => 'Aucun jeton d acces SharePoint trouve.',
        'error.sharepoint_permissions' => 'Le jeton applicatif n a pas de permissions Graph. Configurez les permissions applicatives et le consentement administrateur dans Entra.',
        'error.unexpected' => 'Une erreur inattendue est survenue.',
        'api.error.unauthorized' => 'Cle API invalide ou absente.',
        'api.error.summary_id_required' => 'Le parametre "summary_id" est obligatoire.',
        'api.error.not_found' => 'Resume introuvable ou acces refuse.',
        'api.error.invalid_summary_type' => 'Le fichier n a pas le statut "Resume de reunion".',
        'api.ok' => 'OK',
    ],
];

/**
 * Functies
 */

/**
 * Geeft het pad naar het user-prefs bestand voor een e-mailadres.
 * Geeft null terug als het e-mailadres niet beschikbaar / onveilig is.
 */
function getUserPrefsPath(string $email): ?string
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    $dir = __DIR__ . '/../data/user_prefs';
    $filename = preg_replace('/[^a-z0-9._\-]/', '_', $email) . '.json';
    return $dir . '/' . $filename;
}

function loadUserPrefs(string $email): array
{
    $path = getUserPrefsPath($email);
    if ($path === null || !is_file($path)) {
        return [];
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function saveUserPref(string $email, string $key, mixed $value): void
{
    $path = getUserPrefsPath($email);
    if ($path === null) {
        return;
    }
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $prefs = loadUserPrefs($email);
    $prefs[$key] = $value;
    file_put_contents($path, json_encode($prefs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function getCurrentLanguage(): string
{
    $lang = (string) ($_SESSION['lang'] ?? 'nl');
    return array_key_exists($lang, SUPPORTED_LANGUAGES) ? $lang : 'nl';
}

function getLanguageFlagSvg(string $language): string
{
    return (string) (FLAG_SVGS[$language] ?? FLAG_SVGS['nl'] ?? '');
}

/**
 * Geeft de vertaling voor $key in de actieve taal.
 * Extra $args worden via sprintf ingevoegd (voor %d, %s, etc.).
 */
function LOC(string $key, mixed ...$args): string
{
    $lang = getCurrentLanguage();
    $translations = TRANSLATIONS[$lang] ?? TRANSLATIONS['nl'];
    $string = $translations[$key] ?? (TRANSLATIONS['nl'][$key] ?? $key);

    return $args !== [] ? sprintf($string, ...$args) : $string;
}

/**
 * Page load
 */

// Laad opgeslagen taalvoorkeur als de sessie nog geen taal heeft
if (!isset($_SESSION['lang'])) {
    $prefEmail = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));
    if ($prefEmail !== '') {
        $savedPrefs = loadUserPrefs($prefEmail);
        if (isset($savedPrefs['lang']) && array_key_exists($savedPrefs['lang'], SUPPORTED_LANGUAGES)) {
            $_SESSION['lang'] = $savedPrefs['lang'];
        }
    }
}

// Verwerk taalwissel via ?lang= en sla permanent op
if (isset($_GET['lang']) && array_key_exists($_GET['lang'], SUPPORTED_LANGUAGES)) {
    $_SESSION['lang'] = $_GET['lang'];
    $prefEmail = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));
    if ($prefEmail !== '') {
        saveUserPref($prefEmail, 'lang', $_GET['lang']);
    }
}
