<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['procurement_request_id', 'uploaded_by_user_id', 'document_key', 'original_name', 'path', 'size'])]
#[Hidden(['path'])]
class ProcurementAttachment extends Model
{
    protected function casts(): array
    {
        return ['size' => 'integer'];
    }
}
