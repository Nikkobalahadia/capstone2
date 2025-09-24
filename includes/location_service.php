<?php
class LocationService {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Calculate distance between two coordinates using Haversine formula
     */
    public function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        if (empty($lat1) || empty($lon1) || empty($lat2) || empty($lon2)) {
            return null;
        }
        
        $earthRadius = 6371; // Earth's radius in kilometers
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }
    
    /**
     * Get users within specified radius
     */
    public function getUsersWithinRadius($centerLat, $centerLon, $radiusKm, $excludeUserId = null) {
        $query = "
            SELECT id, first_name, last_name, city, region, latitude, longitude,
                   (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * 
                   cos(radians(longitude) - radians(?)) + sin(radians(?)) * 
                   sin(radians(latitude)))) AS distance
            FROM users 
            WHERE latitude IS NOT NULL 
            AND longitude IS NOT NULL
            AND is_active = 1
        ";
        
        $params = [$centerLat, $centerLon, $centerLat];
        
        if ($excludeUserId) {
            $query .= " AND id != ?";
            $params[] = $excludeUserId;
        }
        
        $query .= " HAVING distance < ? ORDER BY distance";
        $params[] = $radiusKm;
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get location zones for a city/region
     */
    public function getLocationZones($city = null, $region = null) {
        $query = "SELECT * FROM location_zones WHERE is_active = 1";
        $params = [];
        
        if ($city) {
            $query .= " AND city = ?";
            $params[] = $city;
        }
        
        if ($region) {
            $query .= " AND region = ?";
            $params[] = $region;
        }
        
        $query .= " ORDER BY zone_name";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Calculate location compatibility score
     */
    public function calculateLocationScore($user1, $user2) {
        // Same city gets highest score
        if (!empty($user1['city']) && !empty($user2['city']) && 
            strtolower($user1['city']) === strtolower($user2['city'])) {
            return 100;
        }
        
        // Same region gets good score
        if (!empty($user1['region']) && !empty($user2['region']) && 
            strtolower($user1['region']) === strtolower($user2['region'])) {
            return 75;
        }
        
        // Calculate distance if coordinates available
        if (!empty($user1['latitude']) && !empty($user1['longitude']) && 
            !empty($user2['latitude']) && !empty($user2['longitude'])) {
            
            $distance = $this->calculateDistance(
                $user1['latitude'], $user1['longitude'],
                $user2['latitude'], $user2['longitude']
            );
            
            if ($distance !== null) {
                // Score based on distance (closer = higher score)
                if ($distance <= 5) return 90;      // Within 5km
                if ($distance <= 10) return 80;     // Within 10km
                if ($distance <= 20) return 65;     // Within 20km
                if ($distance <= 50) return 45;     // Within 50km
                if ($distance <= 100) return 25;    // Within 100km
                return 10; // More than 100km
            }
        }
        
        // Fallback to string similarity for location field
        if (!empty($user1['location']) && !empty($user2['location'])) {
            $similarity = 0;
            similar_text(strtolower($user1['location']), strtolower($user2['location']), $similarity);
            return min($similarity, 50); // Cap at 50 for basic string matching
        }
        
        return 20; // Default score for unknown locations
    }
    
    /**
     * Get popular cities from user data
     */
    public function getPopularCities($limit = 20) {
        $stmt = $this->db->prepare("
            SELECT city, region, COUNT(*) as user_count 
            FROM users 
            WHERE city IS NOT NULL AND city != '' 
            AND is_active = 1 
            GROUP BY city, region 
            ORDER BY user_count DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}
?>
