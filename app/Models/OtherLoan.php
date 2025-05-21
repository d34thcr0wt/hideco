<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtherLoan extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_id',
        'borrower_id',
        'loan_type_id',
        'quantity',
        'loan_status',
        'loan_number',
        'loan_release_date',
        'loan_due_date',
        'balance',
        'notes',
    ];

    protected $casts = [
        'loan_release_date' => 'date',
        'loan_due_date' => 'date',
        'quantity' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    // Constants for loan status
    const STATUS_PROCESSING = 'processing';
    const STATUS_APPROVED = 'approved';
    const STATUS_DENIED = 'denied';
    const STATUS_DEFAULTED = 'defaulted';
    const STATUS_PARTIALLY_PAID = 'partially_paid';
    const STATUS_FULLY_PAID = 'fully_paid';

    // Helper method to get status options
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_DENIED => 'Denied',
            self::STATUS_DEFAULTED => 'Defaulted',
            self::STATUS_PARTIALLY_PAID => 'Partially Paid',
            self::STATUS_FULLY_PAID => 'Fully Paid',
        ];
    }

    // Relationships
    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function loanType(): BelongsTo
    {
        return $this->belongsTo(LoanType::class);
    }

    // Method to deduct inventory quantity
    public function deductInventory(): bool
    {
        if ($this->loan_status !== self::STATUS_APPROVED) {
            return false;
        }

        $inventory = $this->inventory;
        if (!$inventory || $inventory->quantity < $this->quantity) {
            return false;
        }

        // Deduct the quantity from inventory
        $inventory->quantity -= $this->quantity;
        
        // If quantity becomes 0, mark as sold
        if ($inventory->quantity <= 0) {
            $inventory->status = Inventory::STATUS_SOLD;
        }
        
        return $inventory->save();
    }

    // Method to return inventory quantity
    public function returnInventory(): bool
    {
        if ($this->loan_status !== self::STATUS_FULLY_PAID) {
            return false;
        }

        $inventory = $this->inventory;
        if (!$inventory) {
            return false;
        }

        // Return the quantity to inventory
        $inventory->quantity += $this->quantity;
        
        // If it was marked as sold, change status back to active
        if ($inventory->status === Inventory::STATUS_SOLD) {
            $inventory->status = Inventory::STATUS_ACTIVE;
        }
        
        return $inventory->save();
    }

    // Method to calculate remaining balance
    public function getRemainingBalance(): float
    {
        return $this->balance;
    }

    // Method to update balance after payment
    public function updateBalance(float $amount): bool
    {
        if ($amount <= 0 || $amount > $this->balance) {
            return false;
        }

        $this->balance -= $amount;

        // Update loan status based on balance
        if ($this->balance <= 0) {
            $this->loan_status = self::STATUS_FULLY_PAID;
            $this->returnInventory();
        } elseif ($this->balance < $this->inventory->selling_price) {
            $this->loan_status = self::STATUS_PARTIALLY_PAID;
        }

        return $this->save();
    }
} 