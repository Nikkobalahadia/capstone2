<?php
if (!isset($db)) {
    require_once __DIR__ . '/../config/config.php';
    $db = getDB();
}
$footer_settings = [];
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM footer_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $footer_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Fallback to default values if database query fails
    $footer_settings = [
        'platform_name' => 'StudyBuddy',
        'platform_tagline' => 'Empowering students worldwide through peer-to-peer learning and meaningful academic connections.',
        'copyright_text' => 'StudyBuddy. All rights reserved.',
        'show_social_links' => '1',
        'facebook_url' => '',
        'twitter_url' => '',
        'instagram_url' => '',
        'linkedin_url' => ''
    ];
}

$platform_name = $footer_settings['platform_name'] ?? 'StudyConnect';
$platform_tagline = $footer_settings['platform_tagline'] ?? 'Empowering students worldwide through peer-to-peer learning and meaningful academic connections.';
$copyright_text = $footer_settings['copyright_text'] ?? 'StudyConnect. All rights reserved.';
$show_social = ($footer_settings['show_social_links'] ?? '1') == '1';
?>

<footer style="background: var(--background-dark); color: var(--text-white); padding: 3rem 0; margin-top: 4rem; border-top: 1px solid #334155;">
    <div class="container">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 3rem; margin-bottom: 2rem;">
            <div>
                <h4 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 1.25rem;">🔒</span> Legal & Policy Stuff
                </h4>
                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.75rem;">
                    <li><a href="<?php echo isset($base_url) ? $base_url : 'http://localhost/study-mentorship-platform'; ?>/legal/terms.php" style="color: var(--text-light); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color='var(--text-light)'">Terms and Conditions</a></li>
                    <li><a href="<?php echo isset($base_url) ? $base_url : 'http://localhost/study-mentorship-platform'; ?>/legal/privacy.php" style="color: var(--text-light); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color='var(--text-light)'">Privacy Policy</a></li>
                    <li><a href="<?php echo isset($base_url) ? $base_url : 'http://localhost/study-mentorship-platform'; ?>/legal/user-agreement.php" style="color: var(--text-light); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color='var(--text-light)'">User Agreement</a></li>
                    <li><a href="<?php echo isset($base_url) ? $base_url : 'http://localhost/study-mentorship-platform'; ?>/legal/disclaimer.php" style="color: var(--text-light); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color='var(--text-light)'">Disclaimer</a></li>
                </ul>
            </div>

            <div>
                <h4 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 1.25rem;">👥</span> User Support
                </h4>
                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.75rem;">
                    <li><a href="<?php echo isset($base_url) ? $base_url : 'http://localhost/study-mentorship-platform'; ?>/support/faq.php" style="color: var(--text-light); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color='var(--text-light)'">Help / FAQs</a></li>
                    <li><a href="<?php echo isset($base_url) ? $base_url : 'http://localhost/study-mentorship-platform'; ?>/support/contact.php" style="color: var(--text-light); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color='var(--text-light)'">Contact Us</a></li>
                    <li><a href="<?php echo isset($base_url) ? $base_url : 'http://localhost/study-mentorship-platform'; ?>/support/report.php" style="color: var(--text-light); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color='var(--text-light)'">Report a Problem or Feedback</a></li>
                </ul>
            </div>

            <div>
                <h4 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 1.25rem;">🌐</span> About the Platform
                </h4>
                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.75rem;">
                    <li><a href="<?php echo isset($base_url) ? $base_url : 'http://localhost/study-mentorship-platform'; ?>/about/index.php" style="color: var(--text-light); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color='var(--text-light)'">About Us</a></li>
                    <li><a href="<?php echo isset($base_url) ? $base_url : 'http://localhost/study-mentorship-platform'; ?>/about/team.php" style="color: var(--text-light); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color='var(--text-light)'">Team / Developers</a></li>
                    <li><a href="<?php echo isset($base_url) ? $base_url : 'http://localhost/study-mentorship-platform'; ?>/about/blog.php" style="color: var(--text-light); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color='var(--text-light)'">Blog / Updates</a></li>
                </ul>
            </div>
        </div>

        <?php if ($show_social && (!empty($footer_settings['facebook_url']) || !empty($footer_settings['twitter_url']) || !empty($footer_settings['instagram_url']) || !empty($footer_settings['linkedin_url']))): ?>
        <div style="border-top: 1px solid #334155; padding-top: 2rem; margin-bottom: 2rem; text-align: center;">
            <h4 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem;">Connect With Us</h4>
            <div style="display: flex; justify-content: center; gap: 1.5rem; flex-wrap: wrap;">
                <?php if (!empty($footer_settings['facebook_url'])): ?>
                <a href="<?php echo htmlspecialchars($footer_settings['facebook_url']); ?>" target="_blank" rel="noopener noreferrer" 
                   style="color: var(--text-light); font-size: 1.5rem; transition: color 0.2s;" 
                   onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color='var(--text-light)'"
                   aria-label="Facebook">
                    <i class="fab fa-facebook"></i>
                </a>
                <?php endif; ?>
                <?php if (!empty($footer_settings['twitter_url'])): ?>
                <a href="<?php echo htmlspecialchars($footer_settings['twitter_url']); ?>" target="_blank" rel="noopener noreferrer" 
                   style="color: var(--text-light); font-size: 1.5rem; transition: color 0.2s;" 
                   onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color='var(--text-light)'"
                   aria-label="Twitter">
                    <i class="fab fa-twitter"></i>
                </a>
                <?php endif; ?>
                <?php if (!empty($footer_settings['instagram_url'])): ?>
                <a href="<?php echo htmlspecialchars($footer_settings['instagram_url']); ?>" target="_blank" rel="noopener noreferrer" 
                   style="color: var(--text-light); font-size: 1.5rem; transition: color 0.2s;" 
                   onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color='var(--text-light)'"
                   aria-label="Instagram">
                    <i class="fab fa-instagram"></i>
                </a>
                <?php endif; ?>
                <?php if (!empty($footer_settings['linkedin_url'])): ?>
                <a href="<?php echo htmlspecialchars($footer_settings['linkedin_url']); ?>" target="_blank" rel="noopener noreferrer" 
                   style="color: var(--text-light); font-size: 1.5rem; transition: color 0.2s;" 
                   onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color='var(--text-light)'"
                   aria-label="LinkedIn">
                    <i class="fab fa-linkedin"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div style="border-top: 1px solid #334155; padding-top: 2rem; text-align: center;">
            <div style="margin-bottom: 1rem;">
                <h3 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($platform_name); ?></h3>
                <p style="color: var(--text-light); max-width: 500px; margin: 0 auto;"><?php echo htmlspecialchars($platform_tagline); ?></p>
            </div>
            <p style="color: var(--text-light); font-size: 0.875rem;">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($copyright_text); ?></p>
        </div>
    </div>
</footer>
