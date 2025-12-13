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
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode'] = ['Lademodus', 'Bestimmt, welche Inhalte in diesem Modul geladen werden.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_load_step'] = ['Ladeschritte', 'Die Anzahl Datensätze pro Anfrage, soweit vorhanden.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_load_max'] = ['Maximale Anzahl Datensätze', 'Die maximale Anzahl Datensätze, soweit vorhanden. 0 = Unbegrenzt.'];

$GLOBALS['TL_LANG']['tl_module']['gutesio_data_layoutType'] = ['Layout', 'Bestimmt, welches Layout für die Auflistung verwendet wird.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_redirect_page'] = ['Weiterleitungsseite', 'Wenn dieses Modul keine Details anzeigen soll, können Sie hier eine alternative Detailseite auswählen. Der Alias der Datensätze wird an die URL dieser Detailseite angehängt.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_showcase_list_page'] = ['Seite mit Schaufensterliste', 'Wählen Sie die Seite aus, auf der die Schaufensterliste eingebunden ist (für Weiterleitung erforderlich).'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_offer_list_page'] = ['Seite mit Inhaltsliste', 'Wählen Sie die Seite aus, auf der die Inhaltsliste eingebunden ist (für Weiterleitung erforderlich).'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_show_details'] = ['Details anzeigen', 'Bestimmt, ob dieses Modul eine Detailansicht haben soll.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_render_searchHtml'] = ['HTML für Contao-Suche erzeugen', 'Bestimmt, ob dieses Modul das HTML für die Contao-Suche erzeugen soll. Dies sollte nur bei der Haupt-Schaufensterliste aktiv sein.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_limit'] = ['Ladeschritte', 'Bestimmt, wieviele Datensätze jeweils in einer Anfrage geladen werden (Standard: 30).'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_max_data'] = ['Maximale Anzahl Datensätze', 'Bestimmt, wieviele Datensätze maximal in der Liste angezeigt werden (0 bedeutet keine Begrenzung).'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode'] = ['Lademodus', 'Bestimmt, wie die Daten geladen werden.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_restrict_postals'] = ['Postleitzahlgebiete', 'Hier kann eine kommagetrennte Liste von Postleitzahlen angegeben werden. Nur Schaufenster mit einer dieser Postleitzahlen werden dann dargestellt. Wenn keine Angabe gemacht wird, werden alle Postleitzahlen geladen.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_load_klaro_consent'] = ['Klaro Consent-Tool aktivieren', 'Lädt das Klaro Consent-Tool für die Anzeige von YouTube- und Vimeo-Videos.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_carousel_template'] = ['Eigenes Template verwenden', 'Wenn gewünscht, kann hier ein eigenes Template ausgewählt werden, welches vom Carousel-Modul verwendet wird (der Name der Template-Datei muss dann "mit mod_gutesio_showcase_carousel_module_" beginnen).'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_show_city'] = ['Ort in Liste anzeigen', 'Zeigt den Ort ebenfalls in der Liste an.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_show_category'] = ['Kategorien in Liste anzeigen', 'Zeigt die Kategorien in der Liste an.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_show_image'] = ['Bild in Liste anzeigen', 'Zeigt das Hauptbild in der Liste an.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_show_selfHelpFocus'] = ['Schwerpunktthemen anzeigen (Selbsthilfe)', 'Zeigt die Schwerpunkte in der Liste an.'];

$GLOBALS['TL_LANG']['tl_module']['gutesio_initial_sorting'] = ['Initiale Sortierung', 'Ändert die initiale Sortierung (Standard: zufällig).'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_enable_filter'] = ['Filter aktivieren', 'Aktiviert den Filter oberhalb der Liste.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_enable_ext_filter'] = ['Filter-Button aktivieren', 'Aktiviert den Filterbutton oberhalb der Liste.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_change_layout_filter'] = ['Layout der Liste nach Filtern ändern', 'Setzen Sie diese Checkbox, wenn die Liste ihr Layout verändern soll, nachdem eine Filtereingabe getätigt wurde.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_layout_filter'] = ['Layout der Liste nach Filtern', 'Bestimmt, in welchem Layout die Liste nach Filtereingabe dargestellt wird.'];

$GLOBALS['TL_LANG']['tl_module']['gutesio_data_type'] = ['Kategorie', 'Nur Schaufenster dieser Kategorie(n) werden angezeigt.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_blocked_types'] = ['Kategorien (Negativauswahl)', 'Schaufenster dieser Kategorie(n) werden nicht angezeigt.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_directory'] = ['Verzeichnis', 'Nur Schaufenster in Kategorien aus diesem Verzeichnis bzw. diesen Verzeichnissen werden angezeigt.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_tags'] = ['Tags', 'Nur Schaufenster, die eines dieser Tags zugeordnet haben, werden angezeigt.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_enable_tag_filter'] = ['Tag-Filter', 'Aktiviert die Tag-Auswahl über dieser Schaufensterliste.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_enable_location_filter'] = ['Ort-Filter', 'Aktiviert die PLZ- und Orteingabe.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_tag_filter_selection'] = ['Tag-Auswahl', 'Bestimmt die Tags, die im Tag-Filter zur Verfügung stehen.'];

$GLOBALS['TL_LANG']['tl_module']['gutesio_data_elements'] = ['Schaufenster einschränken', 'Nur die ausgewählten Schaufenster werden berücksichtigt. Standard: alle.'];

$GLOBALS['TL_LANG']['tl_module']['gutesio_enable_type_filter'] = ['Kategorie-Filter', 'Aktiviert die Kategorie-Auswahl über dieser Schaufensterliste.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_type_filter_selection'] = ['Kategorie-Auswahl', 'Bestimmt die Kategorien, die im Kategorie-Filter zur Verfügung stehen. Wichtig! Ohne Auswahl werden alle Kategorien zur Auswahl gestellt.'];

$GLOBALS['TL_LANG']['tl_module']['gutesio_enable_category_filter'] = ['Kategorie-Filter', 'Aktiviert die Kategorie-Auswahl über dieser Schaufensterliste.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_category_filter_selection'] = ['Kategorie-Auswahl', 'Bestimmt die Kategorien, die im Kategorie-Filter zur Verfügung stehen. Wichtig! Ohne Auswahl werden alle Kategorien zur Auswahl gestellt.'];

$GLOBALS['TL_LANG']['tl_module']['gutesio_child_search_label'] = ['Label des Suchfeldes', 'Das Label des Suchfeldes.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_filter'] = ['Inhaltsfilter', 'Wählen Sie aus, welche Filter über der Liste zur Verfügung stehen sollen.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_search_placeholder'] = ['Platzhalter des Suchfeldes', 'Der Platzhalter des Scuhfeldes.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_search_description'] = ['Beschreibung des Suchfeldes', 'Die Beschreibung des Suchfeldes.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_text_search'] = ['Text vor Suche', 'Der statt der Ergebnisliste ausgegebene Text, wenn noch nicht gesucht wurde.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_text_no_results'] = ['Text keine Ergebnisse', 'Der statt der Ergebnisliste ausgegebene Text, wenn keine Ergebnisse gefunden wurden.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_headline_search_results'] = ['Überschrift der Ergebnisliste', 'Überschrift über der Ergebnisliste, wenn vorhanden.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_headline_recent'] = ['Überschrift über der Liste der neuesten Inhalte', 'Überschrift über der Liste der neuesten Inhalte.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_showcase_link'] = ['Link zu den Schaufenstern', 'Die Seite, auf der die Schaufenster eingebunden sind.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_type'] = ['Anzuzeigende Typen', 'Nur der gewählte Typ wird angezeigt.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_category'] = ['Anzuzeigende Kategorien', 'Nur der gewählte Kategorien werden angezeigt.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_tag'] = ['Anzuzeigende Tags', 'Nur Inhalte, denen mindestens eines dieser Tags zugeordnet wurde, werden angezeigt.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_sort_by_date'] = ['Initial nach Datum sortieren', 'Sortiert die Inhalte initial nach ihrem Beginndatum. Achtung: Wird nur beachtet, wenn nur Veranstaltungen in der Liste dargestellt werden.'];
//$GLOBALS['TL_LANG']['tl_module']['gutesio_child_determine_orientation'] = ['Orientierung ermitteln', 'Bei den Listenbildern soll die Orientierung ermittelt werden. Achtung! Zeitintensiv.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_disable_sorting_filter'] = ['Sortierfilter ausblenden', 'Blendet die Sortiermöglichkeiten aus. sehr Sinnvoll bei einer Veranstaltungsliste.'];

$GLOBALS['TL_LANG']['tl_module']['gutesio_without_tiles'] = ['Kacheln unterhalb ausblenden','Alle Kacheln unterhalb der Details werden ausgeblendet.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_without_contact'] = ['Kontaktdaten ausblenden','Alle Kontaktdaten werden ausgeblendet.'];

$GLOBALS['TL_LANG']['tl_module']['cart_payment_url'] = ['Link zur Bezahlseite', 'Die Seite, auf der der Bezahlprozess durchgeführt wird.'];
$GLOBALS['TL_LANG']['tl_module']['cart_no_items_text'] = ['Text bei leerem Warenkorb', 'Dieser Text wird angezeigt, wenn der Warenkorb leer ist.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_show_contact_data'] = ['Kontaktdaten anzeigen', 'Ist die Checkbox gesetzt, dann werden Kontaktdaten in der Merkliste angezeigt.'];
$GLOBALS['TL_LANG']['tl_module']['cart_page'] = ['Warenkobseite', 'Seite, auf der sich das Warenkorbmodul befindet.'];

$GLOBALS['TL_LANG']['tl_module']['generic_legend'] = 'Allgemeine Einstellungen';
$GLOBALS['TL_LANG']['tl_module']['load_legend'] = 'Lade-Einstellungen';
$GLOBALS['TL_LANG']['tl_module']['showcase_filter_legend'] = 'Filter-Einstellungen';
$GLOBALS['TL_LANG']['tl_module']['showcase_tag_filter_legend'] = 'Tag-Filter-Einstellungen';
$GLOBALS['TL_LANG']['tl_module']['content_legend'] = 'Einstellungen zum Content';
$GLOBALS['TL_LANG']['tl_module']['cart_legend'] = 'Warenkorb-Einstellungen';
$GLOBALS['TL_LANG']['tl_module']['appearance_legend'] = 'Anpassungseinstellungen';
$GLOBALS['TL_LANG']['tl_module']['performance_legend'] = 'Leistungseinstellungen';

$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_theme_color'] = [
    'Banner-Theme-Farbe',
    'Primäre Akzentfarbe für Overlays, das Werbelabel und die Powered-by-Leiste (Hex, z. B. #2ea1db).'
];

// Banner: Videos abspielen
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_play_videos'] = [
    'Videos ausspielen',
    'Spielt Videos im Banner ab. Berücksichtigt MP4-Dateien aus den ausgewählten Ordnern sowie Video-Links (Feld "videoLink") der Inhalte. Bei vorhandenem Video wird zusätzlich eine Video-Slide eingefügt (neben der Bild-Slide).'
];

// Banner: Videos stumm schalten
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_mute_videos'] = [
    'Videos stumm schalten',
    'Schaltet alle Videos (MP4 und YouTube) standardmäßig stumm. Empfohlen für Werbebildschirme.'
];

// Banner: Event-Overlay auf Videos
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_show_event_overlay'] = [
    'Event-Overlay auf Videos',
    'Blendet auf Video-Slides von Veranstaltungen ein dezentes Overlay mit „Veranstaltungsort, Datum Uhrzeit“ ein. Gilt für YouTube- und MP4-Videos von Events.'
];

// Banner: Bilder vollflächig im Hintergrund
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_media_bg_full'] = [
    'Bilder vollflächig im Hintergrund',
    'Stellt Fotos/Bilder immer vollflächig (object-fit: cover) dar. Standard: aktiv.'
];

// Banner: Overlay-Transparenz
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_overlay_opacity'] = [
    'Overlay-Transparenz (%)',
    'Legt die Transparenz der Overlays fest (0–100). 0 = Standard (Template-Voreinstellung). Typische Werte: 45–80.'
];

// Banner: Footer auf Videos ausblenden
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_hide_footer_on_videos'] = [
    'Footer bei Videos ausblenden',
    'Blendt den Footer inklusive QR-Code automatisch aus, wenn eine Video-Slide aktiv ist.'
];

// Banner: Maximale Spieldauer für Videos
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_video_timeout'] = [
    'Max. Spieldauer für Videos (Sekunden)',
    'Legt fest, wie lange Videos maximal abgespielt werden, bevor zur nächsten Slide gewechselt wird. 0 = gesamte Videolänge. Standard: 180 Sekunden.'
];

// Banner: Strikter Bildmodus
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_strict_images'] = [
    'Strikten Bildmodus aktivieren',
    'Blendet Slides mit fehlenden Bildern aus. Es werden nur Bilder angezeigt, die lokal vorhanden sind oder vom CDN schnell bestätigt wurden.'
];

// Listen: Strikter Bildmodus (Showcase/Offer Listen)
$GLOBALS['TL_LANG']['tl_module']['gutesio_strict_images'] = [
    'Strikten Bildmodus für Listen aktivieren',
    'Blendet Listeneinträge ohne verfügbare Bilder aus. Es werden nur Bilder angezeigt, die lokal vorhanden sind oder vom CDN schnell bestätigt wurden.'
];

// Banner-Schutz (optional per GET-Parameter)
$GLOBALS['TL_LANG']['tl_module']['security_legend'] = 'Zugriffsschutz';
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_guard_param'] = [
    'Schutz-Parameter (GET)',
    'Optionaler Name eines GET-Parameters (z. B. "bannerKey"). Ist er gesetzt, wird das Banner nur geladen, wenn der Request diesen Parameter enthält.'
];
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_guard_value'] = [
    'Erwarteter Parameterwert',
    'Optionaler erwarteter Wert für den Schutz-Parameter. Leer lassen, wenn nur das Vorhandensein geprüft werden soll.'
];

// Banner: Links in neuem Tab öffnen
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_links_new_tab'] = [
    'Links in neuem Tab öffnen',
    'Öffnet Banner-Links in einem neuen Browser-Tab (target="_blank").'
];

// Banner: Wechselintervall konfigurierbar
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_interval'] = [
    'Slide-Wechselintervall (ms)',
    'Zeit zwischen zwei Slides in Millisekunden. Standard: 15000.'
];

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
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode_option']['5'] = 'Schaufenster auswählen';
$GLOBALS['TL_LANG']['tl_module']['gutesio_data_mode_option']['6'] = 'Kein Schaufenster laden';

$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option']['0'] = 'Alle Inhalte laden';
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option']['1'] = 'Inhalte nach Typ laden';
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option']['2'] = 'Inhalte nach Kategorie laden';
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option']['3'] = 'Inhalte nach Tags laden';
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option']['4'] = 'Inhalte auswählen';
$GLOBALS['TL_LANG']['tl_module']['gutesio_child_data_mode_option']['5'] = 'Keinen Inhalt laden';

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

$GLOBALS['TL_LANG']['tl_module']['gutesio_max_childs'] = ['Maximale Anzahl an Inhalten pro Schaufenster','Sie können die Anzahl an Angeboten pro Schaufenster einschränken (Standard: 0 = unbegrenzt).'];
$GLOBALS['TL_LANG']['tl_module']['lazyBanner'] = ['Bilder nachladen','Bilder nachladen statt initial alles zu laden.'];
$GLOBALS['TL_LANG']['tl_module']['reloadBanner'] = ['Banner automatisch aktualisieren','Banner automatisiert nach einer Stunde neu laden.'];
$GLOBALS['TL_LANG']['tl_module']['loadMonth'] = ['Inhalte der nächsten X Monate','Wie viele Monate sollen die Veranstaltungen im Voraus angezeigt werden (Standard: 6)?'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_check_position'] = ['Positionsermittlung aktivieren', 'Über diese Checkbox kann die Positionsermittlung für die Liste geschaltet werden. Wenn nicht aktiv, werden statt den nächsten Schaufenstern zufällige Schaufenster geladen.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_show_detail_link'] = ['Detail-Link anzeigen', 'Über diese Checkbox kann der Link auf die Detailseite ein- und ausgeblendet werden.'];
$GLOBALS['TL_LANG']['tl_module']['limit_detail_offers'] = ['Angebotsanzahl begrenzen', 'Hier kann die Anzahl der in den Details angezeigten weiteren Angebote des Anbieters begrenzt werden.'];
$GLOBALS['TL_LANG']['tl_module']['gutesio_hide_events_without_date'] = ['Veranstaltungen ohne Datum ausblenden', 'Sollen Veranstaltungen ohne Datum angezeigt werden oder nicht?'];
// Banner module: mix images from folder
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_folder'] = [
    'Bilder-Verzeichnis(se)',
    'Wählen Sie ein oder mehrere Verzeichnisse aus der Dateiverwaltung (tl_files). Alle Bilder aus den ausgewählten Verzeichnissen werden zufällig in den Slider gemischt. Der in den Metadaten gesetzte Link wird automatisch als Bildlink verwendet.'
];

$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_skip_unlinked'] = [
    'Bilder ohne Link überspringen',
    'Wenn aktiviert, werden Bilder ohne in den Metadaten gesetzten Link übersprungen.'
];

// Banner module: Darstellung & Footer
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_fullscreen'] = [
    'Fullscreen erzwingen',
    'Bilder bildschirmfüllend darstellen (100% der Bildschirmfläche). Bilder werden zentriert mit Zuschnitt (object-fit: cover) angezeigt.'
];

$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_hide_poweredby'] = [
    'Powered-by-Footer ausblenden',
    'Wenn aktiviert, wird der „Powered by“-Footer nicht angezeigt.'
];

$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_poweredby_text'] = [
    'Powered-by-Text',
    'Optionaler Text für den Footer (Standard: "Powered by").'
];

// Footer-Ausrichtung (Kontakt + Logo linksbündig)
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_footer_align_left'] = [
    'Footer linksbündig (Kontakt + Logo)',
    'Wenn aktiviert, werden „Angeboten von …“ und das Logo im Footer linksbündig angezeigt. Der QR‑Code bleibt rechts, sofern Platz ist, oder wird darunter umgebrochen.'
];

// Banner: Werbelabel „Anzeige“
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_show_ad_label'] = [
    'Werbelabel „Anzeige“ anzeigen',
    'Blendet im Banner ein kleines Label „Anzeige“ oben links ein, um bezahlte oder fremdfinanzierte Inhalte klar zu kennzeichnen.'
];

// Medien als Hintergrund im Portrait
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_media_bg_portrait'] = [
    'Medien im Hochformat als Hintergrund darstellen',
    'Zeigt Bilder/Videos in der mobilen Portrait-Ansicht hinter dem Inhalt (ohne Beschnitt). Overlay/QR/Logo/Footerteil liegen darüber. Der Slide bleibt klickbar.'
];

// Performance / Lazy-Loading Optionen
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_lazy_mode'] = [
    'Lazy-Loading Modus',
    'Steuert, wie die Slides initial gerendert und Medien nachgeladen werden (für bessere SEO/LCP in Websites).'
];
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_lazy_mode_option']['0'] = 'Aus (Kompatibilitätsmodus)';
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_lazy_mode_option']['1'] = 'Native Lazy (alle Slides, Bilder/Video lazy)';
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_lazy_mode_option']['2'] = 'SEO Static-First (nur erster Slide, Rest nachladen)';

$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_defer_assets'] = [
    'Slider-Assets verzögert laden',
    'Lädt die Tiny‑Slider Skripte/Styles erst, wenn das Modul in den Viewport kommt.'
];

$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_limit_initial'] = [
    'Anzahl initialer Slides',
    'Wie viele Slides werden initial serverseitig gerendert (nur für "SEO Static-First", Standard: 1).'
];

$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_defer_qr'] = [
    'QR-Codes für nachgeladene Slides überspringen',
    'Reduziert die Initialkosten, indem QR‑Codes erst nachgeladen/gebaut werden (betrifft nur nicht initial gerenderte Slides).'
];

// Banner: QR auch für Bild-Slides mit Link
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_qr_for_images'] = [
    'QR‑Code auch für Bilder mit Link generieren',
    'Wenn aktiviert, wird für Bild‑Slides mit gesetztem Link ebenfalls ein QR‑Code erzeugt und im Footer angezeigt.'
];

// Banner: Option zum Ausblenden der Endzeit bei Veranstaltungen
$GLOBALS['TL_LANG']['tl_module']['gutesio_banner_hide_event_endtime'] = [
    'Endzeit bei Veranstaltungen ausblenden',
    'Wenn aktiviert, zeigen Veranstaltungs‑Slides nur die Startzeit an (keine Endzeit).'
];
