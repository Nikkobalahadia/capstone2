<?php
class MatchmakingEngine {
    private $db;
    
    private $weights = [
        'role_compatibility' => 25,    // 25% - Role-based matching rules
        'subject_expertise' => 20,     // 20% - Subject knowledge alignment
        'location_proximity' => 15,    // 15% - Geographic proximity
        'grade_level' => 12,          // 12% - Academic level compatibility
        'strand_course' => 10,        // 10% - Academic track alignment
        'time_availability' => 10,    // 10% - Schedule compatibility
        'user_rating' => 5,           // 5% - Historical performance
        'activity_level' => 3         // 3% - Platform engagement
    ];
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Find nearest matches based on location proximity using Haversine formula
     */
    public function findNearestMatches($user_id, $limit = 5) {
        $user = $this->getUserProfile($user_id);
        if (!$user || !$user['latitude'] || !$user['longitude']) {
            return [];
        }
        
        $target_roles = $this->getTargetRoles($user['role']);
        $limit = max(1, min(50, (int)$limit));
        
        $role_placeholders = str_repeat('?,', count($target_roles) - 1) . '?';
        
        $query = "
            SELECT DISTINCT u.*, 
                   GROUP_CONCAT(DISTINCT us.subject_name) as subjects,
                   GROUP_CONCAT(DISTINCT us.proficiency_level) as proficiency_levels,
                   COUNT(DISTINCT ua.id) as availability_slots,
                   COALESCE(AVG(sr.rating), 0) as avg_rating,
                   COALESCE(COUNT(DISTINCT sr.id), 0) as rating_count,
                   COALESCE(COUNT(DISTINCT ual.id), 0) as activity_count,
                   COALESCE(MAX(ual.created_at), '1970-01-01 00:00:00') as last_activity,
                   (6371 * acos(cos(radians(?)) * cos(radians(u.latitude)) * cos(radians(u.longitude) - radians(?)) + 
                    sin(radians(?)) * sin(radians(u.latitude)))) AS distance_km
            FROM users u
            LEFT JOIN user_subjects us ON u.id = us.user_id
            LEFT JOIN user_availability ua ON u.id = ua.user_id AND ua.is_active = 1
            LEFT JOIN session_ratings sr ON u.id = sr.rated_id
            LEFT JOIN user_activity_logs ual ON u.id = ual.user_id 
                AND ual.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            WHERE u.role IN ($role_placeholders)
            AND u.id != ? 
            AND u.is_active = 1
            AND u.matchmaking_enabled = 1
            AND (
                u.role IN ('student', 'mentor', 'peer') AND u.is_verified = 1
            )
            AND u.latitude IS NOT NULL 
            AND u.longitude IS NOT NULL
            AND u.id NOT IN (
                SELECT CASE 
                    WHEN student_id = ? THEN mentor_id 
                    ELSE student_id 
                END
                FROM matches 
                WHERE (student_id = ? OR mentor_id = ?) 
                AND status IN ('pending', 'accepted')
            )
            AND u.id NOT IN (
                SELECT rejected_id 
                FROM user_rejections 
                WHERE rejector_id = ? 
                AND expires_at > NOW()
            )
            GROUP BY u.id 
            HAVING distance_km <= 100
            ORDER BY distance_km ASC 
            LIMIT " . $limit;
        
        $params = array_merge(
            [$user['latitude'], $user['longitude'], $user['latitude']], // Haversine formula params
            $target_roles, 
            [$user_id, $user_id, $user_id, $user_id, $user_id]
        );
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $potential_matches = $stmt->fetchAll();
        
        // Calculate enhanced match scores for each potential match
        $scored_matches = [];
        foreach ($potential_matches as $match) {
            $score = $this->calculateEnhancedMatchScore($user, $match);
            $match['match_score'] = $score;
            $match['distance_km'] = round($match['distance_km'], 2);
            $scored_matches[] = $match;
        }
        
        // Sort by combined score (distance + compatibility)
        usort($scored_matches, function($a, $b) {
            // Prioritize closer matches with good compatibility
            $score_a = $a['match_score'] - ($a['distance_km'] * 0.5); // Distance penalty
            $score_b = $b['match_score'] - ($b['distance_km'] * 0.5);
            return $score_b <=> $score_a;
        });
        
        return $scored_matches;
    }
    
    /**
     * Enhanced matching algorithm with comprehensive criteria
     */
    public function findMatches($user_id, $subject = null, $limit = 10) {
        $user = $this->getUserProfile($user_id);
        if (!$user) return [];
        
        $target_roles = $this->getTargetRoles($user['role']);
        $limit = max(1, min(100, (int)$limit));
        
        $role_placeholders = str_repeat('?,', count($target_roles) - 1) . '?';

        $query = "
            SELECT DISTINCT u.*, 
                   GROUP_CONCAT(DISTINCT us.subject_name) as subjects,
                   GROUP_CONCAT(DISTINCT us.proficiency_level) as proficiency_levels,
                   COUNT(DISTINCT ua.id) as availability_slots,
                   COALESCE(AVG(sr.rating), 0) as avg_rating,
                   COALESCE(COUNT(DISTINCT sr.id), 0) as rating_count,
                   COALESCE(COUNT(DISTINCT ual.id), 0) as activity_count,
                   COALESCE(MAX(ual.created_at), '1970-01-01 00:00:00') as last_activity
            FROM users u
            LEFT JOIN user_subjects us ON u.id = us.user_id
            LEFT JOIN user_availability ua ON u.id = ua.user_id AND ua.is_active = 1
            LEFT JOIN session_ratings sr ON u.id = sr.rated_id
            LEFT JOIN user_activity_logs ual ON u.id = ual.user_id 
                AND ual.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            WHERE u.role IN ($role_placeholders)
            AND u.id != ? 
            AND u.is_active = 1
            AND u.matchmaking_enabled = 1
            AND (
                u.role IN ('student', 'mentor', 'peer') AND u.is_verified = 1
            )
            AND u.id NOT IN (
                SELECT CASE 
                    WHEN student_id = ? THEN mentor_id 
                    ELSE student_id 
                END
                FROM matches 
                WHERE (student_id = ? OR mentor_id = ?) 
                AND status IN ('pending', 'accepted')
            )
            AND u.id NOT IN (
                SELECT rejected_id 
                FROM user_rejections 
                WHERE rejector_id = ? 
                AND expires_at > NOW()
            )
        ";
        
        $params = array_merge($target_roles, [$user_id, $user_id, $user_id, $user_id, $user_id]);
        
        if ($subject) {
            $query .= " AND us.subject_name = ?";
            $params[] = $subject;
        }
        
        $query .= " GROUP BY u.id ORDER BY u.created_at DESC LIMIT " . $limit;
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $potential_matches = $stmt->fetchAll();
        
        $scored_matches = [];
        foreach ($potential_matches as $match) {
            $score = $this->calculateEnhancedMatchScore($user, $match, $subject);
            $match['match_score'] = $score;
            $match['score_breakdown'] = $this->getScoreBreakdown($user, $match, $subject);
            $scored_matches[] = $match;
        }
        
        // Sort by match score (highest first)
        usort($scored_matches, function($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });
        
        return $scored_matches;
    }
    
    private function getTargetRoles($user_role) {
        switch ($user_role) {
            case 'student':
                return ['mentor', 'peer'];
            case 'peer':
                return ['mentor', 'peer'];
            case 'mentor':
                return ['student', 'peer'];
            default:
                return ['student', 'mentor', 'peer'];
        }
    }
    
    private function calculateEnhancedMatchScore($user, $potential_match, $subject = null) {
        $total_score = 0;
        
        // 1. Role Compatibility Score (25%)
        $role_score = $this->calculateRoleCompatibilityScore($user['role'], $potential_match['role']);
        $total_score += ($role_score / 100) * $this->weights['role_compatibility'];
        
        // 2. Subject Expertise Score (20%)
        $subject_score = $this->calculateSubjectExpertiseScore($user['id'], $potential_match['id'], $subject);
        $total_score += ($subject_score / 100) * $this->weights['subject_expertise'];
        
        // 3. Location Proximity Score (15%) - Enhanced with coordinates
        $user_coords = null;
        $match_coords = null;
        
        if ($user['latitude'] && $user['longitude']) {
            $user_coords = ['lat' => $user['latitude'], 'lng' => $user['longitude']];
        }
        if ($potential_match['latitude'] && $potential_match['longitude']) {
            $match_coords = ['lat' => $potential_match['latitude'], 'lng' => $potential_match['longitude']];
        }
        
        $location_score = $this->calculateLocationProximityScore(
            $user['location'], 
            $potential_match['location'],
            $user_coords,
            $match_coords
        );
        $total_score += ($location_score / 100) * $this->weights['location_proximity'];
        
        // 4. Grade Level Compatibility Score (12%)
        $grade_score = $this->calculateGradeLevelCompatibilityScore($user['grade_level'], $potential_match['grade_level']);
        $total_score += ($grade_score / 100) * $this->weights['grade_level'];
        
        // 5. Strand & Course Alignment Score (10%)
        $strand_course_score = $this->calculateStrandCourseScore($user, $potential_match);
        $total_score += ($strand_course_score / 100) * $this->weights['strand_course'];
        
        // 6. Time Availability Score (10%)
        $availability_score = $this->calculateTimeAvailabilityScore($user['id'], $potential_match['id']);
        $total_score += ($availability_score / 100) * $this->weights['time_availability'];
        
        // 7. User Rating Score (5%)
        $rating_score = $this->calculateUserRatingScore(
    $potential_match['avg_rating'] ?? null,
    $potential_match['rating_count'] ?? null
);
        $total_score += ($rating_score / 100) * $this->weights['user_rating'];
        
        // 8. Activity Level Score (3%)

$activity_score = $this->calculateActivityLevelScore(
    $potential_match['activity_count'] ?? null,
    $potential_match['last_activity'] ?? null
);
        $total_score += ($activity_score / 100) * $this->weights['activity_level'];
        
        return round($total_score, 2);
    }
    
    private function calculateRoleCompatibilityScore($user_role, $match_role) {
        $compatibility_matrix = [
            'student' => ['mentor' => 100, 'peer' => 85, 'student' => 0],
            'peer' => ['mentor' => 95, 'peer' => 90, 'student' => 80],
            'mentor' => ['student' => 100, 'peer' => 90, 'mentor' => 70]
        ];
        
        return $compatibility_matrix[$user_role][$match_role] ?? 50;
    }
    
    private function calculateSubjectExpertiseScore($user_id, $match_id, $target_subject = null) {
        $user_subjects = $this->getUserSubjects($user_id);
        $match_subjects = $this->getUserSubjects($match_id);
        
        if (empty($user_subjects) || empty($match_subjects)) {
            return 20; // Low score for missing subject data
        }
        
        $max_score = 0;
        $subject_matches = 0;
        $total_user_subjects = count($user_subjects);
        
        foreach ($user_subjects as $user_subject) {
            foreach ($match_subjects as $match_subject) {
                if ($user_subject['subject_name'] === $match_subject['subject_name']) {
                    $subject_matches++;
                    $base_score = 60;
                    
                    // Target subject bonus
                    if ($target_subject && $user_subject['subject_name'] === $target_subject) {
                        $base_score += 25;
                    }
                    
                    // Proficiency complementarity
                    $proficiency_score = $this->calculateProficiencyComplementarity(
                        $user_subject['proficiency_level'], 
                        $match_subject['proficiency_level']
                    );
                    
                    $subject_score = $base_score + $proficiency_score;
                    $max_score = max($max_score, $subject_score);
                }
            }
        }
        
        // Bonus for multiple subject matches
        $overlap_bonus = min(($subject_matches / $total_user_subjects) * 15, 15);
        
        return min($max_score + $overlap_bonus, 100);
    }
    
    private function calculateProficiencyComplementarity($user_level, $match_level) {
        $levels = ['beginner' => 1, 'intermediate' => 2, 'advanced' => 3, 'expert' => 4];
        $user_num = $levels[$user_level] ?? 1;
        $match_num = $levels[$match_level] ?? 1;
        
        $diff = $match_num - $user_num;
        
        // Ideal: helper should be 1-2 levels higher
        if ($diff === 1) return 15; // Perfect mentoring gap
        if ($diff === 2) return 12; // Good mentoring gap
        if ($diff === 0) return 8;  // Peer level
        if ($diff === -1) return 5; // Reverse mentoring possible
        return 2; // Large gap or reverse gap
    }
    
    private function calculateLocationProximityScore($user_location, $match_location, $user_coords = null, $match_coords = null) {
        // If coordinates are available, use distance-based matching
        if ($user_coords && $match_coords && 
            isset($user_coords['lat'], $user_coords['lng'], $match_coords['lat'], $match_coords['lng'])) {
            
            $distance = $this->calculateDistance(
                $user_coords['lat'], $user_coords['lng'],
                $match_coords['lat'], $match_coords['lng']
            );
            
            // Distance-based scoring (in kilometers)
            if ($distance <= 5) return 100;      // Within 5km - excellent
            if ($distance <= 10) return 90;     // Within 10km - very good
            if ($distance <= 25) return 75;     // Within 25km - good
            if ($distance <= 50) return 60;     // Within 50km - fair
            if ($distance <= 100) return 40;    // Within 100km - acceptable
            return 20; // Beyond 100km - poor
        }
        
        // Fallback to existing text-based matching
        return $this->calculateTextLocationScore($user_location, $match_location);
    }
    
    private function calculateDistance($lat1, $lng1, $lat2, $lng2) {
        $earthRadius = 6371; // Earth's radius in kilometers
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }
    
    private function calculateTextLocationScore($user_location, $match_location) {
        if (empty($user_location) || empty($match_location)) return 40;
        
        $user_location = strtolower(trim($user_location));
        $match_location = strtolower(trim($match_location));
        
        // Exact match
        if ($user_location === $match_location) return 100;
        
        // City/region matching
        $user_parts = explode(',', $user_location);
        $match_parts = explode(',', $match_location);
        
        $max_similarity = 0;
        foreach ($user_parts as $user_part) {
            foreach ($match_parts as $match_part) {
                $similarity = 0;
                similar_text(trim($user_part), trim($match_part), $similarity);
                $max_similarity = max($max_similarity, $similarity);
            }
        }
        
        // Distance-based scoring (simplified)
        if ($max_similarity > 80) return 90; // Same city/area
        if ($max_similarity > 60) return 70; // Same region
        if ($max_similarity > 40) return 50; // Same province/state
        return 30; // Different regions
    }
    
    private function calculateGradeLevelCompatibilityScore($user_grade, $match_grade) {
        if (empty($user_grade) || empty($match_grade)) return 50;
        
        $user_num = $this->normalizeGradeLevel($user_grade);
        $match_num = $this->normalizeGradeLevel($match_grade);
        
        $diff = abs($user_num - $match_num);
        
        // Grade level compatibility matrix
        if ($diff === 0) return 100; // Same grade
        if ($diff === 1) return 85;  // Adjacent grades
        if ($diff === 2) return 70;  // Two grades apart
        if ($diff === 3) return 55;  // Three grades apart
        if ($diff <= 5) return 40;   // Within reasonable range
        return 25; // Large grade gap
    }
    
    private function calculateStrandCourseScore($user, $match) {
        $strand_score = 0;
        $course_score = 0;
        
        // Strand alignment
        if (!empty($user['strand']) && !empty($match['strand'])) {
            if (strtolower($user['strand']) === strtolower($match['strand'])) {
                $strand_score = 100;
            } else {
                // Check for related strands (STEM, ABM, HUMSS, etc.)
                $strand_similarity = $this->calculateStrandSimilarity($user['strand'], $match['strand']);
                $strand_score = $strand_similarity;
            }
        } else {
            $strand_score = 50; // Neutral for missing data
        }
        
        // Course alignment
        if (!empty($user['course']) && !empty($match['course'])) {
            $similarity = 0;
            similar_text(strtolower($user['course']), strtolower($match['course']), $similarity);
            $course_score = $similarity;
        } else {
            $course_score = 50; // Neutral for missing data
        }
        
        // Weighted combination (strand 60%, course 40%)
        return ($strand_score * 0.6) + ($course_score * 0.4);
    }
    
    private function calculateStrandSimilarity($strand1, $strand2) {
        $strand_groups = [
            'stem' => ['stem', 'science', 'technology', 'engineering', 'mathematics'],
            'abm' => ['abm', 'business', 'management', 'accounting', 'entrepreneurship'],
            'humss' => ['humss', 'humanities', 'social sciences', 'psychology', 'sociology'],
            'gas' => ['gas', 'general academic', 'liberal arts'],
            'tvl' => ['tvl', 'technical', 'vocational', 'livelihood']
        ];
        
        $strand1_lower = strtolower($strand1);
        $strand2_lower = strtolower($strand2);
        
        foreach ($strand_groups as $group => $keywords) {
            $in_group1 = false;
            $in_group2 = false;
            
            foreach ($keywords as $keyword) {
                if (strpos($strand1_lower, $keyword) !== false) $in_group1 = true;
                if (strpos($strand2_lower, $keyword) !== false) $in_group2 = true;
            }
            
            if ($in_group1 && $in_group2) return 75; // Same strand group
        }
        
        return 25; // Different strand groups
    }
    
    /**
     * Calculate match score using weighted criteria
     */
    public function calculateTimeAvailabilityScore($user_id, $match_id) {
        $user_availability = $this->getUserAvailability($user_id);
        $match_availability = $this->getUserAvailability($match_id);
        
        if (empty($user_availability) || empty($match_availability)) return 40;
        
        $total_overlap_hours = 0;
        $total_user_hours = 0;
        
        foreach ($user_availability as $user_slot) {
            $user_duration = $this->calculateSlotDuration($user_slot);
            $total_user_hours += $user_duration;
            
            foreach ($match_availability as $match_slot) {
                if ($user_slot['day_of_week'] === $match_slot['day_of_week']) {
                    $overlap = $this->calculateTimeOverlap($user_slot, $match_slot);
                    $total_overlap_hours += $overlap;
                }
            }
        }
        
        if ($total_user_hours === 0) return 0;
        
        $overlap_percentage = ($total_overlap_hours / $total_user_hours) * 100;
        
        // Bonus for having substantial overlap
        if ($overlap_percentage > 50) return min($overlap_percentage + 10, 100);
        return $overlap_percentage;
    }
    
    private function calculateActivityLevelScore($activity_count, $last_activity) {
        if (!isset($activity_count) || $activity_count === null || $activity_count == 0) {
            return 30; // Low score for inactive users
        }
        
        $base_score = min(($activity_count / 20) * 60, 60); // Up to 60 points for activity
        
        // Recency bonus
        if ($last_activity && $last_activity !== '1970-01-01 00:00:00') {
            $days_since = (time() - strtotime($last_activity)) / (24 * 60 * 60);
            if ($days_since <= 1) $base_score += 40;      // Very recent
            elseif ($days_since <= 3) $base_score += 30;  // Recent
            elseif ($days_since <= 7) $base_score += 20;  // This week
            elseif ($days_since <= 14) $base_score += 10; // This fortnight
        }
        
        return min($base_score, 100);
    }
    
    public function getScoreBreakdown($user, $match, $subject = null) {
        $user_coords = null;
        $match_coords = null;
        
        if ($user['latitude'] && $user['longitude']) {
            $user_coords = ['lat' => $user['latitude'], 'lng' => $user['longitude']];
        }
        if ($match['latitude'] && $match['longitude']) {
            $match_coords = ['lat' => $match['latitude'], 'lng' => $match['longitude']];
        }
        
        return [
            'role_compatibility' => $this->calculateRoleCompatibilityScore($user['role'], $match['role']),
            'subject_expertise' => $this->calculateSubjectExpertiseScore($user['id'], $match['id'], $subject),
            'location_proximity' => $this->calculateLocationProximityScore($user['location'], $match['location'], $user_coords, $match_coords),
            'grade_level' => $this->calculateGradeLevelCompatibilityScore($user['grade_level'], $match['grade_level']),
            'strand_course' => $this->calculateStrandCourseScore($user, $match),
            'time_availability' => $this->calculateTimeAvailabilityScore($user['id'], $match['id']),
            'user_rating' => $this->calculateUserRatingScore($match['avg_rating'], $match['rating_count']),
            'activity_level' => $this->calculateActivityLevelScore($match['activity_count'], $match['last_activity'])
        ];
    }
    
    private function normalizeGradeLevel($grade) {
        if (empty($grade)) return 10;
        
        // Extract numbers from grade strings
        if (preg_match('/(\\d+)/', $grade, $matches)) {
            $num = (int)$matches[1];
            
            // Handle different grade systems
            if (strpos(strtolower($grade), 'college') !== false || 
                strpos(strtolower($grade), 'university') !== false) {
                return 12 + $num; // College years start after grade 12
            }
            
            return $num;
        }
        
        return 10; // Default fallback
    }
    
    private function calculateSlotDuration($slot) {
        $start = strtotime($slot['start_time']);
        $end = strtotime($slot['end_time']);
        return ($end - $start) / 3600; // Convert to hours
    }
    
    private function calculateTimeOverlap($slot1, $slot2) {
        $start1 = strtotime($slot1['start_time']);
        $end1 = strtotime($slot1['end_time']);
        $start2 = strtotime($slot2['start_time']);
        $end2 = strtotime($slot2['end_time']);
        
        $overlap_start = max($start1, $start2);
        $overlap_end = min($end1, $end2);
        
        if ($overlap_start < $overlap_end) {
            return ($overlap_end - $overlap_start) / 3600; // Return hours of overlap
        }
        
        return 0;
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
                   COALESCE(AVG(sr.rating), 0) as avg_rating,
                   COALESCE(COUNT(DISTINCT sr.id), 0) as rating_count,
                   COALESCE(COUNT(DISTINCT ual.id), 0) as activity_count,
                   COALESCE(MAX(ual.created_at), '1970-01-01 00:00:00') as last_activity
            FROM users u
            LEFT JOIN user_subjects us ON u.id = us.user_id
            LEFT JOIN user_availability ua ON u.id = ua.user_id AND ua.is_active = 1
            LEFT JOIN session_ratings sr ON u.id = sr.rated_id
            LEFT JOIN user_activity_logs ual ON u.id = ual.user_id 
                AND ual.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            WHERE u.role IN ('student', 'peer')
            AND u.id != ? 
            AND u.is_active = 1
            AND u.matchmaking_enabled = 1
            AND (
                u.role IN ('student', 'peer') AND u.is_verified = 1
            )
            AND u.id NOT IN (
                SELECT CASE 
                    WHEN student_id = ? THEN mentor_id 
                    ELSE student_id 
                END
                FROM matches 
                WHERE (student_id = ? OR mentor_id = ?) 
                AND status IN ('pending', 'accepted')
            )
            AND u.id NOT IN (
                SELECT rejected_id 
                FROM user_rejections 
                WHERE rejector_id = ? 
                AND expires_at > NOW()
            )
        ";
        
        $params = [$user_id, $user_id, $user_id, $user_id, $user_id];
        
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
            $score = $this->calculateEnhancedMatchScore($user, $match, $subject);
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
        if (!isset($avg_rating) || !isset($rating_count) || $avg_rating === null || $rating_count === null || !$avg_rating || $rating_count < 1) {
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
            $match_score = $this->calculateEnhancedMatchScore($student, $mentor, $subject);
            
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
    
    private function calculateUserRatingScore($avg_rating, $rating_count) {
        if (!isset($avg_rating) || !isset($rating_count) || $avg_rating === null || $rating_count === null || $avg_rating == 0 || $rating_count < 1) {
            return 50; // Neutral score for new users
        }
        
        // Base score from rating (0-5 scale converted to 0-100)
        $rating_score = ($avg_rating / 5) * 80;
        
        // Reliability bonus based on number of ratings
        $reliability_bonus = min(($rating_count / 10) * 20, 20);
        
        return min($rating_score + $reliability_bonus, 100);
    }
}
?>