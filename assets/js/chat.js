// Real-time chat functionality
class ChatManager {
  constructor(matchId, userId) {
    this.matchId = matchId
    this.userId = userId
    this.lastMessageTime = null
    this.pollInterval = null
    this.isActive = true

    this.init()
  }

  init() {
    this.setupEventListeners()
    this.startPolling()
    this.loadMessages()
  }

  setupEventListeners() {
    // Handle form submission
    const form = document.getElementById("messageForm")
    if (form) {
      form.addEventListener("submit", (e) => {
        e.preventDefault()
        this.sendMessage()
      })
    }

    // Handle Enter key
    const input = document.getElementById("messageInput")
    if (input) {
      input.addEventListener("keypress", (e) => {
        if (e.key === "Enter" && !e.shiftKey) {
          e.preventDefault()
          this.sendMessage()
        }
      })
    }

    // Handle page visibility changes
    document.addEventListener("visibilitychange", () => {
      this.isActive = !document.hidden
      if (this.isActive) {
        this.loadNewMessages()
      }
    })
  }

  async loadMessages() {
    try {
      const response = await fetch(`../api/messages.php?match_id=${this.matchId}`)
      const data = await response.json()

      if (data.messages) {
        this.renderMessages(data.messages)
        this.updateLastMessageTime(data.messages)
        this.scrollToBottom()
      }
    } catch (error) {
      console.error("Failed to load messages:", error)
    }
  }

  async loadNewMessages() {
    if (!this.lastMessageTime) return

    try {
      const response = await fetch(
        `../api/messages.php?match_id=${this.matchId}&since=${encodeURIComponent(this.lastMessageTime)}`,
      )
      const data = await response.json()

      if (data.messages && data.messages.length > 0) {
        this.appendMessages(data.messages)
        this.updateLastMessageTime(data.messages)
        this.scrollToBottom()
      }
    } catch (error) {
      console.error("Failed to load new messages:", error)
    }
  }

  async sendMessage() {
    const input = document.getElementById("messageInput")
    const message = input.value.trim()

    if (!message) return

    // Disable input while sending
    input.disabled = true

    try {
      const response = await fetch("../api/messages.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          match_id: this.matchId,
          message: message,
        }),
      })

      const data = await response.json()

      if (data.success && data.message) {
        // Clear input
        input.value = ""

        // Add message to chat
        this.appendMessages([data.message])
        this.updateLastMessageTime([data.message])
        this.scrollToBottom()
      } else {
        alert("Failed to send message. Please try again.")
      }
    } catch (error) {
      console.error("Failed to send message:", error)
      alert("Failed to send message. Please try again.")
    } finally {
      input.disabled = false
      input.focus()
    }
  }

  renderMessages(messages) {
    const container = document.getElementById("chatMessages")
    if (!container) return

    container.innerHTML = ""

    if (messages.length === 0) {
      container.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                    <p>No messages yet. Start the conversation!</p>
                </div>
            `
      return
    }

    messages.forEach((message) => {
      container.appendChild(this.createMessageElement(message))
    })
  }

  appendMessages(messages) {
    const container = document.getElementById("chatMessages")
    if (!container) return

    // Remove "no messages" placeholder if it exists
    const placeholder = container.querySelector('div[style*="text-align: center"]')
    if (placeholder) {
      placeholder.remove()
    }

    messages.forEach((message) => {
      container.appendChild(this.createMessageElement(message))
    })
  }

  createMessageElement(message) {
    const isOwn = message.sender_id == this.userId
    const messageDiv = document.createElement("div")
    messageDiv.className = `message ${isOwn ? "own" : ""}`

    const initials = (message.first_name.charAt(0) + message.last_name.charAt(0)).toUpperCase()
    const timeAgo = this.formatTimeAgo(message.created_at)

    messageDiv.innerHTML = `
            <div class="message-avatar" style="background: ${isOwn ? "var(--primary-color)" : "var(--secondary-color)"};">
                ${initials}
            </div>
            <div class="message-content">
                <div>${this.escapeHtml(message.message).replace(/\n/g, "<br>")}</div>
                <div class="message-time">${timeAgo}</div>
            </div>
        `

    return messageDiv
  }

  updateLastMessageTime(messages) {
    if (messages.length > 0) {
      this.lastMessageTime = messages[messages.length - 1].created_at
    }
  }

  scrollToBottom() {
    const container = document.getElementById("chatMessages")
    if (container) {
      container.scrollTop = container.scrollHeight
    }
  }

  startPolling() {
    // Poll for new messages every 5 seconds
    this.pollInterval = setInterval(() => {
      if (this.isActive) {
        this.loadNewMessages()
      }
    }, 5000)
  }

  stopPolling() {
    if (this.pollInterval) {
      clearInterval(this.pollInterval)
      this.pollInterval = null
    }
  }

  formatTimeAgo(timestamp) {
    const now = new Date()
    const messageTime = new Date(timestamp)
    const diffInSeconds = Math.floor((now - messageTime) / 1000)

    if (diffInSeconds < 60) {
      return "Just now"
    } else if (diffInSeconds < 3600) {
      const minutes = Math.floor(diffInSeconds / 60)
      return `${minutes} minute${minutes > 1 ? "s" : ""} ago`
    } else if (diffInSeconds < 86400) {
      const hours = Math.floor(diffInSeconds / 3600)
      return `${hours} hour${hours > 1 ? "s" : ""} ago`
    } else {
      return messageTime.toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
      })
    }
  }

  escapeHtml(text) {
    const div = document.createElement("div")
    div.textContent = text
    return div.innerHTML
  }

  destroy() {
    this.stopPolling()
  }
}

// Initialize chat when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  const matchId = window.chatMatchId
  const userId = window.chatUserId

  if (matchId && userId) {
    window.chatManager = new ChatManager(matchId, userId)
  }
})

// Cleanup when page is unloaded
window.addEventListener("beforeunload", () => {
  if (window.chatManager) {
    window.chatManager.destroy()
  }
})
