<?php



namespace App\Models;



use Illuminate\Database\Eloquent\Model;



class OrderLogistic extends Model

{

    protected $table = 'order_logistics';



    public $timestamps = true;



    protected $fillable = [

        'party_code',

        'order_no',

        'lr_no',

        'invoice_no',

        'transport_name',

        'lr_date',

        'no_of_boxes',

        'payment_type',

        'lr_amount',

        'attachment',

        'wa_is_processed',

        'add_status',
        'zoho_attachment_upload'

    ];



    protected $casts = [

        'wa_is_processed' => 'boolean',

    ];



    // Optional: Convert attachment string to array

    public function getAttachmentListAttribute()

    {

        return $this->attachment ? explode(',', $this->attachment) : [];

    }



    // Optional: Relationship with address (for joins if needed)

    public function address()

    {

        return $this->hasOne(Address::class, 'acc_code', 'party_code');

    }



    // Optional: Relationship with invoice

    public function invoice()

    {

        return $this->hasOne(InvoiceOrder::class, 'invoice_no', 'invoice_no');

    }

}

