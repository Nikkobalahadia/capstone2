<?php
require_once '../config/config.php';

if (!is_logged_in()) {
    redirect('../auth/login.php');
}

$user = get_logged_in_user();
$db = getDB();

$success = '';
$error = '';

// Handle preference update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['location_preference'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token.';
    } else {
        $preference = sanitize_input($_POST['location_preference']);
        
        if (!in_array($preference, ['nearby', 'far', 'random'])) {
            $error = 'Invalid location preference.';
        } else {
            try {
                $stmt = $db->prepare("UPDATE users SET location_preference = ? WHERE id = ?");
                $stmt->execute([$preference, $user['id']]);
                
                // Update session
                $_SESSION['user']['location_preference'] = $preference;
                
                $success = 'Location preference updated successfully!';
            } catch (Exception $e) {
                error_log("Location preference update error: " . $e->getMessage());
                $error = 'Failed to update preference. Please try again.';
            }
        }
    }
}

// Get current preference
$current_preference = $user['location_preference'] ?? 'nearby';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Location Preference - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #fafafa;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #1a1a1a;
        }

        .card-subtitle {
            color: #666;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
        }

        .alert-success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .alert-error {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }

        .preference-options {
            display: grid;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .preference-option {
            display: flex;
            align-items: flex-start;
            padding: 1.5rem;
            border: 2px solid #e5e5e5;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .preference-option:hover {
            border-color: #2563eb;
            background: #f0f9ff;
        }

        .preference-option input[type="radio"] {
            margin-right: 1rem;
            margin-top: 0.25rem;
            cursor: pointer;
            width: 20px;
            height: 20px;
        }

        .preference-option input[type="radio"]:checked + .preference-content {
            color: #2563eb;
        }

        .preference-option.selected {
            border-color: #2563eb;
            background: #f0f9ff;
        }

        .preference-content {
            flex: 1;
        }

        .preference-title {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.25rem;
            color: #1a1a1a;
        }

        .preference-description {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.5;
        }

        .preference-icon {
            font-size: 1.5rem;
            margin-right: 1rem;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
        }

        .btn {
            flex: 1;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #e5e5e5;
            color: #1a1a1a;
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: #d4d4d4;
        }

        .info-box {
            background: #f0f9ff;
            border-left: 4px solid #2563eb;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 2rem;
        }

        .info-box-title {
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 0.5rem;
        }

        .info-box-text {
            color: #1e40af;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .card {
                padding: 1.5rem;
            }

            .preference-option {
                padding: 1rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <a href="../profile/index.php" style="color: #2563eb; text-decoration: none; margin-bottom: 1rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-arrow-left"></i> Back to Profile
            </a>

            <h1 class="card-title">
                <i class="fas fa-map-marker-alt"></i> Location Preference
            </h1>
            <p class="card-subtitle">Choose how you'd like to match with study partners based on location</p>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo $success; ?></div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo $error; ?></div>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                <div class="preference-options">
                    <!-- Nearby Option -->
                    <label class="preference-option <?php echo $current_preference === 'nearby' ? 'selected' : ''; ?>">
                        <input type="radio" name="location_preference" value="nearby" 
                               <?php echo $current_preference === 'nearby' ? 'checked' : ''; ?> 
                               onchange="updateSelection(this)">
                        <div class="preference-content">
                            <div class="preference-title">
                                <i class="fas fa-location-dot"></i> Nearby Matches
                            </div>
                            <div class="preference-description">
                                Match with study partners in your area. Prioritizes users within 5-25 km of your location. Great for in-person study sessions and local connections.
                            </div>
                        </div>
                    </label>

                    <!-- Far Option -->
                    <label class="preference-option <?php echo $current_preference === 'far' ? 'selected' : ''; ?>">
                        <input type="radio" name="location_preference" value="far" 
                               <?php echo $current_preference === 'far' ? 'checked' : ''; ?> 
                               onchange="updateSelection(this)">
                        <div class="preference-content">
                            <div class="preference-title">
                                <i class="fas fa-globe"></i> Far/Different Location
                            </div>
                            <div class="preference-description">
                                Match with study partners from different locations. Prioritizes users 50+ km away. Perfect for online sessions and connecting with people from different regions.
                            </div>
                        </div>
                    </label>

                    <!-- Random Option -->
                    <label class="preference-option <?php echo $current_preference === 'random' ? 'selected' : ''; ?>">
                        <input type="radio" name="location_preference" value="random" 
                               <?php echo $current_preference === 'random' ? 'checked' : ''; ?> 
                               onchange="updateSelection(this)">
                        <div class="preference-content">
                            <div class="preference-title">
                                <i class="fas fa-shuffle"></i> No Location Preference
                            </div>
                            <div class="preference-description">
                                Match with anyone regardless of location. Ignores distance and focuses on subject compatibility, availability, and ratings. Best for flexible learners.
                            </div>
                        </div>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Preference
                    </button>
                    <a href="../profile/index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>

            <div class="info-box">
                <div class="info-box-title">
                    <i class="fas fa-info-circle"></i> How This Works
                </div>
                <div class="info-box-text">
                    Your location preference will be used when finding matches. The matchmaking algorithm will prioritize matches based on your preference while still considering subject compatibility, availability, and ratings. You can change this preference anytime.
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateSelection(radio) {
            // Remove selected class from all options
            document.querySelectorAll('.preference-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to parent label
            radio.closest('.preference-option').classList.add('selected');
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            const checkedRadio = document.querySelector('input[type="radio"]:checked');
            if (checkedRadio) {
                checkedRadio.closest('.preference-option').classList.add('selected');
            }
        });
    </script>
</body>
</html>
