
<?php
session_start();
if (!isset($_SESSION['user'])) exit("Unauthorized");

$user = $_SESSION['user'];
$file = 'notifications.json';

if (file_exists($file)) {
    $notifications = json_decode(file_get_contents($file), true);
    $notifications['users'][$user] = $notifications['global_count'] ?? 0;
    file_put_contents($file, json_encode($notifications, JSON_PRETTY_PRINT));
    echo "OK";
}
?>
