<?php
/**
 * This file belongs to gutes.io and is published exclusively for use
 * in gutes.io operator or provider pages.
 
 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.io
 */

$strName = 'offer_list';

$GLOBALS['TL_LANG'][$strName]['price'] = ['Preis', 'Preis inkl. MwSt. in EUR.'];
$GLOBALS['TL_LANG'][$strName]['strikePrice'] = ['Streichpreis', 'Streichpreis inkl. MwSt. in EUR.'];
$GLOBALS['TL_LANG'][$strName]['discount'] = ['Rabatt', 'Rabatt des Produktes in Prozent.'];
$GLOBALS['TL_LANG'][$strName]['color'] = ['Farbe', 'Farbe des Produktes.'];
$GLOBALS['TL_LANG'][$strName]['size'] = ['Größe', 'Größe des Produktes.'];
$GLOBALS['TL_LANG'][$strName]['appointmentUponAgreementContent'] = "Termin nach Absprache";
$GLOBALS['TL_LANG'][$strName]['appointmentUponAgreement_startingAt'] = "ab";


$GLOBALS['TL_LANG'][$strName]['beginDate'] = ['Beginndatum', ''];
$GLOBALS['TL_LANG'][$strName]['beginTime'] = ['Beginnzeit', ''];
$GLOBALS['TL_LANG'][$strName]['endDate'] = ['Enddatum', ''];
$GLOBALS['TL_LANG'][$strName]['endTime'] = ['Endzeit', ''];
$GLOBALS['TL_LANG'][$strName]['minCredit'] = 'Mindestguthaben';
$GLOBALS['TL_LANG'][$strName]['maxCredit'] = 'Höchstguthaben';
$GLOBALS['TL_LANG'][$strName]['maxCredit_format'] = 'bis zu %s € Guthaben';
$GLOBALS['TL_LANG'][$strName]['locationElementId'] = ['Ort der Veranstaltung', 'Der Ort der Veranstaltung.'];

$GLOBALS['TL_LANG'][$strName]['frontend']['list']['taxInfo'] = '*Preise inkl. MwSt.';
$GLOBALS['TL_LANG'][$strName]['frontend']['details']['taxInfo'] = '*Preise inkl. %s MwSt.';
$GLOBALS['TL_LANG'][$strName]['frontend']['details']['noTaxInfo'] = '*Preise frei von MwSt.';
$GLOBALS['TL_LANG'][$strName]['frontend']['details']['headline'] = 'Details';
$GLOBALS['TL_LANG'][$strName]['frontend']['details']['contact'] = 'Kontakt';
$GLOBALS['TL_LANG'][$strName]['frontend']['putOnWishlist'] = 'Merken';
$GLOBALS['TL_LANG'][$strName]['frontend']['removeFromWishlist'] = 'Gemerkt';

$GLOBALS['TL_LANG'][$strName]['description'] = "Beschreibung";
$GLOBALS['TL_LANG'][$strName]['detailData'] = "Detaildaten";
$GLOBALS['TL_LANG'][$strName]['tags'] = "Tags";
$GLOBALS['TL_LANG'][$strName]['contact'] = "Kontakt";
$GLOBALS['TL_LANG'][$strName]['displayType'] = "Kategorie:";
$GLOBALS['TL_LANG'][$strName]['infoFile_label'] = "Weitere Informationen";
$GLOBALS['TL_LANG'][$strName]['infoFile_title'] = "Weitere Informationen ansehen";
$GLOBALS['TL_LANG'][$strName]['offeredBy'] = "Angeboten von folgenden Anbietern:";
$GLOBALS['TL_LANG'][$strName]['chooseDateRange'] = "Zeitraum auswählen";
$GLOBALS['TL_LANG'][$strName]['chooseDateRange_desc'] = "Hier können Sie einen Filterzeitraum auswählen, um die Veranstaltungen einzugrenzen.";

$GLOBALS['TL_LANG'][$strName]['filterFromPlaceholder'] = "Datum von";
$GLOBALS['TL_LANG'][$strName]['filterUntilPlaceholder'] = "Datum bis";


$GLOBALS['TL_LANG'][$strName]['price_replacer_options'] = [
    'free' => 'kostenfrei',
    'on_demand' => 'auf Anfrage'
];

$GLOBALS['TL_LANG'][$strName]['frontend']['product'] = 'Produkt';
$GLOBALS['TL_LANG'][$strName]['frontend']['event'] = 'Veranstaltung';
$GLOBALS['TL_LANG'][$strName]['frontend']['news'] = 'Neuigkeit';
$GLOBALS['TL_LANG'][$strName]['frontend']['exhibition'] = 'Ausstellung';
$GLOBALS['TL_LANG'][$strName]['frontend']['advertisement'] = 'Anzeige';
$GLOBALS['TL_LANG'][$strName]['frontend']['job'] = 'Job';
$GLOBALS['TL_LANG'][$strName]['frontend']['arrangement'] = 'Arrangements';
$GLOBALS['TL_LANG'][$strName]['frontend']['service'] = 'Dienstleistung';
$GLOBALS['TL_LANG'][$strName]['frontend']['startingAt'] = 'ab';

$GLOBALS['TL_LANG'][$strName]['frontend']['cc_form']['modal_button_label'] = 'Click and Collect';
$GLOBALS['TL_LANG'][$strName]['frontend']['cc_form']['confirm_button_text'] = 'jetzt bestellen';
$GLOBALS['TL_LANG'][$strName]['frontend']['cc_form']['close_button_text'] = 'schließen';
$GLOBALS['TL_LANG'][$strName]['frontend']['cc_form']['email'] = ['Ihre E-Mail-Adresse für Rückfragen', ''];
$GLOBALS['TL_LANG'][$strName]['frontend']['cc_form']['name'] = ['Name', ''];
$GLOBALS['TL_LANG'][$strName]['frontend']['cc_form']['earliest'] = ['Abholzeitpunkt', ''];
$GLOBALS['TL_LANG'][$strName]['frontend']['cc_form']['notes'] = ['Anmerkungen', ''];

$GLOBALS['TL_LANG'][$strName]['filter']['open_filter'] = 'Erweiterte Suche';
$GLOBALS['TL_LANG'][$strName]['frontend']['cp_form']['modal_button_label'] = 'Click and Pay';
$GLOBALS['TL_LANG'][$strName]['frontend']['cp_form']['confirm_button_text'] = 'jetzt bestellen';
$GLOBALS['TL_LANG'][$strName]['frontend']['cp_form']['close_button_text'] = 'schließen';
$GLOBALS['TL_LANG'][$strName]['frontend']['cp_form']['email'] = ['Ihre E-Mail-Adresse für Rückfragen', ''];
$GLOBALS['TL_LANG'][$strName]['frontend']['cp_form']['name'] = ['Name', ''];
$GLOBALS['TL_LANG'][$strName]['frontend']['cp_form']['credit'] = ['Guthaben', ''];
$GLOBALS['TL_LANG'][$strName]['frontend']['cp_form']['notes'] = ['Anmerkungen', ''];

$GLOBALS['TL_LANG'][$strName]['filter']['open_filter'] = 'Filter öffnen';
$GLOBALS['TL_LANG'][$strName]['filter']['close_filter'] = 'Filter schliessen';
$GLOBALS['TL_LANG'][$strName]['filter']['sorting']['random'] = "Standard";
$GLOBALS['TL_LANG'][$strName]['filter']['sorting']['price_asc'] = "Preis aufsteigend";
$GLOBALS['TL_LANG'][$strName]['filter']['sorting']['price_desc'] = "Preis absteigend";

