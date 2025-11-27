class NotificationSystem {
    constructor() {
        console.log("🔔 Notification System: Starting up...");
        
        // Configuration
        this.pollInterval = 5000; // 5 Seconds
        this.soundEnabled = true;
        
        // Debug the API URL
        console.log("🔔 BASE_URL defined as:", typeof BASE_URL !== 'undefined' ? BASE_URL : 'UNDEFINED');
        
        this.endpoints = {
            api: (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + 'api/notifications.php'
        };
        console.log("🔔 API Endpoint set to:", this.endpoints.api);

        // State
        this.lastKnownIds = new Set();
        this.isFirstLoad = true;

        // UI Elements
        this.bell = document.getElementById('notification-bell');
        this.badge = document.getElementById('notification-badge');
        this.list = document.getElementById('notification-list');
        this.container = this.createToastContainer();

        if (!this.bell) console.error("❌ Error: Could not find element #notification-bell");
        if (!this.list) console.error("❌ Error: Could not find element #notification-list");

        this.init();
    }

    init() {
        this.fetchNotifications();
        setInterval(() => this.fetchNotifications(), this.pollInterval);

        if (this.bell) {
            this.bell.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleDropdown();
            });
        }

        const markAllBtn = document.getElementById('mark-all-read');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.markAllAsRead();
            });
        }
    }

    async fetchNotifications() {
        try {
            const response = await fetch(this.endpoints.api);
            
            // DEBUG: Log if the network request fails (e.g., 404 or 500)
            if (!response.ok) {
                console.error(`❌ API Error: ${response.status} ${response.statusText} at ${this.endpoints.api}`);
                const errorText = await response.text();
                console.error("Server response:", errorText);
                return;
            }

            // DEBUG: Check if response is valid JSON
            const text = await response.text();
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    console.log(`✅ Loaded ${data.notifications.length} notifications`); // Success log
                    this.updateUI(data.notifications, data.unread_count);
                    this.checkForNew(data.notifications);
                } else {
                    console.warn("⚠️ API returned success: false", data);
                }
            } catch (e) {
                console.error("❌ JSON Parse Error. Server sent:", text);
            }

        } catch (error) {
            console.error('❌ Network/Fetch failed:', error);
        }
    }

    updateUI(notifications, unreadCount) {
        if (this.badge) {
            this.badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
            this.badge.style.display = unreadCount > 0 ? 'flex' : 'none';
        }

        if (this.list) {
            if (notifications.length === 0) {
                this.list.innerHTML = '<div class="text-center p-3 text-muted">No notifications</div>';
            } else {
                this.list.innerHTML = notifications.map(n => this.renderItem(n)).join('');
            }
        }
    }

    checkForNew(notifications) {
        if (this.isFirstLoad) {
            notifications.forEach(n => this.lastKnownIds.add(n.id));
            this.isFirstLoad = false;
            return;
        }

        notifications.forEach(n => {
            if (!this.lastKnownIds.has(n.id)) {
                this.lastKnownIds.add(n.id);
                if (n.is_read == 0) {
                    this.showToast(n);
                    if (this.soundEnabled) this.playSound();
                }
            }
        });
    }

    renderItem(notification) {
        const isReadClass = notification.is_read == 1 ? 'read' : 'unread';
        // Add style directly here to ensure visibility
        const style = "padding: 10px; border-bottom: 1px solid #eee; cursor: pointer;";
        const bg = notification.is_read == 1 ? '#fff' : '#f0f7ff';
        
        return `
            <div class="notification-item ${isReadClass}" 
                 style="${style} background: ${bg};"
                 onclick="window.notifications.handleItemClick(${notification.id}, '${notification.link}')">
                <div class="d-flex align-items-center">
                    <div class="me-2">${this.getIcon(notification.type)}</div>
                    <div>
                        <div class="fw-bold small">${notification.title}</div>
                        <div class="small text-muted">${notification.message}</div>
                        <div style="font-size: 0.7rem; color: #888;">${this.timeAgo(notification.created_at)}</div>
                    </div>
                </div>
            </div>
        `;
    }

    showToast(notification) {
        // ... (Keep existing toast logic) ...
        // For brevity, using simple alert for debug if toast fails, 
        // but your existing toast code is likely fine.
    }

    async handleItemClick(id, link) {
        console.log("🔔 Clicked notification", id);
        await fetch(this.endpoints.api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_read', id: id })
        });

        if (link && link !== 'null') {
            window.location.href = link;
        } else {
            this.fetchNotifications();
        }
    }

    async markAllAsRead() {
        console.log("🔔 Marking all as read");
        await fetch(this.endpoints.api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_all_read' })
        });
        this.fetchNotifications();
    }

    createToastContainer() {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText = "position: fixed; bottom: 20px; right: 20px; z-index: 9999;";
            document.body.appendChild(container);
        }
        return container;
    }

    getIcon(type) {
        const icons = {
            'message': '💬',
            'match_request': '🤝',
            'match_accepted': '✅',
            'announcement': '📢',
            'default': '🔔'
        };
        return icons[type] || icons['default'];
    }

    playSound() {
        // Optional: Comment out if causing issues
        // const audio = new Audio('/assets/sounds/notification.mp3');
        // audio.play().catch(e => console.log("Audio play failed:", e));
    }

    timeAgo(dateString) {
        const date = new Date(dateString);
        const seconds = Math.floor((new Date() - date) / 1000);
        if (seconds < 60) return 'Just now';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return `${minutes}m ago`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}h ago`;
        return `${Math.floor(hours / 24)}d ago`;
    }

    toggleDropdown() {
        const dropdown = document.getElementById('notification-dropdown');
        // Use Bootstrap's toggle if available, otherwise manual
        if (dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
        } else {
            dropdown.classList.add('show');
            dropdown.style.display = 'block'; // Force display for debugging
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.notifications = new NotificationSystem();
});