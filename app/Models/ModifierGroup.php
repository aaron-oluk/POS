<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModifierGroup extends Model
{
    protected $fillable = [
        'name', 'multiple',
    ];

    protected function casts(): array
    {
        return [
            'multiple' => 'boolean',
        ];
    }

    public function options()
    {
        return $this->hasMany(ModifierOption::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'modifier_group_product');
    }
}
