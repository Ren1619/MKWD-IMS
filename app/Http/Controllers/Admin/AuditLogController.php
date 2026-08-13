<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogIndexRequest;
use App\Models\AuditLog;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __invoke(AuditLogIndexRequest $request): Response
    {
        $filters = $request->validated();

        /** @var LengthAwarePaginator<int, AuditLog> $auditLogs */
        $auditLogs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['event'] ?? null, fn ($query, string $event) => $query->where('event', $event))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $auditLogs->through(fn (AuditLog $auditLog): array => [
            'id' => $auditLog->id,
            'event' => $auditLog->event,
            'description' => $auditLog->description,
            'subject_type' => $auditLog->auditable_type ? class_basename($auditLog->auditable_type) : null,
            'subject_id' => $auditLog->auditable_id,
            'changed_attributes' => array_values(array_unique([
                ...array_keys($auditLog->old_values ?? []),
                ...array_keys($auditLog->new_values ?? []),
            ])),
            'ip_address' => $auditLog->ip_address,
            'created_at' => $auditLog->created_at,
            'user' => $auditLog->user,
        ]);

        return Inertia::render('Admin/AuditLogs/Index', [
            'auditLogs' => $auditLogs,
            'filters' => $filters,
        ]);
    }
}
