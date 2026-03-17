#!/usr/bin/env node

/**
 * Server‑side Lucide icon renderer.
 *
 * Reads HTML from stdin, converts elements with `data-lucide`
 * into inline SVG icons, and outputs the rendered HTML to stdout.
 */

const { JSDOM } = require('jsdom');
const lucide = require('lucide');


/**
 * Convert kebab‑case to PascalCase to match Lucide icon exports.
 *
 * Example: 'arrow-right' → 'ArrowRight'.
 */
function toPascalCase(str) {
    return str
        .split('-')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join('');
}


/**
 * Convert Lucide’s array format to SVG string.
 *
 * Lucide icons are arrays: ['svg', {attributes}, [[child, attrs], ...]].
 * This builds an SVG markup string from that structure.
 */
function arrayToSvg(iconArray) {
    if (!Array.isArray(iconArray) || iconArray.length < 3) {
        return null;
    }

    const [tagName, attrs, children] = iconArray;

    if (tagName !== 'svg') {
        return null;
    }

    let svg = '<svg';

    for (const [key, value] of Object.entries(attrs)) {
        svg += ` ${key}="${value}"`;
    }

    svg += '>';

    if (Array.isArray(children)) {
        children.forEach(child => {
            if (Array.isArray(child) && child.length >= 2) {
                const [childTag, childAttrs] = child;
                svg += `<${childTag}`;

                if (childAttrs && typeof childAttrs === 'object') {
                    for (const [key, value] of Object.entries(childAttrs)) {
                        svg += ` ${key}="${value}"`;
                    }
                }

                svg += '/>';
            }
        });
    }

    svg += '</svg>';

    return svg;
}


// Read HTML from stdin in chunks.
let html = '';

process.stdin.setEncoding('utf8');

process.stdin.on('data', function(chunk) {
    html += chunk;
});

process.stdin.on('end', function() {
    try {
        // Create a virtual DOM from the input HTML.
        const dom = new JSDOM(html);
        const document = dom.window.document;

        // Find all elements with the data‑lucide attribute.
        const iconElements = document.querySelectorAll('[data-lucide]');

        iconElements.forEach(function(element) {
            const iconName = element.getAttribute('data-lucide');
            const pascalName = toPascalCase(iconName);

            // Lucide exposes icons by PascalCase name.
            const iconArray = lucide.icons[pascalName];

            if (iconArray) {
                const svgString = arrayToSvg(iconArray);

                if (svgString) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = svgString;
                    const svg = tempDiv.firstChild;

                    // Preserve the original class attribute.
                    const className = element.getAttribute('class');
                    if (className) {
                        svg.setAttribute('class', className);
                    }

                    element.parentNode.replaceChild(svg, element);
                }
            }
        });

        // Output only the body content to avoid <html><body> wrapping.
        process.stdout.write(document.body.innerHTML);

    } catch (error) {
        // Log errors to stderr, but still output original HTML as fallback.
        process.stderr.write('Error rendering icons: ' + error.message + '\n');
        process.stderr.write(error.stack + '\n');

        // Prevent total render failure if icons cannot be processed.
        process.stdout.write(html);
    }
});
