<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TagihanItem extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'tagihan_item';

    protected $fillable = ['tagihan_id', 'jenis_biaya_id', 'nama', 'cara_hitung', 'nominal_satuan', 'jumlah', 'subtotal'];

    protected function casts(): array
    {
        return ['nominal_satuan' => 'integer', 'jumlah' => 'integer', 'subtotal' => 'integer'];
    }

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(TagihanSemester::class, 'tagihan_id');
    }
}
