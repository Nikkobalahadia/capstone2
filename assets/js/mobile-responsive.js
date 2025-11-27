// ============================================
// Mobile Responsive System for StudyConnect
// ============================================

class MobileResponsiveManager {
  constructor() {
    this.hamburger = document.querySelector(".hamburger");
    this.navLinks = document.querySelector(".nav-links");
    this.sidebar = document.querySelector(".sidebar");
    this.sidebarToggle = document.querySelector("#sidebarToggle");
    this.mainContent = document.querySelector(".main-content");
    this.header = document.querySelector(".header");
    this.screenWidth = window.innerWidth;
    
    this.init();
  }

  init() {
    this.setupMobileMenu();
    this.setupSidebarToggle();
    this.setupResponsiveListeners();
    this.setupTouchHandlers();
    this.preventZoomOnInputs();
  }

  // Mobile Navigation Menu Toggle
  setupMobileMenu() {
    if (this.hamburger && this.navLinks) {
      this.hamburger.addEventListener("click", (e) => {
        e.stopPropagation();
        this.hamburger.classList.toggle("active");
        this.navLinks.classList.toggle("active");
      });

      // Close menu when clicking on links
      const links = this.navLinks.querySelectorAll("a");
      links.forEach((link) => {
        link.addEventListener("click", () => {
          this.closeMenu();
        });
      });

      // Close menu when clicking outside
      document.addEventListener("click", (event) => {
        if (
          this.hamburger &&
          this.navLinks &&
          !this.hamburger.contains(event.target) &&
          !this.navLinks.contains(event.target)
        ) {
          this.closeMenu();
        }
      });
    }
  }

  // Admin Sidebar Toggle
  setupSidebarToggle() {
    if (this.sidebarToggle && this.sidebar) {
      this.sidebarToggle.addEventListener("click", (e) => {
        e.stopPropagation();
        this.sidebar.classList.toggle("active");
        this.adjustMainContent();
      });

      // Close sidebar when clicking outside on mobile
      if (window.innerWidth <= 768) {
        document.addEventListener("click", (event) => {
          if (
            this.sidebar &&
            !this.sidebar.contains(event.target) &&
            !this.sidebarToggle.contains(event.target)
          ) {
            this.sidebar.classList.remove("active");
          }
        });
      }
    }
  }

  // Handle responsive layout changes
  setupResponsiveListeners() {
    let resizeTimer;
    window.addEventListener("resize", () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => {
        this.screenWidth = window.innerWidth;
        this.handleResponsiveLayout();
      }, 250);
    });

    // Initial check
    this.handleResponsiveLayout();
  }

  handleResponsiveLayout() {
    // Close menus on larger screens
    if (this.screenWidth > 768) {
      this.closeMenu();
      if (this.sidebar) {
        this.sidebar.classList.remove("active");
      }
    }

    // Adjust header height for landscape mode
    if (window.innerHeight < 600 && window.orientation === 90) {
      document.body.classList.add("landscape-mode");
    } else {
      document.body.classList.remove("landscape-mode");
    }
  }

  // Touch and swipe handlers for mobile
  setupTouchHandlers() {
    let touchStartX = 0;
    let touchEndX = 0;

    document.addEventListener("touchstart", (e) => {
      touchStartX = e.changedTouches[0].screenX;
    });

    document.addEventListener("touchend", (e) => {
      touchEndX = e.changedTouches[0].screenX;
      this.handleSwipe(touchStartX, touchEndX);
    });
  }

  handleSwipe(startX, endX) {
    const swipeThreshold = 50;
    const diff = startX - endX;

    // Swipe left to close sidebar (if open)
    if (diff > swipeThreshold && this.sidebar) {
      if (this.sidebar.classList.contains("active")) {
        this.sidebar.classList.remove("active");
      }
    }

    // Swipe right to open sidebar (if closed)
    if (diff < -swipeThreshold && this.sidebar) {
      if (!this.sidebar.classList.contains("active") && window.innerWidth <= 768) {
        this.sidebar.classList.add("active");
      }
    }
  }

  // Prevent iOS zoom on form inputs
  preventZoomOnInputs() {
    const inputs = document.querySelectorAll("input, select, textarea");
    inputs.forEach((input) => {
      input.addEventListener("focus", () => {
        input.style.fontSize = "16px";
      });
    });
  }

  // Utility Methods
  closeMenu() {
    if (this.hamburger && this.navLinks) {
      this.hamburger.classList.remove("active");
      this.navLinks.classList.remove("active");
    }
  }

  adjustMainContent() {
    if (window.innerWidth > 768 && this.mainContent) {
      this.mainContent.style.marginLeft = this.sidebar?.classList.contains("active")
        ? "250px"
        : "0";
    }
  }

  // Method to dynamically add responsive images
  makeImagesResponsive() {
    const images = document.querySelectorAll("img");
    images.forEach((img) => {
      if (!img.classList.contains("responsive-img")) {
        img.classList.add("responsive-img");
        img.style.maxWidth = "100%";
        img.style.height = "auto";
      }
    });
  }

  // Method to handle modal responsiveness
  setupModals() {
    const modals = document.querySelectorAll(".modal");
    modals.forEach((modal) => {
      if (window.innerWidth < 768) {
        modal.classList.add("mobile-modal");
      }
    });
  }

  // Debounce utility for performance
  static debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }
}

// Initialize on DOM ready
document.addEventListener("DOMContentLoaded", () => {
  window.mobileManager = new MobileResponsiveManager();
});

// Handle orientation changes
window.addEventListener("orientationchange", () => {
  setTimeout(() => {
    if (window.mobileManager) {
      window.mobileManager.handleResponsiveLayout();
    }
  }, 100);
});