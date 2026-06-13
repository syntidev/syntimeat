<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleAbono extends Model
{
    protected $fillable = [
        'sale_id','business_id','branch_id',
        'payment_method_id','amount_bs','amount_usd',
        'rate','reference','created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_bs'  => 'decimal:2',
            'amount_usd' => 'decimal:4',
            'rate'       => 'decimal:4',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
