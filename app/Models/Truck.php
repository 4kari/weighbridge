<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Truck extends Model
{
    use HasFactory;

    protected $fillable = [
        'plate_number',
        'driver_name',
        'tare_weight_kg',
        'max_net_weight_kg',
    ];

    protected $casts = [
        'tare_weight_kg' => 'float',
        'max_net_weight_kg' => 'float',
    ];

    public function weighbridgeTickets(): HasMany
    {
        return $this->hasMany(WeighbridgeTicket::class);
    }
}
