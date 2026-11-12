import formats from '../../input-formats.json';

export function validationErrorMessages(
    errors: unknown,
    field = '',
): { field: string; message: string }[] {
    if (typeof errors === 'string') {
        return errors.trim() ? [{ field, message: errors }] : [];
    }

    if (Array.isArray(errors)) {
        return errors.flatMap((error) => validationErrorMessages(error, field));
    }

    if (errors && typeof errors === 'object') {
        return Object.entries(errors).flatMap(([key, value]) =>
            validationErrorMessages(value, field ? `${field}.${key}` : key),
        );
    }

    return [];
}

export function revealInputError(element: HTMLElement, focus = false): void {
    if (!element.isConnected) {
        return;
    }

    for (
        let parent = element.parentElement;
        parent;
        parent = parent.parentElement
    ) {
        if (parent.tagName === 'DETAILS') {
            parent.setAttribute('open', '');
        }
    }

    const target =
        element.getAttribute('aria-hidden') === 'true' ||
        element.classList.contains('sr-only')
            ? (element.parentElement?.querySelector<HTMLElement>(
                  '[role="combobox"], button[aria-haspopup], button',
              ) ?? element)
            : element;

    if (focus) {
        target.focus({ preventScroll: true });
    }

    const summary = target
        .closest?.('form')
        ?.querySelector<HTMLElement>('[data-validation-summary]');
    const previousMargin = target.style?.scrollMarginTop;

    if (summary) {
        target.style.scrollMarginTop = `${summary.getBoundingClientRect().height + 16}px`;
    }

    target.scrollIntoView({
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
            ? 'auto'
            : 'smooth',
        block: 'center',
        inline: 'nearest',
    });

    if (summary) {
        target.style.scrollMarginTop = previousMargin ?? '';
    }
}

export type InputConstraints = {
    value: string;
    type?: string;
    field?: string;
    required?: boolean;
    min?: string;
    max?: string;
    minLength?: number;
    maxLength?: number;
    step?: string;
    pattern?: string;
    badInput?: boolean;
    typeMismatch?: boolean;
    patternMismatch?: boolean;
    valueMissing?: boolean;
    stepMismatch?: boolean;
};

export function matchesInputFormat(pattern: string, value: string): boolean {
    return new RegExp(`^(?:${pattern})$`, 'u').exec(value)?.[0] === value;
}

export function inputValidationError(input: InputConstraints): string | null {
    const { type = 'text', value } = input;

    if (['checkbox', 'radio'].includes(type)) {
        return input.valueMissing ? 'This field is required.' : null;
    }

    if (input.badInput) {
        return type === 'date'
            ? formats.date.message
            : type === 'month'
              ? formats.month.message
              : type === 'datetime-local'
                ? formats.datetime.message
                : 'Enter a valid number.';
    }

    if (input.valueMissing || (input.required && value.trim() === '')) {
        return 'This field is required.';
    }

    if (value === '') {
        return null;
    }

    if (input.typeMismatch) {
        return type === 'email'
            ? 'Enter a valid email address.'
            : 'Enter a valid URL.';
    }

    const length = [...value].length;

    if (input.minLength && length < input.minLength) {
        return `Enter at least ${input.minLength} characters.`;
    }

    if (
        input.maxLength !== undefined &&
        input.maxLength >= 0 &&
        length > input.maxLength
    ) {
        return `Enter no more than ${input.maxLength} characters.`;
    }

    const field = input.field
        ?.replaceAll('-', '_')
        .replaceAll('[', '.')
        .replaceAll(']', '');
    const namedFormat = (
        ['stock_number', 'uacs_object_code', 'property_number_prefix'] as const
    ).find(
        (name) =>
            field === name ||
            field?.endsWith(`.${name}`) ||
            field?.endsWith(`_${name}`),
    );
    const format =
        namedFormat ??
        (type === 'number'
            ? !input.step || input.step === '1'
                ? 'integer'
                : 'number'
            : type === 'date'
              ? 'date'
              : type === 'month'
                ? 'month'
                : type === 'datetime-local'
                  ? 'datetime'
                  : null);

    if (format && !matchesInputFormat(formats[format].pattern, value.trim())) {
        return formats[format].message;
    }

    if (
        format === 'month' &&
        (Number(value.slice(5, 7)) < 1 || Number(value.slice(5, 7)) > 12)
    ) {
        return formats.month.message;
    }

    if (format === 'date' || format === 'datetime') {
        const date = new Date(`${value.slice(0, 10)}T00:00:00Z`);

        if (
            Number.isNaN(date.getTime()) ||
            date.toISOString().slice(0, 10) !== value.slice(0, 10)
        ) {
            return formats[format].message;
        }

        if (
            format === 'datetime' &&
            (Number(value.slice(11, 13)) > 23 ||
                Number(value.slice(14, 16)) > 59)
        ) {
            return formats.datetime.message;
        }
    }

    if (input.pattern) {
        try {
            if (!matchesInputFormat(input.pattern, value)) {
                return 'Use the requested format for this field.';
            }
        } catch {
            // Browser-only pattern syntax still receives authoritative server validation.
        }
    }

    if (input.patternMismatch) {
        return 'Use the requested format for this field.';
    }

    if (type === 'number') {
        const number = Number(value);

        if (!Number.isFinite(number)) {
            return formats.number.message;
        }

        if (
            input.min !== undefined &&
            input.min !== '' &&
            number < Number(input.min)
        ) {
            return `Enter a value of at least ${input.min}.`;
        }

        if (
            input.max !== undefined &&
            input.max !== '' &&
            number > Number(input.max)
        ) {
            return `Enter a value no greater than ${input.max}.`;
        }

        if (input.stepMismatch) {
            return `Use increments of ${input.step || '1'}.`;
        }
    }

    if (['date', 'month', 'datetime-local'].includes(type)) {
        if (input.min && value < input.min) {
            return `Select a date on or after ${input.min}.`;
        }

        if (input.max && value > input.max) {
            return `Select a date on or before ${input.max}.`;
        }
    }

    return null;
}
