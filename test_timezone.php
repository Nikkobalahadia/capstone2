<?php
// test_timezone_v2.php - Advanced timezone debugging
session_start();
date_default_timezone_set('Asia/Manila');

try {
    $conn = new PDO(
        "mysql:host=localhost;dbname=study_mentorship_platform",
        "root",
        ""
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h3>Timezone Configuration Test</h3>";
    echo "<hr>";
    
    // Test 1: Before setting timezone
    echo "<h4>Test 1: Before Setting Timezone</h4>";
    $stmt = $conn->query("SELECT NOW() as mysql_time, @@session.time_zone as session_tz");
    $result = $stmt->fetch();
    echo "MySQL NOW(): <strong>" . $result['mysql_time'] . "</strong><br>";
    echo "MySQL Session TZ: <strong>" . $result['session_tz'] . "</strong><br>";
    echo "PHP date(): <strong>" . date('Y-m-d H:i:s') . "</strong><br><br>";
    
    // Test 2: Try setting timezone with +08:00
    echo "<h4>Test 2: After Setting TZ to +08:00</h4>";
    $conn->exec("SET time_zone = '+08:00'");
    $stmt = $conn->query("SELECT NOW() as mysql_time, @@session.time_zone as session_tz");
    $result = $stmt->fetch();
    echo "MySQL NOW(): <strong>" . $result['mysql_time'] . "</strong><br>";
    echo "MySQL Session TZ: <strong>" . $result['session_tz'] . "</strong><br><br>";
    
    // Test 3: Try setting timezone with Asia/Manila
    echo "<h4>Test 3: After Setting TZ to 'Asia/Manila'</h4>";
    try {
        $conn->exec("SET time_zone = 'Asia/Manila'");
        $stmt = $conn->query("SELECT NOW() as mysql_time, @@session.time_zone as session_tz");
        $result = $stmt->fetch();
        echo "MySQL NOW(): <strong>" . $result['mysql_time'] . "</strong><br>";
        echo "MySQL Session TZ: <strong>" . $result['session_tz'] . "</strong><br>";
    } catch (Exception $e) {
        echo "<strong style='color: red;'>ERROR: " . $e->getMessage() . "</strong><br>";
        echo "Your MySQL server doesn't have timezone tables loaded. Using +08:00 instead.<br>";
    }
    echo "<br>";
    
    // Test 4: Check actual message timestamps
    echo "<h4>Test 4: Recent Messages from Database</h4>";
    $stmt = $conn->query("SELECT id, sender_id, message, created_at FROM messages ORDER BY created_at DESC LIMIT 5");
    $messages = $stmt->fetchAll();
    
    if ($messages) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Sender</th><th>Message</th><th>Created At (DB)</th><th>Expected Manila Time</th></tr>";
        foreach ($messages as $msg) {
            // Convert from UTC to Manila (add 8 hours)
            $dt = new DateTime($msg['created_at'], new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('Asia/Manila'));
            $manila_time = $dt->format('Y-m-d H:i:s');
            
            echo "<tr>";
            echo "<td>" . $msg['id'] . "</td>";
            echo "<td>" . $msg['sender_id'] . "</td>";
            echo "<td>" . htmlspecialchars(substr($msg['message'], 0, 30)) . "...</td>";
            echo "<td>" . $msg['created_at'] . "</td>";
            echo "<td><strong style='color: green;'>" . $manila_time . "</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No messages found.<br>";
    }
    echo "<br>";
    
    // Test 5: Insert a test message and check
    echo "<h4>Test 5: Insert Test Message</h4>";
    
    // Reset timezone to +08:00
    $conn->exec("SET time_zone = '+08:00'");
    
    // Create temp table
    $conn->exec("CREATE TEMPORARY TABLE test_messages (id INT AUTO_INCREMENT PRIMARY KEY, message TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    
    // Insert with NOW()
    $conn->exec("INSERT INTO test_messages (message) VALUES ('Test message')");
    
    $stmt = $conn->query("SELECT created_at FROM test_messages LIMIT 1");
    $test = $stmt->fetch();
    echo "Inserted timestamp: <strong>" . $test['created_at'] . "</strong><br>";
    echo "Current Manila time should be: <strong>" . date('Y-m-d H:i:s') . "</strong><br>";
    
    // Check if they match (within 1 minute)
    $diff = abs(strtotime($test['created_at']) - strtotime(date('Y-m-d H:i:s')));
    if ($diff < 60) {
        echo "<strong style='color: green;'>✓ Timestamps match! Timezone is working correctly.</strong><br>";
    } else {
        echo "<strong style='color: red;'>✗ Timestamps don't match. Difference: " . $diff . " seconds</strong><br>";
    }
    
    echo "<hr>";
    echo "<h4>Recommendations:</h4>";
    echo "<ol>";
    echo "<li>If Test 5 shows timestamps match: Your MySQL configuration is correct</li>";
    echo "<li>If timestamps are still 8 hours off in your app: The issue is in JavaScript formatting</li>";
    echo "<li>Current PHP timezone: <strong>" . date_default_timezone_get() . "</strong></li>";
    echo "<li>Expected offset: UTC+8 (Manila Time)</li>";
    echo "</ol>";
    
} catch(PDOException $e) {
    echo "Connection error: " . $e->getMessage();
}
?>