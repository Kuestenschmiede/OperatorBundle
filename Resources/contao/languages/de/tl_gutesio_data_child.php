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

$strName = 'tl_gutesio_data_child';

$GLOBALS['TL_LANG'][$strName]['price'] = ['Preis', 'Preis inkl. MwSt. in EUR.'];
$GLOBALS['TL_LANG'][$strName]['strikePrice'] = ['Streichpreis', 'Streichpreis inkl. MwSt. in EUR.'];
$GLOBALS['TL_LANG'][$strName]['discount'] = ['Rabatt', 'Rabatt des Produktes in Prozent.'];
$GLOBALS['TL_LANG'][$strName]['color'] = ['Farbe', 'Farbe des Produktes.'];
$GLOBALS['TL_LANG'][$strName]['size'] = ['Größe', 'Größe des Produktes.'];

$GLOBALS['TL_LANG'][$strName]['beginDate'] = ['Beginndatum', ''];
$GLOBALS['TL_LANG'][$strName]['beginTime'] = ['Beginnzeit', ''];
$GLOBALS['TL_LANG'][$strName]['endDate'] = ['Enddatum', ''];
$GLOBALS['TL_LANG'][$strName]['endTime'] = ['Endzeit', ''];
$GLOBALS['TL_LANG'][$strName]['locationElementId'] = ['Ort der Veranstaltung', 'Der Ort der Veranstaltung.'];

$GLOBALS['TL_LANG'][$strName]['frontend']['list']['taxInfo'] = '*Preise inkl. MwSt.';
$GLOBALS['TL_LANG'][$strName]['frontend']['details']['taxInfo'] = '*Preise inkl. %s MwSt.';
$GLOBALS['TL_LANG'][$strName]['frontend']['details']['noTaxInfo'] = '*Preise frei von MwSt.';
$GLOBALS['TL_LANG'][$strName]['frontend']['details']['headline'] = 'Details';
$GLOBALS['TL_LANG'][$strName]['frontend']['details']['contact'] = 'Kontakt';

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
$GLOBALS['TL_LANG'][$strName]['frontend']['cc_form']['confirm_button_text'] = 'Bestätigen';
$GLOBALS['TL_LANG'][$strName]['frontend']['cc_form']['close_button_text'] = 'Schließen';
$GLOBALS['TL_LANG'][$strName]['frontend']['cc_form']['email'] = ['Emailadresse', 'Eine Emailadresse für Rückfragen.'];
$GLOBALS['TL_LANG'][$strName]['frontend']['cc_form']['earliest'] = ['Frühester Abholzeitpunkt', ''];
$GLOBALS['TL_LANG'][$strName]['frontend']['cc_form']['notes'] = ['Notizen', ''];

$GLOBALS['TL_LANG'][$strName]['notification']['error'] = 'Fehler';
$GLOBALS['TL_LANG'][$strName]['notification']['form_invalid'] = 'Fehler in den Formulardaten.';
$GLOBALS['TL_LANG'][$strName]['notification']['email_missing'] = 'Die Emailadresse ist ein Pflichtfeld.';
$GLOBALS['TL_LANG'][$strName]['notification']['email_invalid_format'] = 'Die Emailadresse ist in einem ungültigen Format.';
$GLOBALS['TL_LANG'][$strName]['notification']['earliest_missing'] = 'Der früheste Abholzeitpunkt ist ein Pflichtfeld.';
$GLOBALS['TL_LANG'][$strName]['notification']['email_and_earliest_missing'] = 'Die Emailadresse und der früheste Abholzeitpunkt sind Pflichtfelder.';
$GLOBALS['TL_LANG'][$strName]['notification']['generic_error'] = 'Ein Fehler ist aufgetreten.';
$GLOBALS['TL_LANG'][$strName]['notification']['email_error'] = 'Die Angabe ist keine gültige Emailadresse.';

$GLOBALS['TL_LANG'][$strName]['notification']['success'] = 'Erfolg';
$GLOBALS['TL_LANG'][$strName]['notification']['cc_email_sent'] = 'Ihre Bestellung wurde verschickt. Geben Sie dem Anbieter etwas Zeit, um diese zu prüfen.';

$GLOBALS['TL_LANG'][$strName]['filter']['open_filter'] = 'Filter öffnen';
$GLOBALS['TL_LANG'][$strName]['filter']['close_filter'] = 'Filter schliessen';
$GLOBALS['TL_LANG'][$strName]['filter']['sorting']['random'] = "Standard";
$GLOBALS['TL_LANG'][$strName]['filter']['sorting']['price_asc'] = "Preis aufsteigend";
$GLOBALS['TL_LANG'][$strName]['filter']['sorting']['price_desc'] = "Preis absteigend";

