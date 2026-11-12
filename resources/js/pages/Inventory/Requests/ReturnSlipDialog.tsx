import { useHttp } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { create } from '@/routes/inventory/returns';
import PrepareRmsDialog from '../Returns/PrepareRmsDialog';
import type { PrepareRmsProps } from '../Returns/PrepareRmsDialog';

export function ReturnSlipDialog({
    request,
    onClose,
}: {
    request: {
        id: number;
        ris_no: string;
        irf_no: string | null;
        requester_name: string;
        office_name: string | null;
    };
    onClose: () => void;
}) {
    const { get, cancel, processing } = useHttp<
        Record<string, never>,
        PrepareRmsProps
    >({});
    const [preparation, setPreparation] = useState<PrepareRmsProps>(() => ({
        sources: { data: [], links: [], from: null, to: null, total: 0 },
        items: [],
        references: [],
        filters: { supply_request_id: request.id },
        supplyRequest: {
            id: request.id,
            reference: request.irf_no ?? request.ris_no,
            requester_name: request.requester_name,
            office_name: request.office_name,
        },
    }));
    const [loaded, setLoaded] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const loadSources = useCallback(
        (search = '', page = 1) => {
            cancel();
            void get(
                create.url({
                    query: { supply_request_id: request.id, search, page },
                }),
                {
                    onSuccess: (data) => {
                        setPreparation(data);
                        setLoaded(true);
                        setError(null);
                    },
                    onHttpException: () =>
                        setError(
                            'The returnable batches could not be loaded. Please try again.',
                        ),
                    onNetworkError: () =>
                        setError(
                            'The server could not be reached. Please try again.',
                        ),
                },
            ).catch(() => undefined);
        },
        [cancel, get, request.id],
    );

    useEffect(() => {
        loadSources();

        return cancel;
    }, [cancel, loadSources]);

    return (
        <PrepareRmsDialog
            {...preparation}
            onClose={onClose}
            onBrowse={loadSources}
            sourceError={error}
            sourcesLoading={processing || (!loaded && !error)}
        />
    );
}
