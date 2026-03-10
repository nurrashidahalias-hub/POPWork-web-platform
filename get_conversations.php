<?php
session_start();
include("db_connect.php");
if (!isset($_SESSION['user_id'])) exit();

$sql = "SELECT c.conversation_id, u.username, u.email 
        FROM conversations c
        JOIN users u ON u.user_id = (CASE WHEN c.employer_id = ? THEN c.worker_id ELSE c.employer_id END)
        WHERE c.employer_id = ? OR c.worker_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($conv = $result->fetch_assoc()) {
    $name = $conv['username'] ?: $conv['email'];
    echo "<a href='chat.php?conversation_id={$conv['conversation_id']}'>" . htmlspecialchars($name) . "</a><br>";
}
