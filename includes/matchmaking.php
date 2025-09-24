<?php
// Matchmaking Algorithm Class
class MatchmakingEngine {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Find potential matches for a user based on weighted criteria
     */
    public function findMatches($user_id, $subject = null, $limit = 10) {
        $user = $this->getUserProfile($user_id);
        if (!$user) return [];
        
        if ($user['role'] === 'student') {
            // Students looking for help can only match with mentors and peers
            $target_roles = ['mentor', 'peer'];
        } elseif ($user['role'] === 'peer') {
            // Peers looking for help can match with mentors and other peers
            $target_roles = ['mentor', 'peer'];
        } else {
            // Mentors looking for students can match with students and peers
            $target_roles = ['student', 'peer'];
        }
        
        $limit = max(1, min(100, (int)$limit)); // Clamp between 1 and 100 for security
        
        // Build the query with support for multiple target roles
        $role_placeholders = str_repeat('?,', count($target_roles) - 1) . '?';
        
        $query = "
            SELECT DISTINCT u.*, 
                   GROUP_CONCAT(DISTINCT us.subject_name) as subjects,
                   GROUP_CONCAT(DISTINCT us.proficiency_level) as proficiency_levels,
                   COUNT(DISTINCT ua.id) as availability_slots,
                   AVG(sr.rating) as avg_rating,
                   COUNT(DISTINCT sr.id) as rating_count
            FROM users u
            LEFT JOIN user_subjects us ON u.id = us.user_id
            LEFT JOIN user_availability ua ON u.id = ua.user_id AND ua.is_active = 1
            LEFT JOIN session_ratings sr ON u.id = sr.rated_id
            WHERE u.role IN ($role_placeholders)
            AND u.id != ? 
            AND u.is_active = 1
            AND u.id NOT IN (
                SELECT CASE 
                    WHEN student_id = ? THEN mentor_id 
                    ELSE student_id 
                END
                FROM matches 
                WHERE (student_id = ? OR mentor_id = ?) 
                AND status IN ('pending', 'accepted')
            )
        ";
        
        $params = array_merge($target_roles, [$user_id, $user_id, $user_id, $user_id]);
        
        // Add subject filter if specified
        if ($subject) {
            $query .= " AND us.subject_name = ?";
            $params[] = $subject;
        }
        
        $query .= " GROUP BY u.id ORDER BY u.created_at DESC LIMIT " . $limit;
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $potential_matches = $stmt->fetchAll();
        
        // Calculate match scores for each potential match
        $scored_matches = [];
        foreach ($potential_matches as $match) {
            $score = $this->calculateMatchScore($user, $match, $subject);
            $match['match_score'] = $score;
            $scored_matches[] = $match;
        }
        
        // Sort by match score (highest first)
        usort($scored_matches, function($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });
        
        return $scored_matches;
    }
    
    /**
     * Find students who need help (for mentors/peers offering help)
     */
    public function findStudentsNeedingHelp($user_id, $subject = null, $limit = 10) {
        $user = $this->getUserProfile($user_id);
        if (!$user || !in_array($user['role'], ['mentor', 'peer'])) return [];
        
        $limit = max(1, min(100, (int)$limit));
        
        $query = "
            SELECT DISTINCT u.*, 
                   GROUP_CONCAT(DISTINCT us.subject_name) as subjects,
                   GROUP_CONCAT(DISTINCT us.proficiency_level) as proficiency_levels,
                   COUNT(DISTINCT ua.id) as availability_slots,
                   AVG(sr.rating) as avg_rating,
                   COUNT(DISTINCT sr.id) as rating_count
            FROM users u
            LEFT JOIN user_subjects us ON u.id = us.user_id
            LEFT JOIN user_availability ua ON u.id = ua.user_id AND ua.is_active = 1
            LEFT JOIN session_ratings sr ON u.id = sr.rated_id
            WHERE u.role IN ('student', 'peer')
            AND u.id != ? 
            AND u.is_active = 1
            AND u.id NOT IN (
                SELECT CASE 
                    WHEN student_id = ? THEN mentor_id 
                    ELSE student_id 
                END
                FROM matches 
                WHERE (student_id = ? OR mentor_id = ?) 
                AND status IN ('pending', 'accepted')
            )
        ";
        
        $params = [$user_id, $user_id, $user_id, $user_id];
        
        // Add subject filter if specified - find users who need help with this subject
        if ($subject) {
            $query .= " AND us.subject_name = ? AND us.proficiency_level IN ('beginner', 'intermediate')";
            $params[] = $subject;
        }
        
        $query .= " GROUP BY u.id ORDER BY u.created_at DESC LIMIT " . $limit;
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $potential_matches = $stmt->fetchAll();
        
        // Calculate match scores for each potential match
        $scored_matches = [];
        foreach ($potential_matches as $match) {
            $score = $this->calculateMatchScore($user, $match, $subject);
            $match['match_score'] = $score;
            $scored_matches[] = $match;
        }
        
        // Sort by match score (highest first)
        usort($scored_matches, function($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });
        
        return $scored_matches;
    }
    
    /**
     * Calculate match score using weighted criteria
     */
    private function calculateMatchScore($user, $potential_match, $subject = null) {
        $score = 0;
        $weights = [
            'subject_match' => 40,      // 40% - Subject compatibility
            'grade_level' => 20,        // 20% - Grade level proximity
            'location' => 15,           // 15% - Location proximity
            'availability' => 15,       // 15% - Schedule compatibility
            'rating' => 10              // 10% - User rating
        ];
        
        // 1. Subject Match Score
        $subject_score = $this->calculateSubjectScore($user['id'], $potential_match['id'], $subject);
        $score += ($subject_score / 100) * $weights['subject_match'];
        
        // 2. Grade Level Proximity Score
        $grade_score = $this->calculateGradeLevelScore($user['grade_level'], $potential_match['grade_level']);
        $score += ($grade_score / 100) * $weights['grade_level'];
        
        // 3. Location Proximity Score
        $location_score = $this->calculateLocationScore($user['location'], $potential_match['location']);
        $score += ($location_score / 100) * $weights['location'];
        
        // 4. Availability Compatibility Score
        $availability_score = $this->calculateAvailabilityScore($user['id'], $potential_match['id']);
        $score += ($availability_score / 100) * $weights['availability'];
        
        // 5. Rating Score
        $rating_score = $this->calculateRatingScore($potential_match['avg_rating'], $potential_match['rating_count']);
        $score += ($rating_score / 100) * $weights['rating'];
        
        return round($score, 2);
    }
    
    private function calculateSubjectScore($user_id, $match_id, $target_subject = null) {
        // Get user's subjects
        $user_subjects = $this->getUserSubjects($user_id);
        $match_subjects = $this->getUserSubjects($match_id);
        
        if (empty($user_subjects) || empty($match_subjects)) {
            return 0;
        }
        
        $score = 0;
        $total_subjects = count($user_subjects);
        
        foreach ($user_subjects as $user_subject) {
            foreach ($match_subjects as $match_subject) {
                if ($user_subject['subject_name'] === $match_subject['subject_name']) {
                    // Base score for subject match
                    $subject_score = 60;
                    
                    // Bonus for target subject match
                    if ($target_subject && $user_subject['subject_name'] === $target_subject) {
                        $subject_score += 40;
                    }
                    
                    // Proficiency level compatibility bonus
                    $proficiency_bonus = $this->getProficiencyBonus(
                        $user_subject['proficiency_level'], 
                        $match_subject['proficiency_level']
                    );
                    $subject_score += $proficiency_bonus;
                    
                    $score = max($score, $subject_score);
                }
            }
        }
        
        return min($score, 100);
    }
    
    private function getProficiencyBonus($user_level, $match_level) {
        $levels = ['beginner' => 1, 'intermediate' => 2, 'advanced' => 3, 'expert' => 4];
        $user_num = $levels[$user_level] ?? 1;
        $match_num = $levels[$match_level] ?? 1;
        
        // Ideal: mentor should be 1-2 levels higher than student
        $diff = abs($user_num - $match_num);
        
        if ($diff === 0) return 10; // Same level
        if ($diff === 1) return 20; // One level difference (ideal)
        if ($diff === 2) return 15; // Two levels difference
        return 5; // More than 2 levels difference
    }
    
    private function calculateGradeLevelScore($user_grade, $match_grade) {
        if (empty($user_grade) || empty($match_grade)) return 50;
        
        // Extract numeric part for comparison
        $user_num = $this->extractGradeNumber($user_grade);
        $match_num = $this->extractGradeNumber($match_grade);
        
        $diff = abs($user_num - $match_num);
        
        if ($diff === 0) return 100; // Same grade
        if ($diff === 1) return 80;  // One grade difference
        if ($diff === 2) return 60;  // Two grades difference
        if ($diff === 3) return 40;  // Three grades difference
        return 20; // More than 3 grades difference
    }
    
    private function extractGradeNumber($grade) {
        if (strpos($grade, 'Grade') !== false) {
            return (int) filter_var($grade, FILTER_SANITIZE_NUMBER_INT);
        }
        if (strpos($grade, 'College') !== false) {
            $year = filter_var($grade, FILTER_SANITIZE_NUMBER_INT);
            return 12 + $year; // Convert college years to equivalent grade numbers
        }
        return 10; // Default fallback
    }
    
    private function calculateLocationScore($user_location, $match_location) {
        if (empty($user_location) || empty($match_location)) return 30;
        
        // Simple string similarity for location matching
        $similarity = 0;
        similar_text(strtolower($user_location), strtolower($match_location), $similarity);
        
        return min($similarity, 100);
    }
    
    private function calculateAvailabilityScore($user_id, $match_id) {
        $user_availability = $this->getUserAvailability($user_id);
        $match_availability = $this->getUserAvailability($match_id);
        
        if (empty($user_availability) || empty($match_availability)) return 30;
        
        $overlapping_slots = 0;
        $total_user_slots = count($user_availability);
        
        foreach ($user_availability as $user_slot) {
            foreach ($match_availability as $match_slot) {
                if ($user_slot['day_of_week'] === $match_slot['day_of_week']) {
                    // Check for time overlap
                    if ($this->hasTimeOverlap($user_slot, $match_slot)) {
                        $overlapping_slots++;
                        break;
                    }
                }
            }
        }
        
        return $total_user_slots > 0 ? ($overlapping_slots / $total_user_slots) * 100 : 0;
    }
    
    private function hasTimeOverlap($slot1, $slot2) {
        $start1 = strtotime($slot1['start_time']);
        $end1 = strtotime($slot1['end_time']);
        $start2 = strtotime($slot2['start_time']);
        $end2 = strtotime($slot2['end_time']);
        
        return ($start1 < $end2) && ($end1 > $start2);
    }
    
    private function calculateRatingScore($avg_rating, $rating_count) {
        if (!isset($avg_rating) || !isset($rating_count) || !$avg_rating || $rating_count < 1) {
            return 50; // Neutral score for new users
        }
        
        // Base score from rating (0-5 scale converted to 0-100)
        $rating_score = ($avg_rating / 5) * 80;
        
        // Reliability bonus based on number of ratings
        $reliability_bonus = min(($rating_count / 10) * 20, 20);
        
        return min($rating_score + $reliability_bonus, 100);
    }
    
    // Helper methods
    private function getUserProfile($user_id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }
    
    private function getUserSubjects($user_id) {
        $stmt = $this->db->prepare("SELECT subject_name, proficiency_level FROM user_subjects WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }
    
    private function getUserAvailability($user_id) {
        $stmt = $this->db->prepare("SELECT day_of_week, start_time, end_time FROM user_availability WHERE user_id = ? AND is_active = 1");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Create a match request
     */
    public function createMatch($student_id, $mentor_id, $subject, $message = '') {
        try {
            $this->db->beginTransaction();
            
            // Get user profiles to determine roles
            $student = $this->getUserProfile($student_id);
            $mentor = $this->getUserProfile($mentor_id);
            
            // For peer-to-peer matches, we need to determine who is the "student" and who is the "mentor" for this specific subject
            if ($student['role'] === 'peer' && $mentor['role'] === 'peer') {
                // Both are peers - the one requesting help becomes the "student" for this match
                // This is handled by the calling code passing the correct IDs
            }
            
            // Calculate match score
            $match_score = $this->calculateMatchScore($student, $mentor, $subject);
            
            // Insert match
            $stmt = $this->db->prepare("
                INSERT INTO matches (student_id, mentor_id, subject, match_score, status) 
                VALUES (?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$student_id, $mentor_id, $subject, $match_score]);
            $match_id = $this->db->lastInsertId();
            
            // Log activity
            $log_stmt = $this->db->prepare("
                INSERT INTO user_activity_logs (user_id, action, details, ip_address) 
                VALUES (?, 'match_request', ?, ?)
            ");
            $log_stmt->execute([
                $student_id, 
                json_encode([
                    'match_id' => $match_id, 
                    'mentor_id' => $mentor_id, 
                    'subject' => $subject,
                    'student_role' => $student['role'],
                    'mentor_role' => $mentor['role']
                ]), 
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $this->db->commit();
            return $match_id;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Accept or reject a match
     */
    public function respondToMatch($match_id, $user_id, $response) {
        if (!in_array($response, ['accepted', 'rejected'])) {
            throw new Exception('Invalid response');
        }
        
        try {
            $this->db->beginTransaction();
            
            // Verify user is part of this match
            $stmt = $this->db->prepare("SELECT * FROM matches WHERE id = ? AND (student_id = ? OR mentor_id = ?)");
            $stmt->execute([$match_id, $user_id, $user_id]);
            $match = $stmt->fetch();
            
            if (!$match) {
                throw new Exception('Match not found or unauthorized');
            }
            
            // Update match status
            $update_stmt = $this->db->prepare("UPDATE matches SET status = ?, updated_at = NOW() WHERE id = ?");
            $update_stmt->execute([$response, $match_id]);
            
            // Log activity
            $log_stmt = $this->db->prepare("
                INSERT INTO user_activity_logs (user_id, action, details, ip_address) 
                VALUES (?, 'match_response', ?, ?)
            ");
            $log_stmt->execute([
                $user_id, 
                json_encode(['match_id' => $match_id, 'response' => $response]), 
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
?>
