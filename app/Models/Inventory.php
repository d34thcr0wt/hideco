<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_type',
        'item_name',
        'borrower_name',
        'borrow_date',
        'quantity',
        'grams',
        'amount_per_gram',
        'original_price',
        'selling_price',
        'downpayment',
        'status',
        'notes',
    ];

    protected $casts = [
        'borrow_date' => 'date',
        'quantity' => 'decimal:2',
        'grams' => 'decimal:2',
        'amount_per_gram' => 'decimal:2',
        'original_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'downpayment' => 'decimal:2',
    ];

    // Constants for product types
    const TYPE_GOLD = 'gold';
    const TYPE_JAPAN = 'japan';
    const TYPE_LABUBU = 'labubu';

    // Constants for status
    const STATUS_ACTIVE = 'active';
    const STATUS_SOLD = 'sold';
    const STATUS_RETURNED = 'returned';

    // Helper method to get product type options
    public static function getProductTypeOptions(): array
    {
        return [
            self::TYPE_GOLD => 'Gold Products',
            self::TYPE_JAPAN => 'Japan Products',
            self::TYPE_LABUBU => 'Labubu',
        ];
    }

    // Helper method to get status options
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_SOLD => 'Sold',
            self::STATUS_RETURNED => 'Returned',
        ];
    }

    // Helper method to calculate total value for gold products
    public function getGoldTotalValue(): float
    {
        if ($this->product_type === self::TYPE_GOLD) {
            return $this->grams * $this->amount_per_gram;
        }
        return 0;
    }

    // Helper method to calculate remaining balance
    public function getRemainingBalance(): float
    {
        if ($this->product_type === self::TYPE_GOLD) {
            return $this->selling_price - ($this->downpayment ?? 0);
        }
        return $this->selling_price;
    }
} 