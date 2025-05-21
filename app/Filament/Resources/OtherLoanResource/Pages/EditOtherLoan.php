<?php

namespace App\Filament\Resources\OtherLoanResource\Pages;

use App\Models\ThirdParty;
use Illuminate\Support\Facades\Http;
use App\Filament\Resources\OtherLoanResource;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use Bavix\Wallet\Models\Wallet;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Filament\Resources\Pages\CreateRecord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Carbon\Carbon;
use App\Notifications\LoanStatusNotification;
use App\Models\Inventory;
use App\Models\Loan;

class EditOtherLoan extends EditRecord
{
    protected static string $resource = OtherLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make(), Actions\DeleteAction::make()];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Retrieve the original loan data before the update
        $originalLoan = Loan::find($record->id);

        $originalQuantity = $originalLoan->quantity;
        $newQuantity = $data['quantity'];
        $inventoryId = $data['inventory_id'];
        $data['loan_status'] = $data['loan_status'] ?? 'approved';

        // Calculate the change in quantity
        $quantityChange = $originalQuantity - $newQuantity; // This is the amount to add back to inventory if positive, or subtract less if negative

        // Find the associated inventory item
        $inventoryItem = Inventory::find($inventoryId);

        if ($inventoryItem) {
            // Calculate the potential new inventory quantity
            $potentialNewInventoryQuantity = $inventoryItem->quantity + $quantityChange;

            // Check if the potential new quantity is negative
            if ($potentialNewInventoryQuantity < 0) {
                // Prevent the update and show an error notification
                Notification::make()
                    ->danger()
                    ->title('Inventory Error')
                    ->body('Cannot reduce inventory quantity below zero.')
                    ->send();

                // Return the original record to prevent saving the changes
                return $record;
            }

            // Update the inventory quantity
            $inventoryItem->quantity = $potentialNewInventoryQuantity;
            $inventoryItem->save();
        }

        // Add the existing loan_type_id to $data if it's not present (because the field is hidden)
        if (!isset($data['loan_type_id'])) {
            $data['loan_type_id'] = $record->loan_type_id;
        }

        // --- Wallet Adjustment Logic based on Principal Amount Change ---
        $originalPrincipalAmount = $originalLoan->principal_amount;
        $newPrincipalAmount = $data['principal_amount'] ?? 0; // Use 0 if not set, though it should be by loan type calculation

        $principalAmountChange = $newPrincipalAmount - $originalPrincipalAmount; // Positive if new amount is higher, negative if lower

        // Add logging to inspect values
        \Log::info('Wallet Adjustment Debug:', [
            'originalPrincipalAmount' => $originalPrincipalAmount,
            'newPrincipalAmount' => $newPrincipalAmount,
            'principalAmountChange' => $principalAmountChange,
            'loan_number' => $record->loan_number,
            'wallet_name_from_data' => $data['from_this_account'],
        ]);

        $wallet = Wallet::findOrFail($data['from_this_account']);

        if ($wallet) {
            if ($principalAmountChange > 0) {
                // If principal amount increased, withdraw the difference
                $wallet->withdraw($principalAmountChange, ['meta' => 'Additional loan amount disbursed due to quantity change for loan ' . $record->loan_number]);
            } elseif ($principalAmountChange < 0) {
                // If principal amount decreased, deposit the absolute value of the difference
                $wallet->deposit(abs($principalAmountChange), ['meta' => 'Loan amount returned to wallet due to quantity change for loan ' . $record->loan_number]);
            }
        } else {
            // Optional: Add a notification if the wallet is not found
            Notification::make()
                ->warning()
                ->title('Wallet Not Found')
                ->body('The specified wallet for this loan could not be found.')
                ->send();
        }
        // --- End Wallet Adjustment Logic ---

        $principle_amount = $data['principal_amount'] ?? 0;
        $loan_duration = $data['loan_duration'] ?? 0;
        $loan_percent = \App\Models\LoanType::findOrFail($data['loan_type_id'])->interest_rate ?? 0;

        $interest_amount = ceil(1.0 * ($principle_amount * $loan_percent / 100) * $loan_duration);
        $total_repayment = ceil(1.0 * $principle_amount + (($principle_amount * $loan_percent / 100) * $loan_duration));
        $amortization_amount = ceil(1.0 * $total_repayment / $loan_duration);

        $data['amortization_amount'] = $amortization_amount;
        $data['repayment_amount'] = $total_repayment;
        $data['interest_amount'] = $interest_amount;
        $data['interest_rate'] = $loan_percent;
        $data['balance'] = $total_repayment;

        if ($data['loan_status'] === 'approved') {
            // if ($wallet) {
            //     $wallet->withdraw($data['principal_amount'], ['meta' => 'Loan amount disbursed from ' . $data['from_this_account']]);
            // } else {
            //     // Optional: Add a notification if the wallet is not found for this disbursement
            //     Notification::make()
            //         ->warning()
            //         ->title('Wallet Not Found for Disbursement')
            //         ->body('The specified wallet for initial disbursement could not be found.')
            //         ->send();
            // }

            //Check if they have the Loan Agreement Form template for this type of loan
            $loan_agreement_text = \App\Models\LoanAgreementForms::where('loan_type_id', '=', $data['loan_type_id'])->first();

            $borrower = \App\Models\Borrower::findOrFail($data['borrower_id']);
            $loan_type = \App\Models\LoanType::findOrFail($data['loan_type_id']);

            $loan_cycle = \App\Models\LoanType::findOrFail($data['loan_type_id'])->interest_cycle;
            $loan_release_date = $data['loan_release_date'];
            $loan_date = Carbon::createFromFormat('Y-m-d', $loan_release_date);
    
            switch ($loan_cycle) {
                case 'day': $data['loan_due_date'] = $loan_date->addDays($loan_duration); break;
                case 'week': $data['loan_due_date'] = $loan_date->addWeeks($loan_duration); break;
                case 'month': $data['loan_due_date'] = $loan_date->addMonths($loan_duration); break;
                default: $data['loan_due_date'] = $loan_date->addWeeks($loan_duration); break;
            }

            $company_name = env('APP_NAME');
            $borrower_name = $borrower->first_name . ' ' . $borrower->last_name;
            $borrower_full = $borrower->full_name;
            $borrower_email = $borrower->email ?? '';
            $borrower_phone = $borrower->mobile ?? '';
            $borrower_address = $borrower->address;
            $borrower_national_id = $borrower->identification ?? '';
            $borrower_account_number = $borrower->bank_account_number ?? '';
            $borrower_bank_name = $borrower->bank_name ?? '';
            $loan_name = $loan_type->loan_name;
            $loan_interest_rate = $data['interest_rate'];
            $loan_amount = $data['principal_amount'];
            $loan_duration = $data['loan_duration'];
            $loan_release_date = Carbon::parse($data['loan_release_date'])->format('F j, Y');
            $loan_repayment_amount = $data['repayment_amount'];
            $loan_interest_amount = $data['interest_amount'];
            $loan_due_date = Carbon::parse($data['loan_due_date'])->format('F j, Y');
            $loan_number = $record->loan_number;
            $repay_daily = round($loan_repayment_amount/$loan_duration);

            // The original content with placeholders
            $template_content = $loan_agreement_text->loan_agreement_text;

            // Replace placeholders with actual data
            $template_content = str_replace('[Company Name]', $company_name, $template_content);
            $template_content = str_replace('[Borrower Name]', strtoupper($borrower_name), $template_content);
            $template_content = str_replace('[Loan Tenure]', $loan_duration, $template_content);
            $template_content = str_replace('[Loan Interest Percentage]', $loan_interest_rate, $template_content);
            $template_content = str_replace('[Loan Interest Fee]', $loan_interest_amount, $template_content);
            $template_content = str_replace('[Loan Amount]', $loan_amount, $template_content);
            $template_content = str_replace('[Borrower Repayment Amount]', $loan_repayment_amount, $template_content);
            $template_content = str_replace('[Loan Due Date]', $loan_due_date, $template_content);
            $template_content = str_replace('[Borrower Email]', $borrower_email, $template_content);
            $template_content = str_replace('[Borrower Phone]', $borrower_phone, $template_content);
            $template_content = str_replace('[Borrower Address]', strtoupper($borrower_address), $template_content);
            $template_content = str_replace('[Borrower National ID]', $borrower_national_id, $template_content);
            $template_content = str_replace('[Borrower Account Number]', $borrower_account_number, $template_content);
            $template_content = str_replace('[Borrower Bank Name]', $borrower_bank_name, $template_content);
            $template_content = str_replace('[Loan Number]', $loan_number, $template_content);
            $template_content = str_replace('[Loan Release Date]', $loan_release_date, $template_content);
            $template_content = str_replace('[Repay Daily]', $repay_daily, $template_content);
            $template_content = str_replace('[Tab]', '&emsp;', $template_content);

            $characters_to_remove = ['<br>', '&nbsp;'];
            $template_content = str_replace($characters_to_remove, '', $template_content);
            // Create a new PhpWord instance
            $phpWord = new PhpWord();

            // Add content to the document (agenda, summary, key points, sentiments)
            $section = $phpWord->addSection();

            \PhpOffice\PhpWord\Shared\Html::addHtml($section, $template_content, false, false);

            // Save the document as a Word file

            $current_year = date('Y');
            $path = public_path('LOAN_AGREEMENT_FORMS/' . $current_year . '/DOCX');
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }
            $file_name = strtoupper(Str::of($borrower_full)->replaceMatches('/[^A-Za-z0-9]/', '_').'_'.$loan_number.'.docx');

            $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($path . '/' . $file_name);
            $data['loan_agreement_file_path'] = 'LOAN_AGREEMENT_FORMS/' . $current_year . '/DOCX' . '/' . $file_name;
        }

        $record->update($data);

        return $record;
    }
    
    protected function getSavedNotification(): ?Notification
    {
        return null; // Disables the default "Saved" notification
    }

    protected function afterSave(): void
    {
        Notification::make()
            ->title('Loan Updated')
            ->success()
            ->body('The loan details have been updated successfully.')
            ->send();

        // Redirect after update
        $this->redirect(request()->header('Referer'));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
