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
            GROUP_CONCAT(mana.color) AS cost,
            GROUP_CONCAT(DISTINCT type.type)  AS types,
            GROUP_CONCAT(DISTINCT ability.ability SEPARATOR '|||') AS abilities
        FROM cards
        LEFT JOIN card_mana     ON cards.id = card_mana.card_id
        LEFT JOIN mana          ON card_mana.mana_id = mana.id
        LEFT JOIN card_type     ON cards.id = card_type.card_id
        LEFT JOIN type          ON card_type.type_id = type.id
        LEFT JOIN card_ability  ON cards.id = card_ability.card_id 
        LEFT JOIN ability       ON card_ability.ability_id = ability.id
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
            $parts = array_map('trim', explode('|||', $row['abilities']));
            $unique = [];
            foreach ($parts as $t) {
                if ($t === '') {
                    continue;
                }
                $key = strtolower($t);
                if (!isset($unique[$key])) {
                    $unique[$key] = true;
                    $abilityList[] = strtolower($t);
                }
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

    $name = trim($_POST["name"] ?? '');
    $power = array_key_exists('power', $_POST) ? trim((string)$_POST["power"]) : null;
    $defense = array_key_exists('defense', $_POST) ? trim((string)$_POST["defense"]) : null;
    $power = ($power === '' || $power === null) ? null : (int)$power;
    $defense = ($defense === '' || $defense === null) ? null : (int)$defense;
    $description = array_key_exists('description', $_POST) ? trim((string)$_POST['description']) : null;
    $description = ($description === '') ? null : $description;
    $manaCosts = $_POST["mana"] ?? [];
    $types = $_POST["types"] ?? $_POST['type'] ?? [];
    $abilities = $_POST["abilities"] ?? $_POST["ability"] ?? [];

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

    $stmt = $conn->prepare("
        INSERT INTO cards (name, power, defense, description)
        VALUES (:name, :power, :defense, :description)
    ");

    $stmt->bindParam(":name", $name);
    $stmt->bindParam(":power", $power, $power === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindParam(":defense", $defense, $defense === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindParam(":description", $description, $description === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

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

    if (!empty($abilities) && is_array($abilities)) {
        $normalizedAbilities = [];
        foreach ($abilities as $key => $value) {
            $abilityName = is_numeric($key) ? trim((string)$value) : trim((string)$key);
            if ($abilityName !== '') {
                $normalizedAbilities[strtolower($abilityName)] = $abilityName;
            }
        }

        if ($normalizedAbilities) {
            $findAbility  = $conn->prepare("SELECT id FROM ability WHERE LOWER(ability) = LOWER(:a) LIMIT 1");
            $linkAbility  = $conn->prepare("INSERT IGNORE INTO card_ability (card_id, ability_id) VALUES (:c, :a)");
            foreach ($normalizedAbilities as $abilityName) {
                $findAbility->execute([':a' => $abilityName]);
                $abilityId = $findAbility->fetchColumn();
                if ($abilityId) {
                    $linkAbility->execute([':c' => $card_id, ':a' => (int)$abilityId]);
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
    "abilities" => array_values($normalizedAbilities ?? []),
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

    parse_str(file_get_contents("php://input"), $data);

    $name = $data["name"];
    $power = array_key_exists('power', $data) ? trim((string)$data["power"]) : null;
    $defense = array_key_exists('defense', $data) ? trim((string)$data["defense"]) : null;
    $power = ($power === '' || $power === null) ? null : (int)$power;
    $defense = ($defense === '' || $defense === null) ? null : (int)$defense;
    $description = array_key_exists('description', $data) ? trim((string)$data['description']) : null;
    $description = ($description === '') ? null : $description;
    $mana = $data["mana"] ?? null;
    $types = $data["types"] ?? null;
    $abilities = $data["types"] ?? null;

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

    if ($id) {
        $stmt = $conn->prepare("
            UPDATE cards
            SET 
            name = :name, 
            power = :power, 
            defense = :defense,
            description = :description,
            WHERE id = :id
            ");

        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":power", $power, $power === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(":defense", $defense, $defense === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(":description", $description, $description === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
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
                        $addMana->execute([":card" => $id, ":mana" => $mana_id]);
                    }
                }
            }
        }


        // --- Update types ---
        if ($types && is_array($types)) {
            // Remove old types
            $conn->prepare("DELETE FROM card_type WHERE card_id = :id")->execute([":id" => $id]);

            $findType = $conn->prepare("SELECT id FROM type WHERE LOWER(type) = LOWER(:t) LIMIT 1");
            $addType = $conn->prepare("INSERT INTO card_type (card_id, type_id) VALUES (:card, :type)");

            foreach ($types as $t) {
                $findType->execute([":t" => $t]);
                $type_id = $findType->fetchColumn();
                if ($type_id) {
                    $addType->execute([":card" => $id, ":type" => $type_id]);
                }
            }
        }

        // --- Update abilities ---
        if ($abilities && is_array($abilities)) {
            // Remove old abilities
            $conn->prepare("DELETE FROM card_ability WHERE card_id = :id")->execute([":id" => $id]);

            $findAbility = $conn->prepare("SELECT id FROM ability WHERE LOWER(ability) = LOWER(:a) LIMIT 1");
            $addAbility = $conn->prepare("INSERT INTO card_ability (card_id, ability_id) VALUES (:card, :ability)");

            foreach ($abilities as $a) {
                $findAbility->execute([":t" => $a]);
                $ability_id = $findAbility->fetchColumn();
                if ($ability_id) {
                    $addAbility->execute([":card" => $id, ":ability" => $ability_id]);
                }
            }
        }


        echo "Card updated";
    } else {
        echo "missing id";
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

    // parse x-www-form-urlencoded body
    $raw = file_get_contents("php://input");
    $data = [];
    parse_str($raw, $data);

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

    // ----- PATCH: abilities -----
// 1) Full replace if `abilities` or `ability` is provided (backward compatible)
    if (array_key_exists('abilities', $data) || array_key_exists('ability', $data)) {
        $abilitiesInput = $data['abilities'] ?? $data['ability'] ?? null;
        if (is_string($abilitiesInput)) {
            $abilitiesInput = [$abilitiesInput];
        }
        if (!is_array($abilitiesInput)) {
            $abilitiesInput = [];
        }

        $cleanAbilities = [];
        foreach ($abilitiesInput as $k => $v) {
            $s = is_numeric($k) ? (string)$v : (string)$k;
            $s = trim($s);
            if ($s !== '') {
                $cleanAbilities[strtolower($s)] = $s;
            }
        }

        // replace semantics: clear then insert
        $conn->prepare("DELETE FROM card_ability WHERE card_id = :id")->execute([":id" => $id]);

        if (!empty($cleanAbilities)) {
            // upsert to get id reliably
            $upsertAbility = $conn->prepare("
            INSERT INTO ability (ability) VALUES (:a)
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
        ");
            $checkLink  = $conn->prepare("SELECT 1 FROM card_ability WHERE card_id = :card AND ability_id = :ability LIMIT 1");
            $insLink    = $conn->prepare("INSERT INTO card_ability (card_id, ability_id) VALUES (:card, :ability)");

            foreach ($cleanAbilities as $name) {
                $upsertAbility->execute([':a' => $name]);
                $abilityId = (int)$conn->lastInsertId();
                $checkLink->execute([':card' => $id, ':ability' => $abilityId]);
                if (!$checkLink->fetchColumn()) {
                    $insLink->execute([':card' => $id, ':ability' => $abilityId]);
                }
            }
        }
    }

// 2) Fine-grained merge ops: add/remove/replace (only if provided)
    $toAddRaw    = $data['abilities_add']    ?? $data['ability_add']    ?? null;
    $toRemoveRaw = $data['abilities_remove'] ?? $data['ability_remove'] ?? null;
    $toReplace   = $data['abilities_replace'] ?? null; // expects assoc: old => new

    $normalizeList = function ($in) {
        $out = [];
        if ($in === null) {
            return $out;
        }
        if (is_string($in)) {
            $in = [$in];
        }
        if (!is_array($in)) {
            return $out;
        }
        foreach ($in as $k => $v) {
            $s = is_numeric($k) ? (string)$v : (string)$k;
            $s = trim($s);
            if ($s !== '') {
                $out[strtolower($s)] = $s; // de-dupe case-insensitively
            }
        }
        return array_values($out);
    };

    $toAdd    = $normalizeList($toAddRaw);
    $toRemove = $normalizeList($toRemoveRaw);

    if (!empty($toAdd) || !empty($toRemove) || (is_array($toReplace) && !empty($toReplace))) {
        $upsertAbility = $conn->prepare("
        INSERT INTO ability (ability) VALUES (:a)
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
    ");
        $findAbility  = $conn->prepare("SELECT id FROM ability WHERE LOWER(ability) = LOWER(:a) LIMIT 1");
        $checkLink    = $conn->prepare("SELECT 1 FROM card_ability WHERE card_id = :card AND ability_id = :ability LIMIT 1");
        $insLink      = $conn->prepare("INSERT INTO card_ability (card_id, ability_id) VALUES (:card, :ability)");
        $delLink      = $conn->prepare("DELETE FROM card_ability WHERE card_id = :card AND ability_id = :ability");

        // Add
        foreach ($toAdd as $name) {
            $upsertAbility->execute([':a' => $name]);
            $abilityId = (int)$conn->lastInsertId();
            $checkLink->execute([':card' => $id, ':ability' => $abilityId]);
            if (!$checkLink->fetchColumn()) {
                $insLink->execute([':card' => $id, ':ability' => $abilityId]);
            }
        }

        // Remove
        foreach ($toRemove as $name) {
            $findAbility->execute([':a' => $name]);
            $abilityId = $findAbility->fetchColumn();
            if ($abilityId) {
                $delLink->execute([':card' => $id, ':ability' => (int)$abilityId]);
            }
        }

        // Replace (exact text match: old -> new)
        if (is_array($toReplace)) {
            foreach ($toReplace as $old => $new) {
                $old = trim((string)$old);
                $new = trim((string)$new);
                if ($old === '' || $new === '') {
                    continue;
                }

                // Ensure new ability exists -> get id
                $upsertAbility->execute([':a' => $new]);
                $newId = (int)$conn->lastInsertId();

                // Find old id
                $findAbility->execute([':a' => $old]);
                $oldId = $findAbility->fetchColumn();
                if (!$oldId) {
                    continue;
                }

                // If already linked to new, just remove old link; else add new link then remove old
                $checkLink->execute([':card' => $id, ':ability' => $newId]);
                if (!$checkLink->fetchColumn()) {
                    $insLink->execute([':card' => $id, ':ability' => $newId]);
                }
                $delLink->execute([':card' => $id, ':ability' => (int)$oldId]);
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
