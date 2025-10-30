<?php

declare(strict_types=1);

require __DIR__ . '/../../db.php';

function validateID()
{
    global $conn;
    if (empty($_GET["id"])) {
        http_response_code(400);
        exit;
    }

    $id = $_GET["id"];

    if (!is_numeric($id)) {
        header("Content-Type: application/json; charset=utf-8");
        http_response_code(400);
        echo json_encode(["message" => "ID is malformed"]);
        exit;
    }

    $id = intval($id, 10);

    $stmt = $conn->prepare("SELECT * FROM cards WHERE id = :id");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($result)) {
        http_response_code(404);
        exit;
    }

    return $id;
}

function readBody(): array
{
    $raw = file_get_contents("php://input");
    $ct  = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

    if (stripos($ct, 'application/json') !== false) {
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    // fallback for form-urlencoded
    $data = [];
    parse_str($raw, $data);
    // but if it's a normal POST form (multipart or classic), PHP already filled $_POST
    if (empty($data) && !empty($_POST)) {
        return $_POST;
    }
    return $data;
}

if ($_SERVER["REQUEST_METHOD"] === "GET" && empty($_GET["id"])) {
    header("Content-Type: application/json; charset=utf-8");

    $limit  = isset($_GET["limit"])  ? (int)$_GET["limit"]  : 5;
    $offset = isset($_GET["offset"]) ? (int)$_GET["offset"] : 0;

    // COUNT distinct card names for pagination
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM (
            SELECT name
            FROM cards
            GROUP BY name
        ) AS g
    ");
    $stmt->execute();
    $countRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = (int)$countRow['total'];

    // Return one row per name: representative id + amount
    $stmt = $conn->prepare("
        SELECT 
            MIN(id) AS id,         -- representative id
            name,
            COUNT(*) AS amount     -- how many copies
        FROM cards
        GROUP BY name
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
    $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as &$row) {
        $row['amount'] = (int)$row['amount'];
        $row['url'] = "http://localhost:8080/collection/cards?id=" . $row['id']; // representative
    }
    unset($row);

    $nextOffset = $offset + $limit;
    $prevOffset = max(0, $offset - $limit);

    $next = "http://localhost:8080/collection/cards?offset=$nextOffset&limit=$limit";
    $prev = "http://localhost:8080/collection/cards?offset=$prevOffset&limit=$limit";

    echo json_encode([
        "count"    => $total,                              // number of unique names
        "next"     => ($nextOffset < $total) ? $next : null,
        "previous" => ($offset <= 0) ? null : $prev,
        "results"  => $results
    ], JSON_UNESCAPED_SLASHES);
    exit;
}


if ($_SERVER["REQUEST_METHOD"] === "GET" && !empty($_GET["id"])) {
    $id = validateID();

    $stmt = $conn->prepare("
        SELECT 
            cards.id,
            cards.name,
            cards.power,
            cards.defense,
            cards.description,
            cards.abilities,
            GROUP_CONCAT(mana.color) AS cost,
            GROUP_CONCAT(DISTINCT type.type)  AS types
        FROM cards
        LEFT JOIN card_mana     ON cards.id = card_mana.card_id
        LEFT JOIN mana          ON card_mana.mana_id = mana.id
        LEFT JOIN card_type     ON cards.id = card_type.card_id
        LEFT JOIN type          ON card_type.type_id = type.id
        WHERE cards.id = :id
        GROUP BY cards.id
        ");
    $stmt->bindParam(":id", $id);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as &$row) {
        $costObj = [];
        if (!empty($row['cost'])) {
            $colors = array_map('trim', explode(',', $row['cost']));
            foreach ($colors as $color) {
                if ($color === '') {
                    continue;
                }
                        $key = strtolower($color);
                        $costObj[$key] = ($costObj[$key] ?? 0) + 1;
            }
        }
                // hypermedia control
            $costObj['url'] = 'http://localhost:8080/collection/mana';
            $row['cost'] = $costObj;

            $typeList = [];
        if (!empty($row['types'])) {
            $parts = array_map('trim', explode(',', $row['types']));
            $unique = [];
            foreach ($parts as $t) {
                if ($t === '') {
                    continue;
                }
                $key = strtolower($t);
                if (!isset($unique[$key])) {
                    $unique[$key] = true;
                    $typeList[] = strtolower($t);
                }
            }
        }

            $abilityList = [];
        if (!empty($row['abilities'])) {
            $decoded = json_decode($row["abilities"], true);
            if (is_array($decoded)) {
                $abilityList = array_values(
                    array_filter(
                        array_map('trim', $decoded),
                        fn($a) => $a !== ''
                    )
                );
            }
        }
            $row['types'] = [
                'type' => $typeList,
                'url' => 'http://localhost:8080/collection/type'
            ];
            $row['abilities'] = [
            'list' => $abilityList
            ];
            $row["url"] = "http://localhost:8080/collection/cards?id=" . $row["id"];
            unset($row['id']);
    }
        unset($row);

    header("Content-Type: application/json; charset=utf-8");

    echo json_encode($results);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json; charset=utf-8");

    $data = readBody();   // <--- use helper

    $name        = trim($data["name"] ?? '');
    $powerRaw    = $data["power"]   ?? null;
    $defenseRaw  = $data["defense"] ?? null;
    $power       = ($powerRaw === '' || $powerRaw === null) ? null : (int)$powerRaw;
    $defense     = ($defenseRaw === '' || $defenseRaw === null) ? null : (int)$defenseRaw;
    $description = array_key_exists('description', $data) ? trim((string)$data['description']) : null;
    $description = ($description === '') ? null : $description;

    $manaCosts = $data["mana"]  ?? [];
    $types     = $data["types"] ?? $data["type"] ?? [];
    $abilities = $data["abilities"] ?? $data["ability"] ?? [];

    if (is_array($manaCosts)) {
        $cleanMana = [];

        foreach ($manaCosts as $color => $value) {
            $value = trim((string)$value);

            if ($value === '' || (int)$value === 0) {
                continue;
            }
            $cleanMana[$color] = (int)$value; // force integer
        }

        $manaCosts = $cleanMana;
    }

    $abilityList = [];
    if (is_array($abilities)) {
        foreach ($abilities as $a) {
            $a = trim((string)$a);
            if ($a !== '') {
                $abilityList[] = $a;
            }
        }
    } elseif (is_string($abilities)) {
        $abilities = trim($abilities);
        if ($abilities !== '') {
            $abilityList[] = $abilities;
        }
    }

    $abilitiesJson = !empty($abilityList)
        ? json_encode($abilityList, JSON_UNESCAPED_UNICODE)
        : null;

    $stmt = $conn->prepare("
        INSERT INTO cards (name, power, defense, description, abilities)
        VALUES (:name, :power, :defense, :description, :abilities)
    ");

    $stmt->bindParam(":name", $name);
    $stmt->bindParam(":power", $power, $power === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindParam(":defense", $defense, $defense === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindParam(":description", $description, $description === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindParam(":abilities", $abilitiesJson, $abilitiesJson === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

    $stmt->execute();
    $card_id = (int)$conn->lastInsertId();

    if (!empty($manaCosts) && is_array($manaCosts)) {
        $findMana = $conn->prepare("SELECT id FROM mana WHERE LOWER(color) = LOWER(:color) LIMIT 1");
        $insertLink = $conn->prepare("INSERT INTO card_mana (card_id, mana_id) VALUES (:card_id, :mana_id)");

        foreach ($manaCosts as $color => $amount) {
            $findMana->execute([':color' => $color]);
            $mana_id = $findMana->fetchColumn();

            if ($mana_id) {
                for ($i = 0; $i < (int)$amount; $i++) {
                    $insertLink->execute([
                        ':card_id' => $card_id,
                        ':mana_id' => $mana_id,
                    ]);
                }
            }
        }
    }

    if (!empty($types) && is_array($types)) {
        $normalized = [];
        foreach ($types as $key => $value) {
            $typeName = is_numeric($key) ? trim((string)$value) : trim((string)$key);
            if ($typeName !== '') {
                $normalized[strtolower($typeName)] = $typeName;
            }
        }

        if ($normalized) {
            $findType  = $conn->prepare("SELECT id FROM type WHERE LOWER(type) = LOWER(:t) LIMIT 1");
            $linkType  = $conn->prepare("INSERT IGNORE INTO card_type (card_id, type_id) VALUES (:c, :t)");
            foreach ($normalized as $typeName) {
                $findType->execute([':t' => $typeName]);
                $typeId = $findType->fetchColumn();
                if ($typeId) {
                    $linkType->execute([':c' => $card_id, ':t' => (int)$typeId]);
                }
            }
        }
    }

    http_response_code(201);
    echo json_encode([
    "message" => "Card created",
    "id" => $card_id,
    "name" => $name,
    "power" => $power,
    "defense" => $defense,
    "description" => $description,
    "abilities" => $abilityList,
    "mana" => $manaCosts,
    "types" => array_values($normalized ?? [])
    ]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "PUT") {
    header("Content-Type: application/json; charset=utf-8");

    if (empty($_GET["id"])) {
        http_response_code(400);
        exit;
    }


    $id = $_GET["id"];

    if (!is_numeric($id)) {
        header("Content-Type: application/json; charset=utf-8");
        http_response_code(400);
        echo json_encode(["message" => "ID is malformed"]);
        exit;
    }

    $id = intval($id, 10);

    $data = readBody();

    $name = $data["name"];
    $power = array_key_exists('power', $data) ? trim((string)$data["power"]) : null;
    $defense = array_key_exists('defense', $data) ? trim((string)$data["defense"]) : null;
    $power = ($power === '' || $power === null) ? null : (int)$power;
    $defense = ($defense === '' || $defense === null) ? null : (int)$defense;
    $description = array_key_exists('description', $data) ? trim((string)$data['description']) : null;
    $description = ($description === '') ? null : $description;
    $abilities = $data["abilities"] ?? $data["ability"] ?? null;

    $mana = $data["mana"] ?? null;
    $types = $data["types"] ?? null;

    if (is_array($mana)) {
        $cleanMana = [];

        foreach ($mana as $color => $value) {
            $value = trim((string)$value);

            if ($value === '' || (int)$value === 0) {
                continue;
            }
            $cleanMana[$color] = (int)$value;
        }

        $mana = $cleanMana;
    }

    $abilitiesJson = null;
    if (is_array($abilities)) {
        $cleanAbilities = [];
        foreach ($abilities as $a) {
            $a = trim((string)$a);
            if ($a !== '') {
                $cleanAbilities[] = $a;
            }
        }
        if (!empty($cleanAbilities)) {
            $abilitiesJson = json_encode($cleanAbilities, JSON_UNESCAPED_UNICODE);
        }
    } elseif (is_string($abilities)) {
        $abilities = trim($abilities);
        if ($abilities !== '') {
            $abilitiesJson = json_encode([$abilities], JSON_UNESCAPED_UNICODE);
        }
    }

    if ($id) {
        $stmt = $conn->prepare("
            UPDATE cards
            SET 
            name = :name, 
            power = :power, 
            defense = :defense,
            description = :description,
            abilities = :abilities
            WHERE id = :id
            ");

        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":power", $power, $power === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(":defense", $defense, $defense === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(":description", $description, $description === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(":abilities", $abilitiesJson, $abilitiesJson === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        $stmt->execute();

        if ($mana && is_array($mana)) {
            $conn->prepare("DELETE FROM card_mana WHERE card_id = :id")->execute([":id" => $id]);

            $findMana = $conn->prepare("SELECT id FROM mana WHERE LOWER(color) = LOWER(:color) LIMIT 1");
            $addMana = $conn->prepare("INSERT INTO card_mana (card_id, mana_id) VALUES (:card, :mana)");

            foreach ($mana as $color => $count) {
                $findMana->execute([":color" => $color]);
                $mana_id = $findMana->fetchColumn();
                if ($mana_id) {
                    for ($i = 0; $i < (int)$count; $i++) {
                        $addMana->execute([
                            ":card" => $id,
                            ":mana" => $mana_id
                        ]);
                    }
                }
            }
        } else {
            // PUT without mana → clear mana
            $conn->prepare("DELETE FROM card_mana WHERE card_id = :id")->execute([":id" => $id]);
        }



        // ----- types -----
        if ($types && is_array($types)) {
            $conn->prepare("DELETE FROM card_type WHERE card_id = :id")->execute([":id" => $id]);

            $findType = $conn->prepare("SELECT id FROM type WHERE LOWER(type) = LOWER(:t) LIMIT 1");
            $addType = $conn->prepare("INSERT INTO card_type (card_id, type_id) VALUES (:card, :type)");

            foreach ($types as $t) {
                $findType->execute([":t" => $t]);
                $type_id = $findType->fetchColumn();
                if ($type_id) {
                    $addType->execute([
                        ":card" => $id,
                        ":type" => $type_id
                    ]);
                }
            }
        } else {
            // PUT without types → clear types
            $conn->prepare("DELETE FROM card_type WHERE card_id = :id")->execute([":id" => $id]);
        }


        echo json_encode(["message" => "Card Updated"]);
    } else {
        echo json_encode(["message" => "Missing ID"]);
    }
}

// ---------- PATCH /collection/cards?id=123  (partial update) ----------
if ($_SERVER["REQUEST_METHOD"] === "PATCH") {
    header("Content-Type: application/json; charset=utf-8");

    if (empty($_GET["id"]) || !is_numeric($_GET["id"])) {
        http_response_code(400);
        echo json_encode(["message" => "Missing or malformed id"]);
        exit;
    }
    $id = (int)$_GET["id"];

    $data = readBody();

    // DEBUG GUARD (keep while testing)
    if (!is_array($data) || $data === []) {
        http_response_code(400);
        echo json_encode([
            "message" => "PATCH body was empty or not JSON/form",
            "got" => $data
        ]);
        exit;
    }

    // Build dynamic UPDATE only for provided scalar fields
    $fields = [];
    $params = [":id" => $id];

    $up = function (string $key, string $col) use (&$fields, &$params, $data) {
        if (array_key_exists($key, $data)) {
            $fields[] = "$col = :$key";
            $params[":$key"] = ($col === 'power' || $col === 'defense') ? (int)$data[$key] : (string)$data[$key];
        }
    };

    if (array_key_exists('name', $data)) {
        $fields[] = "name = :name";
        $params[':name'] = (string)$data['name'];
    }

    if (array_key_exists('power', $data)) {
        $fields[] = "power = :power";
        $val = trim((string)$data['power']);
        $params[':power'] = ($val === '') ? null : (int)$val; // empty ⇒ NULL, else int
    }

    if (array_key_exists('defense', $data)) {
        $fields[] = "defense = :defense";
        $val = trim((string)$data['defense']);
        $params[':defense'] = ($val === '') ? null : (int)$val; // empty ⇒ NULL, else int
    }

    if (array_key_exists('description', $data)) {
        $fields[] = "description = :description";
        $val = trim((string)$data['description']);
        $params[':description'] = ($val === '') ? null : $val; // empty ⇒ NULL
    }

    // abilities as JSON (new)
    if (array_key_exists('abilities', $data) || array_key_exists('ability', $data)) {
        $abilitiesInput = $data['abilities'] ?? $data['ability'];

        // normalize to array of non-empty strings
        $abilityList = [];
        if (is_array($abilitiesInput)) {
            foreach ($abilitiesInput as $a) {
                $a = trim((string)$a);
                if ($a !== '') {
                    $abilityList[] = $a;
                }
            }
        } else { // single string
            $abilitiesInput = trim((string)$abilitiesInput);
            if ($abilitiesInput !== '') {
                $abilityList[] = $abilitiesInput;
            }
        }

        $fields[] = "abilities = :abilities";
        $params[':abilities'] = !empty($abilityList)
            ? json_encode($abilityList, JSON_UNESCAPED_UNICODE)
            : null;
    }

    if ($fields) {
        $sql = "UPDATE cards SET " . implode(", ", $fields) . " WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
    }

    // Relations (PATCH: only touch if provided)
    if (isset($data["mana"]) && is_array($data["mana"])) {
        $cleanMana = [];

        foreach ($data["mana"] as $color => $value) {
            $color = trim((string)$color);
            $value = trim((string)$value);

            if ($color === '') {
                continue;
            }
            if ($value === '' || (int)$value <= 0) {
                continue;
            }

            $cleanMana[$color] = (int)$value;
        }

        $conn->prepare("DELETE FROM card_mana WHERE card_id = :id")->execute([":id" => $id]);
        if (!empty($cleanMana)) {
            $findMana = $conn->prepare("SELECT id FROM mana WHERE LOWER(color)=LOWER(:c) LIMIT 1");
            $insMana  = $conn->prepare("INSERT INTO card_mana (card_id, mana_id) VALUES (:card,:mana)");
            foreach ($cleanMana as $color => $count) {
                  $findMana->execute([":c" => $color]);
                  $manaId = $findMana->fetchColumn();
                if (!$manaId) {
                    continue;
                }
                for ($i = 0; $i < (int)$count; $i++) {
                    $insMana->execute([":card" => $id, ":mana" => (int)$manaId]);
                }
            }
        }
    }

    if (isset($data["types"]) && is_array($data["types"])) {
        // ----- PATCH: types (replace only if client sent types or type) -----
        $typesInput = $data['types'] ?? $data['type'] ?? null;

        if ($typesInput !== null) {
            // normalize to array
            if (is_string($typesInput)) {
                $typesInput = [$typesInput];
            } elseif (!is_array($typesInput)) {
                $typesInput = [];
            }

            // clean & dedupe case-insensitively
            $cleanTypes = [];
            foreach ($typesInput as $k => $v) {
                $name = is_numeric($k) ? (string)$v : (string)$k;
                $name = trim($name);
                if ($name === '') {
                    continue;
                }
                $cleanTypes[strtolower($name)] = $name;
            }

            // replace semantics: delete existing links first
            $conn->prepare("DELETE FROM card_type WHERE card_id = :id")
                 ->execute([":id" => $id]);

            if (!empty($cleanTypes)) {
                $findType = $conn->prepare("SELECT id FROM type WHERE LOWER(type) = LOWER(:t) LIMIT 1");
                $insType  = $conn->prepare("INSERT INTO card_type (card_id, type_id) VALUES (:card, :type)");

                $missing = [];
                foreach ($cleanTypes as $typeName) {
                    $findType->execute([':t' => $typeName]);
                    $typeId = $findType->fetchColumn();

                    if (!$typeId) {
                        $missing[] = $typeName;
                        continue;
                    }
                    $insType->execute([':card' => $id, ':type' => (int)$typeId]);
                }

                // If any type wasn't found, return 400 so you know why nothing appeared
                if (!empty($missing)) {
                    http_response_code(400);
                    echo json_encode([
                        "error" => "unknown_types",
                        "detail" => "These types do not exist in table `type`",
                        "types" => array_values($missing)
                    ]);
                    exit;
                }
            }
        }
    }

    echo json_encode(["message" => "Card patched (PATCH)", "id" => $id]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
    header("Content-Type: application/json; charset=utf-8");

    $id   = $_GET["id"] ?? null;
    $name = $_GET["name"] ?? null;
    $all  = isset($_GET["all"]) && $_GET["all"] === "true"; // optional flag for deleting all copies

    if ($id) {
        $stmt = $conn->prepare("DELETE FROM cards WHERE id = :id");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode(["message" => "Card deleted by id", "id" => (int)$id]);
        exit;
    }

    if ($name) {
        $name = trim($name);

        if ($all) {
            $stmt = $conn->prepare("DELETE FROM cards WHERE name = :name");
            $stmt->bindParam(":name", $name);
            $stmt->execute();

            echo json_encode(["message" => "All copies deleted", "name" => $name]);
            exit;
        } else {
            $stmt = $conn->prepare("DELETE FROM cards WHERE name = :name ORDER BY id ASC LIMIT 1");
            $stmt->bindParam(":name", $name);
            $stmt->execute();

            echo json_encode(["message" => "One copy deleted", "name" => $name]);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(["message" => "Missing id or name"]);
    exit;
}
