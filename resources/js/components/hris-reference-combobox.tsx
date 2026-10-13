import { Combobox } from '@base-ui/react/combobox';
import { Check, ChevronsUpDown, X } from 'lucide-react';
import { useMemo } from 'react';

import { cn } from '@/lib/utils';
import type { HrisReference } from '@/types/inventory';

type HrisReferenceComboboxProps = {
    references: HrisReference[];
    name: string;
    id: string;
    value?: string;
    defaultValue?: string;
    onValueChange?: (value: string) => void;
    placeholder?: string;
    emptyMessage?: string;
    required?: boolean;
    disabled?: boolean;
    className?: string;
    'aria-invalid'?: boolean;
};

export function formatHrisReferenceName(reference: HrisReference): string {
    if (reference.type && reference.type !== 'employee') {
        return reference.name;
    }

    const firstName = reference.first_name?.trim();
    const middleName = reference.middle_name?.trim();
    const lastName = reference.last_name?.trim();

    if (!firstName || !lastName) {
        return reference.name;
    }

    return `${lastName}, ${[firstName, middleName].filter(Boolean).join(' ')}`;
}

export function HrisReferenceCombobox({
    references,
    name,
    id,
    value,
    defaultValue,
    onValueChange,
    placeholder = 'Select employee',
    emptyMessage = 'No matching employees found.',
    required = false,
    disabled = false,
    className,
    'aria-invalid': ariaInvalid,
}: HrisReferenceComboboxProps) {
    const sortedReferences = useMemo(
        () =>
            [...references].sort((left, right) =>
                formatHrisReferenceName(left).localeCompare(
                    formatHrisReferenceName(right),
                ),
            ),
        [references],
    );
    const referenceById = useMemo(
        () =>
            new Map(
                sortedReferences.map((reference) => [
                    reference.id.toString(),
                    reference,
                ]),
            ),
        [sortedReferences],
    );
    const selectedReference =
        value === undefined ? undefined : (referenceById.get(value) ?? null);
    const defaultReference = defaultValue
        ? (referenceById.get(defaultValue) ?? null)
        : null;

    return (
        <Combobox.Root
            items={sortedReferences}
            name={name}
            value={selectedReference}
            defaultValue={defaultReference}
            onValueChange={(selectedValue) =>
                onValueChange?.(selectedValue?.id.toString() ?? '')
            }
            itemToStringLabel={formatHrisReferenceName}
            itemToStringValue={(reference) => reference.id.toString()}
            isItemEqualToValue={(reference, selectedValue) =>
                reference.id === selectedValue.id
            }
            required={required}
            disabled={disabled}
            autoHighlight
            filter={(reference, query) => {
                const searchableText = [
                    formatHrisReferenceName(reference),
                    reference.name,
                    reference.code,
                    reference.email,
                    reference.type,
                ]
                    .filter(Boolean)
                    .join(' ')
                    .toLocaleLowerCase();

                return searchableText.includes(
                    query.trim().toLocaleLowerCase(),
                );
            }}
        >
            <Combobox.InputGroup
                className={cn(
                    'relative flex h-9 w-full items-center rounded-md border border-input bg-background shadow-xs transition-colors focus-within:border-ring focus-within:ring-2 focus-within:ring-ring/50 dark:bg-input/30 data-disabled:cursor-not-allowed data-disabled:opacity-50',
                    ariaInvalid &&
                        'border-destructive ring-2 ring-destructive/20 dark:border-destructive/50 dark:ring-destructive/40',
                    className,
                )}
            >
                <Combobox.Input
                    id={id}
                    placeholder={placeholder}
                    aria-invalid={ariaInvalid}
                    className="h-full min-w-0 flex-1 bg-transparent px-3 pr-16 text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed"
                />
                <div className="absolute inset-y-0 right-1 flex items-center gap-0.5">
                    <Combobox.Clear
                        aria-label="Clear selection"
                        className="hidden size-7 items-center justify-center rounded-sm text-muted-foreground outline-none hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring data-visible:flex"
                    >
                        <X className="size-4" />
                    </Combobox.Clear>
                    <Combobox.Trigger
                        aria-label="Show employee options"
                        className="flex size-7 items-center justify-center rounded-sm text-muted-foreground outline-none hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <ChevronsUpDown className="size-4" />
                    </Combobox.Trigger>
                </div>
            </Combobox.InputGroup>

            <Combobox.Portal>
                <Combobox.Positioner
                    sideOffset={4}
                    align="start"
                    className="isolate z-[60]"
                >
                    <Combobox.Popup className="max-h-(--available-height) w-max max-w-[min(24rem,var(--available-width))] min-w-(--anchor-width) origin-(--transform-origin) overflow-hidden rounded-lg bg-popover text-popover-foreground shadow-md ring-1 ring-foreground/10 duration-100 data-open:animate-in data-open:fade-in-0 data-open:zoom-in-95 data-closed:animate-out data-closed:fade-out-0 data-closed:zoom-out-95">
                        <Combobox.Empty>
                            <div className="px-3 py-6 text-center text-sm text-muted-foreground">
                                {emptyMessage}
                            </div>
                        </Combobox.Empty>
                        <Combobox.List className="max-h-72 overflow-y-auto p-1">
                            {(reference: HrisReference) => (
                                <Combobox.Item
                                    key={reference.id}
                                    value={reference}
                                    className="relative flex cursor-default items-center rounded-md py-2 pr-9 pl-2 text-sm outline-none select-none data-highlighted:bg-accent data-highlighted:text-accent-foreground"
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="font-medium wrap-break-word whitespace-normal">
                                            {formatHrisReferenceName(reference)}
                                        </div>
                                        {(reference.code ||
                                            (reference.type &&
                                                reference.type !==
                                                    'employee')) && (
                                            <div className="text-xs wrap-break-word whitespace-normal text-muted-foreground">
                                                {[
                                                    reference.code,
                                                    reference.type &&
                                                    reference.type !==
                                                        'employee'
                                                        ? reference.type
                                                        : null,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </div>
                                        )}
                                    </div>
                                    <Combobox.ItemIndicator className="absolute right-2 flex size-4 items-center justify-center">
                                        <Check className="size-4" />
                                    </Combobox.ItemIndicator>
                                </Combobox.Item>
                            )}
                        </Combobox.List>
                    </Combobox.Popup>
                </Combobox.Positioner>
            </Combobox.Portal>
        </Combobox.Root>
    );
}
