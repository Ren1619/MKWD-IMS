import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';
import InputError from '@/components/input-error';
import ValidationSummary from '@/components/validation-summary';
import type { ValidationSummaryEntry } from '@/components/validation-summary';
import {
    inputValidationError,
    revealInputError,
    validationErrorMessages,
} from '@/lib/input-validation';

type Control = HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement;
type FieldError = {
    control: Control;
    container: HTMLElement;
    id: string;
    message: string;
};

function fieldLabel(control?: Control, field = ''): string {
    const label =
        control?.labels?.[0] ??
        control?.parentElement?.querySelector('label') ??
        control?.parentElement?.parentElement?.querySelector('label');

    if (label) {
        const copy = label.cloneNode(true) as HTMLElement;
        copy.querySelectorAll(
            '[role="alert"], [data-validation-summary]',
        ).forEach((error) => error.remove());

        return copy.textContent?.replace(/\s+/g, ' ').trim() || 'Field';
    }

    return (
        control?.getAttribute('aria-label') ||
        field ||
        control?.name ||
        control?.id ||
        'Field'
    )
        .replaceAll('_', ' ')
        .replaceAll('.', ' / ');
}

const normalizeField = (field: string) =>
    field.replace(/\[([^\]]+)\]/g, '.$1').replaceAll('-', '_');

function isControl(target: EventTarget | null): target is Control {
    return (
        target instanceof HTMLInputElement ||
        target instanceof HTMLTextAreaElement ||
        target instanceof HTMLSelectElement
    );
}

function errorFor(control: Control): string | null {
    if (
        control.matches(':disabled') ||
        ('readOnly' in control && control.readOnly) ||
        (control instanceof HTMLInputElement &&
            (control.readOnly ||
                ['hidden', 'submit', 'button', 'reset'].includes(control.type)))
    ) {
        return null;
    }

    return inputValidationError({
        value: control.value,
        field: control.name || control.id,
        type: control.type,
        required: control.required,
        min: control.getAttribute('min') ?? undefined,
        max: control.getAttribute('max') ?? undefined,
        minLength:
            control instanceof HTMLSelectElement
                ? undefined
                : control.minLength,
        maxLength:
            control instanceof HTMLSelectElement
                ? undefined
                : control.maxLength,
        step: control.getAttribute('step') ?? undefined,
        pattern: control.getAttribute('pattern') ?? undefined,
        badInput: control.validity.badInput,
        typeMismatch: control.validity.typeMismatch,
        patternMismatch: control.validity.patternMismatch,
        valueMissing: control.validity.valueMissing,
        stepMismatch: control.validity.stepMismatch,
    });
}

export default function InputValidation({ children }: { children: ReactNode }) {
    const [errors, setErrors] = useState<FieldError[]>([]);
    const [summaries, setSummaries] = useState<
        { host: HTMLElement; entries: ValidationSummaryEntry[] }[]
    >([]);

    useEffect(() => {
        const active = new Map<Control, FieldError>();
        const summaryHosts = new Map<HTMLElement, HTMLElement>();
        const serverErrors = new Map<
            HTMLElement,
            (ValidationSummaryEntry & {
                field: string;
                initialValue?: string;
            })[]
        >();
        const originals = new Map<
            Control,
            { invalid: string | null; describedBy: string | null }
        >();
        let sequence = 0;
        let revealFrame = 0;
        let submittedForm: HTMLFormElement | null = null;
        const afterErrorsRender = (callback: () => void) => {
            cancelAnimationFrame(revealFrame);
            revealFrame = requestAnimationFrame(() => {
                revealFrame = requestAnimationFrame(callback);
            });
        };
        const revealFirst = (origin: Control, focus: boolean) => {
            afterErrorsRender(() => {
                const controls = origin.form
                    ? [...origin.form.elements].filter(isControl)
                    : [origin];
                const first = controls.find(
                    (control) => active.has(control) && control.isConnected,
                );

                if (first) {
                    revealInputError(first, focus);
                }
            });
        };
        const refresh = () => {
            const visible = [...active.values()].filter(
                (entry) => entry.control.isConnected,
            );
            setErrors(visible);
            const grouped = new Map<HTMLElement, ValidationSummaryEntry[]>();

            for (const [scope, entries] of serverErrors) {
                if (scope.isConnected) {
                    grouped.set(scope, [...entries]);
                }
            }

            for (const entry of visible) {
                const scope =
                    entry.control.form ??
                    entry.control.closest<HTMLElement>('[role="dialog"]');

                if (!scope) {
                    continue;
                }

                const entries = grouped.get(scope) ?? [];
                grouped.set(scope, [
                    ...entries.filter((item) => item.target !== entry.control),
                    {
                        id: entry.id,
                        label: fieldLabel(entry.control),
                        message: entry.message,
                        target: entry.control,
                    },
                ]);
            }

            const next = [];

            for (const [scope, entries] of grouped) {
                if (entries.length === 0) {
                    continue;
                }

                entries.sort((first, second) => {
                    if (
                        !first.target ||
                        !second.target ||
                        first.target === second.target
                    ) {
                        return 0;
                    }

                    return first.target.compareDocumentPosition(second.target) &
                        Node.DOCUMENT_POSITION_FOLLOWING
                        ? -1
                        : 1;
                });

                let host = summaryHosts.get(scope);

                if (!host) {
                    host = document.createElement('div');
                    host.id = `validation-summary-${++sequence}`;
                    host.className = 'sticky top-0 z-20 col-span-full';
                    host.setAttribute('data-validation-summary-host', '');
                    scope.prepend(host);
                    summaryHosts.set(scope, host);
                }

                next.push({ host, entries });
            }

            for (const [scope, host] of summaryHosts) {
                if (!grouped.get(scope)?.length || !scope.isConnected) {
                    host.remove();
                    summaryHosts.delete(scope);
                    serverErrors.delete(scope);
                }
            }

            setSummaries(next);
        };
        const clear = (control: Control) => {
            const original = originals.get(control);

            if (original) {
                if (original.invalid === null) {
                    control.removeAttribute('aria-invalid');
                } else {
                    control.setAttribute('aria-invalid', original.invalid);
                }

                if (original.describedBy === null) {
                    control.removeAttribute('aria-describedby');
                } else {
                    control.setAttribute(
                        'aria-describedby',
                        original.describedBy,
                    );
                }

                originals.delete(control);
            }

            active.delete(control);
        };
        const validate = (control: Control) => {
            const message = errorFor(control);

            if (!message) {
                clear(control);

                return true;
            }

            const container =
                control.getAttribute('role') === 'combobox' ||
                control.classList.contains('sr-only')
                    ? control.parentElement?.parentElement
                    : control.parentElement;

            if (!container) {
                return true;
            }

            let entry = active.get(control);

            if (!entry) {
                entry = {
                    control,
                    container,
                    id: `input-format-error-${++sequence}`,
                    message,
                };
                originals.set(control, {
                    invalid: control.getAttribute('aria-invalid'),
                    describedBy: control.getAttribute('aria-describedby'),
                });
                active.set(control, entry);
            }

            entry.message = message;
            control.setAttribute('aria-invalid', 'true');
            control.setAttribute(
                'aria-describedby',
                [originals.get(control)?.describedBy, entry.id]
                    .filter(Boolean)
                    .join(' '),
            );

            return false;
        };
        const blur = (event: FocusEvent) => {
            if (isControl(event.target)) {
                if (!validate(event.target)) {
                    revealFirst(event.target, false);
                }

                refresh();
            }
        };
        const input = (event: Event) => {
            if (!isControl(event.target)) {
                return;
            }

            const control = event.target;
            let serverChanged = false;

            for (const [scope, entries] of serverErrors) {
                const remaining = entries.filter(
                    (entry) =>
                        entry.target !== control &&
                        (!control.name ||
                            normalizeField(entry.field) !==
                                normalizeField(control.name)),
                );

                if (remaining.length !== entries.length) {
                    serverErrors.set(scope, remaining);
                    serverChanged = true;
                }
            }

            if (active.has(control) || serverChanged) {
                if (!validate(control)) {
                    revealFirst(control, false);
                }

                refresh();
            }
        };
        const invalid = (event: Event) => {
            if (isControl(event.target)) {
                event.preventDefault();

                const controls = event.target.form
                    ? [...event.target.form.elements].filter(isControl)
                    : [event.target];
                const invalidControls = controls.filter(
                    (control) => !validate(control),
                );

                if (invalidControls.length > 0) {
                    revealFirst(invalidControls[0], true);
                }

                refresh();
            }
        };
        const submit = (event: SubmitEvent) => {
            if (!(event.target instanceof HTMLFormElement)) {
                return;
            }

            const form = event.target;
            submittedForm = form;
            const controls = [...form.elements].filter(isControl);
            const invalidControls = controls.filter(
                (control) => !validate(control),
            );
            refresh();

            if (invalidControls.length > 0) {
                event.preventDefault();
                event.stopImmediatePropagation();
                revealFirst(invalidControls[0], true);
            }
        };
        const reset = () => {
            cancelAnimationFrame(revealFrame);

            for (const control of active.keys()) {
                clear(control);
            }

            serverErrors.clear();

            refresh();
        };
        const removeNavigationListener = router.on('start', reset);
        const removeErrorListener = router.on('error', (event) => {
            afterErrorsRender(() => {
                const scope = submittedForm?.isConnected
                    ? submittedForm
                    : [
                          ...document.querySelectorAll<HTMLElement>(
                              '[role="dialog"]',
                          ),
                      ]
                          .filter(
                              (dialog) => dialog.getClientRects().length > 0,
                          )
                          .at(-1);

                if (!scope) {
                    return;
                }

                const controls = [
                    ...scope.querySelectorAll('input,textarea,select'),
                ].filter(isControl);
                const alerts = [
                    ...scope.querySelectorAll<HTMLElement>('[role="alert"]'),
                ];
                serverErrors.set(
                    scope,
                    validationErrorMessages(event.detail.errors).map(
                        (error, index) => {
                            const inline = alerts.find(
                                (alert) =>
                                    alert.textContent?.trim() === error.message,
                            );
                            const target =
                                controls.find(
                                    (control) =>
                                        control.name &&
                                        (normalizeField(control.name) ===
                                            normalizeField(error.field) ||
                                            normalizeField(
                                                error.field,
                                            ).endsWith(
                                                `.${normalizeField(control.name)}`,
                                            )),
                                ) ??
                                inline?.parentElement?.querySelector<HTMLElement>(
                                    'input:not([type="hidden"]),textarea,select,[role="combobox"]',
                                ) ??
                                inline;
                            const candidate = target ?? null;
                            const fieldControl = isControl(candidate)
                                ? candidate
                                : undefined;

                            return {
                                id: `server-error-${index}`,
                                field: error.field,
                                label: fieldLabel(fieldControl, error.field),
                                message: error.message,
                                target,
                                initialValue: fieldControl?.value,
                            };
                        },
                    ),
                );
                refresh();
                afterErrorsRender(() => {
                    const firstError =
                        scope.querySelector<HTMLElement>('[role="alert"]') ??
                        summaryHosts
                            .get(scope)
                            ?.querySelector<HTMLElement>(
                                '[data-validation-summary]',
                            );

                    if (firstError) {
                        revealInputError(firstError);
                    }
                });
            });
        });
        const observer = new MutationObserver(() => {
            let removed = false;

            for (const control of active.keys()) {
                if (!control.isConnected || !errorFor(control)) {
                    clear(control);
                    removed = true;
                }
            }

            for (const [scope, entries] of serverErrors) {
                if (!scope.isConnected) {
                    serverErrors.delete(scope);
                    removed = true;
                    continue;
                }

                const remaining = entries.filter((entry) => {
                    const target = entry.target ?? null;

                    return (
                        !target ||
                        (target.isConnected &&
                            (!isControl(target) ||
                                target.value === entry.initialValue))
                    );
                });

                if (remaining.length !== entries.length) {
                    serverErrors.set(scope, remaining);
                    removed = true;
                }
            }

            if (removed) {
                refresh();
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
        document.addEventListener('focusout', blur, true);
        document.addEventListener('input', input, true);
        document.addEventListener('change', input, true);
        document.addEventListener('invalid', invalid, true);
        document.addEventListener('submit', submit, true);
        document.addEventListener('reset', reset, true);

        return () => {
            document.removeEventListener('focusout', blur, true);
            document.removeEventListener('input', input, true);
            document.removeEventListener('change', input, true);
            document.removeEventListener('invalid', invalid, true);
            document.removeEventListener('submit', submit, true);
            document.removeEventListener('reset', reset, true);
            removeNavigationListener();
            removeErrorListener();
            cancelAnimationFrame(revealFrame);
            observer.disconnect();

            for (const control of active.keys()) {
                clear(control);
            }

            for (const host of summaryHosts.values()) {
                host.remove();
            }
        };
    }, []);

    return (
        <>
            {children}
            {errors.map((error) =>
                createPortal(
                    <InputError
                        id={error.id}
                        message={error.message}
                        className="w-full basis-full"
                    />,
                    error.container,
                    error.id,
                ),
            )}
            {summaries.map(({ host, entries }) =>
                createPortal(
                    <ValidationSummary
                        entries={entries}
                        onSelect={(entry) => {
                            if (entry.target) {
                                revealInputError(entry.target, true);
                            }
                        }}
                    />,
                    host,
                    host.id,
                ),
            )}
        </>
    );
}
