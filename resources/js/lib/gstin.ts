const CHARACTERS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
const PATTERN = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/;

export type GstinCheck =
    | { status: 'empty' }
    | { status: 'incomplete' | 'format' | 'checksum' }
    | { status: 'valid'; stateCode: string; pan: string };

export function normalizeGstin(value: string): string {
    return value.replace(/\s+/g, '').toUpperCase();
}

function checkCharacter(firstFourteen: string): string {
    let sum = 0;

    for (let index = 0; index < firstFourteen.length; index++) {
        const product =
            CHARACTERS.indexOf(firstFourteen[index]) *
            (index % 2 === 0 ? 1 : 2);
        sum += Math.floor(product / 36) + (product % 36);
    }

    return CHARACTERS[(36 - (sum % 36)) % 36];
}

/**
 * Mirrors App\Support\Gstin so the form can give feedback while typing.
 * The server remains the authority.
 */
export function checkGstin(value: string, stateCodes: string[]): GstinCheck {
    const gstin = normalizeGstin(value);

    if (gstin === '') {
        return { status: 'empty' };
    }

    if (gstin.length < 15) {
        return { status: 'incomplete' };
    }

    if (!PATTERN.test(gstin) || !stateCodes.includes(gstin.slice(0, 2))) {
        return { status: 'format' };
    }

    if (checkCharacter(gstin.slice(0, 14)) !== gstin[14]) {
        return { status: 'checksum' };
    }

    return {
        status: 'valid',
        stateCode: gstin.slice(0, 2),
        pan: gstin.slice(2, 12),
    };
}
