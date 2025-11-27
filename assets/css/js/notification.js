class NotificationSystem {
    constructor() {
        // Configuration
        this.pollInterval = 5000; // 5 Seconds
        this.soundEnabled = true;
        this.endpoints = {
            api: '/api/notifications.php'
        };

        // State
        this.lastKnownIds = new Set();
        this.isFirstLoad = true;

        // UI Elements
        this.bell = document.getElementById('notification-bell');
        this.badge = document.getElementById('notification-badge');
        this.list = document.getElementById('notification-list');
        this.container = this.createToastContainer();

        this.init();
    }

    init() {
        // Start polling
        this.fetchNotifications();
        setInterval(() => this.fetchNotifications(), this.pollInterval);

        // Event Listeners
        if (this.bell) {
            this.bell.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleDropdown();
            });
        }

        // Mark all read button
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
            const data = await response.json();

            if (data.success) {
                this.updateUI(data.notifications, data.unread_count);
                this.checkForNew(data.notifications);
            }
        } catch (error) {
            console.error('Notification poll failed:', error);
        }
    }

    updateUI(notifications, unreadCount) {
        // 1. Update Badge
        if (this.badge) {
            this.badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
            this.badge.style.display = unreadCount > 0 ? 'flex' : 'none';
        }

        // 2. Update Dropdown List (if open or on first load)
        if (this.list) {
            if (notifications.length === 0) {
                this.list.innerHTML = '<li class="empty-state">No notifications</li>';
            } else {
                this.list.innerHTML = notifications.map(n => this.renderItem(n)).join('');
            }
        }
    }

    checkForNew(notifications) {
        // On first load, just track IDs, don't spam toasts
        if (this.isFirstLoad) {
            notifications.forEach(n => this.lastKnownIds.add(n.id));
            this.isFirstLoad = false;
            return;
        }

        // Check for new IDs
        notifications.forEach(n => {
            if (!this.lastKnownIds.has(n.id)) {
                this.lastKnownIds.add(n.id);
                // Only show toast if it's unread
                if (n.is_read == 0) {
                    this.showToast(n);
                    if (this.soundEnabled) this.playSound();
                }
            }
        });
    }

    renderItem(notification) {
        const isReadClass = notification.is_read == 1 ? 'read' : 'unread';
        const icon = this.getIcon(notification.type);
        
        return `
            <div class="notification-item ${isReadClass}" onclick="window.notifications.handleItemClick(${notification.id}, '${notification.link}')">
                <div class="icon">${icon}</div>
                <div class="content">
                    <div class="title">${notification.title}</div>
                    <div class="message">${notification.message}</div>
                    <div class="time">${this.timeAgo(notification.created_at)}</div>
                </div>
            </div>
        `;
    }

    showToast(notification) {
        const toast = document.createElement('div');
        toast.className = 'notification-toast slide-in';
        toast.innerHTML = `
            <div class="toast-header">
                <strong>${notification.title}</strong>
                <button onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
            <div class="toast-body" onclick="window.location.href='${notification.link}'">
                ${notification.message}
            </div>
        `;

        this.container.appendChild(toast);

        // Auto remove after 5s
        setTimeout(() => {
            toast.classList.add('slide-out');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    async handleItemClick(id, link) {
        // Mark as read via API
        await fetch(this.endpoints.api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_read', id: id })
        });

        // Redirect
        if (link && link !== 'null') {
            window.location.href = link;
        } else {
            this.fetchNotifications(); // Just refresh if no link
        }
    }

    async markAllAsRead() {
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
        const audio = new Audio('/assets/sounds/notification.mp3'); // Make sure this file exists
        audio.play().catch(e => console.log("Audio play failed (browser policy):", e));
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
        if(dropdown) dropdown.classList.toggle('show');
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    window.notifications = new NotificationSystem();
});