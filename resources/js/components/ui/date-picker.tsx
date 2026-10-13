import { CalendarDays, X } from 'lucide-react';
import { useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type DatePickerProps = {
    id?: string;
    name?: string;
    value?: string;
    defaultValue?: string;
    onChange?: (value: string, event?: ChangeEvent<HTMLInputElement>) => void;
    placeholder?: string;
    disabled?: boolean;
    readOnly?: boolean;
    required?: boolean;
    min?: string;
    max?: string;
    includeTime?: boolean;
    autoSubmit?: boolean;
    className?: string;
    'aria-invalid'?: boolean;
};

function formatDateValue(value: string, includeTime: boolean): string {
    const [datePart, timePart] = value.split('T');
    const [year, month, day] = datePart.split('-').map(Number);

    if (!year || !month || !day) {
        return value;
    }

    const [hour = 0, minute = 0] = (timePart ?? '').split(':').map(Number);
    const date = new Date(year, month - 1, day, hour, minute);

    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        ...(includeTime
            ? { hour: 'numeric', minute: '2-digit' as const }
            : {}),
    }).format(date);
}

export function DatePicker({
    id,
    name,
    value,
    defaultValue = '',
    onChange,
    placeholder = 'Select date',
    disabled = false,
    readOnly = false,
    required = false,
    min,
    max,
    includeTime = false,
    autoSubmit = false,
    className,
    'aria-invalid': ariaInvalid,
}: DatePickerProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [internalValue, setInternalValue] = useState(defaultValue);
    const currentValue = value ?? internalValue;

    function setDate(
        nextValue: string,
        event?: ChangeEvent<HTMLInputElement>,
    ) {
        if (value === undefined) {
            setInternalValue(nextValue);
        }

        onChange?.(nextValue, event);

        if (autoSubmit) {
            requestAnimationFrame(() => inputRef.current?.form?.requestSubmit());
        }
    }

    function openPicker() {
        const input = inputRef.current;

        if (!input || disabled || readOnly) {
            return;
        }

        try {
            input.showPicker();
        } catch {
            input.focus();
            input.click();
        }
    }

    return (
        <div className={cn('relative', className)}>
            <Button
                id={id}
                type="button"
                variant="outline"
                className={cn(
                    'w-full justify-start pr-20 text-left font-normal',
                    !currentValue && 'text-muted-foreground',
                )}
                disabled={disabled || readOnly}
                aria-invalid={ariaInvalid}
                aria-haspopup="dialog"
                onClick={openPicker}
            >
                <span className="truncate">
                    {currentValue
                        ? formatDateValue(currentValue, includeTime)
                        : placeholder}
                </span>
            </Button>
            {currentValue && !disabled && !readOnly && (
                <button
                    type="button"
                    className="absolute top-1/2 right-10 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    aria-label="Clear date"
                    onClick={() => setDate('')}
                >
                    <X className="size-4" />
                </button>
            )}
            <CalendarDays className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <input
                ref={inputRef}
                name={name}
                type={includeTime ? 'datetime-local' : 'date'}
                value={currentValue}
                min={min}
                max={max}
                required={required}
                disabled={disabled}
                readOnly={readOnly}
                tabIndex={-1}
                aria-hidden="true"
                className="pointer-events-none absolute size-px opacity-0"
                onChange={(event) => setDate(event.target.value, event)}
            />
        </div>
    );
}
