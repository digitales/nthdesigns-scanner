/** Higher score = weaker business = warmer lead. Bands at 41 and 71. */
export function scoreBand(value) {
    if (value == null) return 'low';
    if (value >= 71) return 'high';
    if (value >= 41) return 'mid';
    return 'low';
}

export function normalizeAngle(angle) {
    if (angle === 'accessibility' || angle === 'a11y') return 'a11y';
    if (angle === 'combined' || angle === 'both') return 'both';
    return 'gbp';
}

export function gradeFromCombined(combined) {
    if (combined >= 85) return 'D';
    if (combined >= 70) return 'C';
    if (combined >= 50) return 'C+';
    if (combined >= 30) return 'B';
    return 'B+';
}

export function gradeColor(combined) {
    if (combined >= 71) return 'var(--color-sev-critical)';
    if (combined >= 41) return 'oklch(0.55 0.13 50)';
    return 'var(--color-positive)';
}
