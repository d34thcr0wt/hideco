<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use HasFactory;

    // Constants for loan status
    const STATUS_PROCESSING = 'processing';
    const STATUS_APPROVED = 'approved';
    const STATUS_DENIED = 'denied';
    const STATUS_DEFAULTED = 'defaulted';
    const STATUS_PARTIALLY_PAID = 'partially_paid';
    const STATUS_FULLY_PAID = 'fully_paid';
    const STATUS_ACTIVE = 'active';
    const STATUS_SOLD = 'sold'; // Added for inventory tracking

    public function loan_type()
    {
        return $this->belongsTo(LoanType::class, 'loan_type_id', 'id');
    }

    public function borrower()
    {
        return $this->belongsTo(Borrower::class, 'borrower_id', 'id');
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class, 'inventory_id', 'id');
    }

    public function getLoanDueDateAttribute($value)
    {
        return date('d,F Y', strtotime($value));
    }

    protected $casts = [
        'activate_loan_agreement_form' => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['balance', 'loan_status', 'loan_number', 'inventory_id', 'quantity'];
}
