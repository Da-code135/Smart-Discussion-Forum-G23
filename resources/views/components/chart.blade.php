@props([
    'type' => 'bar',
    'labels' => [],
    'datasets' => [],
    'height' => 280,
    'options' => '{}',
    'id' => 'chart-' . uniqid(),
])

@php
    // Normalize options: when passed as :options="[...]" it's an array;
    // when using the default or a string prop, decode it.
    $optionsArray = is_array($options) ? $options : (json_decode($options, true) ?: []);
@endphp

<canvas id="{{ $id }}" height="{{ $height }}" style="max-height: {{ $height }}px;"></canvas>

@push('scripts')
<script>
(function () {
    'use strict';

    function initChart() {
        if (typeof Chart === 'undefined') {
            // Chart.js not loaded yet — retry after a short delay
            setTimeout(initChart, 100);
            return;
        }

        var ctx = document.getElementById('{{ $id }}');
        if (!ctx) return;

        // Color palette that matches the app theme
        var colors = [
            '#59623e', '#7c5639', '#4caf50', '#2196f3', '#ff9800',
            '#f44336', '#9c27b0', '#00bcd4', '#e91e63', '#3f51b5',
        ];

        var baseOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 16,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        font: { family: "'Work Sans', sans-serif", size: 12 },
                        color: '#46483e',
                    },
                },
                tooltip: {
                    backgroundColor: '#1e1b15',
                    titleFont: { family: "'Manrope', sans-serif", size: 13, weight: '600' },
                    bodyFont: { family: "'Work Sans', sans-serif", size: 12 },
                    cornerRadius: 8,
                    padding: 12,
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        font: { family: "'Work Sans', sans-serif", size: 11 },
                        color: '#77786d',
                    },
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(30, 27, 21, 0.06)',
                    },
                    ticks: {
                        font: { family: "'Work Sans', sans-serif", size: 11 },
                        color: '#77786d',
                        stepSize: 1,
                    },
                },
            },
        };

        // Merge user options into base
        var mergedOptions = deepMerge(baseOptions, @json($optionsArray));

        // Assign colors to datasets that don't have any
        var preparedDatasets = @json($datasets).map(function (ds, i) {
            if (!ds.backgroundColor) {
                if ('{{ $type }}' === 'doughnut' || '{{ $type }}' === 'pie') {
                    ds.backgroundColor = colors.slice(0, Math.max(@json(count($labels)), 1));
                } else {
                    ds.backgroundColor = colors[i % colors.length];
                }
            }
            if (!ds.borderColor && ds.backgroundColor) {
                ds.borderColor = Array.isArray(ds.backgroundColor)
                    ? ds.backgroundColor
                    : colors[i % colors.length];
            }
            if (('{{ $type }}' === 'bar' || '{{ $type }}' === 'line') && !ds.borderWidth) {
                ds.borderWidth = 2;
            }
            if ('{{ $type }}' === 'doughnut' || '{{ $type }}' === 'pie') {
                ds.borderWidth = 2;
                ds.borderColor = '#ffffff';
                ds.hoverBorderColor = '#ffffff';
                ds.hoverBorderWidth = 3;
            }
            return ds;
        });

        // Only render if datasets have data
        var hasData = preparedDatasets.some(function (ds) {
            if (!ds.data || ds.data.length === 0) return false;
            return ds.data.some(function (v) { return v !== null && v !== undefined && v !== 0; });
        });

        if (!hasData) {
            ctx.parentElement.innerHTML =
                '<div style="display:flex;align-items:center;justify-content:center;height:' +
                {{ $height }} +
                'px;color:var(--text-muted,#77786d);font-size:13px;">No data to display</div>';
            return;
        }

        new Chart(ctx, {
            type: '{{ $type }}',
            data: {
                labels: @json($labels),
                datasets: preparedDatasets,
            },
            options: mergedOptions,
        });
    }

    // Wait for DOM then start
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initChart);
    } else {
        initChart();
    }

    // Deep merge utility (simple recursive merge)
    function deepMerge(target, source) {
        var result = JSON.parse(JSON.stringify(target));
        for (var key in source) {
            if (source.hasOwnProperty(key)) {
                if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
                    result[key] = deepMerge(result[key] || {}, source[key]);
                } else {
                    result[key] = source[key];
                }
            }
        }
        return result;
    }
})();
</script>
@endpush
