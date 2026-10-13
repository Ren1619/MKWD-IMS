import { Input } from '@/components/ui/input';

export type ResponsibilityCenterOption = {
    code: string;
    name: string;
};

type ResponsibilityCenterComboboxProps = {
    id: string;
    options: ResponsibilityCenterOption[];
    value: string;
    onValueChange: (value: string) => void;
    'aria-invalid'?: boolean;
};

export function ResponsibilityCenterCombobox({
    id,
    options,
    value,
    onValueChange,
    'aria-invalid': ariaInvalid,
}: ResponsibilityCenterComboboxProps) {
    const listId = `${id}-options`;

    return (
        <>
            <Input
                id={id}
                list={listId}
                role="combobox"
                aria-autocomplete="list"
                aria-controls={listId}
                className="min-h-11"
                value={value}
                onChange={(event) => onValueChange(event.target.value)}
                placeholder="Search or enter an RC code"
                aria-invalid={ariaInvalid}
            />
            <datalist id={listId}>
                {options.map((option) => (
                    <option key={option.code} value={option.code}>
                        {option.name}
                    </option>
                ))}
            </datalist>
        </>
    );
}
