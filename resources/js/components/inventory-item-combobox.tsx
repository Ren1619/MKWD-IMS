import { Combobox } from '@base-ui/react/combobox';
import { Check, ChevronsUpDown, X } from 'lucide-react';
import { cn } from '@/lib/utils';

export type InventoryItemOption = {
    inventory_item_id: number;
    name: string;
    unit_of_measure: string;
};

type InventoryItemComboboxProps = {
    id: string;
    items: InventoryItemOption[];
    value: string;
    onValueChange: (value: string) => void;
    'aria-invalid'?: boolean;
};

export function InventoryItemCombobox({
    id,
    items,
    value,
    onValueChange,
    'aria-invalid': ariaInvalid,
}: InventoryItemComboboxProps) {
    return (
        <Combobox.Root
            items={items}
            value={items.find((item) => item.name === value) ?? null}
            inputValue={value}
            onInputValueChange={(text) => onValueChange(text)}
            onValueChange={(item) => {
                if (item) {
                    onValueChange(item.name);
                }
            }}
            itemToStringLabel={(item) => item.name}
            isItemEqualToValue={(item, selected) =>
                item.inventory_item_id === selected.inventory_item_id
            }
            autoHighlight
        >
            <Combobox.InputGroup
                className={cn(
                    'relative flex h-9 w-full items-center rounded-md border border-input bg-background shadow-xs transition-colors focus-within:border-ring focus-within:ring-2 focus-within:ring-ring/50 dark:bg-input/30 data-disabled:cursor-not-allowed data-disabled:opacity-50',
                    ariaInvalid &&
                        'border-destructive ring-2 ring-destructive/20 dark:border-destructive/50 dark:ring-destructive/40',
                )}
            >
                <Combobox.Input
                    id={id}
                    placeholder="Search or enter a description"
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
                        aria-label="Show item options"
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
                                No matching items. You can enter a custom
                                description.
                            </div>
                        </Combobox.Empty>
                        <Combobox.List className="max-h-72 overflow-y-auto p-1">
                            {(item: InventoryItemOption) => (
                                <Combobox.Item
                                    key={item.inventory_item_id}
                                    value={item}
                                    className="relative flex cursor-default items-center rounded-md py-2 pr-9 pl-2 text-sm outline-none select-none data-highlighted:bg-accent data-highlighted:text-accent-foreground"
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="font-medium wrap-break-word whitespace-normal">
                                            {item.name}
                                        </div>
                                        <div className="text-xs wrap-break-word whitespace-normal text-muted-foreground">
                                            {item.unit_of_measure}
                                        </div>
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
