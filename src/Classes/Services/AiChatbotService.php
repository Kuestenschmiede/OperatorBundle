<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.
 * @package    gutesio
 * @copyright (c) 2010-2026, by Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */

namespace gutesio\OperatorBundle\Classes\Services;

use Contao\Database;
use Contao\PageModel;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use gutesio\OperatorBundle\Classes\Curl\CurlPostRequest;
use con4gis\CoreBundle\Resources\contao\models\C4gLogModel;

class AiChatbotService
{
    /**
     * @var \con4gis\MapsBundle\Classes\Services\AreaService
     */
    private $areaService;

    /**
     * @var \con4gis\MapsBundle\Classes\Services\RouteService
     */
    private $routeService;

    public function __construct(
        \con4gis\MapsBundle\Classes\Services\AreaService $areaService,
        \con4gis\MapsBundle\Classes\Services\RouteService $routeService
    ) {
        $this->areaService = $areaService;
        $this->routeService = $routeService;
    }

    public function getTableConfig(): array
    {
        $config = [
            [
                'table' => 'tl_gutesio_data_element',
                'fields' => 'name, description, locationStreet, locationStreetNumber, locationZip, locationCity, phone, email, website, opening_hours'
            ],
            [
                'table' => 'tl_gutesio_data_child_product',
                'baseTable' => 'tl_gutesio_data_child',
                'fields' => 't1.name, t1.description, t1.shortDescription, t2.price, t2.strikePrice, t2.brand'
            ],
            [
                'table' => 'tl_gutesio_data_child_event',
                'baseTable' => 'tl_gutesio_data_child',
                'fields' => 't1.name, t1.description, t1.shortDescription, t2.beginDate, t2.endDate, t2.beginTime, t2.endTime, t2.eventPrice'
            ],
            [
                'table' => 'tl_gutesio_data_child_job',
                'baseTable' => 'tl_gutesio_data_child',
                'fields' => 't1.name, t1.description, t1.shortDescription, t2.applicationContactEMail, t2.applicationContactPhone, t2.workHours'
            ],
            [
                'table' => 'tl_gutesio_data_child_voucher',
                'baseTable' => 'tl_gutesio_data_child',
                'fields' => 't1.name, t1.description, t1.shortDescription, t2.credit'
            ],
            [
                'table' => 'tl_gutesio_data_child_person',
                'baseTable' => 'tl_gutesio_data_child',
                'fields' => 't1.name, t1.description, t1.shortDescription, t2.dateOfBirth'
            ],
            [
                'table' => 'tl_gutesio_data_child_realestate',
                'baseTable' => 'tl_gutesio_data_child',
                'fields' => 't1.name, t1.description, t1.shortDescription'
            ],
            [
                'table' => 'tl_gutesio_data_child_exhibition',
                'baseTable' => 'tl_gutesio_data_child',
                'fields' => 't1.name, t1.description, t1.shortDescription'
            ],
            [
                'table' => 'tl_gutesio_data_child',
                'fields' => 'name, description, shortDescription',
                'where' => "typeId IN ('arrangement', 'service')"
            ]
        ];

        // Filter out tables that do not exist
        $db = Database::getInstance();
        return array_filter($config, function($item) use ($db) {
            if (!$db->tableExists($item['table'])) {
                return false;
            }
            if (isset($item['baseTable']) && !$db->tableExists($item['baseTable'])) {
                return false;
            }
            return true;
        });
    }

    public function getResponse(string $userQuestion, $lat = null, $lon = null, array $history = [], string $threadId = null): array
    {
        $startTotal = microtime(true);
        $settings = GutesioOperatorSettingsModel::findSettings();
        if (!$settings || !$settings->aiEnabled) {
            return ['answer' => "KI Chatbot ist nicht aktiviert."];
        }

        $userCoords = null;
        if ($lat !== null && $lon !== null) {
            $userCoords = [floatval($lon), floatval($lat)]; // AreaService expects [lon, lat]
        }

        // We now use a hybrid approach:
        // 1. A global base context (the latest records) - cached for performance
        // 2. A dynamic search context based on the user question - generated on the fly
        
        $cacheKey = 'ai_context_base_' . ($settings->aiMaxContextRecords ?: 'default');
        $cache = \Contao\System::getContainer()->get('cache.app');
        $cachedBaseData = $cache->getItem($cacheKey);

        if ($cachedBaseData->isHit()) {
            $baseData = $cachedBaseData->get();
            $cacheHit = true;
        } else {
            $baseData = $this->prepareContext($settings, "", 50); // Latest 50 records as base
            $cachedBaseData->set($baseData);
            $cachedBaseData->expiresAfter(3600); // 1 hour cache
            $cache->save($cachedBaseData);
            $cacheHit = false;
        }

        // Merge base and search data, ensuring uniqueness by UUID
        $contextStart = microtime(true);
        // We search using the latest question and potentially the whole conversation context
        // but for performance we primarily search with the latest question
        $searchData = $this->prepareContext($settings, $userQuestion, 100); 
        
        $contextData = $this->mergeContextData($baseData, $searchData);
        $contextEnd = microtime(true);

        // Extract search terms for potential follow-up questions
        $searchTerms = $this->extractSearchTerms($userQuestion);
        
        // If it's a short response/follow-up, also try to extract search terms from history
        if ($userQuestion !== "" && count($searchTerms) === 0 && count($history) > 0) {
            $lastUserMsg = "";
            for ($i = count($history) - 1; $i >= 0; $i--) {
                if ($history[$i]['role'] === 'user' && strlen($history[$i]['content']) > 5) {
                    $lastUserMsg = $history[$i]['content'];
                    break;
                }
            }
            if ($lastUserMsg) {
                $historySearchTerms = $this->extractSearchTerms($lastUserMsg);
                if (!empty($historySearchTerms)) {
                    C4gLogModel::addLogEntry("operator", "AI Chatbot: No search terms in current question, using history terms: " . implode(", ", $historySearchTerms));
                    $historySearchData = $this->prepareContext($settings, $lastUserMsg, 50, $historySearchTerms);
                    // Add to existing searchData (avoiding duplicates)
                    foreach ($historySearchData['records'] as $uuid => $rec) {
                        if (!isset($searchData['records'][$uuid])) {
                            $searchData['records'][$uuid] = $rec;
                        }
                    }
                    // Re-merge
                    $contextData = $this->mergeContextData($baseData, $searchData);
                }
            }
        }

        // Check if user specifically asks for directions/routing
        $routingTrigger = ['wegbeschreibung', 'weg', 'route', 'wie komme ich', 'anfahrt', 'richtung', 'pfad', 'routing', 'navigation'];
        $wantsRouting = false;
        foreach ($routingTrigger as $trigger) {
            if (mb_stripos($userQuestion, $trigger) !== false) {
                $wantsRouting = true;
                break;
            }
        }

        // Also check history for routing intent if the current question is short
        if (!$wantsRouting && count($history) > 0 && strlen($userQuestion) < 50) {
            $lastUserMsg = "";
            for ($i = count($history) - 1; $i >= 0; $i--) {
                if ($history[$i]['role'] === 'user') {
                    $lastUserMsg = $history[$i]['content'];
                    break;
                }
            }
            if ($lastUserMsg) {
                foreach ($routingTrigger as $trigger) {
                    if (mb_stripos($lastUserMsg, $trigger) !== false) {
                        $wantsRouting = true;
                        break;
                    }
                }
            }
        }

        $context = $contextData['context'];
        $tileData = $contextData['tileData'];
        $distanceData = $contextData['distanceData'] ?? [];

        // Add dynamic distances and optionally routing instructions if coordinates are provided
        if ($userCoords) {
            $locationNote = "Der Standort des Benutzers ist freigegeben und bekannt (Lat: " . $lat . ", Lon: " . $lon . "). Nutze die Entfernungsangaben bei den Objekten für Empfehlungen.\n";
            if ($wantsRouting) {
                $locationNote .= "Der Benutzer möchte eine Wegbeschreibung erhalten. Priorisiere die Wegbeschreibung aus dem Kontext gegenüber allgemeinen Hinweisen.\n";
                C4gLogModel::addLogEntry("operator", "AI Chatbot: User wants routing.");
            }
            $contextLines = explode("\n", trim($context));
            $newContext = "";
            $routingAdded = false;

            foreach ($contextLines as $line) {
                $uuidMatch = [];
                if (preg_match('/\[([a-f0-9-]+)\]/', $line, $uuidMatch)) {
                    $uuid = $uuidMatch[1];
                    if (isset($distanceData[$uuid])) {
                        $coords = $distanceData[$uuid];
                        $distance = $this->areaService->calculateDistance($userCoords, $coords);
                        if ($distance < 1000) {
                            $line .= ", Entf: " . round($distance) . "m";
                        } else {
                            $line .= ", Entf: " . round($distance / 1000, 1) . "km";
                        }

                        // If user wants routing, we add directions for the first found relevant item (nearest if possible)
                        // In a real search, the first records are usually the most relevant
            if ($wantsRouting && !$routingAdded && $distance < 50000) { // Increased to 50km
                            $directions = $this->getRouteDirections($userCoords, $coords, $settings);
                            if ($directions && mb_strlen($directions) > 10) {
                                $line .= ", Wegbeschreibung zu Fuß: " . $directions;
                                $routingAdded = true;
                                // Explicitly mark this line as having the desired routing
                                $line = ">>> GEWÜNSCHTE ROUTE: " . $line;
                                C4gLogModel::addLogEntry("operator", "AI Chatbot: Routing added to context for " . $uuid);
                            } else {
                                $reason = $directions ?: 'null';
                                C4gLogModel::addLogEntry("operator", "AI Chatbot Routing failed or empty for " . $uuid . " (distance: " . $distance . "m). Result: " . $reason);
                                $locationNote .= "HINWEIS: Die Routenberechnung für '" . $uuid . "' lieferte kein Ergebnis: " . $reason . "\n";
                            }
                        }
                    }
                }
                $newContext .= $line . "\n";
            }
            if ($wantsRouting && !$routingAdded) {
                // Try one more time with the first context record if it has distance but no route yet
                // (Maybe it was just outside the first few lines we checked)
                $locationNote .= "HINWEIS: Für das gewünschte Ziel konnte keine Wegbeschreibung berechnet werden. Informiere den Benutzer darüber.\n";
                C4gLogModel::addLogEntry("operator", "AI Chatbot: No routing could be added to context.");
            }
            $context = $locationNote . $newContext;
        }

        // Final safety length check
        if (strlen($context) > 20000) {
            $context = mb_substr($context, 0, 19997) . "...";
        }

        $apiStart = microtime(true);
        $assistantName = $settings->aiAssistantName ?: 'KI';
        $additionalKnowledge = $settings->aiAdditionalKnowledge ?: '';
        $systemPrompt = "Du bist eine hilfreiche Assistentin namens " . $assistantName . ". Beantworte Fragen basierend auf den bereitgestellten Daten.
WICHTIG: Nutze ZUERST die Informationen aus dem Kontext. Das Projektwissen im Kontext ist aktueller und spezifischer als dein allgemeines Wissen.
" . ($additionalKnowledge ? "Zusätzliches Projektwissen:\n" . $additionalKnowledge . "\n\n" : "") . "
Nenne wichtige Details wie Adressen, Telefonnummern, Preise oder Öffnungszeiten nur dann, wenn sie für die Beantwortung der Frage wirklich relevant sind. Halte dich bei den Detailbeschreibungen kurz, da der Benutzer weitere Informationen über die bereitgestellten Links findet.
Nutze die Öffnungszeiten (opening_hours), um Fragen zur Verfügbarkeit heute oder an bestimmten Tagen zu beantworten.
Wenn der Standort des Benutzers freigegeben ist, steht dies explizit im Kontext (inkl. Koordinaten). In diesem Fall sind bei den Objekten oft Entfernungsangaben (z.B. 'Entf: 500m') enthalten. Nutze diese Daten aktiv, um Fragen zur Nähe oder Erreichbarkeit (\"Was ist in meiner Nähe?\", \"Wie weit ist es zu...\") präzise zu beantworten. Wenn der Standort bekannt ist, behaupte NIEMALS das Gegenteil und frage nicht nach dem Standort. Bestätige dem Benutzer bei Bedarf, dass du seinen Standort für die Empfehlungen berücksichtigst.
Wegbeschreibungen:
- Wenn im Kontext eine Zeile mit '>>> GEWÜNSCHTE ROUTE:' beginnt, enthält diese die vom Benutzer angeforderte Wegbeschreibung. Gib diese UNBEDINGT als Liste aus.
- Behaupte NIEMALS, dass du keine Wegbeschreibung oder Kartenansicht hast, wenn Informationen wie 'Wegbeschreibung zu Fuß' oder 'GEWÜNSCHTE ROUTE' im Kontext stehen.
- Wenn im Kontext eine Wegbeschreibung steht, nutze NUR diese und erfinde keine eigenen allgemeinen Beschreibungen.
- Falls der Benutzer nach einer Wegbeschreibung fragt, diese aber nicht im Kontext für das Zielobjekt steht (kein 'Wegbeschreibung zu Fuß' vorhanden), erkläre höflich, dass du für dieses spezifische Objekt gerade keine Route berechnen kannst (z.B. weil es zu weit weg ist oder Geodaten fehlen), anstatt nach dem Standort zu fragen.
Wichtig: Wenn du ein Schaufenster oder einen Inhalt (Event, Produkt, Job etc.) empfiehlst oder nennst, füge UNBEDINGT am Ende der jeweiligen Beschreibung den tag [TILE:UUID] ein (ersetze UUID durch die tatsächliche ID in eckigen Klammern aus dem Kontext, z.B. [TILE:1234-abcd]). Dies ermöglicht es dem System, eine visuelle Kachel anzuzeigen.
Verlinkung:
- Bevorzuge IMMER die internen Links (gekennzeichnet mit 'L:' oder 'Schaufenster-Link:').
- Wenn du Kontaktdaten (Telefon, E-Mail, Adresse) eines Angebots nennst, verlinke auch IMMER das zugehörige Schaufenster (Schaufenster-Link).
- Die Schaufenster und Angebote sollen möglichst immer im Ergebnis verlinkt werden.
- Gib externe Webseiten (gekennzeichnet mit 'website:') NUR aus, wenn der Benutzer explizit danach fragt.
- Gib jeden Link in einer EIGENEN Zeile aus, damit sie sauber getrennt sind.
- Füge KEINE Satzzeichen direkt am Ende einer URL an.

Antworte freundlich, kurz und präzise in der Sprache des Benutzers. Bitte die User duzen.";

        $newThreadId = $threadId;
        if ($settings->aiAssistantId) {
            $apiResult = $this->callAssistantApi($settings, $systemPrompt, $context, $userQuestion, $history, $threadId);
            $answer = $apiResult['answer'];
            $newThreadId = $apiResult['threadId'];
        } else {
            $answer = $this->callAiApi($settings, $systemPrompt, $context, $userQuestion, $history);
        }
        $apiEnd = microtime(true);
        
        // Performance logging
        $totalTime = round((microtime(true) - $startTotal) * 1000, 2);
        $contextTime = round(($contextEnd - $contextStart) * 1000, 2);
        $apiTime = round(($apiEnd - $apiStart) * 1000, 2);
        C4gLogModel::addLogEntry("operator", sprintf(
            "AI Chatbot Performance: Total %sms (Context: %sms, Cache: %s, API: %sms, ContextLength: %d chars)",
            $totalTime, $contextTime, ($cacheHit ? 'HIT' : 'MISS'), $apiTime, strlen($context)
        ));
        
        // Parse the answer for [TILE:UUID] tags and collect data for those tiles
        $foundTiles = [];
        $foundUuids = [];
        preg_match_all('/\[TILE:([a-f0-9-]+)\]/i', $answer, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $uuid) {
                if (isset($tileData[$uuid])) {
                    $foundTiles[] = $tileData[$uuid];
                    $foundUuids[] = $uuid;
                }
            }
        }

        // Fallback: If no tiles found but we had context, maybe the AI forgot the tag. 
        // We auto-append the most relevant tile if the user asked for directions or if only one tile is in context
        if (empty($foundTiles) && !empty($tileData)) {
            $relevantUuid = null;
            if ($wantsRouting) {
                // Find the one we added routing for
                foreach (explode("\n", $context) as $line) {
                    if (strpos($line, 'Wegbeschreibung zu Fuß:') !== false) {
                        preg_match('/\[([a-f0-9-]+)\]/', $line, $uuidMatch);
                        if (isset($uuidMatch[1])) {
                            $relevantUuid = $uuidMatch[1];
                            break;
                        }
                    }
                }
            }
            
            if (!$relevantUuid) {
                // Try to find a UUID from the context that is mentioned in the answer (e.g. by name)
                foreach ($tileData as $uuid => $tile) {
                    if (mb_stripos($answer, $tile['name']) !== false) {
                        $relevantUuid = $uuid;
                        break;
                    }
                }
            }
            
            if (!$relevantUuid && count($tileData) === 1) {
                $relevantUuid = array_key_first($tileData);
            }
            
            if ($relevantUuid && isset($tileData[$relevantUuid])) {
                $foundTiles[] = $tileData[$relevantUuid];
                // Also append the tag to the answer if it's missing to make it more obvious
                if (strpos($answer, "[TILE:" . $relevantUuid . "]") === false) {
                    $answer .= "\n\n[TILE:" . $relevantUuid . "]";
                }
            }
        }

        return [
            'answer' => $answer,
            'tiles' => $foundTiles,
            'threadId' => $newThreadId
        ];
    }

    private function extractSearchTerms(string $userQuestion): array
    {
        $searchTerms = [];
        if ($userQuestion === "") {
            return $searchTerms;
        }

        // Priority search for quoted terms or phrases
        if (preg_match('/"([^"]+)"/', $userQuestion, $matches)) {
            $searchTerms[] = $matches[1];
        }

        $words = preg_split('/[\s,?.!]+/', mb_strtolower($userQuestion), -1, PREG_SPLIT_NO_EMPTY);
        $stopWords = ['der', 'die', 'das', 'und', 'ein', 'eine', 'mit', 'für', 'von', 'aus', 'nach', 'ist', 'sind', 'was', 'wer', 'wie', 'wo', 'gibt', 'mich', 'mir', 'dir', 'dich', 'zeig', 'suche', 'finde', 'hallo', 'moin', 'bitte', 'danke', 'kannst', 'kann', 'geben', 'zeigen', 'mir', 'dir', 'mich', 'dich', 'euch', 'ihnen', 'uns', 'ihr', 'wir', 'sie', 'es', 'informationen', 'info', 'infos', 'details'];
        foreach ($words as $word) {
            if (strlen($word) > 2 && !in_array($word, $stopWords) && !in_array($word, $searchTerms)) {
                $searchTerms[] = $word;
            }
        }
        
        return $searchTerms;
    }

    private function prepareContext($settings, string $userQuestion = "", int $limit = 0, array $searchTerms = []): array
    {
        $context = "";
        $tileData = [];
        $distanceData = [];
        $records = []; // Store raw records to handle uniqueness during merge
        $syncConfig = $this->getTableConfig();
        
        $db = Database::getInstance();
        $maxRecords = $limit;
        if ($maxRecords <= 0) {
            $maxRecords = intval($settings->aiMaxContextRecords);
            if ($maxRecords <= 0) {
                $maxRecords = 100;
            }
        }
        
        $recordsCount = 0;
        $maxContextLength = 15000; // Reduced from 30000 to avoid TPM limits

        // Build search condition if user question is provided
        if (empty($searchTerms) && $userQuestion !== "") {
            $searchTerms = $this->extractSearchTerms($userQuestion);
        }

        C4gLogModel::addLogEntry("operator", "AI Chatbot Search Terms: " . implode(", ", $searchTerms));

        // If context length already exceeds limit, stop early
        if ($maxContextLength > 0 && strlen($context) >= $maxContextLength) {
            return ['records' => $records];
        }

        // Pre-fetch relations to avoid N+1 queries
        $tableUuids = [];
        
        $searchTermsString = !empty($searchTerms) ? implode(' ', $searchTerms) : '';

        foreach ($syncConfig as $config) {
            $table = $config['table'];
            $baseTable = $config['baseTable'] ?? null;
            
            $queryTable = $baseTable ?: $table;
            $sql = "SELECT uuid FROM `" . $queryTable . "` WHERE published='1'";
            if (isset($config['where'])) {
                $sql .= " AND (" . $config['where'] . ")";
            }

            if (!empty($searchTerms)) {
                $searchConditions = [];
                foreach ($searchTerms as $term) {
                    $searchConditions[] = "(name LIKE '%" . $term . "%' OR description LIKE '%" . $term . "%' OR shortDescription LIKE '%" . $term . "%' OR fullTextContent LIKE '%" . $term . "%')";
                }
                $sql .= " AND (" . implode(" AND ", $searchConditions) . ")";
                
                // If it's a multi-word search, also allow OR as fallback if AND yields no results
                $fallbackSql = $sql;
                $fallbackSql = str_replace(implode(" AND ", $searchConditions), "(" . implode(" OR ", $searchConditions) . ")", $fallbackSql);
                
                // If it's specifically about a restaurant, add it to the search terms if not present
                if (mb_stripos($userQuestion, 'restaurant') !== false && !in_array('restaurant', $searchTerms)) {
                    $searchTerms[] = 'restaurant';
                    $searchTermsString = implode(' ', $searchTerms);
                }
            }

            // If it's a very specific search (e.g. for a name), we might want to prioritize it
            if ($searchTermsString !== '') {
                $priorityTerm = $searchTerms[0];
                if (count($searchTerms) > 1 && $searchTerms[0] === 'restaurant') {
                    $priorityTerm = $searchTerms[1];
                }
                $priorityOrder = "(CASE 
                    WHEN name LIKE '" . $priorityTerm . "' THEN 0 
                    WHEN name LIKE '" . $priorityTerm . "%' THEN 1 
                    WHEN name LIKE '% " . $priorityTerm . "%' THEN 2 
                    WHEN name LIKE '%" . $priorityTerm . "%' THEN 3 
                    WHEN name LIKE '%" . $searchTermsString . "%' THEN 4 
                    ELSE 5 END)";
                $sql .= " ORDER BY " . $priorityOrder . ", tstamp DESC";
            } else {
                $sql .= " ORDER BY tstamp DESC";
            }
            
            // In search mode, we don't strictly limit to $maxRecords here, 
            // but we take enough to have a good chance of finding the right one
            $sql .= " LIMIT " . max(50, intval($maxRecords - $recordsCount));
            
            try {
                $objUuids = $db->prepare($sql)->execute();
                if ($objUuids->count() === 0 && isset($fallbackSql)) {
                    $objUuids = $db->prepare($fallbackSql)->execute();
                }
                while ($objUuids->next()) {
                    $tableUuids[$table][] = $objUuids->uuid;
                }
            } catch (\Throwable $e) {
                // Ignore errors for specific tables
            }
        }
        
        $batchRelations = $this->batchGetRelations($tableUuids);

        foreach ($syncConfig as $config) {
            if ($maxRecords > 0 && $recordsCount >= $maxRecords) {
                break;
            }

            $table = $config['table'];
            if (!$db->tableExists($table)) {
                continue;
            }

            $baseTable = $config['baseTable'] ?? null;
            $fieldsStr = $config['fields'];
            if (!$table || !$fieldsStr) {
                continue;
            }

            $fields = array_map('trim', explode(',', $fieldsStr));
            $selectFields = [];
            foreach ($fields as $field) {
                if (strpos($field, '.') !== false) {
                    $parts = explode('.', $field);
                    $selectFields[] = "`" . $parts[0] . "`.`" . $parts[1] . "`";
                } else {
                    $selectFields[] = "`" . $field . "`";
                }
            }

            if ($baseTable) {
                if (!in_array('`t1`.`id`', $selectFields)) $selectFields[] = '`t1`.`id`';
                if (!in_array('`t1`.`uuid`', $selectFields)) $selectFields[] = '`t1`.`uuid`';
                if (!in_array('`t1`.`alias`', $selectFields)) $selectFields[] = '`t1`.`alias`';
                if (!in_array('`t1`.`published`', $selectFields)) $selectFields[] = '`t1`.`published`';
                if ($baseTable === 'tl_gutesio_data_child' && !in_array('`t1`.`typeId`', $selectFields)) {
                    $selectFields[] = '`t1`.`typeId`';
                }
                if (!in_array('`t1`.`imageCDN`', $selectFields)) $selectFields[] = '`t1`.`imageCDN`';
                $query = "SELECT " . implode(',', $selectFields) . " FROM `" . $table . "` t2 INNER JOIN `" . $baseTable . "` t1 ON t2.`childId` = t1.`uuid` WHERE t1.`published`='1'";
            } else {
                if (!in_array('`id`', $selectFields)) $selectFields[] = '`id`';
                if (!in_array('`uuid`', $selectFields)) $selectFields[] = '`uuid`';
                if (!in_array('`alias`', $selectFields)) $selectFields[] = '`alias`';
                if (!in_array('`published`', $selectFields)) $selectFields[] = '`published`';
                if ($table === 'tl_gutesio_data_child' && !in_array('`typeId`', $selectFields)) {
                    $selectFields[] = '`typeId`';
                }
                if (!in_array('`imageCDN`', $selectFields)) $selectFields[] = '`imageCDN`';
                if ($table === 'tl_gutesio_data_element') {
                    if (!in_array('`geox`', $selectFields)) $selectFields[] = '`geox`';
                    if (!in_array('`geoy`', $selectFields)) $selectFields[] = '`geoy`';
                }
                $query = "SELECT " . implode(',', $selectFields) . " FROM `" . $table . "` WHERE `published`='1'";
            }

            if (isset($config['where'])) {
                $query .= " AND (" . $config['where'] . ")";
            }

            if (!empty($searchTerms)) {
                $queryTableAlias = $baseTable ? 't1' : '`'.$table.'`';
                $searchConditions = [];
                foreach ($searchTerms as $term) {
                    $searchConditions[] = "(" . $queryTableAlias . ".name LIKE '%" . $term . "%' OR " . $queryTableAlias . ".description LIKE '%" . $term . "%' OR " . $queryTableAlias . ".shortDescription LIKE '%" . $term . "%' OR " . $queryTableAlias . ".fullTextContent LIKE '%" . $term . "%')";
                }
                $query .= " AND (" . implode(" AND ", $searchConditions) . ")";
                
                // Fallback OR search
                $fallbackQuery = $query;
                $fallbackQuery = str_replace(implode(" AND ", $searchConditions), implode(" OR ", $searchConditions), $fallbackQuery);
            }

            if ($searchTermsString !== '') {
                $priorityTerm = $searchTerms[0];
                if (count($searchTerms) > 1 && $searchTerms[0] === 'restaurant') {
                    $priorityTerm = $searchTerms[1];
                }
                $priorityOrder = "(CASE 
                    WHEN " . $queryTableAlias . ".name LIKE '" . $priorityTerm . "' THEN 0 
                    WHEN " . $queryTableAlias . ".name LIKE '" . $priorityTerm . "%' THEN 1 
                    WHEN " . $queryTableAlias . ".name LIKE '% " . $priorityTerm . "%' THEN 2 
                    WHEN " . $queryTableAlias . ".name LIKE '%" . $priorityTerm . "%' THEN 3 
                    WHEN " . $queryTableAlias . ".name LIKE '%" . $searchTermsString . "%' THEN 4 
                    ELSE 5 END)";
                $query .= " ORDER BY " . $priorityOrder . ", " . $queryTableAlias . ".`tstamp` DESC";
            } else {
                $query .= " ORDER BY " . $queryTableAlias . ".`tstamp` DESC";
            }
            
            // Limit to something reasonable but enough to find the target
            $query .= " LIMIT " . max(50, ($maxRecords - $recordsCount));
            
            try {
                $objRows = $db->prepare($query)->execute();
                if ($objRows->count() === 0 && isset($fallbackQuery)) {
                    $objRows = $db->prepare($fallbackQuery)->execute();
                }
                while ($objRows->next()) {
                    $rowContext = [];
                    $rowContext[] = "[" . $objRows->uuid . "]";
                    foreach ($fields as $field) {
                        $columnName = $field;
                        if (strpos($field, '.') !== false) {
                            $parts = explode('.', $field);
                            $columnName = end($parts);
                        }
                        $val = $objRows->$columnName;
                        if (in_array($columnName, ['beginDate', 'endDate', 'dateOfBirth']) && $val && is_numeric($val)) {
                            $val = date('d.m.Y', (int)$val);
                        } elseif (in_array($columnName, ['beginTime', 'endTime', 'startTime']) && $val && is_numeric($val)) {
                            $val = date('H:i', (int)$val);
                        }
                        if ($columnName === 'opening_hours' && $val) {
                            $val = str_replace("\\", "", (string)$val);
                        }
                        if (in_array($columnName, ['description', 'shortDescription']) && strlen((string)$val) > 180) {
                            $val = mb_substr((string)$val, 0, 177) . "...";
                        }
                        if ($val !== null && $val !== '') {
                            $rowContext[] = $columnName . ": " . $val;
                        }
                    }

                    $relations = $batchRelations[$objRows->uuid] ?? ['categories' => '', 'tags' => ''];
                    if ($relations['categories']) {
                        $rowContext[] = "Kat: " . $relations['categories'];
                    }
                    if ($relations['tags']) {
                        $rowContext[] = "Tags: " . $relations['tags'];
                    }

                    if ($table === 'tl_gutesio_data_element' && $objRows->geox && $objRows->geoy) {
                        $distanceData[$objRows->uuid] = [$objRows->geox, $objRows->geoy];
                    }
                    
                    $link = $this->generateLink($table, $objRows, $settings);
                    if ($link) {
                        $rowContext[] = "L: " . $link;
                    }

                    // Link to showcase for child data
                    if (strpos($table, 'tl_gutesio_data_child') === 0) {
                        $showcaseSql = "SELECT e.name, e.uuid, e.alias, e.imageCDN FROM tl_gutesio_data_element e JOIN tl_gutesio_data_child_connection c ON e.uuid = c.elementId WHERE c.childId = ? LIMIT 1";
                        $objShowcase = $db->prepare($showcaseSql)->execute($objRows->uuid);
                        if ($objShowcase->next()) {
                            $rowContext[] = "Schaufenster: " . $objShowcase->name;
                            $showcaseLink = $this->generateLink('tl_gutesio_data_element', $objShowcase, $settings);
                            if ($showcaseLink) {
                                $rowContext[] = "Schaufenster-Link: " . $showcaseLink;
                            }
                            
                            // If the child record itself has no image, try to use the showcase image
                            if (!$objRows->imageCDN && $objShowcase->imageCDN) {
                                $records[$objRows->uuid]['tile']['image'] = $this->formatImageUrl($objShowcase->imageCDN, $settings);
                            }
                        }
                    }
                    
                    $rowStr = implode(", ", $rowContext);
                    $records[$objRows->uuid] = [
                        'context' => $rowStr,
                        'tile' => [
                            'uuid' => $objRows->uuid,
                            'name' => $objRows->name,
                            'image' => $this->formatImageUrl($objRows->imageCDN, $settings),
                            'link' => $link,
                            'type' => ($table === 'tl_gutesio_data_element' ? 'element' : 'child'),
                            'typeName' => $relations['categories']
                        ],
                        'distance' => ($distanceData[$objRows->uuid] ?? null)
                    ];

                    $recordsCount++;
                    $context .= $rowStr . "\n";
                }
            } catch (\Throwable $e) {
                // Ignore errors for specific tables
            }
        }

        return ['records' => $records];
    }

    private function getRouteDirections(array $from, array $to, $settings): string
    {
        if (!$settings->detail_profile) {
            return "Routing-Profil ist nicht konfiguriert.";
        }

        try {
            // locations are expected as "lat,lon" strings in an array
            $locations = [
                $from[1] . ',' . $from[0],
                $to[1] . ',' . $to[0]
            ];

            if (isset($settings->aiRoutingProfile) && $settings->aiRoutingProfile) {
                $profile = intval($settings->aiRoutingProfile);
                $routeJson = $this->routeService->getResponse(
                    $profile,
                    0, // layer id, not strictly needed for just the route instructions
                    $locations,
                    0, // detour
                    $GLOBALS['TL_LANGUAGE'] ?: 'de',
                    $settings->detail_profile // profile
                );

                $routeData = json_decode($routeJson, true);

                if (isset($routeData['error'])) {
                    return "Wegbeschreibung konnte nicht berechnet werden (" . $routeData['error'] . ").";
                }
            }

            // Extract instructions from Valhalla/con4gisIO response structure
            $instructions = [];
            if (isset($routeData['trip']['legs'][0]['maneuvers'])) {
                foreach ($routeData['trip']['legs'][0]['maneuvers'] as $maneuver) {
                    $instructions[] = $maneuver['instruction'];
                }
            } elseif (isset($routeData['routes'][0]['legs'][0]['steps'])) {
                // OSRM/ORS format
                foreach ($routeData['routes'][0]['legs'][0]['steps'] as $step) {
                    $instructions[] = $step['instruction'] ?: $step['name'];
                }
            }

            if (empty($instructions)) {
                C4gLogModel::addLogEntry("operator", "AI Chatbot Routing: No instructions found in response for profile " . $profile);
                return "Keine detaillierte Wegbeschreibung verfügbar.";
            }

            return implode("\n", $instructions);

        } catch (\Throwable $e) {
            C4gLogModel::addLogEntry("operator", "AI Chatbot Routing Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return "Fehler bei der Routenberechnung.";
        }
    }

    private function mergeContextData(array $baseData, array $searchData): array
    {
        // Merge records, search results take precedence or just fill up
        $allRecords = $searchData['records'] + $baseData['records'];
        
        $context = "";
        $tileData = [];
        $distanceData = [];
        
        foreach ($allRecords as $uuid => $data) {
            $context .= $data['context'] . "\n";
            $tileData[$uuid] = $data['tile'];
            if ($data['distance']) {
                $distanceData[$uuid] = $data['distance'];
            }
        }
        
        return [
            'context' => $context,
            'tileData' => $tileData,
            'distanceData' => $distanceData
        ];
    }

    private function formatImageUrl($image, $settings): string
    {
        if ($image && strpos($image, 'http') !== 0 && strpos($image, '/') !== 0) {
            $cdnUrl = $settings->cdnUrl;
            if ($cdnUrl) {
                $image = rtrim($cdnUrl, '/') . '/' . ltrim($image, '/');
            }
        }
        return (string)$image;
    }

    private function batchGetRelations(array $tableUuids): array
    {
        $db = Database::getInstance();
        $relations = [];

        foreach ($tableUuids as $table => $uuids) {
            if (empty($uuids)) continue;
            
            if ($table === 'tl_gutesio_data_element') {
                // Element Categories
                $sql = "SELECT t1.elementId, t2.name FROM tl_gutesio_data_element_type t1 JOIN tl_gutesio_data_type t2 ON t1.typeId = t2.uuid WHERE t1.elementId IN ('" . implode("','", $uuids) . "')";
                $objResult = $db->prepare($sql)->execute();
                while ($objResult->next()) {
                    $relations[$objResult->elementId]['categories'][] = $objResult->name;
                }

                // Element Tags
                $sql = "SELECT t1.elementId, t2.name FROM tl_gutesio_data_tag_element t1 JOIN tl_gutesio_data_tag t2 ON t1.tagId = t2.uuid WHERE t1.elementId IN ('" . implode("','", $uuids) . "')";
                $objResult = $db->prepare($sql)->execute();
                while ($objResult->next()) {
                    $relations[$objResult->elementId]['tags'][] = $objResult->name;
                }
            } elseif (strpos($table, 'tl_gutesio_data_child') === 0) {
                // Child Categories
                $sql = "SELECT t1.uuid, t2.name FROM tl_gutesio_data_child t1 JOIN tl_gutesio_data_child_type t2 ON t1.typeId = t2.uuid WHERE t1.uuid IN ('" . implode("','", $uuids) . "')";
                $objResult = $db->prepare($sql)->execute();
                while ($objResult->next()) {
                    $relations[$objResult->uuid]['categories'][] = $objResult->name;
                }

                // Child Tags
                $sql = "SELECT t1.childId, t2.name FROM tl_gutesio_data_child_tag t1 JOIN tl_gutesio_data_tag t2 ON t1.tagId = t2.uuid WHERE t1.childId IN ('" . implode("','", $uuids) . "')";
                $objResult = $db->prepare($sql)->execute();
                while ($objResult->next()) {
                    $relations[$objResult->childId]['tags'][] = $objResult->name;
                }
            }
        }

        // Stringify categories and tags
        foreach ($relations as $uuid => $data) {
            $relations[$uuid]['categories'] = isset($data['categories']) ? implode(', ', $data['categories']) : '';
            $relations[$uuid]['tags'] = isset($data['tags']) ? implode(', ', $data['tags']) : '';
        }

        return $relations;
    }

    public function generateLink(string $table, $row, $settings): string
    {
        $pageId = 0;
        $params = '/' . ($row->alias ?: $row->id);

        if ($table === 'tl_gutesio_data_element') {
            $pageId = $settings->showcaseDetailPage;
        } elseif (strpos($table, 'tl_gutesio_data_child') === 0) {
            $typeId = $row->typeId;
            switch ($typeId) {
                case 'event':
                    $pageId = $settings->eventDetailPage;
                    break;
                case 'product':
                    $pageId = $settings->productDetailPage;
                    break;
                case 'job':
                    $pageId = $settings->jobDetailPage;
                    break;
                case 'voucher':
                    $pageId = $settings->voucherDetailPage;
                    break;
                case 'person':
                    $pageId = $settings->personDetailPage;
                    break;
                case 'arrangement':
                    $pageId = $settings->arrangementDetailPage;
                    break;
                case 'service':
                    $pageId = $settings->serviceDetailPage;
                    break;
                case 'realestate':
                    $pageId = $settings->realestateDetailPage;
                    break;
                case 'exhibition':
                    $pageId = $settings->exhibitionDetailPage;
                    break;
            }
        }

        if ($pageId && is_numeric($pageId)) {
            try {
                $page = PageModel::findByPk($pageId);
                if ($page instanceof PageModel) {
                    return $page->getAbsoluteUrl($params);
                }
            } catch (\Throwable $e) {
                // Ignore errors during link generation
            }
        }

        return "";
    }

    private function getRelations(string $table, string $uuid): array
    {
        $db = Database::getInstance();
        $categories = [];
        $tags = [];

        if ($table === 'tl_gutesio_data_element') {
            // Element Categories
            $sql = "SELECT t2.name FROM tl_gutesio_data_element_type t1 JOIN tl_gutesio_data_type t2 ON t1.typeId = t2.uuid WHERE t1.elementId = ?";
            $objResult = $db->prepare($sql)->execute($uuid);
            while ($objResult->next()) {
                $categories[] = $objResult->name;
            }

            // Element Tags
            $sql = "SELECT t2.name FROM tl_gutesio_data_tag_element t1 JOIN tl_gutesio_data_tag t2 ON t1.tagId = t2.uuid WHERE t1.elementId = ?";
            $objResult = $db->prepare($sql)->execute($uuid);
            while ($objResult->next()) {
                $tags[] = $objResult->name;
            }
        } elseif (strpos($table, 'tl_gutesio_data_child') === 0) {
            // Child Categories (base table has typeId)
            $sql = "SELECT t2.name FROM tl_gutesio_data_child t1 JOIN tl_gutesio_data_child_type t2 ON t1.typeId = t2.uuid WHERE t1.uuid = ?";
            $objResult = $db->prepare($sql)->execute($uuid);
            while ($objResult->next()) {
                $categories[] = $objResult->name;
            }

            // Child Tags
            $sql = "SELECT t2.name FROM tl_gutesio_data_child_tag t1 JOIN tl_gutesio_data_tag t2 ON t1.tagId = t2.uuid WHERE t1.childId = ?";
            $objResult = $db->prepare($sql)->execute($uuid);
            while ($objResult->next()) {
                $tags[] = $objResult->name;
            }
        }

        return [
            'categories' => implode(', ', $categories),
            'tags' => implode(', ', $tags)
        ];
    }

    private function callAiApi($settings, $systemPrompt, $context, $userQuestion, array $history = []): string
    {
        $endpoint = trim($settings->aiApiEndpoint);
        $apiKey = trim($settings->aiApiKey);
        $model = trim($settings->aiModel ?: 'gpt-4o');

        if (!$endpoint || !$apiKey) {
            return "KI Konfiguration unvollständig (Endpunkt oder API Key fehlt).";
        }

        if (filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            return "Fehler: Der KI API Endpunkt '" . $endpoint . "' ist keine gültige URL. Bitte geben Sie eine vollständige URL an (z.B. https://api.openai.com/v1/chat/completions).";
        }

        $postRequest = new CurlPostRequest();
        $postRequest->setUrl($endpoint);
        $postRequest->setHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey
        ]);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt . "\n\nKontext:\n" . $context]
        ];

        // Add history
        foreach ($history as $msg) {
            $messages[] = $msg;
        }

        // Add latest question
        $messages[] = ['role' => 'user', 'content' => $userQuestion];

        $payload = [
            'model' => $model,
            'messages' => $messages
        ];

        // Only set temperature if it's not a model known to not support it
        // OpenAI o1, o3 and future 'reasoning' models often don't support custom temperatures
        // We also check for 'gpt-5' just in case (as seen in some test environments)
        $isReasoningModel = (
            strpos($model, 'o1') !== false || 
            strpos($model, 'o3') !== false || 
            strpos($model, 'reasoning') !== false || 
            strpos($model, 'gpt-5') !== false
        );
        
        if (!$isReasoningModel) {
            $payload['temperature'] = 0.5;
        }

        $postRequest->setPostData(json_encode($payload));

        try {
            $response = $postRequest->send();
            $responseData = $response->getData();
            $statusCode = $response->getStatusCode();
            
            // If we get a 400 error specifically about 'temperature', retry without it
            if ($statusCode === 400 && strpos($responseData, 'temperature') !== false && isset($payload['temperature'])) {
                unset($payload['temperature']);
                $postRequest->setPostData(json_encode($payload));
                $response = $postRequest->send();
                $responseData = $response->getData();
                $statusCode = $response->getStatusCode();
            }
            
            if ($statusCode === 200 || $statusCode === "200") {
                if (!$responseData) {
                    C4gLogModel::addLogEntry("operator", "AI API Empty Response. Status: " . $statusCode);
                    return "Fehler: Die KI hat eine leere Antwort geliefert.";
                }
                
                $data = json_decode($responseData, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    C4gLogModel::addLogEntry("operator", "AI API JSON Decode Error: " . json_last_error_msg() . " - Raw Data: " . $responseData);
                    return "Fehler: Die Antwort der KI konnte nicht verarbeitet werden (JSON Fehler).";
                }
                
                if (isset($data['choices'][0]['message']['content'])) {
                    return $data['choices'][0]['message']['content'];
                }
                
                C4gLogModel::addLogEntry("operator", "AI API Invalid Response Structure: " . $responseData);
                return "Fehler: Die KI hat eine ungültige Antwortstruktur geliefert.";
            } else {
                $errorMsg = "Fehler bei der Anfrage an die KI (HTTP " . $statusCode . ")";
                
                // Try to parse error message from API response
                if ($responseData) {
                    $errorData = json_decode($responseData, true);
                    if (isset($errorData['error']['message'])) {
                        $errorMsg .= ": " . $errorData['error']['message'];
                    } elseif ($statusCode === 0) {
                        $errorMsg .= ": " . $responseData;
                    }
                }
                
                $hint = "";
                if ($statusCode === 404) {
                    if (strpos($endpoint, 'openai.com') !== false && strpos($endpoint, '/chat/completions') === false) {
                        $hint = " Hinweis: Für OpenAI muss der Endpunkt in der Regel auf '/v1/chat/completions' enden.";
                    }
                    if (strpos($model, '40') !== false) {
                        $hint .= " Hinweis: Prüfen Sie den Modellnamen (Tippfehler '40' statt '4o'?).";
                    }
                }
                
                C4gLogModel::addLogEntry("operator", "AI API Error: HTTP " . $statusCode . " - URL: " . $endpoint . " - Content: " . $responseData);
                return $errorMsg . "." . $hint . " Bitte prüfen Sie das System-Log.";
            }
        } catch (\Exception $e) {
            C4gLogModel::addLogEntry("operator", "AI API Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return "Ausnahme bei der Anfrage an die KI: " . $e->getMessage();
        }
    }

    private function callAssistantApi($settings, $systemPrompt, $context, $userQuestion, array $history = [], string $threadId = null): array
    {
        $apiKey = trim($settings->aiApiKey);
        $assistantId = trim($settings->aiAssistantId);

        if (!$apiKey || !$assistantId) {
            return ['answer' => "Assistant-Konfiguration unvollständig.", 'threadId' => null];
        }

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'OpenAI-Beta: assistants=v2'
            ]);

            // 1. Get or Create a thread
            if ($threadId) {
                // Verify thread exists or just use it
                // For simplicity we just use it and handle errors during message creation
            } else {
                curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/threads");
                curl_setopt($ch, CURLOPT_POST, 1);
                
                $initialMessages = [];
                // If we have history but no threadId, populate the new thread
                if (!empty($history)) {
                    foreach ($history as $msg) {
                        $initialMessages[] = ['role' => $msg['role'], 'content' => $msg['content']];
                    }
                }
                
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'messages' => $initialMessages
                ]));
                $result = curl_exec($ch);
                $threadData = json_decode($result, true);
                $threadId = $threadData['id'] ?? null;
                if (!$threadId) {
                    C4gLogModel::addLogEntry("operator", "Assistant API Error (Thread Creation): " . $result);
                    return ['answer' => "Fehler beim Erstellen des Threads.", 'threadId' => null];
                }
            }

            // 2. Add the latest user message to the thread
            curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/threads/$threadId/messages");
            curl_setopt($ch, CURLOPT_POST, 1);
            
            $messagePayload = [
                'role' => 'user',
                'content' => $userQuestion
            ];
            
            // In hybrid mode with Assistant, we provide the context as a hidden message before the user question
            // or we use additional_instructions. Since we already use instructions for the run, 
            // adding a message with the dynamic context can help the Assistant 'see' the latest data.
            $contextMessage = [
                'role' => 'user',
                'content' => "DYNAMISCHER KONTEXT (Wissensdatenbank-Auszug):\n" . $context . "\n\nNutze diesen Kontext für die Beantwortung der nächsten Frage."
            ];
            
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($contextMessage));
            curl_exec($ch);
            
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messagePayload));
            $result = curl_exec($ch);
            $msgData = json_decode($result, true);
            if (!isset($msgData['id'])) {
                $errorMsg = $result;
                if (isset($msgData['error']['message'])) {
                    $errorMsg = $msgData['error']['message'];
                }
                C4gLogModel::addLogEntry("operator", "Assistant API Error (Message Creation): " . $errorMsg . " (Thread ID: $threadId)");
                return ['answer' => "Fehler beim Senden der Nachricht an den Assistant: " . $errorMsg . ". Bitte Chat zurücksetzen.", 'threadId' => $threadId];
            }

            // 3. Create a run
            curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/threads/$threadId/runs");
            $instructions = $systemPrompt . "\n\nAktueller dynamischer Kontext (enthält ggf. Entfernungen basierend auf dem Standort des Benutzers):\n" . $context;
            // Also add a system message to the thread before running if it's the first time or as additional instructions
            // OpenAI v2 Assistants support additional_instructions and also overriding instructions for the run.
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'assistant_id' => $assistantId,
                'instructions' => $instructions,
                'additional_instructions' => "Nutze zwingend die Informationen aus dem 'dynamischen Kontext' für Entfernungen und Wegbeschreibungen."
            ]));
            $result = curl_exec($ch);
            $runData = json_decode($result, true);
            $runId = $runData['id'] ?? null;
            if (!$runId) {
                $errorMsg = $result;
                if (isset($runData['error']['message'])) {
                    $errorMsg = $runData['error']['message'];
                }
                C4gLogModel::addLogEntry("operator", "Assistant API Error (Run Creation): " . $errorMsg);
                return ['answer' => "Fehler beim Erstellen des Runs: " . $errorMsg, 'threadId' => $threadId];
            }

            // 4. Wait for completion
            $status = 'queued';
            $maxWait = 60; // Increased to 60 seconds
            $startTime = time();
            $pollCount = 0;
            while (in_array($status, ['queued', 'in_progress', 'requires_action']) && (time() - $startTime) < $maxWait) {
                $pollCount++;
                
                // If it requires action (function call), we should probably fail or handle it
                // but for now we don't have tools enabled in the assistant config here.
                if ($status === 'requires_action') {
                    C4gLogModel::addLogEntry("operator", "Assistant Run requires action (tool call) but none handled.");
                    break; 
                }

                // Exponential backoff for polling (1s, 1s, 2s, 2s, 3s...)
                $sleepTime = min(3, ceil($pollCount / 2));
                sleep($sleepTime);
                
                curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/threads/$threadId/runs/$runId");
                curl_setopt($ch, CURLOPT_POST, 0);
                $result = curl_exec($ch);
                $runData = json_decode($result, true);
                $status = $runData['status'] ?? 'failed';
                
                if ($status === 'failed') {
                $errorMsg = "unknown error";
                if (isset($runData['last_error']['message'])) {
                    $errorMsg = $runData['last_error']['message'];
                } elseif (isset($runData['last_error'])) {
                    $errorMsg = json_encode($runData['last_error']);
                }
                C4gLogModel::addLogEntry("operator", "Assistant Run Failed: " . $errorMsg);
                return ['answer' => "KI-Anfrage fehlgeschlagen: " . $errorMsg, 'threadId' => $threadId];
            }
            if ($status === 'completed') {
                C4gLogModel::addLogEntry("operator", "Assistant Run Completed in " . (time() - $startTime) . "s (Polls: $pollCount)");
            }
        }

        if ($status !== 'completed') {
            // If it failed or timed out, we might want to cancel the run to avoid blocking the thread
            if (in_array($status, ['queued', 'in_progress', 'requires_action'])) {
                curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/threads/$threadId/runs/$runId/cancel");
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_exec($ch);
            }
            return ['answer' => "KI-Anfrage dauerte zu lange (Status: $status). Bitte versuchen Sie es in Kürze erneut.", 'threadId' => $threadId];
        }

            // 5. Get messages
            curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/threads/$threadId/messages?limit=1");
            curl_setopt($ch, CURLOPT_POST, 0);
            $result = curl_exec($ch);
            curl_close($ch);
            $messagesData = json_decode($result, true);
            
            if (isset($messagesData['data'][0]['content'][0]['text']['value'])) {
                return [
                    'answer' => $messagesData['data'][0]['content'][0]['text']['value'],
                    'threadId' => $threadId
                ];
            }

            return ['answer' => "Keine Antwort vom Assistant erhalten.", 'threadId' => $threadId];

        } catch (\Exception $e) {
            C4gLogModel::addLogEntry("operator", "Assistant API Exception: " . $e->getMessage());
            return ['answer' => "Fehler bei Assistant-Anfrage: " . $e->getMessage(), 'threadId' => $threadId];
        }
    }
}
