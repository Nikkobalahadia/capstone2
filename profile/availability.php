<?php
require_once '../config/config.php';
require_once '../config/notification_helper.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('../auth/login.php');
}

$user = get_logged_in_user();
if (!$user) {
    redirect('../auth/login.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $availability_data = $_POST['availability'] ?? [];
        $has_valid_data = false;
        
        try {
            $db = getDB();
            $db->beginTransaction();
            
            // Clear existing availability
            $clear_stmt = $db->prepare("DELETE FROM user_availability WHERE user_id = ?");
            $clear_stmt->execute([$user['id']]);
            
            // Add new availability
            $insert_stmt = $db->prepare("INSERT INTO user_availability (user_id, day_of_week, start_time, end_time, is_active) VALUES (?, ?, ?, ?, 1)");
            
            foreach ($availability_data as $day => $times) {
                if (!empty($times['start']) && !empty($times['end'])) {
                    $start = strtotime($times['start']);
                    $end = strtotime($times['end']);
                    
                    if ($times['end'] === '00:00:00') {
                        $end = strtotime('24:00:00');
                    }
                    
                    if ($end > $start) {
                        $insert_stmt->execute([$user['id'], $day, $times['start'], $times['end']]);
                        $has_valid_data = true;
                    }
                }
            }
            
            $db->commit();
            $success = 'Availability updated successfully!';
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Failed to update availability. Please try again.';
        }
    }
}

// Get existing availability
$db = getDB();
$availability_stmt = $db->prepare("SELECT day_of_week, start_time, end_time FROM user_availability WHERE user_id = ? AND is_active = 1");
$availability_stmt->execute([$user['id']]);
$existing_availability = [];
while ($row = $availability_stmt->fetch()) {
    $existing_availability[$row['day_of_week']] = [
        'start' => $row['start_time'],
        'end' => $row['end_time']
    ];
}

$days = [
    'monday' => ['icon' => 'fa-coffee', 'color' => '#ef4444'],
    'tuesday' => ['icon' => 'fa-code', 'color' => '#f59e0b'],
    'wednesday' => ['icon' => 'fa-book', 'color' => '#10b981'],
    'thursday' => ['icon' => 'fa-graduation-cap', 'color' => '#3b82f6'],
    'friday' => ['icon' => 'fa-star', 'color' => '#8b5cf6'],
    'saturday' => ['icon' => 'fa-sun', 'color' => '#f59e0b'],
    'sunday' => ['icon' => 'fa-moon', 'color' => '#6366f1']
];

function generate_time_options($selected_val, $type = 'start') {
    $options_html = '<option value="">Not available</option>';
    $start = strtotime('00:00');
    $end_of_day = strtotime('24:00');
    
    $current = ($type === 'end') ? strtotime('+30 minutes', $start) : $start;
    $limit = ($type === 'start') ? strtotime('23:30') : $end_of_day;
    
    while ($current <= $limit) {
        $time_val = date('H:i:s', $current);
        $time_display = date('g:i A', $current);
        
        if ($time_val === '00:00:00' && $current > $start) {
            $time_display = '12:00 AM (Next Day)';
        }
        
        $selected = ($selected_val === $time_val) ? 'selected' : '';
        $options_html .= "<option value=\"$time_val\" $selected>$time_display</option>";
        
        $current = strtotime('+30 minutes', $current);
    }
    return $options_html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Availability Schedule - Study Buddy</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --text-primary: #1a1a1a;
            --text-secondary: #666;
            --border-color: #e5e5e5;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.1);
            --bg-color: #fafafa;
            --card-bg: white;
            --error-bg: #fee2e2;
            --error-border: #fca5a5;
            --error-text: #991b1b;
        }

        [data-theme="dark"] {
            --primary-color: #3b82f6;
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --border-color: #374151;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.3);
            --bg-color: #111827;
            --card-bg: #1f2937;
            --error-bg: #2f1d1d;
            --error-border: #fca5a5;
            --error-text: #fecaca;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            overflow-x: hidden;
            width: 100%;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-color);
            color: var(--text-primary);
            transition: background-color 0.3s ease, color 0.3s ease;
            min-height: 100vh;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            padding: 0.5rem 0;
            margin-bottom: 1.5rem;
            transition: color 0.2s;
        }
        .back-button:hover {
            color: var(--primary-color);
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        main {
            padding: 2rem 0;
        }
        
        /* Page Header */
        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        .page-header p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Card */
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .card-body {
            padding: 1.5rem;
        }
        
        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success {
            background: #dcfce7;
            border: 1px solid #86efac;
            color: #166534;
        }
        .alert-error {
            background: var(--error-bg);
            border: 1px solid var(--error-border);
            color: var(--error-text);
        }

        /* Availability Day Card */
        .day-card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 1rem;
            display: grid;
            grid-template-columns: 80px 1fr auto;
            align-items: center;
            gap: 1.5rem;
            padding: 1rem;
            transition: all 0.3s ease;
        }
        
        .day-card.error {
            border-color: var(--error-border);
            background: var(--error-bg);
        }

        .day-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            flex-shrink: 0;
            margin: 0 auto;
        }
        .day-header {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            text-transform: capitalize;
        }
        .time-inputs {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .time-inputs span {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        .time-input select {
            width: 140px;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            color: var(--text-primary);
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .time-input select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        /* Button */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
        }
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .btn-secondary {
            background: var(--border-color);
            color: var(--text-primary);
        }
        .btn-secondary:hover {
            background: rgba(0,0,0,0.1);
        }
        [data-theme="dark"] .btn-secondary:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .availability-utils {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 1rem;
        }

        .btn-group {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            main {
                padding: 1rem 0;
            }
            .container {
                padding: 0 0.75rem;
            }
            input, select, textarea, button {
                font-size: 16px !important;
            }
        }

        @media (max-width: 640px) {
            .day-card {
                grid-template-columns: 1fr;
                gap: 1rem;
                text-align: center;
            }
            .time-inputs {
                flex-direction: column;
                gap: 0.75rem;
            }
            .time-input select {
                width: 100%;
            }
            .btn-group {
                flex-direction: column;
            }
            .btn {
                width: 100%;
            }
            .availability-utils {
                justify-content: stretch;
            }
            .availability-utils .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<script>
    (function() {
        const theme = localStorage.getItem('theme');
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.body.classList.add('dark-mode');
        } else {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    })();
</script>

<main>
    <div class="container">
        <a href="index.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Profile
        </a>
        
        <div class="page-header">
            <h1>Set Your Availability</h1>
            <p>Let matches know when you're free. Select your available time slots in 30-minute intervals for each day.</p>
        </div>

        <div id="form-error" class="alert alert-error" style="display: none;"></div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="availability-utils">
            <button type="button" id="copy-to-all" class="btn btn-secondary btn-sm">
                <i class="fas fa-copy"></i> Apply Monday to All
            </button>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="" id="availability-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <?php foreach ($days as $day => $props): 
                        $start_time = $existing_availability[$day]['start'] ?? '';
                        $end_time = $existing_availability[$day]['end'] ?? '';
                    ?>
                        <div class="day-card" data-day="<?php echo $day; ?>">
                            <div class="day-icon" style="background-color: <?php echo $props['color']; ?>;">
                                <i class="fas <?php echo $props['icon']; ?>"></i>
                            </div>
                            <div class="day-header">
                                <?php echo htmlspecialchars(ucfirst($day)); ?>
                            </div>
                            <div class="time-inputs">
                                <div class="time-input">
                                    <select name="availability[<?php echo $day; ?>][start]" aria-label="<?php echo $day; ?> start time" class="time-select start-time">
                                        <?php echo generate_time_options($start_time, 'start'); ?>
                                    </select>
                                </div>
                                <span>to</span>
                                <div class="time-input">
                                    <select name="availability[<?php echo $day; ?>][end]" aria-label="<?php echo $day; ?> end time" class="time-select end-time">
                                        <?php echo generate_time_options($end_time, 'end'); ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="btn-group">
                        <a href="index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Availability</button>
                    </div>
                </form>
            </div>
        </div>
        
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const copyBtn = document.getElementById('copy-to-all');
        const availabilityForm = document.getElementById('availability-form');
        const errorDiv = document.getElementById('form-error');

        // "Apply Monday to All" functionality
        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                const mondayStart = document.querySelector('select[name="availability[monday][start]"]').value;
                const mondayEnd = document.querySelector('select[name="availability[monday][end]"]').value;
                
                const allDays = ['tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                
                allDays.forEach(day => {
                    document.querySelector(`select[name="availability[${day}][start]"]`).value = mondayStart;
                    document.querySelector(`select[name="availability[${day}][end]"]`).value = mondayEnd;
                });
            });
        }

        // Form Validation
        if (availabilityForm) {
            availabilityForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                let isValid = true;
                let errorMsg = '';
                const dayCards = document.querySelectorAll('.day-card');
                
                errorDiv.style.display = 'none';
                errorDiv.textContent = '';
                dayCards.forEach(card => card.classList.remove('error'));
                
                dayCards.forEach(card => {
                    const startSelect = card.querySelector('.start-time');
                    const endSelect = card.querySelector('.end-time');
                    let startTime = startSelect.value;
                    let endTime = endSelect.value;
                    
                    if ((startTime && !endTime) || (!startTime && endTime)) {
                        isValid = false;
                        errorMsg = 'Please select both a start and end time, or set both to "Not available".';
                        card.classList.add('error');
                    }
                    
                    if (startTime && endTime) {
                        let endValue = (endTime === '00:00:00') ? '24:00:00' : endTime;
                        
                        if (startTime >= endValue) {
                            isValid = false;
                            errorMsg = 'End time must be after start time.';
                            card.classList.add('error');
                        }
                    }
                });
                
                if (isValid) {
                    availabilityForm.submit();
                } else {
                    errorDiv.textContent = errorMsg;
                    errorDiv.style.display = 'flex';
                    const firstError = document.querySelector('.day-card.error');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            });
        }
    });
</script>

</body>
</html>