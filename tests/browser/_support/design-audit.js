return (function () {
    const parse = (c) => {
        const m = c.match(/rgba?\(([^)]+)\)/);
        if (!m) return null;
        const p = m[1].split(/[,\s\/]+/).filter(Boolean).map(Number);
        return { r: p[0], g: p[1], b: p[2], a: p[3] === undefined ? 1 : p[3] };
    };
    const luminance = ({ r, g, b }) => {
        const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
        return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
    };
    const blend = (top, under) => ({
        r: top.r * top.a + under.r * (1 - top.a),
        g: top.g * top.a + under.g * (1 - top.a),
        b: top.b * top.a + under.b * (1 - top.a),
        a: 1,
    });
    // Gradients and images cannot be sampled, so text over them is skipped instead of guessed.
    const backgroundOf = (el) => {
        const layers = [];
        for (let e = el; e; e = e.parentElement) {
            const cs = getComputedStyle(e);
            if (cs.backgroundImage !== 'none') return null;
            const c = parse(cs.backgroundColor);
            if (c && c.a > 0) {
                layers.push(c);
                if (c.a === 1) break;
            }
        }
        let base = { r: 255, g: 255, b: 255, a: 1 };
        for (let i = layers.length - 1; i >= 0; i--) base = blend(layers[i], base);
        return base;
    };
    const label = (el) => (el.className && el.className.toString().trim().split(/\s+/)[0]) || el.tagName.toLowerCase();

    const sizes = {}, families = {}, weights = {}, fails = [], small = [];
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const seen = new Set();
    let node;
    while ((node = walker.nextNode())) {
        const text = node.textContent.trim();
        const el = node.parentElement;
        if (!text || seen.has(el)) continue;
        seen.add(el);
        const cs = getComputedStyle(el);
        const box = el.getBoundingClientRect();
        if (cs.display === 'none' || cs.visibility === 'hidden' || +cs.opacity === 0 || !box.width || !box.height) continue;

        const size = parseFloat(cs.fontSize);
        const weight = cs.fontWeight;
        sizes[size] = (sizes[size] || 0) + 1;
        weights[weight] = (weights[weight] || 0) + 1;
        const family = cs.fontFamily.split(',')[0].replace(/['"]/g, '').trim();
        families[family] = (families[family] || 0) + 1;

        const fg = parse(cs.color);
        const bg = backgroundOf(el);
        if (fg && bg) {
            const shown = blend(fg, bg);
            const a = luminance(shown), b = luminance(bg);
            const ratio = (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
            const large = size >= 24 || (size >= 18.66 && +weight >= 700);
            if (ratio < (large ? 3 : 4.5)) fails.push({ text: text.slice(0, 30), ratio: +ratio.toFixed(2), size, cls: label(el) });
        }
        if (size < 12) small.push({ text: text.slice(0, 26), size, cls: label(el) });
    }

    const targets = [];
    document.querySelectorAll('a[href], button, input:not([type=hidden]), select, textarea, [role=button]').forEach((el) => {
        const box = el.getBoundingClientRect();
        const cs = getComputedStyle(el);
        if (!box.width || !box.height || cs.visibility === 'hidden' || cs.display === 'none') return;
        // Links inside running text are exempt under WCAG 2.2 target size
        if (el.tagName === 'A' && getComputedStyle(el).display === 'inline') return;
        if (box.height < 24 || box.width < 24) {
            targets.push({ text: (el.textContent || el.name || '').trim().slice(0, 22), w: Math.round(box.width), h: Math.round(box.height), cls: label(el) });
        }
    });

    return JSON.stringify({
        path: location.pathname,
        viewport: innerWidth,
        sidewaysScroll: document.documentElement.scrollWidth - innerWidth,
        sizes, families, weights,
        contrastFails: fails,
        smallText: small,
        smallTargets: targets,
    });
})();
