<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ['first_name', 'last_name', 'email', 'phone', 'color'];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getTotalSpentAttribute(): float
    {
        return (float) $this->orders()->where('status', 'completed')->sum('total');
    }

    public function getOrdersCountAttribute(): int
    {
        return $this->orders()->where('status', 'completed')->count();
    }

    public function getTierAttribute(): string
    {
        $total = $this->total_spent;

        if ($total >= 500) {
            return 'gold';
        }

        return $total >= 200 ? 'silver' : 'bronze';
    }

    public function getInitialsAttribute(): string
    {
        return mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1);
    }
}
