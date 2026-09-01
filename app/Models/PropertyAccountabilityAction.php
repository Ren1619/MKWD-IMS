<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['property_accountability_document_id', 'actor_user_id', 'action', 'from_status', 'to_status', 'attestation', 'remarks', 'ip_address', 'user_agent'])]
class PropertyAccountabilityAction extends Model
{
    /** @return BelongsTo<PropertyAccountabilityDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(PropertyAccountabilityDocument::class, 'property_accountability_document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
