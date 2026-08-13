import type { ChangeEvent } from 'react';
import { useCallback, useEffect, useRef } from 'react';

type FilterElement = HTMLInputElement | HTMLSelectElement;

type FilterSubmitHandlers = {
    submitImmediately: (event: ChangeEvent<FilterElement>) => void;
    submitAfterDelay: (event: ChangeEvent<HTMLInputElement>) => void;
};

export function useFilterSubmit(delay: number = 350): FilterSubmitHandlers {
    const timeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    const clearPendingSubmit = useCallback((): void => {
        if (timeout.current) {
            clearTimeout(timeout.current);
            timeout.current = null;
        }
    }, []);

    useEffect(() => clearPendingSubmit, [clearPendingSubmit]);

    const submitImmediately = useCallback(
        (event: ChangeEvent<FilterElement>): void => {
            clearPendingSubmit();
            event.currentTarget.form?.requestSubmit();
        },
        [clearPendingSubmit],
    );

    const submitAfterDelay = useCallback(
        (event: ChangeEvent<HTMLInputElement>): void => {
            const form = event.currentTarget.form;

            clearPendingSubmit();
            timeout.current = setTimeout(() => form?.requestSubmit(), delay);
        },
        [clearPendingSubmit, delay],
    );

    return { submitImmediately, submitAfterDelay };
}
