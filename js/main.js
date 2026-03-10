/**
 * PopWork Main JavaScript
 * Handles navigation, mobile menu, and common UI interactions
 */

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    
    // ========================================
    // Mobile Menu Toggle
    // ========================================
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mainNav = document.getElementById('mainNav');
    
    if (mobileMenuToggle && mainNav) {
        mobileMenuToggle.addEventListener('click', function() {
            this.classList.toggle('active');
            mainNav.classList.toggle('active');
            document.body.classList.toggle('menu-open');
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.main-header')) {
                mobileMenuToggle.classList.remove('active');
                mainNav.classList.remove('active');
                document.body.classList.remove('menu-open');
            }
        });

        // Close mobile menu when clicking on a nav link
        const navLinks = mainNav.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                mobileMenuToggle.classList.remove('active');
                mainNav.classList.remove('active');
                document.body.classList.remove('menu-open');
            });
        });
    }

    // ========================================
    // Back to Top Button
    // ========================================
    const backToTopBtn = document.getElementById('backToTop');
    
    if (backToTopBtn) {
        // Show/hide button based on scroll position
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopBtn.classList.add('visible');
            } else {
                backToTopBtn.classList.remove('visible');
            }
        });

        // Scroll to top when clicked
        backToTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    // ========================================
    // Smooth Scroll for Anchor Links
    // ========================================
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== '#' && href !== '#!') {
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });

    // ========================================
    // Active Navigation Link Highlighting
    // ========================================
    const currentPath = window.location.pathname;
    const navLinksAll = document.querySelectorAll('.nav-link');
    
    navLinksAll.forEach(link => {
        const linkPath = new URL(link.href).pathname;
        if (currentPath === linkPath) {
            link.classList.add('active');
        }
    });

    // ========================================
    // Sticky Header on Scroll
    // ========================================
    const header = document.querySelector('.main-header');
    let lastScrollTop = 0;
    
    if (header) {
        window.addEventListener('scroll', function() {
            let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > 100) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }

            // Hide header on scroll down, show on scroll up
            if (scrollTop > lastScrollTop && scrollTop > 500) {
                header.classList.add('hidden');
            } else {
                header.classList.remove('hidden');
            }
            
            lastScrollTop = scrollTop;
        });
    }

    // ========================================
    // Form Validation Helper
    // ========================================
    window.validateForm = function(formId) {
        const form = document.getElementById(formId);
        if (!form) return true;

        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        let isValid = true;

        inputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('error');
                isValid = false;
            } else {
                input.classList.remove('error');
            }

            // Add event listener to remove error on input
            input.addEventListener('input', function() {
                this.classList.remove('error');
            });
        });

        return isValid;
    };

    // ========================================
    // Alert Auto-Close
    // ========================================
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        // Auto-close after 5 seconds
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }, 5000);

        // Manual close button
        const closeBtn = alert.querySelector('.alert-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            });
        }
    });

    // ========================================
    // Lazy Loading Images
    // ========================================
    const lazyImages = document.querySelectorAll('img[data-src]');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.add('loaded');
                    observer.unobserve(img);
                }
            });
        });

        lazyImages.forEach(img => imageObserver.observe(img));
    } else {
        // Fallback for browsers without IntersectionObserver
        lazyImages.forEach(img => {
            img.src = img.dataset.src;
            img.classList.add('loaded');
        });
    }

    // ========================================
    // Confirm Before Delete
    // ========================================
    window.confirmDelete = function(message) {
        return confirm(message || 'Are you sure you want to delete this? This action cannot be undone.');
    };

    // ========================================
    // Loading State for Buttons
    // ========================================
    window.setButtonLoading = function(buttonId, isLoading) {
        const button = document.getElementById(buttonId);
        if (!button) return;

        if (isLoading) {
            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.innerHTML = '<span class="spinner"></span> Loading...';
            button.classList.add('loading');
        } else {
            button.disabled = false;
            button.textContent = button.dataset.originalText || 'Submit';
            button.classList.remove('loading');
        }
    };

    // ========================================
    // Toast Notification System
    // ========================================
    window.showToast = function(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 3000);
    };

    // ========================================
    // Console Warning for Production
    // ========================================
    if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
        console.log('%cStop!', 'color: red; font-size: 40px; font-weight: bold;');
        console.log('%cThis is a browser feature intended for developers. If someone told you to copy-paste something here, it is a scam and will give them access to your account.', 'font-size: 16px;');
    }

    // ========================================
    // Accessibility: Skip to Main Content
    // ========================================
    const skipLink = document.querySelector('.skip-to-main');
    if (skipLink) {
        skipLink.addEventListener('click', function(e) {
            e.preventDefault();
            const mainContent = document.querySelector('main');
            if (mainContent) {
                mainContent.focus();
                mainContent.scrollIntoView({ behavior: 'smooth' });
            }
        });
    }

    console.log('🚀 PopWork initialized successfully!');
});