const NEGATIVE_PATTERNS = [
    /^wrong niche/i,
    /corporate chain|corporate or franchise|franchise|parent company|chain or/i,
    /^no /i,
    /^not /i,
    /could not/i,
    /assessment error/i,
    /missing/i,
    /without/i,
    /lack of/i,
];

const POSITIVE_PATTERNS = [
    /named|owner|principal|direct email|independent|single location|family|local business|established|visible on|booking system/i,
];

export function qualificationFlagTone(flag, status) {
    if (NEGATIVE_PATTERNS.some((pattern) => pattern.test(flag))) {
        return 'negative';
    }

    if (POSITIVE_PATTERNS.some((pattern) => pattern.test(flag))) {
        return 'positive';
    }

    if (status === 'qualified') {
        return 'positive';
    }

    if (status === 'skip') {
        return 'negative';
    }

    return 'neutral';
}

export function groupQualificationFlags(flags, status) {
    const groups = {
        positive: [],
        negative: [],
        neutral: [],
    };

    for (const flag of flags) {
        groups[qualificationFlagTone(flag, status)].push(flag);
    }

    return groups;
}
