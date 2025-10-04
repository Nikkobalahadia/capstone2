class NotificationBell {
  constructor() {
    this.bellBtn = document.getElementById("notificationBellBtn")
    this.dropdown = document.getElementById("notificationDropdown")
    this.badge = document.getElementById("notificationBadge")
    this.notificationList = document.getElementById("notificationList")
    this.markAllReadBtn = document.getElementById("markAllReadBtn")

    this.notifications = []
    this.unreadCount = 0
    this.pollInterval = 10000 // Poll every 10 seconds
    this.isOpen = false

    this.init()
  }

  init() {
    // Load CSS
    this.loadCSS()

    // Setup event listeners
    this.setupEventListeners()

    // Initial fetch
    this.fetchNotifications()

    // Start polling
    this.startPolling()

    console.log("[v0] Notification bell initialized")
  }

  loadCSS() {
    const link = document.createElement("link")
    link.rel = "stylesheet"
    link.href = "assets/css/notification-bell.css"
    document.head.appendChild(link)
  }

  setupEventListeners() {
    // Toggle dropdown
    this.bellBtn.addEventListener("click", (e) => {
      e.stopPropagation()
      this.toggleDropdown()
    })

    // Close dropdown when clicking outside
    document.addEventListener("click", (e) => {
      if (!this.dropdown.contains(e.target) && !this.bellBtn.contains(e.target)) {
        this.closeDropdown()
      }
    })

    // Mark all as read
    this.markAllReadBtn.addEventListener("click", () => {
      this.markAllAsRead()
    })

    // Handle page visibility changes
    document.addEventListener("visibilitychange", () => {
      if (!document.hidden) {
        this.fetchNotifications()
      }
    })
  }

  toggleDropdown() {
    this.isOpen = !this.isOpen
    if (this.isOpen) {
      this.dropdown.classList.add("show")
      this.fetchNotifications()
    } else {
      this.dropdown.classList.remove("show")
    }
  }

  closeDropdown() {
    this.isOpen = false
    this.dropdown.classList.remove("show")
  }

  startPolling() {
    setInterval(() => {
      if (!document.hidden) {
        this.fetchNotifications()
      }
    }, this.pollInterval)
  }

  async fetchNotifications() {
    try {
      const response = await fetch("/api/get-notifications.php")
      const data = await response.json()

      if (data.success) {
        this.notifications = data.notifications
        this.unreadCount = data.unread_count
        this.updateBadge()
        this.renderNotifications()
      }
    } catch (error) {
      console.error("[v0] Failed to fetch notifications:", error)
    }
  }

  updateBadge() {
    if (this.unreadCount > 0) {
      this.badge.textContent = this.unreadCount > 99 ? "99+" : this.unreadCount
      this.badge.style.display = "block"
    } else {
      this.badge.style.display = "none"
    }
  }

  renderNotifications() {
    if (this.notifications.length === 0) {
      this.notificationList.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications yet</p>
                </div>
            `
      return
    }

    this.notificationList.innerHTML = this.notifications
      .map((notification) => this.createNotificationHTML(notification))
      .join("")

    // Add click handlers for notification items
    this.notificationList.querySelectorAll(".notification-item").forEach((item) => {
      item.addEventListener("click", (e) => {
        if (!e.target.classList.contains("notification-action-btn")) {
          const notificationId = item.dataset.id
          this.markAsRead(notificationId)
        }
      })
    })

    // Add click handlers for action buttons
    this.notificationList.querySelectorAll(".notification-action-btn").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.stopPropagation()
        const action = btn.dataset.action
        const notificationId = btn.closest(".notification-item").dataset.id
        this.handleAction(action, notificationId)
      })
    })
  }

  createNotificationHTML(notification) {
    const isUnread = !notification.is_read
    const iconClass = this.getIconClass(notification.type)
    const icon = this.getIcon(notification.type)
    const timeAgo = this.getTimeAgo(notification.created_at)

    let actionsHTML = ""
    if (notification.type === "match_request" && isUnread) {
      const data = JSON.parse(notification.data || "{}")
      actionsHTML = `
                <div class="notification-actions">
                    <button class="notification-action-btn accept" data-action="accept" data-match-id="${data.match_id}">
                        Accept
                    </button>
                    <button class="notification-action-btn decline" data-action="decline" data-match-id="${data.match_id}">
                        Decline
                    </button>
                </div>
            `
    } else if (notification.type === "match_accepted") {
      actionsHTML = `
                <div class="notification-actions">
                    <button class="notification-action-btn view" data-action="view-matches">
                        View Matches
                    </button>
                </div>
            `
    }

    return `
            <div class="notification-item ${isUnread ? "unread" : ""}" data-id="${notification.id}">
                <div class="notification-icon ${iconClass}">
                    <i class="${icon}"></i>
                </div>
                <div class="notification-content">
                    <p class="notification-title">${this.escapeHtml(notification.title)}</p>
                    <p class="notification-message">${this.escapeHtml(notification.message)}</p>
                    <span class="notification-time">${timeAgo}</span>
                    ${actionsHTML}
                </div>
            </div>
        `
  }

  getIconClass(type) {
    const iconMap = {
      match_request: "match-request",
      match_accepted: "match-accepted",
      match_rejected: "match-rejected",
      session_reminder: "session-reminder",
      message: "message",
    }
    return iconMap[type] || "match-request"
  }

  getIcon(type) {
    const iconMap = {
      match_request: "fas fa-handshake",
      match_accepted: "fas fa-check-circle",
      match_rejected: "fas fa-times-circle",
      session_reminder: "fas fa-calendar-check",
      message: "fas fa-envelope",
    }
    return iconMap[type] || "fas fa-bell"
  }

  getTimeAgo(timestamp) {
    const now = new Date()
    const time = new Date(timestamp)
    const diffInSeconds = Math.floor((now - time) / 1000)

    if (diffInSeconds < 60) return "Just now"
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`
    if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`
    return time.toLocaleDateString()
  }

  async markAsRead(notificationId) {
    try {
      const response = await fetch("/api/mark-notification-read.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ notification_id: notificationId }),
      })

      const data = await response.json()
      if (data.success) {
        // Update local state
        const notification = this.notifications.find((n) => n.id == notificationId)
        if (notification && !notification.is_read) {
          notification.is_read = true
          this.unreadCount = Math.max(0, this.unreadCount - 1)
          this.updateBadge()
          this.renderNotifications()
        }
      }
    } catch (error) {
      console.error("[v0] Failed to mark notification as read:", error)
    }
  }

  async markAllAsRead() {
    try {
      const response = await fetch("/api/mark-all-notifications-read.php", {
        method: "POST",
      })

      const data = await response.json()
      if (data.success) {
        // Update local state
        this.notifications.forEach((n) => (n.is_read = true))
        this.unreadCount = 0
        this.updateBadge()
        this.renderNotifications()
      }
    } catch (error) {
      console.error("[v0] Failed to mark all notifications as read:", error)
    }
  }

  async handleAction(action, notificationId) {
    const notification = this.notifications.find((n) => n.id == notificationId)
    if (!notification) return

    const data = JSON.parse(notification.data || "{}")

    switch (action) {
      case "accept":
        await this.respondToMatch(data.match_id, "accepted")
        break
      case "decline":
        await this.respondToMatch(data.match_id, "rejected")
        break
      case "view-matches":
        window.location.href = "/matches/index.php"
        break
    }
  }

  async respondToMatch(matchId, response) {
    try {
      const formData = new FormData()
      formData.append("match_id", matchId)
      formData.append("response", response)

      const result = await fetch("/matches/index.php", {
        method: "POST",
        body: formData,
      })

      if (result.ok) {
        // Refresh notifications
        await this.fetchNotifications()
        alert(`Match ${response} successfully!`)
      }
    } catch (error) {
      console.error("[v0] Failed to respond to match:", error)
      alert("Failed to respond to match. Please try again.")
    }
  }

  escapeHtml(text) {
    const div = document.createElement("div")
    div.textContent = text
    return div.innerHTML
  }
}

// Initialize notification bell when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  window.notificationBell = new NotificationBell()
})
