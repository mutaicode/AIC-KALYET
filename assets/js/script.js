document.addEventListener("DOMContentLoaded", function () {
    // Find all navigation links
    const navLinks = document.querySelectorAll(
        ".navbar-nav .nav-link"
    );

    // Highlight the selected section
    navLinks.forEach(function (link) {
        link.addEventListener("click", function () {
            navLinks.forEach(function (item) {
                item.classList.remove("active");
            });

            this.classList.add("active");

            // Close the mobile menu after selecting a link
            const menu = document.getElementById("mainNavigation");

            if (
                menu &&
                menu.classList.contains("show") &&
                window.bootstrap
            ) {
                const mobileMenu =
                    bootstrap.Collapse.getOrCreateInstance(menu);

                mobileMenu.hide();
            }
        });
    });
});
// Smooth reveal for sections and feature cards
document.addEventListener("DOMContentLoaded", function () {
    const elements = document.querySelectorAll(
        ".section-heading, .feature-card, .about-section .row, .dashboard-preview, " +
        "#leadership .leadership-group, #leadership .leader-card"
    );

    // Use a fallback for browsers without IntersectionObserver
    if (!("IntersectionObserver" in window)) {
        elements.forEach(function (element) {
            element.style.opacity = "1";
        });
        return;
    }

    const observer = new IntersectionObserver(
        function (entries, observer) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add("section-visible");
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.12 }
    );

    elements.forEach(function (element) {
        element.classList.add("section-reveal");
        observer.observe(element);
    });
});
// Animate church statistics when the section becomes visible
const statNumbers = document.querySelectorAll(".stat-number");

if ("IntersectionObserver" in window && statNumbers.length > 0) {
    const statsObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;

            const number = entry.target;
            const target = Number(number.dataset.target);

            if (!Number.isFinite(target) || target <= 0) {
                number.textContent = "0";
                observer.unobserve(number);
                return;
            }

            const duration = 1200;
            const startTime = performance.now();

            function updateNumber(currentTime) {
                const progress = Math.min(
                    (currentTime - startTime) / duration,
                    1
                );

                number.textContent = Math.floor(target * progress);

                if (progress < 1) {
                    requestAnimationFrame(updateNumber);
                }
            }

            requestAnimationFrame(updateNumber);
            observer.unobserve(number);
        });
    }, { threshold: 0.5 });

    statNumbers.forEach(number => statsObserver.observe(number));
}