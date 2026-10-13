import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Children,
    Fragment,
    isValidElement,
    useRef,
    type ChangeEvent,
    type ChangeEventHandler,
    type ReactElement,
    type ReactNode,
    type SelectHTMLAttributes,
} from 'react';

type NativeOptionProps = React.ComponentPropsWithoutRef<'option'>;

type FormSelectProps = Pick<
    SelectHTMLAttributes<HTMLSelectElement>,
    | 'autoComplete'
    | 'className'
    | 'defaultValue'
    | 'disabled'
    | 'form'
    | 'id'
    | 'name'
    | 'required'
    | 'value'
> & {
    children: ReactNode;
    onChange?: ChangeEventHandler<HTMLInputElement>;
    'aria-describedby'?: string;
    'aria-invalid'?: boolean | 'false' | 'true' | 'grammar' | 'spelling';
    'aria-label'?: string;
};

function collectOptions(children: ReactNode): ReactElement<NativeOptionProps>[] {
    return Children.toArray(children).flatMap((child) => {
        if (!isValidElement<NativeOptionProps>(child)) {
            return [];
        }

        if (child.type === Fragment) {
            return collectOptions(child.props.children);
        }

        return child.type === 'option' ? [child] : [];
    });
}

export function FormSelect({
    children,
    className,
    defaultValue,
    disabled,
    form,
    id,
    name,
    onChange,
    required,
    value,
    autoComplete,
    ...triggerProps
}: FormSelectProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const options = collectOptions(children);
    const selectItems = options.map((option) => ({
        label: option.props.children,
        value: String(option.props.value ?? ''),
    }));
    const optionLabels = new Map(
        selectItems.map((option) => [option.value, option.label]),
    );
    const normalizedValue = value === undefined ? undefined : String(value);
    const normalizedDefaultValue =
        defaultValue === undefined ? undefined : String(defaultValue);

    const handleValueChange = (nextValue: string | null): void => {
        const input = inputRef.current;

        if (!input || !onChange) {
            return;
        }

        input.value = nextValue ?? '';
        onChange({
            currentTarget: input,
            target: input,
        } as ChangeEvent<HTMLInputElement>);
    };

    return (
        <Select
            name={name}
            form={form}
            required={required}
            disabled={disabled}
            autoComplete={autoComplete}
            inputRef={inputRef}
            items={selectItems}
            value={normalizedValue}
            defaultValue={normalizedDefaultValue}
            onValueChange={handleValueChange}
        >
            <SelectTrigger id={id} className={className} {...triggerProps}>
                <SelectValue>
                    {(selectedValue) => {
                        if (
                            selectedValue &&
                            typeof selectedValue === 'object' &&
                            'label' in selectedValue
                        ) {
                            return selectedValue.label;
                        }

                        return optionLabels.get(String(selectedValue ?? ''));
                    }}
                </SelectValue>
            </SelectTrigger>
            <SelectContent
                align="start"
                alignItemWithTrigger={false}
                sideOffset={6}
            >
                {options.map((option, index) => (
                    <SelectItem
                        key={`${String(option.props.value ?? '')}-${index}`}
                        value={String(option.props.value ?? '')}
                        disabled={option.props.disabled}
                    >
                        {option.props.children}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
