const MACHINE_FLAG_LABELS = {
    corporate_or_franchise_confirmed: 'Corporate or franchise confirmed',
    wrong_niche_match: 'Wrong niche match',
    already_digitally_strong: 'Already digitally strong',
    insufficient_qualification_data: 'Insufficient qualification data',
    high_review_count: 'High review count',
    no_direct_contact: 'No direct contact details',
    significant_digital_gaps: 'Significant digital gaps',
    operator_override: 'Operator override',
    qualification_skip: 'Qualification skip',
};

const POSITIVE_FLAGS = new Set([
    'significant_digital_gaps',
]);

const NEGATIVE_FLAGS = new Set([
    'corporate_or_franchise_confirmed',
    'wrong_niche_match',
    'already_digitally_strong',
    'insufficient_qualification_data',
    'no_direct_contact',
    'qualification_skip',
    'operator_override',
]);

const REDUNDANT_WHEN_QUAL_FLAGS = new Set([
    'corporate_or_franchise_confirmed',
    'wrong_niche_match',
    'qualification_skip',
]);

function isMachineFlag(flag) {
    if (flag.startsWith('franchise_signal:')) {
        return true;
    }

    return Object.hasOwn(MACHINE_FLAG_LABELS, flag);
}

function hasQualificationFlags(flags) {
    return flags.some((flag) => !isMachineFlag(flag));
}

export function formatValidatorFlag(flag) {
    if (flag.startsWith('franchise_signal:')) {
        const [, pattern, field] = flag.split(':');
        const label = pattern
            ? pattern.replace(/\b\w/g, (char) => char.toUpperCase())
            : 'Unknown';
        const fieldLabel = field ? field.replace(/_/g, ' ') : 'signal';

        return `Franchise signal: ${label} (${fieldLabel})`;
    }

    return MACHINE_FLAG_LABELS[flag] ?? flag;
}

export function shouldDisplayValidatorFlag(flag, flags) {
    if (!REDUNDANT_WHEN_QUAL_FLAGS.has(flag)) {
        return true;
    }

    return !hasQualificationFlags(flags);
}

export function validatorFlagTone(flag, status) {
    if (flag.startsWith('franchise_signal:') || NEGATIVE_FLAGS.has(flag)) {
        return 'negative';
    }

    if (POSITIVE_FLAGS.has(flag)) {
        return 'positive';
    }

    if (flag === 'high_review_count') {
        return status === 'high_chance' ? 'neutral' : 'negative';
    }

    if (!isMachineFlag(flag)) {
        const lower = flag.toLowerCase();

        if (
            lower.includes('corporate')
            || lower.includes('franchise')
            || lower.includes('not a ')
            || lower.includes('not ')
            || lower.includes('no dental')
            || lower.includes('wrong')
        ) {
            return 'negative';
        }

        if (
            lower.includes('independent')
            || lower.includes('owner')
            || lower.includes('local')
            || lower.includes('family')
        ) {
            return 'positive';
        }
    }

    if (status === 'high_chance') {
        return 'positive';
    }

    if (status === 'low_chance') {
        return 'negative';
    }

    return 'neutral';
}

export function groupValidatorFlags(flags, status) {
    const visibleFlags = flags.filter((flag) => shouldDisplayValidatorFlag(flag, flags));
    const groups = {
        positive: [],
        negative: [],
        neutral: [],
    };

    for (const flag of visibleFlags) {
        groups[validatorFlagTone(flag, status)].push(flag);
    }

    return groups;
}
