<?php
/**
 * This file is part of con4gis,
 * the gis-kit for Contao CMS.
 *
 * @package   	con4gis
 * @version        6
 * @author  	    con4gis contributors (see "authors.txt")
 * @license 	    LGPL-3.0-or-later
 * @copyright 	Küstenschmiede GmbH Software & Design
 * @link              https://www.con4gis.org
 *
 */

$strName = "tl_gutesio_operator_settings";

$GLOBALS['TL_LANG'][$strName]['key_legend'] = "Grundeinstellungen";
$GLOBALS['TL_LANG'][$strName]['map_legend'] = "Karteneinstellungen";
$GLOBALS['TL_LANG'][$strName]['page_legend'] = "Seiteneinstellungen";
$GLOBALS['TL_LANG'][$strName]['notification_legend'] = "Benachrichtigungseinstellungen";
$GLOBALS['TL_LANG'][$strName]['pwa_legend'] = "Einstellungen zur PWA";
$GLOBALS['TL_LANG'][$strName]['ai_legend'] = "KI Chatbot Einstellungen";
$GLOBALS['TL_LANG'][$strName]['tax_legend'] = "Steuersätze";
$GLOBALS['TL_LANG'][$strName]['aiEnabled'] = ["KI Chatbot aktivieren", "Aktivieren Sie diese Option, um den KI Chatbot zur Verfügung zu stellen."];
$GLOBALS['TL_LANG'][$strName]['aiAssistantName'] = ["Name der KI-Assistentin", "Geben Sie den Namen an, unter dem die KI im Chat antwortet (Default: KI)."];
$GLOBALS['TL_LANG'][$strName]['aiApiEndpoint'] = ["KI API Endpunkt", "Geben Sie den API Endpunkt der KI an (z.B. OpenAI)."];
$GLOBALS['TL_LANG'][$strName]['aiApiKey'] = ["KI API Schlüssel", "Geben Sie den API Schlüssel für die KI an."];
$GLOBALS['TL_LANG'][$strName]['aiModel'] = ["KI Modell", "Geben Sie das zu verwendende KI Modell an (z.B. gpt-4o-mini)."];
$GLOBALS['TL_LANG'][$strName]['aiMaxContextRecords'] = ["Maximale Anzahl an Kontext-Datensätzen", "Begrenzen Sie die Anzahl der Datensätze, die pro Anfrage als Kontext an die KI gesendet werden, um Kosten zu sparen (0 = unbegrenzt)."];
$GLOBALS['TL_LANG'][$strName]['cdnUrl'] = ['URL zum CDN', 'Geben Sie die URL zum CDN an (zwingend erforderlich).'];
$GLOBALS['TL_LANG'][$strName]['gutesIoUrl'] = ['URL für gutes.digital', 'Geben Sie hier die URL für die gutes.digital Kommunikation an (zwingend erforderlich).'];
$GLOBALS['TL_LANG'][$strName]['gutesIoKey'] = ['API-Schlüssel für gutes.digital', 'Geben Sie hier Ihren Betreiberschlüssel an (zwingend erforderlich).'];
$GLOBALS['TL_LANG'][$strName]['detail_profile'] = ["Kartenprofil für Detailansicht", "Wählen Sie das Kartenprofil aus, welches in der Detailansicht verwendet werden soll."];
$GLOBALS['TL_LANG'][$strName]['detail_map'] = ["Kartenelement für Detailansicht", "Wählen Sie das Kartenelement aus, welches in der Detailansicht verwendet werden soll."];
$GLOBALS['TL_LANG'][$strName]['showcaseDetailPage'] = ["Detailseite Schaufenster", "Wählen Sie die Seite aus, auf der die Detailseite der Schaufenster liegt."];
$GLOBALS['TL_LANG'][$strName]['productDetailPage'] = ["Detailseite Produkte", "Wählen Sie die Seite aus, auf der die Detailseite der Produkte liegt."];
$GLOBALS['TL_LANG'][$strName]['jobDetailPage'] = ["Detailseite Jobs", "Wählen Sie die Seite aus, auf der die Detailseite der Jobs liegt."];
$GLOBALS['TL_LANG'][$strName]['eventDetailPage'] = ["Detailseite Veranstaltungen", "Wählen Sie die Seite aus, auf der die Detailseite der Veranstaltungen liegt."];
$GLOBALS['TL_LANG'][$strName]['arrangementDetailPage'] = ["Detailseite Arrangements", "Wählen Sie die Seite aus, auf der die Detailseite der Arrangements liegt."];
$GLOBALS['TL_LANG'][$strName]['serviceDetailPage'] = ["Detailseite Dienstleistung", "Wählen Sie die Seite aus, auf der die Detailseite der Dienstleistungen liegt."];
$GLOBALS['TL_LANG'][$strName]['personDetailPage'] = ["Detailseite Personen", "Wählen Sie die Seite aus, auf der die Detailseite der Personen liegt."];
$GLOBALS['TL_LANG'][$strName]['voucherDetailPage'] = ["Detailseite Gutschein", "Wählen Sie die Seite aus, auf der die Detailseite der Gutscheine liegt."];
$GLOBALS['TL_LANG'][$strName]['realestateDetailPage'] = ["Detailseite Immobilien", "Wählen Sie die Seite aus, auf der die Detailseite der Immobilien liegt."];
$GLOBALS['TL_LANG'][$strName]['exhibitionDetailPage'] = ["Detailseite Ausstellungen", "Wählen Sie die Seite aus, auf der die Detailseite der Ausstellungen liegt."];
$GLOBALS['TL_LANG'][$strName]['cartPage'] = ["Warenkorb Seite", "Die Warenkorb Seite."];
$GLOBALS['TL_LANG'][$strName]['taxRegular'] = ["Normaler Steuersatz", "Der aktuell gültige normale Steuersatz. Wird in den Details der Inhalte angezeigt, die den normalen Steuersatz gewählt haben."];
$GLOBALS['TL_LANG'][$strName]['taxReduced'] = ["Ermäßigter Steuersatz", "Der aktuell gültige ermäßigte Steuersatz. Wird in den Details der Inhalte angezeigt, die den ermäßigten Steuersatz gewählt haben."];
$GLOBALS['TL_LANG'][$strName]['popupFields'] = ['Popup Felder', 'Auswahl der Felder für das Popup.'];
$GLOBALS['TL_LANG'][$strName]['popupFieldsReduced'] = ['Popup Felder (reduziert)', 'Auswahl der Felder für das reduzierte Popup (Elemente im Kartenausschnitt).'];
$GLOBALS['TL_LANG'][$strName]['dailyEventPushConfig'] = ['Konfiguration der Pushnachrichten für Veranstaltungen', 'Hier können Sie verschiedene Pushnachrichten für Veranstaltungs-Informationen konfigurieren.'];
$GLOBALS['TL_LANG'][$strName]['pushTime'] = ['Versendungsdatum', 'Geben Sie die Uhrzeit ein, zu der täglich die Pushnachrichten zu den aktuellen Veranstaltungen versendet werden sollen.'];
$GLOBALS['TL_LANG'][$strName]['pushMessage'] = ['Inhalt der Nachricht', 'Geben Sie den Inhalt der täglichen Pushnachricht ein.'];
$GLOBALS['TL_LANG'][$strName]['subscriptionTypes'] = ['Abonnement-Typen', 'Wählen Sie die Abonnement-Typen aus, die diese Pushnachricht erhalten sollen.'];
$GLOBALS['TL_LANG'][$strName]['pushRedirectPage'] = ['Weiterleitungsseite', 'Wählen Sie die Seite aus, auf die in der Pushnachricht verlinkt werden soll.'];
$GLOBALS['TL_LANG'][$strName]['sendForAllEventTypes'] = ['Kategorie-Einschränkung ignorieren', 'Wenn diese Checkbox gesetzt ist, wird'];

$GLOBALS['TL_LANG'][$strName]['popupFieldsRefs'] =[
    'image'     => 'Bild',
    'name'      => 'Name',
    'types'     => 'Kategorien',
    'desc'      => 'Beschreibung',
    'tags'      => 'Tags',
    'contacts'  => 'Kontakt',
    'wishlist'  => 'Button Wunschliste',
    'more'      => 'Button mehr'
];