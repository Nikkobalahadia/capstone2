class HybridNotificationSystem {
  constructor() {
    this.pollInterval = 5000 // Poll every 5 seconds
    this.heartbeatInterval = 30000 // Heartbeat every 30 seconds
    this.isPolling = false
    this.notifications = []

    this.init()
  }

  init() {
    this.startHeartbeat()
    this.startPolling()
    this.setupEventListeners()
    console.log("[v0] Hybrid notification system initialized")
  }

  startHeartbeat() {
    // Send heartbeat to maintain online status
    setInterval(() => {
      this.sendHeartbeat()
    }, this.heartbeatInterval)

    // Send initial heartbeat
    this.sendHeartbeat()
  }

  startPolling() {
    if (this.isPolling) return

    this.isPolling = true
    this.pollForNotifications()
  }

  stopPolling() {
    this.isPolling = false
  }

  async sendHeartbeat() {
    try {
      await fetch("/api/notifications.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ action: "heartbeat" }),
      })
      console.log("[v0] Heartbeat sent")
    } catch (error) {
      console.error("[v0] Heartbeat failed:", error)
    }
  }

  async pollForNotifications() {
    if (!this.isPolling) return

    try {
      const response = await fetch("/api/notifications.php")
      const data = await response.json()

      if (data.notifications) {
        this.processNotifications(data.notifications)
      }
    } catch (error) {
      console.error("[v0] Notification polling failed:", error)
    }

    // Schedule next poll
    setTimeout(() => {
      this.pollForNotifications()
    }, this.pollInterval)
  }

  processNotifications(newNotifications) {
    newNotifications.forEach((notification) => {
      if (!this.notifications.find((n) => n.id === notification.id)) {
        this.notifications.push(notification)
        this.showNotification(notification)
        this.markAsDelivered(notification.id)
      }
    })
  }

  showNotification(notification) {
    const notificationHtml = this.createNotificationElement(notification)
    this.displayNotification(notificationHtml)

    // Play notification sound (optional)
    this.playNotificationSound()

    console.log("[v0] Showing notification:", notification)
  }

  createNotificationElement(notification) {
    const type = notification.type === "request" ? "Match Request" : "Match Response"
    const icon = notification.type === "request" ? "🤝" : "✅"

    return `
            <div class="notification-toast" data-id="${notification.id}">
                <div class="notification-header">
                    <span class="notification-icon">${icon}</span>
                    <strong>${type}</strong>
                    <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
                </div>
                <div class="notification-body">
                    <p><strong>${notification.sender_name}</strong> ${notification.type === "request" ? "sent you a match request" : "responded to your request"}</p>
                    <p><small>Subject: ${notification.subject}</small></p>
                </div>
                <div class="notification-actions">
                    ${
                      notification.type === "request"
                        ? `
                        <button class="btn btn-sm btn-success" onclick="respondToMatch(${notification.match_id}, 'accepted')">
                            Accept
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="respondToMatch(${notification.match_id}, 'rejected')">
                            Decline
                        </button>
                    `
                        : `
                        <button class="btn btn-sm btn-primary" onclick="window.location.href='/matches/index.php'">
                            View Matches
                        </button>
                    `
                    }
                </div>
            </div>
        `
  }

  displayNotification(html) {
    let container = document.getElementById("notification-container")
    if (!container) {
      container = document.createElement("div")
      container.id = "notification-container"
      container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 400px;
            `
      document.body.appendChild(container)
    }

    const notificationDiv = document.createElement("div")
    notificationDiv.innerHTML = html
    container.appendChild(notificationDiv.firstElementChild)

    // Auto-remove after 10 seconds
    setTimeout(() => {
      const element = container.querySelector(`[data-id="${notificationDiv.firstElementChild.dataset.id}"]`)
      if (element) element.remove()
    }, 10000)
  }

  async markAsDelivered(notificationId) {
    try {
      await fetch("/api/notifications.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "mark_delivered",
          notification_id: notificationId,
        }),
      })
    } catch (error) {
      console.error("[v0] Failed to mark notification as delivered:", error)
    }
  }

  async markAsSeen(notificationId) {
    try {
      await fetch("/api/notifications.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "mark_seen",
          notification_id: notificationId,
        }),
      })
    } catch (error) {
      console.error("[v0] Failed to mark notification as seen:", error)
    }
  }

  playNotificationSound() {
    // Create a subtle notification sound
    const audioContext = new (window.AudioContext || window.webkitAudioContext)()
    const oscillator = audioContext.createOscillator()
    const gainNode = audioContext.createGain()

    oscillator.connect(gainNode)
    gainNode.connect(audioContext.destination)

    oscillator.frequency.setValueAtTime(800, audioContext.currentTime)
    oscillator.frequency.setValueAtTime(600, audioContext.currentTime + 0.1)

    gainNode.gain.setValueAtTime(0.1, audioContext.currentTime)
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2)

    oscillator.start(audioContext.currentTime)
    oscillator.stop(audioContext.currentTime + 0.2)
  }

  setupEventListeners() {
    // Handle page visibility changes
    document.addEventListener("visibilitychange", () => {
      if (document.hidden) {
        this.stopPolling()
      } else {
        this.startPolling()
      }
    })

    // Handle page unload
    window.addEventListener("beforeunload", () => {
      this.stopPolling()
    })
  }
}

// Global function for responding to matches from notifications
async function respondToMatch(matchId, response) {
  try {
    const formData = new FormData()
    formData.append("match_id", matchId)
    formData.append("response", response)
    formData.append("csrf_token", document.querySelector('meta[name="csrf-token"]')?.content || "")

    const result = await fetch("/matches/index.php", {
      method: "POST",
      body: formData,
    })

    if (result.ok) {
      // Remove notification
      const notification = document.querySelector(`[data-id*="${matchId}"]`)
      if (notification) notification.remove()

      // Show success message
      alert(`Match ${response} successfully!`)

      // Refresh matches page if we're on it
      if (window.location.pathname.includes("matches")) {
        window.location.reload()
      }
    }
  } catch (error) {
    console.error("[v0] Failed to respond to match:", error)
    alert("Failed to respond to match. Please try again.")
  }
}

// Initialize the notification system when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  window.hybridNotifications = new HybridNotificationSystem()
})

// Add CSS for notifications
const notificationStyles = `
    .notification-toast {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        margin-bottom: 10px;
        padding: 16px;
        animation: slideIn 0.3s ease-out;
    }
    
    .notification-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 8px;
    }
    
    .notification-icon {
        margin-right: 8px;
        font-size: 18px;
    }
    
    .notification-close {
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
        color: #64748b;
    }
    
    .notification-body p {
        margin: 4px 0;
    }
    
    .notification-actions {
        margin-top: 12px;
        display: flex;
        gap: 8px;
    }
    
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`

const styleSheet = document.createElement("style")
styleSheet.textContent = notificationStyles
document.head.appendChild(styleSheet)
