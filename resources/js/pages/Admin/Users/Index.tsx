import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Plus, ShieldCheck, UserCheck, UserX } from 'lucide-react';
import { useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import InputError from '@/components/input-error';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/shared/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useFilterSubmit } from '@/hooks/use-filter-submit';
import { index, store, update } from '@/routes/admin/users';
import type { UserRole } from '@/types/auth';
import type { Paginated } from '@/types/inventory';

type Employee = {
    id: number;
    code: string | null;
    name: string;
    email: string | null;
};

type RoleOption = { value: string; label: string };

type ManagedUser = {
    id: number;
    hris_reference_id: number | null;
    name: string;
    email: string;
    role: UserRole;
    is_active: boolean;
    last_login_at: string | null;
    employee: Employee | null;
};

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring';

function CreateUserDialog({
    employees,
    roles,
}: {
    employees: Employee[];
    roles: RoleOption[];
}) {
    const [employeeId, setEmployeeId] = useState('');
    const employee = employees.find(
        (item) => item.id.toString() === employeeId,
    );

    return (
        <Dialog>
            <DialogTrigger render={<Button />}>
                <Plus /> Add account
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Create IMS account</DialogTitle>
                    <DialogDescription>
                        Grant an employee access to this inventory system.
                    </DialogDescription>
                </DialogHeader>
                <Form action={store()} resetOnSuccess className="grid gap-4">
                    {({ errors, processing }) => (
                        <>
                            <div>
                                <label
                                    htmlFor="create-employee"
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Employee link
                                </label>
                                <select
                                    id="create-employee"
                                    name="hris_reference_id"
                                    className={selectClass}
                                    value={employeeId}
                                    onChange={(event) =>
                                        setEmployeeId(event.target.value)
                                    }
                                >
                                    <option value="">
                                        No employee link (system account)
                                    </option>
                                    {employees.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.name}
                                            {item.code ? ' · ' + item.code : ''}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={errors.hris_reference_id}
                                />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        htmlFor="create-name"
                                        className="mb-1.5 block text-sm font-medium"
                                    >
                                        Name
                                    </label>
                                    <Input
                                        key={'name-' + employeeId}
                                        id="create-name"
                                        name="name"
                                        defaultValue={employee?.name ?? ''}
                                        required
                                        maxLength={255}
                                        placeholder="e.g. Maria Santos"
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div>
                                    <label
                                        htmlFor="create-email"
                                        className="mb-1.5 block text-sm font-medium"
                                    >
                                        Email
                                    </label>
                                    <Input
                                        key={'email-' + employeeId}
                                        id="create-email"
                                        name="email"
                                        type="email"
                                        defaultValue={employee?.email ?? ''}
                                        required
                                        maxLength={255}
                                        placeholder="e.g. maria.santos@example.com"
                                    />
                                    <InputError message={errors.email} />
                                </div>
                            </div>
                            <AccessFields roles={roles} errors={errors} />
                            <PasswordFields
                                errors={errors}
                                idPrefix="create"
                                required
                            />
                            <Button disabled={processing}>
                                {processing ? 'Creating…' : 'Create account'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function AccessFields({
    roles,
    errors,
    user,
}: {
    roles: RoleOption[];
    errors: Record<string, string>;
    user?: ManagedUser;
}) {
    const idPrefix = user ? `edit-${user.id}` : 'create';

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div>
                <label
                    htmlFor={`${idPrefix}-role`}
                    className="mb-1.5 block text-sm font-medium"
                >
                    Role
                </label>
                <select
                    id={`${idPrefix}-role`}
                    name="role"
                    className={selectClass}
                    defaultValue={user?.role ?? 'employee'}
                    required
                >
                    {roles.map((role) => (
                        <option key={role.value} value={role.value}>
                            {role.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.role} />
            </div>
            <div>
                <label
                    htmlFor={`${idPrefix}-status`}
                    className="mb-1.5 block text-sm font-medium"
                >
                    Status
                </label>
                <select
                    id={`${idPrefix}-status`}
                    name="is_active"
                    className={selectClass}
                    defaultValue={user?.is_active === false ? '0' : '1'}
                    required
                >
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
                <InputError message={errors.is_active} />
            </div>
        </div>
    );
}

function PasswordFields({
    errors,
    idPrefix,
    required = false,
}: {
    errors: Record<string, string>;
    idPrefix: string;
    required?: boolean;
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div>
                <label
                    htmlFor={`${idPrefix}-password`}
                    className="mb-1.5 block text-sm font-medium"
                >
                    {required ? 'Temporary password' : 'New password'}
                </label>
                <Input
                    id={`${idPrefix}-password`}
                    name="password"
                    type="password"
                    autoComplete="new-password"
                    placeholder={
                        required
                            ? 'e.g. a secure temporary passphrase'
                            : 'e.g. a new secure passphrase'
                    }
                    required={required}
                />
                <InputError message={errors.password} />
            </div>
            <div>
                <label
                    htmlFor={`${idPrefix}-password-confirmation`}
                    className="mb-1.5 block text-sm font-medium"
                >
                    Confirm password
                </label>
                <Input
                    id={`${idPrefix}-password-confirmation`}
                    name="password_confirmation"
                    type="password"
                    autoComplete="new-password"
                    required={required}
                    placeholder={
                        required
                            ? 'e.g. repeat the temporary passphrase'
                            : 'e.g. repeat the new passphrase'
                    }
                />
                <InputError message={errors.password_confirmation} />
            </div>
        </div>
    );
}

function EditUserDialog({
    user,
    employees,
    roles,
}: {
    user: ManagedUser;
    employees: Employee[];
    roles: RoleOption[];
}) {
    return (
        <Dialog>
            <DialogTrigger render={<Button size="sm" variant="outline" />}>
                <Pencil /> Edit
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Edit {user.name}</DialogTitle>
                    <DialogDescription>
                        Update access, employee link, or password.
                    </DialogDescription>
                </DialogHeader>
                <Form action={update(user.id)} className="grid gap-4">
                    {({ errors, processing }) => (
                        <>
                            <div>
                                <label
                                    htmlFor={`edit-employee-${user.id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Employee link
                                </label>
                                <select
                                    id={`edit-employee-${user.id}`}
                                    name="hris_reference_id"
                                    className={selectClass}
                                    defaultValue={
                                        user.hris_reference_id?.toString() ?? ''
                                    }
                                >
                                    <option value="">No employee link</option>
                                    {employees.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.name}
                                            {item.code ? ' · ' + item.code : ''}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={errors.hris_reference_id}
                                />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        htmlFor={`edit-name-${user.id}`}
                                        className="mb-1.5 block text-sm font-medium"
                                    >
                                        Name
                                    </label>
                                    <Input
                                        id={`edit-name-${user.id}`}
                                        name="name"
                                        defaultValue={user.name}
                                        required
                                        maxLength={255}
                                        placeholder="e.g. Maria Santos"
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div>
                                    <label
                                        htmlFor={`edit-email-${user.id}`}
                                        className="mb-1.5 block text-sm font-medium"
                                    >
                                        Email
                                    </label>
                                    <Input
                                        id={`edit-email-${user.id}`}
                                        name="email"
                                        type="email"
                                        defaultValue={user.email}
                                        required
                                        maxLength={255}
                                        placeholder="e.g. maria.santos@example.com"
                                    />
                                    <InputError message={errors.email} />
                                </div>
                            </div>
                            <AccessFields
                                roles={roles}
                                errors={errors}
                                user={user}
                            />
                            <PasswordFields
                                errors={errors}
                                idPrefix={`edit-${user.id}`}
                            />
                            <Button disabled={processing}>
                                {processing ? 'Saving…' : 'Save changes'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function UsersIndex({
    users,
    employees,
    roles,
    filters,
}: {
    users: Paginated<ManagedUser>;
    employees: Employee[];
    roles: RoleOption[];
    filters: { search?: string; role?: string; status?: string };
}) {
    const { submitAfterDelay, submitImmediately } = useFilterSubmit();

    return (
        <>
            <Head title="User Management" />
            <div className="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-end">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Administration
                        </p>
                        <h1 className="text-2xl font-semibold">
                            User management
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Control who can sign in and what level of access
                            they have.
                        </p>
                    </div>
                    <CreateUserDialog employees={employees} roles={roles} />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Account directory</CardTitle>
                        <CardDescription>
                            {users.total} managed IMS account
                            {users.total === 1 ? '' : 's'}.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        <Form
                            action={index()}
                            options={{ preserveState: true }}
                            className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center"
                        >
                            {({ errors }) => (
                                <>
                                    <div className="grid w-full gap-1.5 sm:w-80">
                                        <label
                                            htmlFor="account-search"
                                            className="text-sm font-medium"
                                        >
                                            Search accounts
                                        </label>
                                        <Input
                                            id="account-search"
                                            name="search"
                                            defaultValue={filters.search}
                                            placeholder="e.g. Maria Santos or maria@example.com"
                                            maxLength={100}
                                            onChange={submitAfterDelay}
                                        />
                                        <InputError message={errors.search} />
                                    </div>
                                    <div className="grid w-full gap-1.5 sm:w-44">
                                        <label
                                            htmlFor="account-role"
                                            className="text-sm font-medium"
                                        >
                                            Role
                                        </label>
                                        <select
                                            id="account-role"
                                            name="role"
                                            className={selectClass}
                                            defaultValue={filters.role ?? ''}
                                            onChange={submitImmediately}
                                        >
                                            <option value="">All roles</option>
                                            {roles.map((role) => (
                                                <option
                                                    key={role.value}
                                                    value={role.value}
                                                >
                                                    {role.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.role} />
                                    </div>
                                    <div className="grid w-full gap-1.5 sm:w-40">
                                        <label
                                            htmlFor="account-status"
                                            className="text-sm font-medium"
                                        >
                                            Status
                                        </label>
                                        <select
                                            id="account-status"
                                            name="status"
                                            className={selectClass}
                                            defaultValue={filters.status ?? ''}
                                            onChange={submitImmediately}
                                        >
                                            <option value="">
                                                All statuses
                                            </option>
                                            <option value="active">
                                                Active
                                            </option>
                                            <option value="inactive">
                                                Inactive
                                            </option>
                                        </select>
                                        <InputError message={errors.status} />
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="ghost"
                                            nativeButton={false}
                                            render={<Link href={index()} />}
                                        >
                                            Clear
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>

                        <div className="overflow-x-auto rounded-lg border border-border/70">
                            <Table className="w-full min-w-[850px] text-sm [&_tbody_tr]:transition-colors [&_tbody_tr]:hover:bg-muted/35 [&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3">
                                <TableHeader className="border-b bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    <TableRow>
                                        <TableHead className="pb-3">
                                            Account
                                        </TableHead>
                                        <TableHead className="pb-3">
                                            Employee
                                        </TableHead>
                                        <TableHead className="pb-3">
                                            Role
                                        </TableHead>
                                        <TableHead className="pb-3">
                                            Status
                                        </TableHead>
                                        <TableHead className="pb-3">
                                            Last sign-in
                                        </TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody className="divide-y">
                                    {users.data.map((user) => (
                                        <TableRow key={user.id}>
                                            <TableCell className="py-3">
                                                <div className="font-medium">
                                                    {user.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {user.email}
                                                </div>
                                            </TableCell>
                                            <TableCell className="py-3">
                                                {user.employee ? (
                                                    <>
                                                        <div>
                                                            {user.employee.name}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {user.employee
                                                                .code ??
                                                                'No employee number'}
                                                        </div>
                                                    </>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        System account
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="py-3">
                                                <Badge variant="outline">
                                                    {user.role ===
                                                    'super_admin' ? (
                                                        <ShieldCheck />
                                                    ) : null}
                                                    {roles.find(
                                                        (role) =>
                                                            role.value ===
                                                            user.role,
                                                    )?.label ?? user.role}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="py-3">
                                                <Badge
                                                    variant={
                                                        user.is_active
                                                            ? 'secondary'
                                                            : 'destructive'
                                                    }
                                                >
                                                    {user.is_active ? (
                                                        <UserCheck />
                                                    ) : (
                                                        <UserX />
                                                    )}
                                                    {user.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="py-3 text-muted-foreground">
                                                {user.last_login_at
                                                    ? new Date(
                                                          user.last_login_at,
                                                      ).toLocaleString()
                                                    : 'Never'}
                                            </TableCell>
                                            <TableCell className="py-3 text-right">
                                                <EditUserDialog
                                                    user={user}
                                                    employees={employees}
                                                    roles={roles}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {users.data.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={6}
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                No accounts match these filters.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        <DataPagination links={users.links} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        { title: 'Administration', href: index() },
        { title: 'User Management', href: index() },
    ],
};
