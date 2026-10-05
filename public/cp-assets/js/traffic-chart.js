/**
 * Draws the line chart on the Traffic and Sign-ups pages, one line per key in
 * data.labels, a dashed line per key in data.previous for the period it is
 * compared with, and a marker for each point in data.markers. Colours come
 * from utility classes on the page, so the chart follows the light and dark palettes.
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
    var markers = {};
    var chart = null;

    (data.markers || []).forEach(function (marker) {
        markers[marker.index] = marker.labels;
    });

    function colour(name) {
        var swatch = host.querySelector('[data-chart-color="' + name + '"]');

        return swatch ? getComputedStyle(swatch).color : 'rgb(100, 116, 139)';
    }

    function withAlpha(rgb, alpha) {
        var parts = rgb.match(/\d+(\.\d+)?/g) || [0, 0, 0];

        return 'rgba(' + parts[0] + ', ' + parts[1] + ', ' + parts[2] + ', ' + alpha + ')';
    }

    function datasets() {
        var sets = Object.keys(data.labels).map(function (key, index) {
            var line = colour(key);

            return {
                label: data.labels[key],
                data: data.points.map(function (p) { return p[key]; }),
                borderColor: line,
                backgroundColor: withAlpha(line, index === 0 ? 0.15 : 0.08),
                fill: true,
                tension: 0.3,
                borderWidth: 2,
                pointRadius: data.points.length > 45 ? 0 : 2,
                pointHoverRadius: 4
            };
        });

        Object.keys(data.previous || {}).forEach(function (key) {
            sets.push({
                label: data.previous[key],
                data: data.points.map(function (p) { return p['previous_' + key]; }),
                borderColor: withAlpha(colour(key), 0.55),
                borderDash: [5, 4],
                borderWidth: 1.5,
                fill: false,
                tension: 0.3,
                pointRadius: 0,
                pointHoverRadius: 3
            });
        });

        return sets;
    }

    // A thin vertical line on each point something happened, under the data lines.
    var markerLines = {
        id: 'trafficMarkers',
        beforeDatasetsDraw: function (instance) {
            var indexes = Object.keys(markers);
            if (indexes.length === 0) {
                return;
            }

            var ctx = instance.ctx;
            var area = instance.chartArea;
            var x = instance.scales.x;

            ctx.save();
            ctx.strokeStyle = withAlpha(colour('marker'), 0.8);
            ctx.fillStyle = colour('marker');
            ctx.setLineDash([3, 3]);
            ctx.lineWidth = 1;

            indexes.forEach(function (index) {
                var position = x.getPixelForValue(Number(index));
                ctx.beginPath();
                ctx.moveTo(position, area.top);
                ctx.lineTo(position, area.bottom);
                ctx.stroke();
                ctx.beginPath();
                ctx.arc(position, area.top + 4, 3, 0, Math.PI * 2);
                ctx.fill();
            });

            ctx.restore();
        }
    };

    function draw() {
        var dark = root.getAttribute('data-mode') === 'dark';
        var grid = dark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(15, 23, 42, 0.08)';
        var ticks = dark ? '#92afd3' : '#64748b';
        var rtl = root.dir === 'rtl';

        if (chart) {
            chart.destroy();
        }

        chart = new window.Chart(canvas, {
            type: 'line',
            data: {
                labels: data.points.map(function (p) { return p.label; }),
                datasets: datasets()
            },
            plugins: [markerLines],
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
                            },
                            footer: function (items) {
                                return items.length && markers[items[0].dataIndex] ? markers[items[0].dataIndex] : [];
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
