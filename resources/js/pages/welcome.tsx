import { Form, Head } from '@inertiajs/react';
import { Boxes, Droplets } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useAppPage } from '@/hooks/use-app-page';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

function WaterDrop({ className }: { className: string }) {
    return (
        <svg
            aria-hidden="true"
            className={className}
            viewBox="0 0 40 54"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                d="M20 2S2 22 2 34c0 10.5 8.1 18 18 18s18-7.5 18-18C38 22 20 2 20 2Z"
                fill="currentColor"
            />
            <ellipse
                cx="14"
                cy="28"
                rx="4"
                ry="6"
                fill="currentColor"
                className="text-white/35"
            />
        </svg>
    );
}

export default function Welcome({ status, canResetPassword }: Props) {
    const { name } = useAppPage().props;
    const appName =
        import.meta.env.VITE_APP_NAME || 'MKWD Inventory Management System';

    return (
        <>
            <Head title="Log in" />

            <div className="relative flex min-h-svh items-center justify-center overflow-hidden bg-background px-4 py-10 text-foreground sm:px-6 sm:py-14">
                <div
                    className="pointer-events-none absolute inset-0"
                    style={{
                        background:
                            'radial-gradient(circle at 50% 35%, color-mix(in oklab, var(--primary) 12%, transparent), transparent 42%)',
                    }}
                />
                <div className="pointer-events-none absolute -top-32 -left-32 size-80 rounded-full bg-primary/10 blur-3xl" />
                <div className="pointer-events-none absolute right-[-8rem] bottom-16 size-96 rounded-full bg-accent/40 blur-3xl" />

                <WaterDrop className="pointer-events-none absolute top-[13%] left-[8%] h-9 w-7 text-primary/20 motion-safe:animate-bounce" />
                <WaterDrop className="pointer-events-none absolute top-[24%] right-[9%] h-7 w-5 text-primary/20 [animation-delay:700ms] [animation-duration:4s] motion-safe:animate-bounce" />
                <WaterDrop className="pointer-events-none absolute bottom-[25%] left-[13%] hidden h-6 w-4 text-primary/15 [animation-delay:1300ms] [animation-duration:5s] motion-safe:animate-bounce sm:block" />

                <div className="pointer-events-none absolute right-[12%] bottom-[24%] hidden size-20 rounded-full border border-primary/15 [animation-duration:4s] motion-safe:animate-ping lg:block" />
                <div className="pointer-events-none absolute right-[12%] bottom-[24%] hidden size-12 rounded-full border border-primary/20 [animation-delay:800ms] [animation-duration:4s] motion-safe:animate-ping lg:block" />

                <svg
                    aria-hidden="true"
                    className="pointer-events-none absolute right-0 bottom-0 left-0 h-44 w-full text-primary/10"
                    viewBox="0 0 1440 180"
                    preserveAspectRatio="none"
                >
                    <path
                        d="M0 90c180-48 360 48 540 12s360-10 540 15 270-34 360-12v75H0Z"
                        fill="currentColor"
                    />
                    <path
                        d="M0 128c240-54 480 42 720 0s480 42 720 0v52H0Z"
                        fill="currentColor"
                        className="text-primary/10"
                    />
                </svg>

                <main className="relative z-10 flex w-full max-w-5xl flex-col items-center gap-8">
                    <header className="flex flex-col items-center gap-3 text-center motion-safe:animate-in motion-safe:duration-500 motion-safe:fade-in motion-safe:slide-in-from-top-3">
                        <div className="flex size-20 items-center justify-center rounded-full border border-border bg-card shadow-lg shadow-primary/10 sm:size-24">
                            <Droplets className="size-10 text-primary sm:size-12" />
                        </div>
                        <div>
                            <p className="text-xs font-semibold tracking-[0.24em] text-primary uppercase">
                                Metro Kidapawan Water District
                            </p>
                            <h1 className="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">
                                {name}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground sm:text-base">
                                Inventory Management System
                            </p>
                        </div>
                    </header>

                    <section className="grid w-full overflow-hidden rounded-2xl border border-border bg-card shadow-xl sm:grid-cols-[0.9fr_1.1fr]">
                        <div className="relative flex min-h-64 flex-col justify-between overflow-hidden bg-muted/60 p-7 text-foreground sm:min-h-[34rem] sm:p-10">
                            <div className="absolute -top-20 -right-20 size-64 rounded-full border border-primary/15" />
                            <div className="absolute -top-8 -right-8 size-40 rounded-full border border-primary/15" />
                            <div className="absolute -bottom-24 -left-20 size-72 rounded-full bg-primary/10" />

                            <div className="relative flex size-14 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/20">
                                <Boxes className="size-7" />
                            </div>

                            <div className="relative mt-12 sm:mt-0">
                                <p className="text-sm font-semibold tracking-[0.18em] text-primary uppercase">
                                    {appName}
                                </p>
                                <h2 className="mt-3 max-w-sm text-3xl font-bold tracking-tight sm:text-4xl">
                                    Every item accounted for.
                                </h2>
                                <p className="mt-4 max-w-md text-sm leading-6 text-muted-foreground sm:text-base">
                                    One secure workspace for supplies,
                                    equipment, custodians, and inventory
                                    activity.
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-col justify-center p-7 sm:p-10 lg:p-12">
                            <div>
                                <p className="text-sm font-semibold text-primary">
                                    Inventory workspace
                                </p>
                                <h2 className="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">
                                    Sign in to your account
                                </h2>
                                <p className="mt-3 text-sm leading-6 text-muted-foreground sm:text-base">
                                    Enter your credentials to access inventory
                                    data, asset records, and accountability
                                    tools.
                                </p>
                            </div>

                            {status && (
                                <div className="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-300">
                                    {status}
                                </div>
                            )}

                            <div className="mt-7">
                                <Form
                                    {...store.form()}
                                    resetOnSuccess={['password']}
                                    className="flex flex-col gap-5"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="email">
                                                    Email address
                                                </Label>
                                                <Input
                                                    id="email"
                                                    type="email"
                                                    name="email"
                                                    required
                                                    autoFocus
                                                    tabIndex={1}
                                                    autoComplete="email"
                                                    placeholder="e.g. maria.santos@example.com"
                                                    className="h-11 rounded-lg bg-background"
                                                />
                                                <InputError
                                                    message={errors.email}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <div className="flex items-center justify-between gap-4">
                                                    <Label htmlFor="password">
                                                        Password
                                                    </Label>
                                                    {canResetPassword && (
                                                        <TextLink
                                                            href={request()}
                                                            className="text-xs font-medium text-primary"
                                                            tabIndex={5}
                                                        >
                                                            Forgot password?
                                                        </TextLink>
                                                    )}
                                                </div>
                                                <PasswordInput
                                                    id="password"
                                                    name="password"
                                                    required
                                                    tabIndex={2}
                                                    autoComplete="current-password"
                                                    placeholder="e.g. your account password"
                                                    className="h-11 rounded-lg bg-background"
                                                />
                                                <InputError
                                                    message={errors.password}
                                                />
                                            </div>

                                            <div className="flex items-center gap-2.5">
                                                <Checkbox
                                                    id="remember"
                                                    name="remember"
                                                    tabIndex={3}
                                                    className="data-[state=checked]:border-primary data-[state=checked]:bg-primary"
                                                />
                                                <Label
                                                    htmlFor="remember"
                                                    className="cursor-pointer text-sm font-normal text-muted-foreground"
                                                >
                                                    Keep me signed in
                                                </Label>
                                            </div>

                                            <button
                                                type="submit"
                                                tabIndex={4}
                                                disabled={processing}
                                                data-test="login-button"
                                                className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 text-sm font-semibold text-primary-foreground shadow-lg shadow-primary/20 transition hover:-translate-y-0.5 hover:bg-primary/90 hover:shadow-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-60"
                                            >
                                                {processing && <Spinner />}
                                                {processing
                                                    ? 'Signing in...'
                                                    : 'Sign in'}
                                            </button>
                                        </>
                                    )}
                                </Form>
                            </div>
                        </div>
                    </section>

                    <p className="text-center text-xs text-muted-foreground">
                        Secure inventory and asset records for MKWD operations
                    </p>
                </main>
            </div>
        </>
    );
}
