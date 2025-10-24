<?php

/**
 * Check if mentor has overdue commission payments
 * @param int $mentor_id The mentor's user ID
 * @param PDO $db Database connection
 * @return array ['has_overdue' => bool, 'overdue_count' => int, 'total_overdue' => float, 'oldest_days' => int]
 */
function check_overdue_commissions($mentor_id, $db) {
    $grace_period_days = 2; // 2-day grace period
    
    $query = "
        SELECT 
            COUNT(*) as overdue_count,
            SUM(commission_amount) as total_overdue,
            DATEDIFF(NOW(), MIN(created_at)) as oldest_days
        FROM commission_payments
        WHERE mentor_id = ?
        AND payment_status IN ('pending', 'rejected')
        AND DATEDIFF(NOW(), created_at) > ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$mentor_id, $grace_period_days]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'has_overdue' => $result['overdue_count'] > 0,
        'overdue_count' => (int)$result['overdue_count'],
        'total_overdue' => (float)$result['total_overdue'],
        'oldest_days' => (int)$result['oldest_days']
    ];
}

/**
 * Check if mentor account should be suspended due to unpaid commissions
 * @param int $mentor_id The mentor's user ID
 * @param PDO $db Database connection
 * @return bool True if account should be suspended
 */
function should_suspend_mentor($mentor_id, $db) {
    $suspension_threshold_days = 30; // Suspend after 30 days
    
    $query = "
        SELECT COUNT(*) as count
        FROM commission_payments
        WHERE mentor_id = ?
        AND payment_status IN ('pending', 'rejected')
        AND DATEDIFF(NOW(), created_at) > ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$mentor_id, $suspension_threshold_days]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] > 0;
}

/**
 * Get mentor's commission payment summary
 * @param int $mentor_id The mentor's user ID
 * @param PDO $db Database connection
 * @return array Summary of commission payments
 */
function get_commission_summary($mentor_id, $db) {
    $query = "
        SELECT 
            COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_count,
            SUM(CASE WHEN payment_status = 'pending' THEN commission_amount ELSE 0 END) as pending_amount,
            COUNT(CASE WHEN payment_status = 'submitted' THEN 1 END) as submitted_count,
            SUM(CASE WHEN payment_status = 'submitted' THEN commission_amount ELSE 0 END) as submitted_amount,
            COUNT(CASE WHEN payment_status = 'verified' THEN 1 END) as verified_count,
            SUM(CASE WHEN payment_status = 'verified' THEN commission_amount ELSE 0 END) as verified_amount,
            MIN(CASE WHEN payment_status IN ('pending', 'rejected') THEN created_at END) as oldest_unpaid
        FROM commission_payments
        WHERE mentor_id = ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$mentor_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Update overdue status for all commission payments
 * Automatically marks payments as overdue if they exceed 2 days and are not verified
 * @param PDO $db Database connection
 * @return int Number of records updated
 */
function update_overdue_status($db) {
    try {
        // Mark payments as overdue if they're older than 2 days and not verified
        $query = "
            UPDATE commission_payments 
            SET is_overdue = 1
            WHERE payment_status != 'verified' 
            AND DATEDIFF(NOW(), created_at) > 2
            AND is_overdue = 0
        ";
        $db->exec($query);
        
        // Mark verified payments as not overdue
        $query2 = "
            UPDATE commission_payments 
            SET is_overdue = 0
            WHERE payment_status = 'verified'
            AND is_overdue = 1
        ";
        $db->exec($query2);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error updating overdue status: " . $e->getMessage());
        return false;
    }
}
