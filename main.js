// Global variables
let isLoading = true;
let particlesArray = [];

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize loading screen
    initLoadingScreen();
    
    // Initialize particles
    initParticles();
    
    // Initialize navbar
    initNavbar();
    
    // Initialize animations
    initAnimations();
    
    // Initialize counters
    initCounters();
    
    // Initialize progress bars
    initProgressBars();
    
    // Initialize testimonial slider
    initTestimonialSlider();
    
    // Initialize typing effect
    initTypingEffect();
});

// Loading Screen
function initLoadingScreen() {
    setTimeout(() => {
        const loadingScreen = document.getElementById('loading-screen');
        if (loadingScreen) {
            loadingScreen.style.opacity = '0';
            setTimeout(() => {
                loadingScreen.style.display = 'none';
                isLoading = false;
            }, 500);
        }
    }, 3000);
}

// Particles System
function initParticles() {
    const particlesContainer = document.getElementById('particles-container') || document.getElementById('login-particles');
    if (!particlesContainer) return;
    
    // Create particles
    for (let i = 0; i < 50; i++) {
        createParticle(particlesContainer);
    }
    
    // Mouse interaction
    document.addEventListener('mousemove', (e) => {
        if (isLoading) return;
        
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = e.clientX + 'px';
        particle.style.top = e.clientY + 'px';
        particle.style.animationDuration = Math.random() * 3 + 2 + 's';
        particlesContainer.appendChild(particle);
        
        setTimeout(() => {
            particle.remove();
        }, 5000);
    });
}

function createParticle(container) {
    const particle = document.createElement('div');
    particle.className = 'particle';
    
    // Random position
    particle.style.left = Math.random() * 100 + '%';
    particle.style.top = Math.random() * 100 + '%';
    
    // Random animation duration
    particle.style.animationDuration = Math.random() * 20 + 10 + 's';
    particle.style.animationDelay = Math.random() * 5 + 's';
    
    container.appendChild(particle);
    
    // Remove and recreate particle after animation
    setTimeout(() => {
        particle.remove();
        if (container.parentNode) {
            createParticle(container);
        }
    }, (Math.random() * 20 + 10) * 1000);
}

// Navbar functionality
function initNavbar() {
    const navbar = document.getElementById('mainNav');
    if (!navbar) return;
    
    // Scroll effect
    window.addEventListener('scroll', () => {
        if (window.scrollY > 100) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// GSAP Animations
function initAnimations() {
    if (typeof gsap === 'undefined') return;
    
    // Register ScrollTrigger plugin
    gsap.registerPlugin(ScrollTrigger);
    
    // Hero animations
    if (document.querySelector('.hero-section')) {
        gsap.fromTo('.hero-title', 
            { opacity: 0, y: 100 },
            { opacity: 1, y: 0, duration: 1.5, delay: 3.5 }
        );
    }
    
    // Service cards animation
    gsap.utils.toArray('.service-card').forEach(card => {
        gsap.fromTo(card,
            { opacity: 0, y: 50 },
            {
                opacity: 1,
                y: 0,
                duration: 0.8,
                scrollTrigger: {
                    trigger: card,
                    start: 'top 80%',
                    end: 'bottom 20%',
                    toggleActions: 'play none none reverse'
                }
            }
        );
    });
    
    // Timeline animation
    gsap.utils.toArray('.timeline-item').forEach((item, index) => {
        gsap.fromTo(item,
            { opacity: 0, x: index % 2 === 0 ? -100 : 100 },
            {
                opacity: 1,
                x: 0,
                duration: 0.8,
                delay: index * 0.2,
                scrollTrigger: {
                    trigger: item,
                    start: 'top 80%',
                    end: 'bottom 20%',
                    toggleActions: 'play none none reverse'
                }
            }
        );
    });
    
    // Flip cards hover effect
    document.querySelectorAll('.flip-card').forEach(card => {
        card.addEventListener('mouseenter', () => {
            gsap.to(card, { scale: 1.05, duration: 0.3 });
        });
        
        card.addEventListener('mouseleave', () => {
            gsap.to(card, { scale: 1, duration: 0.3 });
        });
    });
    
    // Footer animation
    gsap.utils.toArray('.footer-item').forEach((item, index) => {
        gsap.fromTo(item,
            { opacity: 0, y: 30 },
            {
                opacity: 1,
                y: 0,
                duration: 0.6,
                delay: index * 0.2,
                scrollTrigger: {
                    trigger: '.footer',
                    start: 'top 80%',
                    end: 'bottom 20%',
                    toggleActions: 'play none none reverse'
                }
            }
        );
    });
}

// Counter Animation
function initCounters() {
    const counters = document.querySelectorAll('.stat-number');
    
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-target'));
        const increment = target / 100;
        let current = 0;
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const timer = setInterval(() => {
                        current += increment;
                        counter.textContent = Math.floor(current);
                        
                        if (current >= target) {
                            counter.textContent = target;
                            clearInterval(timer);
                        }
                    }, 20);
                    
                    observer.unobserve(counter);
                }
            });
        });
        
        observer.observe(counter);
    });
}

// Progress Bars Animation
function initProgressBars() {
    const progressBars = document.querySelectorAll('.progress-bar');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const progressBar = entry.target;
                const width = progressBar.getAttribute('data-width');
                
                setTimeout(() => {
                    progressBar.style.width = width + '%';
                }, 500);
                
                observer.unobserve(progressBar);
            }
        });
    });
    
    progressBars.forEach(bar => observer.observe(bar));
}

// Testimonial Slider
function initTestimonialSlider() {
    const testimonials = document.querySelectorAll('.testimonial-item');
    let currentTestimonial = 0;
    
    if (testimonials.length === 0) return;
    
    function showNextTestimonial() {
        testimonials[currentTestimonial].classList.remove('active');
        currentTestimonial = (currentTestimonial + 1) % testimonials.length;
        testimonials[currentTestimonial].classList.add('active');
    }
    
    // Auto-rotate testimonials every 4 seconds
    setInterval(showNextTestimonial, 4000);
}

// Typing Effect
function initTypingEffect() {
    const typingElement = document.querySelector('.typing-text');
    if (!typingElement) return;
    
    const text = typingElement.textContent;
    typingElement.textContent = '';
    
    let i = 0;
    function typeWriter() {
        if (i < text.length) {
            typingElement.textContent += text.charAt(i);
            i++;
            setTimeout(typeWriter, 50);
        }
    }
    
    // Start typing after loading screen
    setTimeout(typeWriter, 4000);
}

// Password Toggle
function togglePassword(fieldId, toggleElement) {
    const passwordField = document.getElementById(fieldId);
    const icon = toggleElement.querySelector('i');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        passwordField.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// Form Switching
function switchToRegister() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    
    if (loginForm && registerForm) {
        loginForm.style.display = 'none';
        registerForm.style.display = 'block';
        registerForm.style.animation = 'slideInRight 0.5s ease-out';
    }
}

function switchToLogin() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    
    if (loginForm && registerForm) {
        registerForm.style.display = 'none';
        loginForm.style.display = 'block';
        loginForm.style.animation = 'slideInRight 0.5s ease-out';
    }
}

// Utility functions
function debounce(func, wait) {
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

// Page-specific initializations
function initSponsoringPage() {
    // Sponsor logo floating animation
    const sponsorLogos = document.querySelectorAll('.sponsor-logo');
    sponsorLogos.forEach((logo, index) => {
        logo.style.animationDelay = `${index * 0.5}s`;
    });
}

function initHostingPage() {
    // Server animation
    const serverLights = document.querySelectorAll('.light');
    serverLights.forEach((light, index) => {
        light.style.animationDelay = `${index * 0.2}s`;
    });
    
    // Cloud animation
    const clouds = document.querySelectorAll('.cloud-shape');
    clouds.forEach(cloud => {
        cloud.addEventListener('mouseenter', () => {
            cloud.style.transform = 'scale(1.1)';
            cloud.style.transition = 'transform 0.3s ease';
        });
        
        cloud.addEventListener('mouseleave', () => {
            cloud.style.transform = 'scale(1)';
        });
    });
}

// Initialize page-specific features based on current page
function initPageSpecific() {
    const currentPage = window.location.pathname;
    
    if (currentPage.includes('sponsoring')) {
        initSponsoringPage();
    } else if (currentPage.includes('hosting')) {
        initHostingPage();
    }
}

// Call page-specific initialization
document.addEventListener('DOMContentLoaded', initPageSpecific);

// Error handling
window.addEventListener('error', function(e) {
    console.error('JavaScript error:', e.error);
});

// Performance monitoring
if ('performance' in window) {
    window.addEventListener('load', () => {
        setTimeout(() => {
            const perfData = performance.getEntriesByType('navigation')[0];
            console.log(`Page load time: ${perfData.loadEventEnd - perfData.loadEventStart}ms`);
        }, 0);
    });
}