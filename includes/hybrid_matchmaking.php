<?php
require_once 'matchmaking.php';
require_once 'online_status.php';

class HybridMatchmakingEngine extends MatchmakingEngine {
    private $onlineManager;
    private $notificationManager;
    protected $db;
    
    public function __construct($database) {
        parent::__construct($database);
        $this->db = $database;
        $this->onlineManager = new OnlineStatusManager($database);
        $this->notificationManager = new NotificationManager($database);
    }
    
    /**
     * Create a match request with hybrid online/offline handling
     */
    public function createHybridMatch($student_id, $mentor_id, $subject, $message = '') {
        try {
            $this->db->beginTransaction();
            
            // Create the match using parent method
            $match_id = parent::createMatch($student_id, $mentor_id, $subject, $message);
            
            // Get user details
            $student = $this->getUserProfile($student_id);
            $mentor = $this->getUserProfile($mentor_id);
            
            // Check if recipient (mentor) is online
            $is_mentor_online = $this->onlineManager->isUserOnline($mentor_id);
            
            if ($is_mentor_online) {
                // ✅ Mentor is online - create real-time notification
                $this->createRealTimeNotification($match_id, $mentor_id, $student_id, 'request');
                
                // Log as real-time delivery
                $this->logActivity($student_id, 'match_request_realtime', [
                    'match_id' => $match_id,
                    'mentor_id' => $mentor_id,
                    'delivery_method' => 'realtime'
                ]);
                
            } else {
                // ❌ Mentor is offline - save as pending notification
                $this->notificationManager->createNotification(
                    $mentor_id,
                    'match_request',
                    'New Match Request',
                    "You have a match request from {$student['first_name']} {$student['last_name']} for {$subject}",
                    [
                        'match_id' => $match_id,
                        'student_id' => $student_id,
                        'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                        'subject' => $subject,
                        'message' => $message
                    ]
                );
                
                // Log as pending delivery
                $this->logActivity($student_id, 'match_request_pending', [
                    'match_id' => $match_id,
                    'mentor_id' => $mentor_id,
                    'delivery_method' => 'pending'
                ]);
            }
            
            $this->db->commit();
            return [
                'match_id' => $match_id,
                'delivery_method' => $is_mentor_online ? 'realtime' : 'pending',
                'recipient_online' => $is_mentor_online
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Respond to match with notification to requester
     */
    public function respondToHybridMatch($match_id, $user_id, $response) {
        try {
            $this->db->beginTransaction();
            
            // Get match details before responding
            $stmt = $this->db->prepare("SELECT * FROM matches WHERE id = ?");
            $stmt->execute([$match_id]);
            $match = $stmt->fetch();
            
            if (!$match) {
                throw new Exception('Match not found');
            }
            
            // Respond using parent method
            parent::respondToMatch($match_id, $user_id, $response);
            
            // Determine who to notify (the other person in the match)
            $notify_user_id = ($match['student_id'] == $user_id) ? $match['mentor_id'] : $match['student_id'];
            $responder = $this->getUserProfile($user_id);
            
            // Check if requester is online
            $is_requester_online = $this->onlineManager->isUserOnline($notify_user_id);
            
            $response_message = $response === 'accepted' 
                ? "Your match request has been accepted by {$responder['first_name']} {$responder['last_name']}"
                : "Your match request has been declined by {$responder['first_name']} {$responder['last_name']}";
            
            if ($is_requester_online) {
                // Real-time notification
                $this->createRealTimeNotification($match_id, $notify_user_id, $user_id, 'response');
            } else {
                // Pending notification
                $this->notificationManager->createNotification(
                    $notify_user_id,
                    'match_' . $response,
                    'Match Request ' . ucfirst($response),
                    $response_message,
                    [
                        'match_id' => $match_id,
                        'responder_id' => $user_id,
                        'responder_name' => $responder['first_name'] . ' ' . $responder['last_name'],
                        'response' => $response,
                        'subject' => $match['subject']
                    ]
                );
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Create real-time notification record
     */
    private function createRealTimeNotification($match_id, $recipient_id, $sender_id, $type) {
        $stmt = $this->db->prepare("
            INSERT INTO match_notifications (match_id, recipient_id, sender_id, type, status) 
            VALUES (?, ?, ?, ?, 'pending')
        ");
        return $stmt->execute([$match_id, $recipient_id, $sender_id, $type]);
    }
    
    /**
     * Get pending notifications for user when they log in
     */
    public function getPendingMatchRequests($user_id) {
        return $this->notificationManager->getUnreadNotifications($user_id);
    }
    
    /**
     * Get real-time notifications for online user
     */
    public function getRealTimeNotifications($user_id) {
        $stmt = $this->db->prepare("
            SELECT mn.*, m.subject, m.status as match_status,
                   CONCAT(u.first_name, ' ', u.last_name) as sender_name,
                   u.role as sender_role
            FROM match_notifications mn
            JOIN matches m ON mn.match_id = m.id
            JOIN users u ON mn.sender_id = u.id
            WHERE mn.recipient_id = ? 
            AND mn.status = 'pending'
            ORDER BY mn.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Mark real-time notification as delivered
     */
    public function markNotificationDelivered($notification_id) {
        $stmt = $this->db->prepare("
            UPDATE match_notifications 
            SET status = 'delivered', delivered_at = NOW() 
            WHERE id = ?
        ");
        return $stmt->execute([$notification_id]);
    }
    
    /**
     * Mark real-time notification as seen
     */
    public function markNotificationSeen($notification_id) {
        $stmt = $this->db->prepare("
            UPDATE match_notifications 
            SET status = 'seen', seen_at = NOW() 
            WHERE id = ?
        ");
        return $stmt->execute([$notification_id]);
    }
    
    protected function getUserProfile($user_id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }
    
    private function logActivity($user_id, $action, $details) {
        $stmt = $this->db->prepare("
            INSERT INTO user_activity_logs (user_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([
            $user_id, 
            $action, 
            json_encode($details), 
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
}
?>
