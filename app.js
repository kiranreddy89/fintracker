// Fintacker Profile Page JavaScript
class FintrackerProfile {
    constructor() {
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.animateOnLoad();
        this.initializeAnimations();
        this.handleProfileImage();
    }

    setupEventListeners() {
        // Navigation tabs
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', (e) => this.handleNavClick(e));
        });

        // Profile action buttons
        const editProfileBtn = document.querySelector('.btn--primary');
        const settingsBtn = document.querySelector('.btn--outline:nth-child(2)');
        const securityBtn = document.querySelector('.btn--outline:nth-child(3)');
        
        if (editProfileBtn) {
            editProfileBtn.addEventListener('click', () => this.handleEditProfile());
        }
        if (settingsBtn) {
            settingsBtn.addEventListener('click', () => this.handleSettings());
        }
        if (securityBtn) {
            securityBtn.addEventListener('click', () => this.handleSecurity());
        }

        // Floating Action Button
        const fabButton = document.querySelector('.fab-button');
        const fab = document.querySelector('.fab');
        
        if (fabButton) {
            fabButton.addEventListener('click', () => this.toggleFAB());
        }

        // FAB actions
        const fabActions = document.querySelectorAll('.fab-action');
        fabActions.forEach(action => {
            action.addEventListener('click', (e) => this.handleFABAction(e));
        });

        // Close FAB when clicking outside
        document.addEventListener('click', (e) => {
            if (fab && !fab.contains(e.target)) {
                fab.classList.remove('active');
            }
        });

        // Metric cards hover effects
        const metricCards = document.querySelectorAll('.metric-card');
        metricCards.forEach(card => {
            card.addEventListener('mouseenter', () => this.animateMetricCard(card));
            card.addEventListener('click', () => this.handleMetricClick(card));
        });

        // Achievement cards animation
        const achievementCards = document.querySelectorAll('.achievement-card');
        achievementCards.forEach(card => {
            card.addEventListener('mouseenter', () => this.animateAchievement(card));
            card.addEventListener('click', () => this.handleAchievementClick(card));
        });

        // Activity items hover
        const activityItems = document.querySelectorAll('.activity-item');
        activityItems.forEach(item => {
            item.addEventListener('mouseenter', () => this.animateActivityItem(item));
            item.addEventListener('click', () => this.handleActivityClick(item));
        });

        // Smooth scrolling for any anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', (e) => {
                e.preventDefault();
                const target = document.querySelector(anchor.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        // Intersection Observer for scroll animations
        this.setupScrollAnimations();
    }

    handleEditProfile() {
        // In a real app, this would redirect to the settings page.
        // For the demo, we show a notification.
        // window.location.href = 'settings.php';
        this.showNotification('Redirecting to Edit Profile page...', 'info');
    }

    handleSettings() {
        // window.location.href = 'settings.php#account';
        this.showNotification('Redirecting to Settings page...', 'info');
    }

    handleSecurity() {
        // window.location.href = 'settings.php#security';
        this.showNotification('Redirecting to Security settings...', 'info');
    }

    animateButton(btn) {
        if (btn) {
            btn.style.transform = 'scale(0.95)';
            btn.style.background = 'var(--color-secondary-hover)';
            
            setTimeout(() => {
                btn.style.transform = 'scale(1)';
                btn.style.background = 'var(--color-secondary)';
            }, 150);
        }
    }

    handleMetricClick(card) {
        const metricTitle = card.querySelector('h3').textContent;
        this.showNotification(`Viewing detailed ${metricTitle} analytics...`, 'info');
        this.createCardRipple(card);
    }

    handleAchievementClick(card) {
        const achievementTitle = card.querySelector('h4').textContent;
        this.showNotification(`Achievement details: ${achievementTitle}`, 'success');
        this.createCardRipple(card);
    }

    handleActivityClick(item) {
        const activityTitle = item.querySelector('.activity-title').textContent;
        this.showNotification(`Transaction details: ${activityTitle}`, 'info');
        this.createCardRipple(item);
    }

    createCardRipple(element) {
        const ripple = document.createElement('div');
        ripple.style.cssText = `
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            background: rgba(20, 184, 166, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%) scale(0);
            animation: cardRipple 0.6s ease-out;
            pointer-events: none;
            z-index: 10;
        `;
        
        element.style.position = 'relative';
        element.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    handleNavClick(e) {
        const clickedItem = e.target;
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => item.classList.remove('active'));
        clickedItem.classList.add('active');
        this.createRippleEffect(clickedItem, e);
        this.simulateTabSwitch(clickedItem.dataset.tab);
    }

    createRippleEffect(element, event) {
        const ripple = document.createElement('span');
        const rect = element.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = event.clientX - rect.left - size / 2;
        const y = event.clientY - rect.top - size / 2;
        
        ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
            background: rgba(20, 184, 166, 0.3);
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s ease-out;
            pointer-events: none;
        `;
        
        element.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    simulateTabSwitch(tab) {
        const mainContent = document.querySelector('.main-content');
        mainContent.style.opacity = '0.7';
        mainContent.style.transform = 'translateY(10px)';
        
        setTimeout(() => {
            mainContent.style.opacity = '1';
            mainContent.style.transform = 'translateY(0)';
            this.showNotification(`Switched to ${tab} section`, 'info');
        }, 200);
    }

    toggleFAB() {
        const fab = document.querySelector('.fab');
        const fabButton = document.querySelector('.fab-button');
        fab.classList.toggle('active');
        const isActive = fab.classList.contains('active');
        fabButton.style.transform = isActive ? 'rotate(45deg) scale(1.1)' : 'rotate(0deg) scale(1)';
    }

    handleFABAction(e) {
        const action = e.target.dataset.action;
        e.target.style.transform = 'scale(0.95)';
        setTimeout(() => {
            e.target.style.transform = 'scale(1)';
        }, 150);
        
        switch(action) {
            case 'invest':
                this.showNotification('Investment panel opening...', 'success');
                break;
            case 'transfer':
                this.showNotification('Transfer options loading...', 'info');
                break;
            case 'analyze':
                this.showNotification('Portfolio analysis starting...', 'info');
                break;
        }
        
        document.querySelector('.fab').classList.remove('active');
        document.querySelector('.fab-button').style.transform = 'rotate(0deg) scale(1)';
    }

    animateOnLoad() {
        const sections = [
            '.profile-hero',
            '.metrics-grid',
            '.investment-breakdown',
            '.achievements-section',
            '.activity-section'
        ];
        
        sections.forEach((selector, index) => {
            const element = document.querySelector(selector);
            if (element) {
                element.style.opacity = '0';
                element.style.transform = 'translateY(30px)';
                
                setTimeout(() => {
                    element.style.transition = 'all 0.6s ease-out';
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, index * 200);
            }
        });
    }

    initializeAnimations() {
        setTimeout(() => this.animateProgressBars(), 1000);
        setTimeout(() => this.animateAllocationBars(), 1500);
        setTimeout(() => this.animateCounters(), 800);
    }

    animateProgressBars() {
        const progressBars = document.querySelectorAll('.progress-fill');
        progressBars.forEach(bar => {
            const progress = bar.dataset.progress;
            if (progress) {
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = `${progress}%`;
                }, 100);
            }
        });
    }

    animateAllocationBars() {
        const allocationBars = document.querySelectorAll('.allocation-fill');
        allocationBars.forEach((bar, index) => {
            const percentage = bar.dataset.percentage;
            if (percentage) {
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = `${percentage}%`;
                }, index * 200);
            }
        });
    }

    animateCounters() {
        const counters = document.querySelectorAll('[data-value]');
        counters.forEach(counter => {
            const target = parseInt(counter.dataset.value);
            if (isNaN(target)) return;

            let current = 0;
            const duration = 1500; // ms
            const stepTime = 20; // ms
            const steps = duration / stepTime;
            const increment = target / steps;
            
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                counter.textContent = counter.textContent.replace(/[\d,]+/, this.formatNumber(Math.round(current)));
            }, stepTime);
        });
    }

    formatNumber(num) {
        return num.toLocaleString();
    }

    animateMetricCard(card) {
        card.style.boxShadow = '0 10px 40px rgba(20, 184, 166, 0.3)';
        const metricValue = card.querySelector('.metric-value');
        if (metricValue) {
            metricValue.style.transform = 'scale(1.05)';
            setTimeout(() => {
                metricValue.style.transform = 'scale(1)';
            }, 200);
        }
    }

    animateAchievement(card) {
        const icon = card.querySelector('.achievement-icon');
        if (icon) {
            icon.style.transform = 'scale(1.2) rotate(5deg)';
            setTimeout(() => {
                icon.style.transform = 'scale(1) rotate(0deg)';
            }, 300);
        }
    }

    animateActivityItem(item) {
        const icon = item.querySelector('.activity-icon');
        if (icon) {
            icon.style.transform = 'scale(1.1)';
            icon.style.background = 'rgba(20, 184, 166, 0.4)';
            setTimeout(() => {
                icon.style.transform = 'scale(1)';
                icon.style.background = 'rgba(20, 184, 166, 0.2)';
            }, 200);
        }
    }

    setupScrollAnimations() {
        const observerOptions = { threshold: 0.1, rootMargin: '0px 0px -50px 0px' };
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                }
            });
        }, observerOptions);

        const elementsToAnimate = document.querySelectorAll('.metric-card, .achievement-card, .activity-item, .allocation-item');
        elementsToAnimate.forEach(el => observer.observe(el));
    }

    handleProfileImage() {
        const profileImg = document.getElementById('profileImg');
        if (profileImg) {
            profileImg.style.opacity = '0';
            profileImg.addEventListener('load', () => {
                profileImg.style.transition = 'opacity 0.5s';
                profileImg.style.opacity = '1';
            });
        }
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification--${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(20px);
            color: #f5f5f5;
            padding: 16px 20px;
            border-radius: 8px;
            border: 1px solid rgba(20, 184, 166, 0.3);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            z-index: 10000;
            transform: translateX(400px);
            transition: transform 0.3s ease-out;
            max-width: 300px;
        `;
        
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        setTimeout(() => {
            notification.style.transform = 'translateX(400px)';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    addCustomAnimations() {
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                0% { transform: scale(0); opacity: 1; }
                100% { transform: scale(4); opacity: 0; }
            }
            @keyframes cardRipple {
                0% { transform: translate(-50%, -50%) scale(0); opacity: 1; }
                100% { transform: translate(-50%, -50%) scale(20); opacity: 0; }
            }
            .animate-in { animation: slideInUp 0.6s ease-out forwards; }
            @keyframes slideInUp {
                from { opacity: 0; transform: translateY(30px); }
                to { opacity: 1; transform: translateY(0); }
            }
        `;
        document.head.appendChild(style);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const app = new FintrackerProfile();
    app.addCustomAnimations();
});