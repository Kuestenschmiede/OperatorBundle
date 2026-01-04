<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.
 
 * @package    gutesio
 * @copyright (c) 2010-2026, by Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */

$strName = 'offer_list';

$GLOBALS['TL_LANG'][$strName]['price'] = ['Preis', 'Preis inkl. MwSt. in EUR.'];
$GLOBALS['TL_LANG'][$strName]['strikePrice'] = ['Streichpreis', 'Streichpreis inkl. MwSt. in EUR.'];
$GLOBALS['TL_LANG'][$strName]['discount'] = ['Rabatt', 'Rabatt des Produktes in Prozent.'];
$GLOBALS['TL_LANG'][$strName]['color'] = ['Farbe', 'Farbe des Produktes.'];
$GLOBALS['TL_LANG'][$strName]['size'] = ['Größe', 'Größe des Produktes.'];

$GLOBALS['TL_LANG'][$strName]['allergenes'] = ['Hinweise zu Allergenen', ''];
$GLOBALS['TL_LANG'][$strName]['ingredients'] = ['Zutaten', ''];
$GLOBALS['TL_LANG'][$strName]['kJ'] = ['Brennwert (in kJ)', ''];
$GLOBALS['TL_LANG'][$strName]['fat'] = ['Fett (in g)', ''];
$GLOBALS['TL_LANG'][$strName]['saturatedFattyAcidze'] = ['Davon gesättigte Fettsäuren (in g)', ''];
$GLOBALS['TL_LANG'][$strName]['carbonHydrates'] = ['Kohlenhydrate (in g)', ''];
$GLOBALS['TL_LANG'][$strName]['sugar'] = ['Davon Zucker (in g)', ''];
$GLOBALS['TL_LANG'][$strName]['salt'] = ['Salz (in g)', ''];

$GLOBALS['TL_LANG'][$strName]['isbn'] = ['ISBN', ''];
$GLOBALS['TL_LANG'][$strName]['ean'] = ['EAN', ''];
$GLOBALS['TL_LANG'][$strName]['brand'] = ['Marke', ''];
$GLOBALS['TL_LANG'][$strName]['basePriceUnit'] = ['Grundpreis', ''];
$GLOBALS['TL_LANG'][$strName]['basePriceUnitPerPiece'] = ['pro', ''];
$GLOBALS['TL_LANG'][$strName]['availableAmount'] = ['Verfügbare Menge', ''];

$GLOBALS['TL_LANG'][$strName]['appointmentUponAgreementContent'] = "Termin nach Absprache";
$GLOBALS['TL_LANG'][$strName]['appointmentUponAgreement_startingAt'] = "ab";


$GLOBALS['TL_LANG'][$strName]['beginDate'] = ['Beginndatum', ''];
$GLOBALS['TL_LANG'][$strName]['beginTime'] = ['Beginnzeit', ''];
$GLOBALS['TL_LANG'][$strName]['entryTime'] = ['Einlass', ''];
$GLOBALS['TL_LANG'][$strName]['nextDate'] = ['Auch am:', ''];
$GLOBALS['TL_LANG'][$strName]['endDate'] = ['Enddatum', ''];
$GLOBALS['TL_LANG'][$strName]['endTime'] = ['Endzeit', ''];
$GLOBALS['TL_LANG'][$strName]['minCredit'] = 'Mindestguthaben';
$GLOBALS['TL_LANG'][$strName]['maxCredit'] = 'Höchstguthaben';
$GLOBALS['TL_LANG'][$strName]['maxCredit_format'] = 'bis zu %s € Guthaben';
$GLOBALS['TL_LANG'][$strName]['credit'] = 'Guthaben';
$GLOBALS['TL_LANG'][$strName]['credit_format'] = '%s € Guthaben';
$GLOBALS['TL_LANG'][$strName]['locationElementId'] = ['Ort der Veranstaltung', 'Der Ort der Veranstaltung.'];

$GLOBALS['TL_LANG'][$strName]['frontend']['list']['taxInfo'] = '*Preise inkl. MwSt.';
$GLOBALS['TL_LANG'][$strName]['frontend']['details']['taxInfo'] = '*Preise inkl. %s MwSt.';
$GLOBALS['TL_LANG'][$strName]['frontend']['details']['noTaxInfo'] = '*Preise frei von MwSt.';
$GLOBALS['TL_LANG'][$strName]['frontend']['details']['headline'] = 'Details';
$GLOBALS['TL_LANG'][$strName]['frontend']['details']['contact'] = 'Kontakt';
$GLOBALS['TL_LANG'][$strName]['frontend']['putOnWishlist'] = 'Merken';
$GLOBALS['TL_LANG'][$strName]['frontend']['removeFromWishlist'] = 'Gemerkt';

$GLOBALS['TL_LANG'][$strName]['frontend']['putInCart'] = 'Warenkorb';
$GLOBALS['TL_LANG'][$strName]['frontend']['removeFromCart'] = 'Im Warenkorb';

$GLOBALS['TL_LANG'][$strName]['description'] = "Beschreibung";
$GLOBALS['TL_LANG'][$strName]['detailData'] = "Detaildaten";
$GLOBALS['TL_LANG'][$strName]['tags'] = "Tags";
$GLOBALS['TL_LANG'][$strName]['contact'] = "Kontakt";
$GLOBALS['TL_LANG'][$strName]['displayType'] = "Kategorie:";
$GLOBALS['TL_LANG'][$strName]['infoFile_label'] = "Weitere Informationen";
$GLOBALS['TL_LANG'][$strName]['infoFile_title'] = "Weitere Informationen ansehen";
$GLOBALS['TL_LANG'][$strName]['offeredBy'] = "Angeboten von folgenden Anbietern";
$GLOBALS['TL_LANG'][$strName]['chooseDateRange'] = "Zeitraum";
$GLOBALS['TL_LANG'][$strName]['chooseDateRange_desc'] = "Hier können Sie ein Beginn- und/oder Enddatum setzen.";

$GLOBALS['TL_LANG'][$strName]['filterFromPlaceholder'] = "Datum von";
$GLOBALS['TL_LANG'][$strName]['filterUntilPlaceholder'] = "Datum bis";

$GLOBALS['TL_LANG'][$strName]['otherOffers'] = "Andere Inhalte der Anbieter";
$GLOBALS['TL_LANG'][$strName]['location'] = "Veranstaltungsort";


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
$GLOBALS['TL_LANG'][$strName]['frontend']['person'] = 'Person';
$GLOBALS['TL_LANG'][$strName]['frontend']['startingAt'] = 'ab';

$GLOBALS['TL_LANG'][$strName]['filter']['open_filter'] = 'Filter';
$GLOBALS['TL_LANG'][$strName]['filter']['close_filter'] = 'Filter schliessen';
$GLOBALS['TL_LANG'][$strName]['filter']['sorting']['random'] = "Zufällig";
$GLOBALS['TL_LANG'][$strName]['filter']['sorting']['date_asc'] = "Nach Datum";
$GLOBALS['TL_LANG'][$strName]['filter']['sorting']['tstmp_desc'] = "Letzte Änderung";
$GLOBALS['TL_LANG'][$strName]['filter']['sorting']['name_asc'] = "Name: aufsteigend";
$GLOBALS['TL_LANG'][$strName]['filter']['sorting']['name_desc'] = "Name: absteigend";
$GLOBALS['TL_LANG'][$strName]['filter']['sorting']['price_asc'] = "Preis: aufsteigend";
$GLOBALS['TL_LANG'][$strName]['filter']['sorting']['price_desc'] = "Preis: absteigend";
$GLOBALS['TL_LANG'][$strName]['filter']['apply_filter'] = "Suchen";

