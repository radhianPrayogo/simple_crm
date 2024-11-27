<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'company_id',
        'name',
        'phone',
        'address'
    ];

    protected $appends = [
        'position'
    ];

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function company()
    {
        return $this->hasOne(Company::class, 'id', 'company_id');
    }

    public function getPositionAttribute()
    {
        $user = User::where('id', $this->user_id)->first();
        if ($user) {
            $role = $user->getRoleNames();
            return isset($role[0]) ? ucfirst($role[0]) : '';
        }
        return '';
    }

    public function getEmailAttribute()
    {
        $user = User::where('id', $this->user_id)->first();
        if ($user) {
            return $user->email;
        }
        return '';
    }
}
