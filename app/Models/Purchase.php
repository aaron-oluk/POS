<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = [
        'supplier_id', 'user_id', 'reference_no', 'supply_date', 'total', 'amount_paid', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'supply_date' => 'date',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function getBalanceDueAttribute(): float
    {
        return round((float) $this->total - (float) $this->amount_paid, 2);
    }

    /**
     * 'paid' once amount_paid covers the total, 'partial' once something has
     * been paid but a balance remains, otherwise 'unpaid' (bought on credit).
     */
    public function getPaymentStatusAttribute(): string
    {
        if ($this->balance_due <= 0.009) {
            return 'paid';
        }

        return (float) $this->amount_paid > 0 ? 'partial' : 'unpaid';
    }
}
