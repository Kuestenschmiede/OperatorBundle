<?php
/**
 * con4gis - the gis-kit
 *
 * @version   php 7
 * @package   east_frisia
 * @author    contributors (see "authors.txt")
 * @license   GNU/LGPL http://opensource.org/licenses/lgpl-3.0.html
 * @copyright Küstenschmiede GmbH Software & Design 2011 - 2018
 * @link      https://www.kuestenschmiede.de
 */

$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode'] = ['Lademodus', 'Bestimmt, welche Schaufenster in diesem Modul geladen werden.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode'] = ['Lademodus', 'Bestimmt, welche Angebote in diesem Modul geladen werden.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_load_step'] = ['Ladeschritte', 'Die Anzahl Datensätze pro Anfrage, soweit vorhanden.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_load_max'] = ['Maximale Anzahl Datensätze', 'Die maximale Anzahl Datensätze, soweit vorhanden. 0 = Unbegrenzt.'];

$GLOBALS['TL_LANG']['tl_module']['gutesio_data_layoutType'] = ['Layout', 'Bestimmt, welches Layout für die Auflistung verwendet wird.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_redirect_page'] = ['Weiterleitungsseite', 'Wenn dieses Modul keine Details anzeigen soll, können Sie hier eine alternative Detailseite auswählen. Der Alias der Datensätze wird an die URL dieser Detailseite angehängt.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_showcase_list_page'] = ['Seite mit Schaufensterliste', 'Wählen Sie die Seite aus, auf der die Schaufensterliste eingebunden ist (für Weiterleitung erforderlich).'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_offer_list_page'] = ['Seite mit Angebotsliste', 'Wählen Sie die Seite aus, auf der die Angebotsliste eingebunden ist (für Weiterleitung erforderlich).'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_show_details'] = ['Details anzeigen', 'Bestimmt, ob dieses Modul eine Detailansicht haben soll.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_render_searchHtml'] = ['HTML für Contao-Suche erzeugen', 'Bestimmt, ob dieses Modul das HTML für die Contao-Suche erzeugen soll. Dies sollte nur bei der Haupt-Schaufensterliste aktiv sein.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_limit'] = ['Ladeschritte', 'Bestimmt, wieviele Datensätze jeweils in einer Anfrage geladen werden (Standard: 30).'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_max_data'] = ['Maximale Anzahl Datensätze', 'Bestimmt, wieviele Datensätze maximal in der Liste angezeigt werden (0 bedeutet keine Begrenzung).'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode'] = ['Lademodus', 'Bestimmt, wie die Daten geladen werden.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_restrict_postals'] = ['Postleitzahlgebiete', 'Hier kann eine kommagetrennte Liste von Postleitzahlen angegeben werden. Nur Schaufenster mit einer dieser Postleitzahlen werden dann dargestellt. Wenn keine Angabe gemacht wird, werden alle Postleitzahlen geladen.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_load_klaro_consent'] = ['Klaro Consent-Tool aktivieren', 'Lädt das Klaro Consent-Tool für die Anzeige von YouTube- und Vimeo-Videos.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_carousel_template'] = ['Eigenes Template verwenden', 'Wenn gewünscht, kann hier ein eigenes Template ausgewählt werden, welches vom Carousel-Modul verwendet wird (der Name der Template-Datei muss dann "mit mod_gutesio_showcase_carousel_module_" beginnen).'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_show_city'] = ['Ort in Liste anzeigen', 'Zeigt den Ort ebenfalls in der Liste an.'];

$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting'] = ['Initiale Sortierung', 'Ändert die initiale Sortierung (Standard: zufällig).'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_enable_filter'] = ['Filter aktivieren', 'Aktiviert den Filter oberhalb der Liste.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_change_layout_filter'] = ['Layout der Liste nach Filtern ändern', 'Setzen Sie diese Checkbox, wenn die Liste ihr Layout verändern soll, nachdem eine Filtereingabe getätigt wurde.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_layout_filter'] = ['Layout der Liste nach Filtern', 'Bestimmt, in welchem Layout die Liste nach Filtereingabe dargestellt wird.'];

$GLOBALS['TL_LANG']['tl_module']['gutesio_data_type'] = ['Kategorie', 'Nur Schaufenster dieser Kategorie(n) werden angezeigt.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_blocked_types'] = ['Kategorien (Negativauswahl)', 'Schaufenster dieser Kategorie(n) werden nicht angezeigt.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_directory'] = ['Verzeichnis', 'Nur Schaufenster in Kategorien aus diesem Verzeichnis bzw. diesen Verzeichnissen werden angezeigt.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_tags'] = ['Tags', 'Nur Schaufenster, die eines dieser Tags zugeordnet haben, werden angezeigt.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_enable_tag_filter'] = ['Tag-Filter', 'Aktiviert die Tag-Auswahl über dieser Schaufensterliste.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_tag_filter_selection'] = ['Tag-Auswahl', 'Bestimmt die Tags, die im Tag-Filter zur Verfügung stehen.'];

$GLOBALS['TL_LANG']['tl_module']['gutesio_enable_type_filter'] = ['Kategorie-Filter', 'Aktiviert die Kategorie-Auswahl über dieser Schaufensterliste.'];

$GLOBALS['TL_LANG']['tl_module']['gutesio_child_search_label'] = ['Label des Suchfeldes', 'Das Label des Suchfeldes.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_filter'] = ['Angebotsfilter', 'Wählen Sie aus, welche Filter über der Liste zur Verfügung stehen sollen.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_search_placeholder'] = ['Platzhalter des Suchfeldes', 'Der Platzhalter des Scuhfeldes.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_search_description'] = ['Beschreibung des Suchfeldes', 'Die Beschreibung des Suchfeldes.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_text_search'] = ['Text vor Suche', 'Der statt der Ergebnisliste ausgegebene Text, wenn noch nicht gesucht wurde.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_text_no_results'] = ['Text keine Ergebnisse', 'Der statt der Ergebnisliste ausgegebene Text, wenn keine Ergebnisse gefunden wurden.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_headline_search_results'] = ['Überschrift der Ergebnisliste', 'Überschrift über der Ergebnisliste, wenn vorhanden.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_headline_recent'] = ['Überschrift über der Liste der neuesten Angebote', 'Überschrift über der Liste der neuesten Angebote.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_showcase_link'] = ['Link zu den Schaufenstern', 'Die Seite, auf der die Schaufenster eingebunden sind.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_type'] = ['Anzuzeigende Typen', 'Nur der gewählte Typ wird angezeigt.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_category'] = ['Anzuzeigende Kategorien', 'Nur der gewählte Kategorien werden angezeigt.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_tag'] = ['Anzuzeigende Tags', 'Nur Angebote, denen mindestens eines dieser Tags zugeordnet wurde, werden angezeigt.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_sort_by_date'] = ['Initial nach Datum sortieren', 'Sortiert die Angebote initial nach ihrem Beginndatum. Achtung: Wird nur beachtet, wenn nur Veranstaltungen in der Liste dargestellt werden.'];

$GLOBALS['TL_LANG']['tl_module']['cart_payment_url'] = ['Link zur Bezahlseite', 'Die Seite, auf der der Bezahlprozess durchgeführt wird.'];
$GLOBALS['TL_LANG']['tl_module']['cart_no_items_text'] = ['Text bei leerem Warenkorb', 'Dieser Text wird angezeigt, wenn der Warenkorb leer ist.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_show_contact_data'] = ['Kontaktdaten anzeigen', 'Ist die Checkbox gesetzt, dann werden Kontaktdaten in der Merkliste angezeigt.'];
$GLOBALS['TL_LANG']['tl_module']['cart_page'] = ['Warenkobseite', 'Seite, auf der sich das Warenkorbmodul befindet.'];

$GLOBALS['TL_LANG']['tl_module']['generic_legend'] = 'Allgemeine Einstellungen';
$GLOBALS['TL_LANG']['tl_module']['load_legend'] = 'Lade-Einstellungen';
$GLOBALS['TL_LANG']['tl_module']['showcase_filter_legend'] = 'Filter-Einstellungen';
$GLOBALS['TL_LANG']['tl_module']['showcase_tag_filter_legend'] = 'Tag-Filter-Einstellungen';
$GLOBALS['TL_LANG']['tl_module']['cart_legend'] = 'Warenkorb-Einstellungen';

$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting_option']['random'] = 'zufällig';
$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting_option']['name_asc'] = 'aufsteigend';
$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting_option']['name_desc'] = 'absteigend';
$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting_option']['tstamp_desc'] = 'neueste';
$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting_option']['distance'] = 'Entfernung';

$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode_option']['0'] = 'Alle Schaufenster laden';
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode_option']['1'] = 'Schaufenster nach Kategorien laden';
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode_option']['2'] = 'Schaufenster nach Verzeichnissen laden';
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode_option']['3'] = 'Schaufenster nach Tags laden';
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode_option']['4'] = 'Schaufensterkategorien ausschließen';

$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option']['0'] = 'Alle Angebote laden';
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option']['1'] = 'Angebote nach Typ laden';
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option']['2'] = 'Angebote nach Kategorie laden';
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option']['3'] = 'Angebote nach Tags laden';

$GLOBALS['TL_LANG']['tl_module']['gutesio_child_filter_option']['price'] = "Preissortierung";
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_filter_option']['range'] = "Datumsfilter";

$GLOBALS['TL_LANG']['tl_module']['gutesio_data_layoutType']['options']['plain'] = "Minimales Styling";
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_layoutType']['options']['list'] = "Listenansicht";
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_layoutType']['options']['grid'] = "Grid-Ansicht";

$GLOBALS['TL_LANG']['tl_module']['optional_heading_hint'] = "Das Feld 'Überschrift' ist optional.
                    Die Überschrift wird zwischen Filter und Liste ausgegeben.";
$GLOBALS['TL_LANG']['tl_module']['gutes_heading_hint'] = 'Die Auswahl des h-Tags (z.B. h3) entscheidet auch über die jeweilige Überschrift der Listenelemente.
                    Beispiel: Wird für die Überschrift h3 gewählt,
                    erhalten die Listenelemente darunter jeweils eine h4-Überschrift.';