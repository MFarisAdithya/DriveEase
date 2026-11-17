const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const activeMotion = new Set();

function animateSurface(element, frames, options) {
    if (reducedMotion.matches || !element.animate) return null;
    const animation = element.animate(frames, options);
    activeMotion.add(animation);
    animation.finished.catch(() => {}).finally(() => activeMotion.delete(animation));
    return animation;
}

reducedMotion.addEventListener('change', () => {
    if (reducedMotion.matches) activeMotion.forEach((animation) => animation.cancel());
});

// Content stays visible without JavaScript; only newly visible surfaces animate.
const surfaceSelector = 'main .ui-panel, main article, main .catalog-hero, main form, main .bg-surface.rounded-xl, main .bg-surface.rounded-2xl, section[id] > div, body > div > div.max-w-md';
if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(({ target, isIntersecting }) => {
            if (!isIntersecting) return;
            observer.unobserve(target);
            if (target.contains(document.activeElement)) return;
            animateSurface(target, [
                { opacity: 0.45, translate: '0 18px', scale: '0.975', borderRadius: '30px' },
                { opacity: 1, translate: '0 0', scale: '1', borderRadius: getComputedStyle(target).borderRadius },
            ], { duration: 340, easing: 'cubic-bezier(.22,1,.36,1)' });
        });
    }, { threshold: 0.05 });
    document.querySelectorAll(surfaceSelector).forEach((surface) => {
        // Avoid stacking movement on nested panels or individual form controls.
        if (!surface.parentElement.closest(surfaceSelector)) observer.observe(surface);
    });
}

// Native details remain usable without scripts, including keyboard activation.
document.querySelectorAll('header details').forEach((menu) => {
    const summary = menu.querySelector('summary');
    const panel = menu.querySelector('nav');
    if (!summary || !panel) return;
    let motion;
    let expanded = menu.open;
    const setExpanded = (next) => {
        expanded = next;
        motion?.cancel();
        if (next) menu.open = true;
        const frames = [
            { opacity: 0, translate: '0 -8px', scale: '.94', borderRadius: '24px' },
            { opacity: 1, translate: '0 0', scale: '1', borderRadius: getComputedStyle(panel).borderRadius },
        ];
        motion = animateSurface(panel, next ? frames : [...frames].reverse(), {
            duration: next ? 230 : 150,
            easing: next ? 'cubic-bezier(.22,1,.36,1)' : 'ease-in',
        });
        if (!motion) {
            menu.open = next;
        } else {
            motion.onfinish = () => { menu.open = expanded; };
            motion.oncancel = () => { menu.open = expanded; };
        }
    };
    summary.addEventListener('click', (event) => {
        event.preventDefault();
        setExpanded(!expanded);
    });
    menu.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && expanded) {
            setExpanded(false);
            summary.focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (expanded && !menu.contains(event.target)) setExpanded(false);
    });
    menu.addEventListener('focusout', () => {
        queueMicrotask(() => {
            if (expanded && !menu.contains(document.activeElement)) setExpanded(false);
        });
    });
});
