// Mobile Menu Toggle
document.addEventListener("DOMContentLoaded", () => {
  const hamburger = document.querySelector(".hamburger")
  const navLinks = document.querySelector(".nav-links")

  if (hamburger) {
    hamburger.addEventListener("click", () => {
      hamburger.classList.toggle("active")
      navLinks.classList.toggle("active")
    })

    // Close menu when clicking on a link
    const links = navLinks.querySelectorAll("a")
    links.forEach((link) => {
      link.addEventListener("click", () => {
        hamburger.classList.remove("active")
        navLinks.classList.remove("active")
      })
    })
  }

  // Close menu when clicking outside
  document.addEventListener("click", (event) => {
    if (hamburger && navLinks && !hamburger.contains(event.target) && !navLinks.contains(event.target)) {
      hamburger.classList.remove("active")
      navLinks.classList.remove("active")
    }
  })

  // Admin sidebar toggle
  const sidebarToggle = document.querySelector(".sidebar-toggle")
  const sidebar = document.querySelector(".sidebar")

  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener("click", () => {
      sidebar.classList.toggle("active")
    })
  }
})
