<?php
// test_notification.php inside /sessions/ folder
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/config.php';
require_once '../config/notification_helper.php';

echo "<h1>Notification System Debugger</h1>";

// 1. Test Database Connection
try {
    $db = getDB();
    echo "<p style='color:green'>✅ Database connection successful.</p>";
} catch (Exception $e) {
    die("<p style='color:red'>❌ Database connection failed: " . $e->getMessage() . "</p>");
}

// 2. Check Current User
if (!is_logged_in()) {
    die("<p style='color:red'>❌ You are not logged in. Please log in and try again.</p>");
}
$user = get_logged_in_user();
echo "<p><strong>Current User ID:</strong> " . $user['id'] . " (" . $user['first_name'] . ")</p>";

// 3. Attempt to Create a Test Notification
$test_title = "Test Notification " . date('H:i:s');
$result = create_notification($user['id'], 'info', $test_title, 'This is a manual test message.', '#');

if ($result) {
    echo "<p style='color:green'>✅ Notification inserted into DB successfully (ID: $result).</p>";
} else {
    echo "<p style='color:red'>❌ Failed to insert notification. Check 'notification_helper.php' logic.</p>";
}

// 4. Check File Paths
$api_path = '../api/notifications.php';
if (file_exists($api_path)) {
    echo "<p style='color:green'>✅ API file found at: $api_path</p>";
    echo "<p>👉 <a href='$api_path' target='_blank'>Click here to view API JSON Output</a> (Should show your notifications)</p>";
} else {
    echo "<p style='color:red'>❌ API file NOT found at: $api_path</p>";
    echo "<p><strong>Current location:</strong> " . __DIR__ . "</p>";
    echo "<p><strong>Looking for:</strong> " . realpath(__DIR__ . '/../') . "/api/notifications.php</p>";
}
?>