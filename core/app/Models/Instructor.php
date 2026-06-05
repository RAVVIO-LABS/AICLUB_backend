<?php

namespace App\Models;

use App\Constants\Status;
use App\Traits\instructorNotify;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Instructor extends Authenticatable
{
    use HasApiTokens, instructorNotify;
     /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token','ver_code'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'ver_code_send_at' => 'datetime',
        'account'=>'object',
        'kyc_data'=>'object'
    ];

    protected $appends = ['profile_path'];


    
    public function getProfilePathAttribute(){
        return asset(getFilePath('instructorProfile'));
    }

    public function reviews(){
        return $this->hasMany(Review::class);
    }


    public function courses(){
        return $this->hasMany(Course::class);
    }

    public function liveCourseAssignments()
    {
        return $this->hasMany(CourseLiveTeacherAssignment::class, 'teacher_instructor_id');
    }

    public function oneToOneBookings()
    {
        return $this->hasMany(CourseLiveBooking::class, 'assigned_teacher_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', Status::USER_ACTIVE)->where('ev',Status::VERIFIED)->where('sv',Status::VERIFIED);
    }

    public function scopeOnlyInstructors($query)
    {
        return $query->where('role_type', 'instructor');
    }

    public function scopeOnlyTeachers($query)
    {
        return $query->where('role_type', 'teacher');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class)->orderBy('id','desc');
    }
 

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class)->where('status','!=',Status::PAYMENT_INITIATE);
    }


    public function fullname(): Attribute
    {
        return new Attribute(
            get: fn () => $this->firstname . ' ' . $this->lastname,
        );
    }

    public function mobileNumber(): Attribute
    {
        return new Attribute(
            get: fn () => $this->dial_code . $this->mobile,
        );
    }

    // SCOPES


    public function scopeBanned($query)
    {
        return $query->where('status', Status::USER_BAN);
    }

    public function scopeEmailUnverified($query)
    {
        return $query->where('ev', Status::UNVERIFIED);
    }

    public function scopeMobileUnverified($query)
    {
        return $query->where('sv', Status::UNVERIFIED);
    }

    public function scopeKycUnverified($query)
    {
        return $query->where('kv', Status::KYC_UNVERIFIED);
    }

    public function scopeKycPending($query)
    {
        return $query->where('kv', Status::KYC_PENDING);
    }

    public function scopeEmailVerified($query)
    {
        return $query->where('ev', Status::VERIFIED);
    }

    public function scopeMobileVerified($query)
    {
        return $query->where('sv', Status::VERIFIED);
    }

    public function scopeWithBalance($query)
    {
        return $query->where('balance','>', 0);
    }

    public function loginLogs()
    {
        return $this->hasMany(UserLogin::class);
    }


    public function tickets()
    {
        return $this->hasMany(SupportTicket::class);
    }
    


    

}
