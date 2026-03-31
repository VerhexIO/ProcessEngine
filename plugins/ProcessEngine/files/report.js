/**
 * ProcessEngine - Report Page JS (Faz 12)
 *
 * Chart.js integration for report charts.
 */
(function() {
    'use strict';

    var data = null;

    function loadData() {
        var el = document.getElementById('pe-report-data');
        if (!el) return false;

        data = {
            deptPerf: JSON.parse(el.getAttribute('data-dept-perf') || '[]'),
            stepStats: JSON.parse(el.getAttribute('data-step-stats') || '[]'),
            monthly: JSON.parse(el.getAttribute('data-monthly') || '[]'),
            slaDistribution: JSON.parse(el.getAttribute('data-sla-distribution') || '{}'),
            kpiDept: JSON.parse(el.getAttribute('data-kpi-dept') || '[]'),
            kpiSteps: JSON.parse(el.getAttribute('data-kpi-steps') || '[]'),
            labels: {
                normal: el.getAttribute('data-label-normal') || '',
                warning: el.getAttribute('data-label-warning') || '',
                exceeded: el.getAttribute('data-label-exceeded') || '',
                avgDuration: el.getAttribute('data-label-avg-duration') || '',
                processCount: el.getAttribute('data-label-process-count') || '',
                slaExceeded: el.getAttribute('data-label-sla-exceeded') || ''
            }
        };
        return true;
    }

    function initCharts() {
        if (!loadData()) return;
        renderDeptPerformance();
        renderSlaDistribution();
        renderStepDuration();
        renderMonthlyTrend();
        renderKpiDeptAvg();
        renderKpiStepDist();
    }

    /**
     * Departman Performansı — Bar chart
     */
    function renderDeptPerformance() {
        var canvas = document.getElementById('pe-chart-dept');
        if (!canvas || !data.deptPerf || data.deptPerf.length === 0) return;

        var labels = [];
        var avgData = [];
        var colors = [];
        var palette = ['#36a2eb', '#ff6384', '#ffce56', '#4bc0c0', '#9966ff', '#ff9f40', '#c9cbcf'];

        for (var i = 0; i < data.deptPerf.length; i++) {
            labels.push(data.deptPerf[i].department);
            avgData.push(data.deptPerf[i].avg_duration_hrs);
            colors.push(palette[i % palette.length]);
        }

        new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: data.labels.avgDuration + ' (h)',
                    data: avgData,
                    backgroundColor: colors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    /**
     * SLA Dağılımı — Pie chart
     */
    function renderSlaDistribution() {
        var canvas = document.getElementById('pe-chart-sla');
        if (!canvas || !data.slaDistribution) return;

        var values = [
            data.slaDistribution.normal,
            data.slaDistribution.warning,
            data.slaDistribution.exceeded
        ];

        // Tüm değerler 0 ise boş chart göster
        if (values[0] === 0 && values[1] === 0 && values[2] === 0) {
            values = [100, 0, 0];
        }

        new Chart(canvas.getContext('2d'), {
            type: 'pie',
            data: {
                labels: [data.labels.normal, data.labels.warning, data.labels.exceeded],
                datasets: [{
                    data: values,
                    backgroundColor: ['#4caf50', '#ff9800', '#f44336']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    /**
     * Adım Süre Dağılımı — Horizontal bar chart
     */
    function renderStepDuration() {
        var canvas = document.getElementById('pe-chart-steps');
        if (!canvas || !data.stepStats || data.stepStats.length === 0) return;

        var labels = [];
        var avgData = [];
        var colors = [];
        var palette = ['#36a2eb', '#ff6384', '#ffce56', '#4bc0c0', '#9966ff', '#ff9f40'];

        for (var i = 0; i < data.stepStats.length; i++) {
            var s = data.stepStats[i];
            labels.push(s.step_name + (s.flow_name ? ' (' + s.flow_name + ')' : ''));
            avgData.push(s.avg_duration_hrs);
            colors.push(palette[i % palette.length]);
        }

        new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: data.labels.avgDuration + ' (h)',
                    data: avgData,
                    backgroundColor: colors,
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { beginAtZero: true }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    /**
     * Aylık Trend — Line chart
     */
    function renderMonthlyTrend() {
        var canvas = document.getElementById('pe-chart-trend');
        if (!canvas || !data.monthly || data.monthly.length === 0) return;

        var labels = [];
        var countData = [];
        var exceededData = [];

        for (var i = 0; i < data.monthly.length; i++) {
            labels.push(data.monthly[i].label);
            countData.push(data.monthly[i].count);
            exceededData.push(data.monthly[i].exceeded);
        }

        new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: data.labels.processCount,
                        data: countData,
                        borderColor: '#36a2eb',
                        backgroundColor: 'rgba(54,162,235,0.1)',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: data.labels.slaExceeded,
                        data: exceededData,
                        borderColor: '#f44336',
                        backgroundColor: 'rgba(244,67,54,0.1)',
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                },
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    /**
     * KPI: Departman Bazlı Ort. İş Dakikası — Bar chart
     */
    function renderKpiDeptAvg() {
        var canvas = document.getElementById('pe-chart-kpi-dept');
        if (!canvas || !data.kpiDept || data.kpiDept.length === 0) return;

        var labels = [];
        var avgData = [];
        var colors = [];
        var palette = ['#2196f3', '#e91e63', '#ffc107', '#009688', '#673ab7', '#ff5722'];

        for (var i = 0; i < data.kpiDept.length; i++) {
            labels.push(data.kpiDept[i].department);
            avgData.push(data.kpiDept[i].avg_minutes);
            colors.push(palette[i % palette.length]);
        }

        new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Business Minutes',
                    data: avgData,
                    backgroundColor: colors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    /**
     * KPI: Adım Bazlı Süre Dağılımı — Horizontal bar chart
     */
    function renderKpiStepDist() {
        var canvas = document.getElementById('pe-chart-kpi-steps');
        if (!canvas || !data.kpiSteps || data.kpiSteps.length === 0) return;

        var labels = [];
        var avgData = [];
        var minData = [];
        var maxData = [];

        for (var i = 0; i < data.kpiSteps.length; i++) {
            labels.push(data.kpiSteps[i].step_name);
            avgData.push(data.kpiSteps[i].avg_minutes);
            minData.push(data.kpiSteps[i].min_minutes);
            maxData.push(data.kpiSteps[i].max_minutes);
        }

        new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Min',
                        data: minData,
                        backgroundColor: 'rgba(76,175,80,0.5)',
                        borderWidth: 1
                    },
                    {
                        label: 'Avg',
                        data: avgData,
                        backgroundColor: 'rgba(33,150,243,0.7)',
                        borderWidth: 1
                    },
                    {
                        label: 'Max',
                        data: maxData,
                        backgroundColor: 'rgba(244,67,54,0.5)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { beginAtZero: true }
                },
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCharts);
    } else {
        initCharts();
    }
})();
