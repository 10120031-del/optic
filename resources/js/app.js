import './bootstrap';

function initScrollReveal() {
    const els = document.querySelectorAll('.reveal');
    if (!els.length || !('IntersectionObserver' in window)) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduceMotion) return;

    els.forEach((el) => el.classList.add('js-armed'));

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.1, rootMargin: '0px 0px -40px 0px' }
    );

    els.forEach((el) => observer.observe(el));

    // Safety net: a throttled/backgrounded tab can delay or skip
    // IntersectionObserver callbacks entirely, so force-reveal anything
    // still hidden after a few seconds rather than leave it stuck.
    setTimeout(() => {
        els.forEach((el) => el.classList.add('is-visible'));
        observer.disconnect();
    }, 3000);
}

document.addEventListener('DOMContentLoaded', initScrollReveal);
