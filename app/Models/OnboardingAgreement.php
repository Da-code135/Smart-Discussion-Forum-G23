<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingAgreement extends Model
{
    protected $fillable = ['user_id', 'agreed', 'agreement_version', 'ip_address'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public $timestamps = false;
}
