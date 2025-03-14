<?php
session_start();
include "db.php";

$user_ip = $_SERVER['REMOTE_ADDR'];
$cookie_name = "coupon_claimed";

if (!isset($_COOKIE[$cookie_name])) {
    setcookie($cookie_name, uniqid(), time() + 3600, "/");
}

$user_cookie = $_COOKIE[$cookie_name] ?? uniqid();

// Check if IP or cookie ID exists in logs within the last hour
$sql = "SELECT * FROM logs WHERE (ip_address = ? OR cookie_id = ?) AND claim_time > NOW() - INTERVAL 1 HOUR";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $user_ip, $user_cookie);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "You have already claimed a coupon. Try again later."]);
    exit;
}

// Fetch the next available coupon
$sql = "SELECT * FROM coupons WHERE assigned_to_ip IS NULL ORDER BY id ASC LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $coupon_id = $row['id'];
    $coupon_code = $row['code'];

    // Assign the coupon
    $update_sql = "UPDATE coupons SET assigned_to_ip = ?, assigned_to_cookie = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ssi", $user_ip, $user_cookie, $coupon_id);
    $stmt->execute();

    // Log the claim
    $log_sql = "INSERT INTO logs (ip_address, cookie_id) VALUES (?, ?)";
    $stmt = $conn->prepare($log_sql);
    $stmt->bind_param("ss", $user_ip, $user_cookie);
    $stmt->execute();

    echo json_encode(["status" => "success", "coupon" => $coupon_code]);
} else {
    echo json_encode(["status" => "error", "message" => "No coupons available."]);
}
?>
