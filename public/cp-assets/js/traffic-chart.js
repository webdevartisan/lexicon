/**
 * Draws the views and visitors chart on the Traffic page. Colours come from
 * utility classes on the page, so the chart follows the light and dark palettes.
 */
(function () {
    'use strict';

    var host = document.querySelector('[data-traffic-chart]');
    if (!host || typeof window.Chart !== 'function') {
        return;
    }

    var data = JSON.parse(host.querySelector('[data-chart-series]').textContent);
    var canvas = host.querySelector('canvas');
    var root = document.documentElement;
    var numbers = new Intl.NumberFormat(root.lang || undefined);
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var chart = null;

    function colour(name) {
        return getComputedStyle(host.querySelector('[data-chart-color="' + name + '"]')).color;
    }

    function withAlpha(rgb, alpha) {
        var parts = rgb.match(/\d+(\.\d+)?/g) || [0, 0, 0];

        return 'rgba(' + parts[0] + ', ' + parts[1] + ', ' + parts[2] + ', ' + alpha + ')';
    }

    function draw() {
        var dark = root.getAttribute('data-mode') === 'dark';
        var grid = dark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(15, 23, 42, 0.08)';
        var ticks = dark ? '#92afd3' : '#64748b';
        var views = colour('views');
        var visitors = colour('visitors');
        var rtl = root.dir === 'rtl';

        if (chart) {
            chart.destroy();
        }

        chart = new window.Chart(canvas, {
            type: 'line',
            data: {
                labels: data.points.map(function (p) { return p.label; }),
                datasets: [
                    {
                        label: data.labels.views,
                        data: data.points.map(function (p) { return p.views; }),
                        borderColor: views,
                        backgroundColor: withAlpha(views, 0.15),
                        fill: true,
                        tension: 0.3,
                        borderWidth: 2,
                        pointRadius: data.points.length > 45 ? 0 : 2,
                        pointHoverRadius: 4
                    },
                    {
                        label: data.labels.visitors,
                        data: data.points.map(function (p) { return p.visitors; }),
                        borderColor: visitors,
                        backgroundColor: withAlpha(visitors, 0.08),
                        fill: true,
                        tension: 0.3,
                        borderWidth: 2,
                        pointRadius: data.points.length > 45 ? 0 : 2,
                        pointHoverRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: reduceMotion ? false : { duration: 400 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        rtl: rtl,
                        callbacks: {
                            label: function (item) {
                                return ' ' + item.dataset.label + ': ' + numbers.format(item.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        reverse: rtl,
                        grid: { display: false },
                        ticks: { color: ticks, autoSkip: true, maxTicksLimit: 8, maxRotation: 0 }
                    },
                    y: {
                        position: rtl ? 'right' : 'left',
                        beginAtZero: true,
                        grid: { color: grid },
                        border: { display: false },
                        ticks: {
                            color: ticks,
                            precision: 0,
                            callback: function (value) { return numbers.format(value); }
                        }
                    }
                }
            }
        });
    }

    var wasDark = root.getAttribute('data-mode') === 'dark';
    draw();

    // The mode switch flips data-mode on <html>. Redraw so the grid and ticks follow.
    new MutationObserver(function () {
        var isDark = root.getAttribute('data-mode') === 'dark';

        if (isDark !== wasDark) {
            wasDark = isDark;
            draw();
        }
    }).observe(root, { attributes: true, attributeFilter: ['data-mode'] });
})();
