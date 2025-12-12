<?php

namespace App\Models;

use App\Models\Cart;
use App\Notifications\EmailVerificationNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable implements MustVerifyEmail {
  use Notifiable, HasApiTokens, HasRoles;

  public function sendEmailVerificationNotification() {
    $this->notify(new EmailVerificationNotification());
  }

  protected $casts = [
    'shipper_allocation' => 'array',
  ];

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
    'name', 'email', 'password', 'address', 'city', 'postal_code', 'phone', 'country', 'provider_id', 'email_verified_at', 'verification_code', 'manager_id', 'warehouse_id', 'state', 'company_name', 'gstin', 'banned', 'shipper_allocation', 'party_code', 'virtual_account_number', 'discount', 'aadhar_card', 'gst_data','unapproved', 'email_verified_at', 'credit_days', 'credit_limit', 'own_brand', 'admin_approved_own_brand','categories','last_sent_categories','last_sent_category_updated','user_title','user_type'
  ];

  /**
   * The attributes that should be hidden for arrays.
   *
   * @var array
   */
  protected $hidden = [
    'password', 'remember_token',
  ];

  public function wishlists() {
    return $this->hasMany(Wishlist::class);
  }

  public function warehouse() {
    return $this->belongsTo(Warehouse::class);
  }

  public function user_warehouse() {
    return $this->belongsTo(Warehouse::class,'warehouse_id');
  }

  public function customer() {
    return $this->hasOne(Customer::class);
  }

  public function manager() {
    return $this->hasOne(User::class, 'id', 'manager_id');
  }

  public function get_manager()
  {
      return $this->belongsTo(User::class, 'manager_id')->where('user_type', 'staff');
  }

  public function getManager()
  {
      return $this->belongsTo(User::class, 'manager_id', 'id');
  }

  public function get_user_addresses()
  {
      return $this->hasMany(Address::class, 'acc_code', 'party_code');
  }

  public function get_addresses()
  {
      return $this->hasMany(Address::class, 'user_id', 'id');
  }

  public function address_with_party_code()
  {
      return $this->hasMany(Address::class, 'acc_code', 'party_code');
  }
  
  public function address_by_party_code()
  {
      return $this->hasOne(Address::class, 'acc_code', 'party_code')->latest('id'); // Adjust 'id' if needed for ordering
  }

  public function affiliate_user() {
    return $this->hasOne(AffiliateUser::class);
  }

  public function affiliate_withdraw_request() {
    return $this->hasMany(AffiliateWithdrawRequest::class);
  }

  public function products() {
    return $this->hasMany(Product::class);
  }

  public function seller() {
    return $this->hasOne(Seller::class);
  }

  public function staff() {
    return $this->hasOne(Staff::class);
  }

  public function orders() {
    return $this->hasMany(Order::class);
  }

  public function seller_orders() {
    return $this->hasMany(Order::class, "seller_id");
  }
  public function seller_sales() {
    return $this->hasMany(OrderDetail::class, "seller_id");
  }

  public function wallets() {
    return $this->hasMany(Wallet::class)->orderBy('created_at', 'desc');
  }

  public function club_point() {
    return $this->hasOne(ClubPoint::class);
  }

  public function customer_package() {
    return $this->belongsTo(CustomerPackage::class);
  }

  public function customer_package_payments() {
    return $this->hasMany(CustomerPackagePayment::class);
  }

  public function customer_products() {
    return $this->hasMany(CustomerProduct::class);
  }

  public function seller_package_payments() {
    return $this->hasMany(SellerPackagePayment::class);
  }

  public function carts() {
    return $this->hasMany(Cart::class);
  }

  public function reviews() {
    return $this->hasMany(Review::class);
  }

  public function addresses() {
    return $this->hasMany(Address::class);
  }

  public function affiliate_log() {
    return $this->hasMany(AffiliateLog::class);
  }

  public function product_queries() {
    return $this->hasMany(ProductQuery::class, 'customer_id');
  }
  public function total_due_amounts()
  {
      return $this->hasMany(Address::class, 'user_id')
          ->select('user_id', DB::raw('SUM(due_amount) as total_due_amount'), DB::raw('SUM(overdue_amount) as total_overdue_amount'))
          ->groupBy('user_id');
  }
}
