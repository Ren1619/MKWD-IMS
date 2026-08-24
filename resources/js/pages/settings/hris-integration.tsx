import { Form, Head } from '@inertiajs/react';
import {
    CheckCircle2,
    DatabaseZap,
    Globe2,
    LockKeyhole,
    Save,
    ServerCog,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, update } from '@/routes/hris-integration';

type Props = {
    allowedHosts: string[];
    baseUrl: string;
    employeesPath: string;
    usingDatabaseOverride: boolean;
};

function buildEmployeeEndpoint(baseUrl: string, employeesPath: string) {
    const normalizedBaseUrl = baseUrl.trim().replace(/\/+$/, '');

    if (!normalizedBaseUrl) {
        return 'Not configured';
    }

    const normalizedPath = employeesPath.trim().replace(/^\/+/, '');

    return normalizedPath
        ? `${normalizedBaseUrl}/${normalizedPath}`
        : normalizedBaseUrl;
}

export default function HrisIntegration({
    allowedHosts,
    baseUrl,
    employeesPath,
    usingDatabaseOverride,
}: Props) {
    const [draftBaseUrl, setDraftBaseUrl] = useState(baseUrl);
    const employeeEndpoint = buildEmployeeEndpoint(draftBaseUrl, employeesPath);

    return (
        <>
            <Head title="Employee data API settings" />

            <h1 className="sr-only">Employee data API settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Employee data API"
                    description="Choose the approved HRIS endpoint used to retrieve employee reference data"
                />

                <Card className="bg-linear-to-br from-primary/8 via-card to-card">
                    <CardHeader>
                        <div className="mb-2 flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <DatabaseZap className="size-5" />
                        </div>
                        <CardTitle>Current employee source</CardTitle>
                        <CardDescription>
                            This is the complete endpoint the next HRIS sync
                            will contact.
                        </CardDescription>
                        <CardAction>
                            <Badge variant="secondary">
                                <CheckCircle2 data-icon="inline-start" />
                                {usingDatabaseOverride
                                    ? 'Saved override'
                                    : 'Environment default'}
                            </Badge>
                        </CardAction>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-start gap-3 rounded-lg border bg-background/80 p-3">
                            <Globe2 className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                            <code
                                className="min-w-0 text-xs leading-5 break-all text-foreground sm:text-sm"
                                aria-live="polite"
                            >
                                {employeeEndpoint}
                            </code>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="border-b">
                        <div className="flex items-start gap-3">
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                <ServerCog className="size-4" />
                            </div>
                            <div className="min-w-0">
                                <CardTitle>API connection</CardTitle>
                                <CardDescription>
                                    Changing this affects future employee data
                                    synchronization requests.
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action={update()}
                            options={{ preserveScroll: true }}
                            setDefaultsOnSuccess
                            disableWhileProcessing
                            className="grid gap-6 inert:pointer-events-none inert:opacity-60"
                        >
                            {({ errors, processing, recentlySuccessful }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="base_url">
                                            HRIS API base URL
                                        </Label>
                                        <Input
                                            id="base_url"
                                            name="base_url"
                                            type="url"
                                            value={draftBaseUrl}
                                            onChange={(event) =>
                                                setDraftBaseUrl(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="https://hris.example.gov.ph"
                                            autoComplete="off"
                                            maxLength={2048}
                                            aria-describedby="base-url-help"
                                            required
                                        />
                                        <InputError message={errors.base_url} />
                                        <p
                                            id="base-url-help"
                                            className="text-xs leading-5 text-muted-foreground"
                                        >
                                            Enter only the HTTPS origin. The
                                            employee path{' '}
                                            <code className="rounded bg-muted px-1 py-0.5 text-foreground">
                                                {employeesPath ||
                                                    '/api/v1/employees'}
                                            </code>{' '}
                                            is managed by the server.
                                        </p>
                                    </div>

                                    <div className="grid gap-3">
                                        <div>
                                            <p className="text-sm font-medium">
                                                Approved hosts
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                The URL must match one of these
                                                server-approved destinations.
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            {allowedHosts.length > 0 ? (
                                                allowedHosts.map((host) => (
                                                    <Badge
                                                        key={host}
                                                        variant="outline"
                                                        className="font-mono font-normal"
                                                    >
                                                        {host}
                                                    </Badge>
                                                ))
                                            ) : (
                                                <span className="text-sm text-destructive">
                                                    No hosts are approved in the
                                                    server configuration.
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    <Alert className="bg-muted/30 p-3">
                                        <ShieldCheck />
                                        <AlertTitle>
                                            Protected connection setting
                                        </AlertTitle>
                                        <AlertDescription>
                                            A recent password confirmation is
                                            required. Redirects, embedded
                                            credentials, and unapproved hosts
                                            are blocked.
                                        </AlertDescription>
                                    </Alert>

                                    <div className="flex flex-wrap items-center gap-3">
                                        <Button disabled={processing}>
                                            <Save />
                                            {processing
                                                ? 'Saving...'
                                                : 'Save API source'}
                                        </Button>
                                        {recentlySuccessful && (
                                            <span className="inline-flex items-center gap-1.5 text-sm text-emerald-700 dark:text-emerald-400">
                                                <CheckCircle2 className="size-4" />
                                                Saved securely
                                            </span>
                                        )}
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <div className="flex items-start gap-3 rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
                    <LockKeyhole className="mt-0.5 size-4 shrink-0" />
                    <p>
                        The HRIS API token stays in the server environment. It
                        is never displayed here, submitted by this form, or
                        stored with the URL.
                    </p>
                </div>
            </div>
        </>
    );
}

HrisIntegration.layout = {
    breadcrumbs: [
        {
            title: 'Employee data API',
            href: edit(),
        },
    ],
};
