<?php
namespace App\Filament\Resources\OtherLoanResource\Pages;

use Illuminate\Support\Facades\Http;
use App\Models\ThirdParty;
use Carbon\Carbon;
use App\Filament\Resources\OtherLoanResource;
use Bavix\Wallet\Models\Wallet;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use App\Notifications\LoanStatusNotification;
use App\Models\LoanType;
use App\Models\Inventory;
use Illuminate\Support\Facades\Storage;

class CreateOtherLoan extends CreateRecord
{
    protected static string $resource = OtherLoanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['loan_status'] = $data['loan_status'] ?? 'approved';
        $inventoryId = $data['inventory_id'] ?? null;
        $data['loan_type_id'] = null;

        if ($inventoryId && $inventoryItem = Inventory::find($inventoryId)) {
            $productType = $inventoryItem->product_type;

            $loanType = LoanType::where('loan_name', $productType)
                ->where('interest_cycle', 'custom')
                ->first();

            if ($loanType) {
                $data['loan_type_id'] = $loanType->id;
            } else {
                \Log::warning("No custom loan type found matching inventory product type '{$productType}' during loan creation.", ['inventory_id' => $inventoryId, 'data' => $data]);
            }
        }

        if (isset($data['loan_type_id']) && $loanType = LoanType::find($data['loan_type_id'])) {
             $data['duration_period'] = $loanType->interest_cycle;
        } else {
             Notification::make()
                 ->danger()
                 ->title('Loan Type required')
                 ->body('Could not determine loan duration period because the loan type is missing or invalid. Ensure the selected inventory item has a matching custom loan type.')
                 ->persistent()
                 ->send();
             $this->halt();
        }

        $wallet = Wallet::findOrFail($data['from_this_account']);

        $principle_amount = $data['principal_amount'] ?? 0;
        $loan_duration = $data['loan_duration'] ?? 0;
        $loan_percent = $loanType->interest_rate ?? 0;

        $interest_amount = ceil(($principle_amount * $loan_percent / 100) * $loan_duration);
        $total_repayment = ceil($principle_amount + (($principle_amount * $loan_percent / 100) * $loan_duration));
        $amortization_amount = ceil(1.0 * $total_repayment / $loan_duration);

        $data['amortization_amount'] = $amortization_amount;
        $data['repayment_amount'] = $total_repayment;
        $data['interest_amount'] = $interest_amount;
        $data['interest_rate'] = $loan_percent;

        $data['loan_number'] = IdGenerator::generate(['table' => 'loans', 'field' => 'loan_number', 'length' => 10, 'prefix' => 'LN-']);
        // $data['from_this_account'] = Wallet::findOrFail($data['from_this_account'])->name;

        $data['balance'] = (float) str_replace(',', '', $data['repayment_amount'] ?? 0);
        $data['interest_amount'] = (float) str_replace(',', '', $data['interest_amount'] ?? 0);

        $loan_cycle = $loanType->interest_cycle ?? 'default_cycle';
        $loan_duration = $data['loan_duration'] ?? 0;
        $loan_release_date = $data['loan_release_date'];
        $loan_date = Carbon::createFromFormat('Y-m-d', $loan_release_date);

        switch ($loan_cycle) {
            case 'day': $data['loan_due_date'] = $loan_date->addDays($loan_duration); break;
            case 'week': $data['loan_due_date'] = $loan_date->addWeeks($loan_duration); break;
            case 'month': $data['loan_due_date'] = $loan_date->addMonths($loan_duration); break;
            default: $data['loan_due_date'] = $loan_date->addWeeks($loan_duration); break;
        }

        $bulk_sms_config = ThirdParty::where('name', '=', 'SWIFT-SMS')->latest()->get()->first();
        $borrower = \App\Models\Borrower::findOrFail($data['borrower_id']);

        $base_uri = $bulk_sms_config->base_uri ?? '';
        $end_point = $bulk_sms_config->endpoint ?? '';

        if ($bulk_sms_config && $bulk_sms_config->is_active == 1 && isset($borrower->mobile) && !empty($base_uri) && !empty($end_point) && isset($bulk_sms_config->token) && isset($bulk_sms_config->sender_id) && isset($data['loan_status'])) {
            $url = $base_uri . $end_point;
            $message = 'Hi ' . $borrower->first_name . ', ';
            $loan_amount_msg = $data['principal_amount'] ?? 0;
            $loan_duration_msg = $data['loan_duration'] ?? 0;
            $loan_repayment_amount_msg = $data['repayment_amount'] ?? 0;
            $loan_cycle_msg = $loan_cycle;

            $loanStatus = $data['loan_status'];

            switch ($loanStatus) {
                case 'approved':
                    $message .= 'Congratulations! Your loan application of K' . $loan_amount_msg . ' has been approved successfully. The total repayment amount is K' . $loan_repayment_amount_msg . ' to be repaid in ' . $loan_duration_msg . ' ' . $loan_cycle_msg;
                    break;
                case 'processing':
                    $message .= 'Your loan application of K' . $loan_amount_msg . ' is currently under review. We will notify you once the review process is complete.';
                    break;
                case 'denied':
                    $message .= 'We regret to inform you that your loan application of K' . $loan_amount_msg . ' has been rejected.';
                    break;
                case 'defaulted':
                    $message .= 'Unfortunately, your loan is in default status. Please contact us as soon as possible to discuss the situation.';
                    break;
                default:
                    $message .= 'Your loan application of K' . $loan_amount_msg . ' is in progress. Current status: ' . $loanStatus;
                    break;
            }

            $jsonDataPayments = [
                'sender_id' => $bulk_sms_config->sender_id,
                'numbers' => $borrower->mobile,
                'message' => $message,
            ];

            $jsonDataPayments = json_encode($jsonDataPayments);

            Http::withHeaders([
                'Authorization' => 'Bearer ' . $bulk_sms_config->token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(300)
                ->withBody($jsonDataPayments, 'application/json')
                ->get($url);
        }

        if (!is_null($borrower->email) && isset($data['loan_status'])) {
            $message = 'Hi ' . $borrower->first_name . ', ';
            $loan_amount_msg = $data['principal_amount'] ?? 0;
            $loan_duration_msg = $data['loan_duration'] ?? 0;
            $loan_repayment_amount_msg = $data['repayment_amount'] ?? 0;
            $loan_interest_amount_msg = $data['interest_amount'] ?? 0;
            $loan_due_date_msg = $data['loan_due_date'] ?? 'N/A';
            $loan_number_msg = $data['loan_number'] ?? 'N/A';
            $loan_cycle_msg = $loan_cycle;

            $loanStatus = $data['loan_status'];

            switch ($loanStatus) {
                case 'approved':
                    $message .= 'Congratulations! Your loan application of K' . $loan_amount_msg . ' has been approved successfully. The total repayment amount is K' . $loan_repayment_amount_msg . ' to be repaid in ' . $loan_duration_msg . ' ' . $loan_cycle_msg;
                    break;
                case 'processing':
                    $message .= 'Your loan application of K' . $loan_amount_msg . ' is currently under review. We will notify you once the review process is complete.';
                    break;
                case 'denied':
                    $message .= 'We regret to inform you that your loan application of K' . $loan_amount_msg . ' has been rejected.';
                    break;
                case 'defaulted':
                    $message .= 'Unfortunately, your loan is in default status. Please contact us as soon as possible to discuss the situation.';
                    break;
                default:
                    $message .= 'Your loan application of K' . $loan_amount_msg . ' is in progress. Current status: ' . $loanStatus;
                    break;
            }

            $borrower->notify(new LoanStatusNotification($message));
        }

        if (isset($data['loan_status']) && $data['loan_status'] === 'approved') {
            if (isset($data['principal_amount']) && isset($data['from_this_account'])) {
                 $wallet->withdraw($data['principal_amount'], ['meta' => 'Loan amount disbursed from ' . $data['from_this_account']]);
            } else {
                 Notification::make()
                    ->danger()
                    ->title('Withdrawal Error')
                    ->body('Could not withdraw loan amount. Principal amount or source account missing.')
                    ->persistent()
                    ->send();
            }

            if (isset($data['loan_type_id']) && $loanType = LoanType::find($data['loan_type_id'])) {
                $loan_agreement_text = \App\Models\LoanAgreementForms::where('loan_type_id', '=', $data['loan_type_id'])->first();
                if (!$loan_agreement_text && ($data['activate_loan_agreement_form'] ?? false) == 1) {
                    Notification::make()
                        ->warning()
                        ->title('Invalid Agreement Form!')
                        ->body('Please create a template first if you want to compile the Loan Agreement Form')
                        ->persistent()
                        ->actions([Action::make('create')->button()->url(route('filament.admin.resources.loan-agreement-forms.create'), shouldOpenInNewTab: true)])
                        ->send();
                    $this->halt();
                } else {
                    if (isset($data['borrower_id']) && $borrower = \App\Models\Borrower::find($data['borrower_id'])) {
                         $company_name = env('APP_NAME');
                         $borrower_name = $borrower->first_name . ' ' . $borrower->last_name;
                         $template_content = $loan_agreement_text->loan_agreement_text ?? '';

                         $template_content = str_replace('[Company Name]', $company_name, $template_content);
                         $template_content = str_replace('[Borrower Name]', strtoupper($borrower_name), $template_content);
                         $template_content = str_replace('[Loan Tenure]', $data['loan_duration'] ?? 'N/A', $template_content);
                         $template_content = str_replace('[Loan Interest Percentage]', $data['interest_rate'] ?? 'N/A', $template_content);
                         $template_content = str_replace('[Loan Interest Fee]', $data['interest_amount'] ?? 'N/A', $template_content);
                         $template_content = str_replace('[Loan Amount]', $data['principal_amount'] ?? 'N/A', $template_content);
                         $template_content = str_replace('[Borrower Repayment Amount]', $data['repayment_amount'] ?? 'N/A', $template_content);
                         $template_content = str_replace('[Loan Due Date]', Carbon::parse($data['loan_due_date'] ?? now())->format('F j, Y'), $template_content);
                         $template_content = str_replace('[Borrower Email]', $borrower->email ?? 'N/A', $template_content);
                         $template_content = str_replace('[Borrower Phone]', $borrower->mobile ?? 'N/A', $template_content);
                         $template_content = str_replace('[Borrower Address]', strtoupper($borrower->address ?? 'N/A'), $template_content);
                         $template_content = str_replace('[Borrower National ID]', $borrower->identification ?? 'N/A', $template_content);
                         $template_content = str_replace('[Borrower Account Number]', $borrower->bank_account_number ?? 'N/A', $template_content);
                         $template_content = str_replace('[Borrower Bank Name]', $borrower->bank_name ?? 'N/A', $template_content);
                         $template_content = str_replace('[Loan Number]', $data['loan_number'] ?? 'N/A', $template_content);
                         $template_content = str_replace('[Loan Release Date]', Carbon::parse($data['loan_release_date'] ?? now())->format('F j, Y'), $template_content);
                         $repay_daily = round(($data['repayment_amount'] ?? 0)/($data['loan_duration'] ?? 1));
                         $template_content = str_replace('[Repay Daily]', $repay_daily, $template_content);
                         $template_content = str_replace('[Tab]', '&emsp;', $template_content);

                        $characters_to_remove = ['<br>', '&nbsp;'];
                        $template_content = str_replace($characters_to_remove, '', $template_content);

                         $phpWord = new PhpWord();
                         $section = $phpWord->addSection();
                         \PhpOffice\PhpWord\Shared\Html::addHtml($section, $template_content);

                         $fileName = Str::slug($borrower->full_name) . '-' . $data['loan_number'] . '.docx';
                         $filePath = 'public/loan_agreements/' . $fileName;

                         if (!Storage::exists('public/loan_agreements')) {
                             Storage::makeDirectory('public/loan_agreements');
                         }

                         $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
                         $objWriter->save(Storage::path($filePath));

                         $data['loan_agreement_file_path'] = Storage::url($filePath);

                    } else {
                         Notification::make()
                            ->danger()
                            ->title('Document Compilation Error')
                            ->body('Could not compile loan agreement document. Borrower or Loan Type information missing.')
                            ->persistent()
                            ->send();
                         $data['loan_agreement_file_path'] = null;
                    }
                }
            } else {
                  Notification::make()
                    ->danger()
                    ->title('Loan Type Error')
                    ->body('Could not check for loan agreement template. Loan Type information is missing or invalid.')
                    ->persistent()
                    ->send();
                 $data['loan_agreement_file_path'] = null;
            }
        }

        $quantity = $data['quantity'] ?? 0;

        if ($inventoryId && $quantity > 0) {
            $inventoryItem = Inventory::find($inventoryId);
            if ($inventoryItem) {
                if ($inventoryItem->quantity < $quantity) {
                     Notification::make()
                        ->danger()
                        ->title('Insufficient Inventory')
                        ->body('Cannot create loan. The requested quantity exceeds the available inventory.')
                        ->persistent()
                        ->send();
                     $this->halt();
                }

                $inventoryItem->quantity -= $quantity;
                $inventoryItem->item_sold += $quantity;

                if ($inventoryItem->quantity <= 0) {
                    $inventoryItem->status = 'sold';
                }

                $inventoryItem->save();
            } else {
                \Log::warning("Inventory item not found for quantity deduction.", ['inventory_id' => $inventoryId, 'data' => $data]);
                Notification::make()
                   ->warning()
                   ->title('Inventory Error')
                   ->body('Could not deduct quantity from inventory item because it was not found.')
                   ->persistent()
                   ->send();
            }
        } else if ($inventoryId && $quantity <= 0) {
             \Log::warning("Attempted to create loan with non-positive quantity for inventory item.", ['inventory_id' => $inventoryId, 'quantity' => $quantity, 'data' => $data]);
        }

        return $data;
    }
}
