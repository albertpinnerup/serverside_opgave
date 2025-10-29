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

    $stmt = $conn->prepare("SELECT * FROM mana WHERE id = :id");
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
    $stmt = $conn->query("SELECT color, id FROM mana");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    for ($i = 0; $i < count($results); $i++) {
        $results[$i]["url"] = "http://localhost:8080/collection/mana?id=" . $results[$i]["id"];
        unset($results[$i]["id"]);
    }


    header("Content-Type: application/json; charset=utf-8");

    $output = ["results" => $results];
    echo json_encode($output);
}

if ($_SERVER["REQUEST_METHOD"] === "GET" && !empty($_GET["id"])) {
    $id = validateID();

    $stmt = $conn->prepare("SELECT * FROM mana WHERE id = :id");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    header("Content-Type: application/json; charset=utf-8");

    $output = ["results" => $result];
    echo json_encode($output);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $color = $_POST["color"];

    $stmt = $conn->prepare("INSERT INTO mana (`color`)
                                                    VALUES(:color)");

    $stmt->bindParam(":color", $color);

    $stmt->execute();
}

if ($_SERVER["REQUEST_METHOD"] === "PUT") {
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

    $color = $data["color"];

    if ($id) {
        $stmt = $conn->prepare("
            UPDATE mana
            SET color = :color
            WHERE id = :id
            ");

        $stmt->bindParam(":color", $color);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        $stmt->execute();

        echo "Card updated";
    } else {
        echo "missing id";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
    $id = $_GET["id"];


    if ($id) {
        $stmt = $conn->prepare("
            DELETE FROM mana
            where id = :id
            ");

        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();

        echo "Card deleted";
    } else {
        echo "missing id";
    }
}
