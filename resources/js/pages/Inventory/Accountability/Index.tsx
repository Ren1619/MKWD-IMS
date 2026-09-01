import { Head, router } from '@inertiajs/react';
import { ClipboardSignature, FileWarning, Printer } from 'lucide-react';
import { useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    index,
    issue,
    print,
    transition,
} from '@/routes/inventory/accountability';
import type { PaginationLink } from '@/types/inventory';

type AccountabilityAction = {
    id: number;
    action: string;
    to_status: string;
    remarks: string | null;
    created_at: string;
    actor: { name: string };
};

type AccountabilityDocument = {
    id: number;
    document_no: string;
    document_type: 'PAR' | 'ICS';
    status: string;
    asset_name: string;
    property_number: string | null;
    serial_number: string | null;
    recipient_reference_id: number;
    recipient_name: string;
    recipient_code: string | null;
    acquisition_cost: string;
    issued_at: string;
    actions: AccountabilityAction[];
};

type UndocumentedAsset = {
    inventory_asset_id: number;
    name: string;
    property_number: string | null;
    serial_number: string;
    acquisition_cost: string | null;
    current_custodian: { name: string; code: string | null } | null;
};

type PaginatedDocuments = {
    data: AccountabilityDocument[];
    total: number;
    links: PaginationLink[];
};

type AccountabilityIndexProps = {
    documents: PaginatedDocuments;
    undocumentedAssets: UndocumentedAsset[];
    canManage: boolean;
    currentReferenceId: number | null;
    capitalizationThreshold: number;
};

const remarksClassName =
    'rounded-md border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50';

export default function AccountabilityIndex({
    documents,
    undocumentedAssets,
    canManage,
    currentReferenceId,
    capitalizationThreshold,
}: AccountabilityIndexProps) {
    const [remarks, setRemarks] = useState('');

    function perform(document: AccountabilityDocument, action: string) {
        const label = action.replaceAll('_', ' ');

        if (
            !window.confirm(
                `I confirm I am authorized to ${label} ${document.document_no} and attest that the record is accurate.`,
            )
        ) {
            return;
        }

        router.patch(
            transition.url(document.id),
            { action, attested: true, remarks },
            { preserveScroll: true },
        );
    }

    function issueDocument(asset: UndocumentedAsset) {
        if (
            !window.confirm(
                `Issue the accountability document for ${asset.name} and attest that the custody details are accurate?`,
            )
        ) {
            return;
        }

        router.post(
            issue.url(asset.inventory_asset_id),
            { attested: true },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Property Accountability" />

            <div className="mx-auto flex w-full max-w-[1500px] flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div>
                    <p className="text-sm font-medium text-primary">
                        PAR and ICS controls
                    </p>
                    <h1 className="text-3xl font-semibold">
                        Property accountability
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        PPE at PHP {capitalizationThreshold.toLocaleString()} or
                        more uses PAR; lower-valued durable property uses ICS.
                    </p>
                </div>

                {canManage && undocumentedAssets.length > 0 && (
                    <Card className="border-amber-500/40">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileWarning className="size-5 text-amber-600" />
                                Assigned assets missing a current document
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {undocumentedAssets.map((asset) => (
                                <div
                                    key={asset.inventory_asset_id}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {asset.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {asset.property_number ??
                                                asset.serial_number}{' '}
                                            -{' '}
                                            {asset.current_custodian?.name ??
                                                'Unknown custodian'}{' '}
                                            - PHP{' '}
                                            {Number(
                                                asset.acquisition_cost ?? 0,
                                            ).toLocaleString()}
                                        </div>
                                    </div>
                                    <Button
                                        size="sm"
                                        onClick={() => issueDocument(asset)}
                                    >
                                        Issue{' '}
                                        {Number(asset.acquisition_cost) >=
                                        capitalizationThreshold
                                            ? 'PAR'
                                            : 'ICS'}
                                    </Button>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {canManage && (
                    <label className="grid gap-2 text-sm font-medium">
                        Control remarks
                        <textarea
                            className={remarksClassName}
                            placeholder="Required for witnessed acknowledgment, renewal, return, and cancellation. Record the verification method or reason."
                            value={remarks}
                            onChange={(event) => setRemarks(event.target.value)}
                            rows={3}
                        />
                    </label>
                )}

                <div className="grid gap-4">
                    {documents.data.map((document) => {
                        const canAcknowledge =
                            document.status === 'pending_recipient' &&
                            currentReferenceId ===
                                document.recipient_reference_id;
                        const isCurrent = [
                            'active',
                            'pending_recipient',
                        ].includes(document.status);

                        return (
                            <Card key={document.id}>
                                <CardHeader>
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <CardTitle className="flex items-center gap-2">
                                            <ClipboardSignature className="size-5" />
                                            {document.document_no}
                                        </CardTitle>
                                        <div className="flex gap-2">
                                            <span className="rounded-full border px-2 py-1 text-xs font-medium">
                                                {document.document_type}
                                            </span>
                                            <span className="rounded-full border px-2 py-1 text-xs capitalize">
                                                {document.status.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </span>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <div className="grid gap-2 text-sm md:grid-cols-2">
                                        <p>
                                            <b>Property:</b>{' '}
                                            {document.asset_name}
                                            <br />
                                            <span className="text-muted-foreground">
                                                {document.property_number ??
                                                    document.serial_number}
                                            </span>
                                        </p>
                                        <p>
                                            <b>Recipient:</b>{' '}
                                            {document.recipient_name}
                                            <br />
                                            <span className="text-muted-foreground">
                                                {document.recipient_code ??
                                                    'No employee number'}
                                            </span>
                                        </p>
                                        <p>
                                            <b>Acquisition cost:</b> PHP{' '}
                                            {Number(
                                                document.acquisition_cost,
                                            ).toLocaleString()}
                                        </p>
                                        <p>
                                            <b>Issued:</b>{' '}
                                            {new Date(
                                                document.issued_at,
                                            ).toLocaleString()}
                                        </p>
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            render={
                                                <a
                                                    href={print.url(
                                                        document.id,
                                                    )}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                />
                                            }
                                        >
                                            <Printer />
                                            Print form
                                        </Button>
                                        {canAcknowledge && (
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    perform(
                                                        document,
                                                        'acknowledge',
                                                    )
                                                }
                                            >
                                                Acknowledge custody
                                            </Button>
                                        )}
                                        {document.status ===
                                            'pending_recipient' &&
                                            canManage && (
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        perform(
                                                            document,
                                                            'witnessed_acknowledge',
                                                        )
                                                    }
                                                >
                                                    Record witnessed
                                                    acknowledgment
                                                </Button>
                                            )}
                                        {canManage && isCurrent && (
                                            <>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        perform(
                                                            document,
                                                            'renew',
                                                        )
                                                    }
                                                >
                                                    Renew / supersede
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        perform(
                                                            document,
                                                            'return',
                                                        )
                                                    }
                                                >
                                                    Return property
                                                </Button>
                                            </>
                                        )}
                                        {canManage &&
                                            document.status ===
                                                'pending_recipient' && (
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() =>
                                                        perform(
                                                            document,
                                                            'cancel',
                                                        )
                                                    }
                                                >
                                                    Cancel and unassign
                                                </Button>
                                            )}
                                    </div>

                                    <details>
                                        <summary className="cursor-pointer text-sm font-medium">
                                            Digital control history (
                                            {document.actions.length})
                                        </summary>
                                        <div className="mt-2 grid gap-2">
                                            {document.actions.map((action) => (
                                                <div
                                                    key={action.id}
                                                    className="rounded-md border p-2 text-xs"
                                                >
                                                    <b>{action.actor.name}</b> -{' '}
                                                    {action.action} to{' '}
                                                    {action.to_status} -{' '}
                                                    {new Date(
                                                        action.created_at,
                                                    ).toLocaleString()}
                                                    {action.remarks && (
                                                        <p>{action.remarks}</p>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </details>
                                </CardContent>
                            </Card>
                        );
                    })}

                    {documents.data.length === 0 && (
                        <p className="py-8 text-center text-muted-foreground">
                            No accountability documents are available.
                        </p>
                    )}
                </div>

                <DataPagination links={documents.links} />
            </div>
        </>
    );
}

AccountabilityIndex.layout = {
    breadcrumbs: [
        {
            title: 'Property Accountability',
            href: index(),
        },
    ],
};
