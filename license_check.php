<?php
header("Content-Type: application/json");
require_once "db.php";

$user_id = trim($_POST["user_id"] ?? "");

if ($user_id === "") {
    echo json_encode([
        "success" => false,
        "status" => "OFF",
        "message" => "Missing USER_ID"
    ]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT *,
            CASE
                WHEN last_seen_at IS NULL THEN NULL
                ELSE TIMESTAMPDIFF(SECOND, last_seen_at, NOW())
            END AS check_delta
     FROM licenses
     WHERE user_id=?"
);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "status" => "OFF",
        "message" => "Unknown USER_ID"
    ]);
    exit;
}

$row = $result->fetch_assoc();

$status   = $row["status"];
$mode     = $row["license_mode"];
$duration = (int)$row["duration_seconds"];
$used     = (int)$row["used_seconds"];
$delta    = $row["check_delta"] === null ? null : (int)$row["check_delta"];

/*
 * CALENDAR mode:
 * The license expires at the fixed calendar time.
 */
if ($status === "ON" && $mode === "CALENDAR") {

    $expires = $row["expires_at"] ? strtotime($row["expires_at"]) : null;
    $now = time();

    if ($expires !== null && $now >= $expires) {
        $status = "OFF";

        $stmt2 = $conn->prepare(
            "UPDATE licenses
             SET status='OFF', last_seen_at=NOW()
             WHERE user_id=?"
        );
        $stmt2->bind_param("s", $user_id);
        $stmt2->execute();
        $stmt2->close();
    } else {
        $stmt2 = $conn->prepare(
            "UPDATE licenses SET last_seen_at=NOW() WHERE user_id=?"
        );
        $stmt2->bind_param("s", $user_id);
        $stmt2->execute();
        $stmt2->close();
    }
}

/*
 * USAGE mode:
 * Count only the time between successful license checks.
 *
 * The calculation is done by MySQL using NOW() and TIMESTAMPDIFF(),
 * so PHP/MySQL timezone differences cannot make the usage negative.
 *
 * A gap greater than 120 seconds is treated as the customer's
 * computer/application being OFF, so that long gap is NOT counted.
 */
elseif ($status === "ON" && $mode === "USAGE") {

    if ($delta !== null && $delta > 0 && $delta <= 120) {
        $used += $delta;
    }

    if ($used >= $duration) {
        $used = $duration;
        $status = "OFF";

        $stmt2 = $conn->prepare(
            "UPDATE licenses
             SET status='OFF',
                 used_seconds=?,
                 last_seen_at=NOW()
             WHERE user_id=?"
        );
        $stmt2->bind_param("is", $used, $user_id);
        $stmt2->execute();
        $stmt2->close();

    } else {

        $stmt2 = $conn->prepare(
            "UPDATE licenses
             SET used_seconds=?,
                 last_seen_at=NOW()
             WHERE user_id=?"
        );
        $stmt2->bind_param("is", $used, $user_id);
        $stmt2->execute();
        $stmt2->close();
    }
}

$remaining = null;

if ($status === "ON") {

    if ($mode === "CALENDAR") {
        $expires = $row["expires_at"] ? strtotime($row["expires_at"]) : null;
        if ($expires !== null) {
            $remaining = max(0, $expires - time());
        }
    }

    if ($mode === "USAGE") {
        $remaining = max(0, $duration - $used);
    }
}

echo json_encode([
    "success" => true,
    "user_id" => $user_id,
    "status" => $status,
    "license_mode" => $mode,
    "remaining_seconds" => $remaining,
    "used_seconds" => $used,
    "duration_seconds" => $duration,
    "expires_at" => $row["expires_at"],
    "server_time" => date("Y-m-d H:i:s"),
    "message" => $status === "ON"
        ? "Application authorized"
        : "Application disabled"
]);
?>
