export function createModalPerformance() {
    let activeMetric = null;
    const now = () => window.performance?.now?.() ?? Date.now();
    const point = name => {
        if (!activeMetric) return;
        activeMetric[name] = Math.max(0, now() - activeMetric.startedAt);
    };
    const begin = (contractId, startedAt) => {
        activeMetric = {
            contractId: String(contractId || ''),
            startedAt: Number.isFinite(startedAt) ? startedAt : now(),
        };
        window.__employmentContractModalPerformance = activeMetric;
        point('T0_listAction');
    };
    const finish = () => {
        point('T8_interactive');
        window.requestAnimationFrame(() => window.requestAnimationFrame(() => point('stable')));
    };
    return { begin, point, finish };
}
