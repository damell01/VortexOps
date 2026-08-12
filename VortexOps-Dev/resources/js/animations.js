/**
 * Animation and Transition Effects for VortexOps
 * Handles smooth page transitions, modal animations, and mobile swipe gestures
 */

// Initialize animations on page load
document.addEventListener('DOMContentLoaded', () => {
    initializePageTransitions();
    initializeSwipeGestures();
    initializeScrollAnimations();
    initializeButtonAnimations();
});

/**
 * Page transition animations
 */
function initializePageTransitions() {
    // Add fade-in animation to main content
    const mainContent = document.querySelector('[role="main"]');
    if (mainContent && !mainContent.classList.contains('animate-fade-in')) {
        mainContent.classList.add('animate-fade-in');
    }

    // Listen for Livewire page updates
    if (window.Livewire) {
        window.Livewire.on('navigationComplete', () => {
            fadeInNewContent();
        });
    }
}

/**
 * Fade in new content when page updates
 */
function fadeInNewContent() {
    const elements = document.querySelectorAll('[x-cloak]');
    elements.forEach(el => {
        el.style.opacity = '0';
        el.classList.add('animate-fade-in');
        setTimeout(() => {
            el.style.opacity = '1';
        }, 10);
    });
}

/**
 * Swipe gesture detection for mobile
 */
function initializeSwipeGestures() {
    if (!window.matchMedia('(max-width: 768px)').matches) {
        return; // Skip on desktop
    }

    let touchStartX = 0;
    let touchEndX = 0;

    document.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
    }, false);

    document.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, false);

    function handleSwipe() {
        const diff = touchStartX - touchEndX;
        const threshold = 50;

        // Swipe left - navigate to next
        if (diff > threshold) {
            handleSwipeLeft();
        }

        // Swipe right - navigate back
        if (diff < -threshold) {
            handleSwipeRight();
        }
    }

    function handleSwipeLeft() {
        // Trigger action on swipe left
        const action = document.querySelector('[data-swipe-action="left"]');
        if (action) {
            action.click();
            animateSwipeOut('left');
        }
    }

    function handleSwipeRight() {
        // Go back on swipe right
        if (window.history.length > 1) {
            animateSwipeOut('right');
            window.history.back();
        }
    }

    function animateSwipeOut(direction) {
        const content = document.querySelector('[role="main"]');
        if (!content) return;

        const animation = direction === 'left' ? 'slide-out-to-left' : 'slide-out-to-right';
        content.classList.add(`animate-${animation}`);
    }
}

/**
 * Scroll-triggered animations
 */
function initializeScrollAnimations() {
    if (!window.IntersectionObserver) return;

    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fade-in-up');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe elements with scroll-animate class
    document.querySelectorAll('.scroll-animate').forEach(el => {
        observer.observe(el);
    });
}

/**
 * Button press animations
 */
function initializeButtonAnimations() {
    document.querySelectorAll('button, a[role="button"]').forEach(button => {
        button.addEventListener('mousedown', () => {
            button.classList.add('animate-button-press');
        });

        button.addEventListener('mouseup', () => {
            button.classList.remove('animate-button-press');
        });

        button.addEventListener('mouseleave', () => {
            button.classList.remove('animate-button-press');
        });

        // Touch feedback for mobile
        button.addEventListener('touchstart', () => {
            button.style.opacity = '0.7';
            button.style.transform = 'scale(0.95)';
        });

        button.addEventListener('touchend', () => {
            button.style.opacity = '1';
            button.style.transform = 'scale(1)';
        });
    });
}

/**
 * Utility function to add animation to element
 */
window.animateElement = function(element, animationName, duration = 300) {
    element.style.animation = `${animationName} ${duration}ms ease-out forwards`;
    return new Promise(resolve => {
        setTimeout(resolve, duration);
    });
};

/**
 * Utility function to fade out and remove element
 */
window.fadeOutElement = function(element, duration = 300) {
    return new Promise(resolve => {
        element.style.transition = `opacity ${duration}ms ease-out`;
        element.style.opacity = '0';
        setTimeout(() => {
            element.remove();
            resolve();
        }, duration);
    });
};

/**
 * Utility function to fade in element
 */
window.fadeInElement = function(element, duration = 300) {
    element.style.opacity = '0';
    element.style.transition = `opacity ${duration}ms ease-out`;
    // Trigger reflow
    void element.offsetWidth;
    element.style.opacity = '1';
    return new Promise(resolve => {
        setTimeout(resolve, duration);
    });
};

/**
 * Modal animation helper
 */
window.animateModal = function(modal, show = true) {
    if (show) {
        modal.style.display = 'block';
        modal.classList.add('animate-fade-in');
        modal.querySelector('[role="dialog"]')?.classList.add('animate-scale-in');
    } else {
        modal.classList.add('animate-fade-out');
        modal.querySelector('[role="dialog"]')?.classList.add('animate-shrink-out');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }
};

/**
 * Prevent layout thrashing with animation awareness
 */
window.requestAnimationFrame = window.requestAnimationFrame || function(callback) {
    return setTimeout(callback, 1000 / 60);
};

// Export for use in other modules
export {
    initializePageTransitions,
    initializeSwipeGestures,
    initializeScrollAnimations,
    initializeButtonAnimations
};
