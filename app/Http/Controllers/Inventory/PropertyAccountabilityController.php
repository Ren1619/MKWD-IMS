<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\TransitionPropertyAccountabilityRequest;
use App\Models\InventoryAsset;
use App\Models\PropertyAccountabilityDocument;
use App\Models\User;
use App\Services\InventoryAssetService;
use App\Services\PropertyAccountabilityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PropertyAccountabilityController extends Controller
{
    public function __construct(
        private PropertyAccountabilityService $accountability,
        private InventoryAssetService $assets,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $canManage = $user->canManageInventory();

        $documents = PropertyAccountabilityDocument::query()
            ->when(
                ! $canManage,
                fn ($query) => $query->where('recipient_reference_id', $user->hris_reference_id ?? 0),
            )
            ->with('actions.actor:id,name')
            ->latest('issued_at')
            ->paginate(15)
            ->withQueryString();

        $undocumentedAssets = $canManage
            ? InventoryAsset::query()
                ->with('currentCustodian:id,name,code')
                ->whereNotNull('current_custodian_reference_id')
                ->whereDoesntHave(
                    'accountabilityDocuments',
                    fn ($query) => $query->whereIn('status', ['pending_recipient', 'active']),
                )
                ->orderBy('name')
                ->get([
                    'inventory_asset_id',
                    'name',
                    'property_number',
                    'serial_number',
                    'acquisition_cost',
                    'current_custodian_reference_id',
                ])
            : collect();

        return Inertia::render('Inventory/Accountability/Index', [
            'documents' => $documents,
            'undocumentedAssets' => $undocumentedAssets,
            'canManage' => $canManage,
            'currentReferenceId' => $user->hris_reference_id,
            'capitalizationThreshold' => PropertyAccountabilityDocument::CAPITALIZATION_THRESHOLD,
        ]);
    }

    public function issue(Request $request, InventoryAsset $asset): RedirectResponse
    {
        Gate::authorize('manage-inventory');
        $request->validate(['attested' => ['accepted']]);

        $this->accountability->issueForCurrentAssignment($asset, $request->user());

        return back()->with('success', 'Accountability document issued.');
    }

    public function transition(
        TransitionPropertyAccountabilityRequest $request,
        PropertyAccountabilityDocument $document,
    ): RedirectResponse {
        $action = $request->string('action')->toString();
        $remarks = $request->string('remarks')->toString() ?: null;
        $user = $request->user();

        if ($action === 'acknowledge') {
            $this->accountability->acknowledge($document, $user, $remarks);

            return back()->with('success', 'Property custody acknowledged.');
        }

        Gate::authorize('manage-inventory');

        if ($action === 'witnessed_acknowledge') {
            $this->accountability->acknowledge($document, $user, $remarks, witnessed: true);
        } elseif ($action === 'renew') {
            $this->accountability->renew($document, $user, $remarks);
        } elseif ($action === 'return') {
            $this->assets->unassign($document->asset, $user, $remarks ?: 'Property returned to the Property Unit.');
        } elseif ($action === 'cancel') {
            $this->cancel($document, $user, $remarks);
        } else {
            throw ValidationException::withMessages([
                'action' => 'Unsupported accountability action.',
            ]);
        }

        return back()->with('success', 'Accountability workflow updated.');
    }

    public function print(Request $request, PropertyAccountabilityDocument $document): View
    {
        $user = $request->user();
        $isRecipient = $user->hris_reference_id !== null
            && $user->hris_reference_id === $document->recipient_reference_id;

        abort_unless($user->canManageInventory() || $isRecipient, 403);

        return view('inventory.property-accountability', [
            'document' => $document->load(['actions.actor', 'supersedes']),
        ]);
    }

    private function cancel(
        PropertyAccountabilityDocument $document,
        User $user,
        ?string $remarks,
    ): void {
        if (blank($remarks)) {
            throw ValidationException::withMessages([
                'remarks' => 'A cancellation reason is required.',
            ]);
        }

        $this->accountability->cancel($document, $user, $remarks);
    }
}
