<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class PackageTracking extends Model
{
    protected $table = 'company_package_tracking';

    protected $fillable = [
        'goods_id',
        'company_id',
        'note',
        'cost_added',
        'insurance_added',
        'created_by_admin_id',
    ];

    protected $casts = [
        'cost_added' => 'decimal:2',
        'insurance_added' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Interstate\TruckingCompany::class, 'company_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }
}
