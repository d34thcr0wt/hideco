<?php

namespace App\Filament\Resources\OtherLoanRepaymentsResource\Pages;

use App\Filament\Resources\OtherLoanRepaymentsResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Inventory;
use App\Models\LoanType;
use App\Models\ThirdParty;
use App\Models\Borrower;
use App\Models\LoanAgreementForms;
use App\Notifications\LoanStatusNotification;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use Bavix\Wallet\Models\Wallet;
// Add this line temporarily:
// dd(Wallet::class);
use Haruncpi\LaravelIdGenerator\IdGenerator;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Html;
use App\Models\Loan;
use App\Models\Repayments;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class CreateOtherLoanRepayments extends CreateRecord
{
    protected static string $resource = OtherLoanRepaymentsResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        //Check if they have created the Loan settlement Form template
        $template_content = \App\Models\LoanSettlementForms::latest()->first();
        if (!$template_content) {
             Notification::make()
                ->warning()
                ->title('Invalid Settlement Form!')
                ->body('Please create a loan settlement form first')
                 ->persistent()
                ->actions([Action::make('create')->button()->url(route('filament.admin.resources.loan-settlement-forms.create'), shouldOpenInNewTab: true)])
                 ->send();
        }

        $inventory = Inventory::findOrFail($data['inventory_id']);
        $loan = Loan::where('borrower_id', $data['borrower_id'])
            ->where('inventory_id', $data['inventory_id'])
            ->oldest()
            ->firstOrFail();

        $wallet = Wallet::findOrFail($loan['from_this_account']);

        if (!$wallet) {
                 Notification::make()
                    ->danger()
                ->title('Wallet Not Found')
                ->body('The wallet associated with this loan could not be found. Please check the loan\'s "From this Account" setting.')
                    ->persistent()
                    ->send();
        }

        $loan = Loan::where('borrower_id', $data['borrower_id'])
                        ->where('inventory_id', $data['inventory_id'])
                        ->where('balance', '>', 0)
                        ->oldest()
                        ->firstOrFail();

        $principal_amount = $loan->principal_amount;
        $loan_number = $loan->loan_number;
        $old_balance = (float) $loan->balance;
        $new_balance = $old_balance - ((float) $data['payments']);

        $repayment = \App\Models\Repayments::create([
            'loan_id' => $loan->id,
            'payments' => $data['payments'],
            'balance' => $new_balance,
            'payments_method' => $data['payments_method'],
            'payment_date' => $data['payment_date'],
            'reference_number' => $data['reference_number'] ?? 'No reference ['.auth()->user()->name.']',
            'loan_number' => $loan_number,
            'principal' => $principal_amount,
        ]);

        if ($wallet) {
             $wallet->deposit($data['payments'], ['meta' => 'Loan repayment amount']);
        }

        if ($new_balance <= 0) {
            $data['loan_settlement_file_path'] = $this->settlement_form($loan);

            //update Balance in Loans Table
            $loan->update([
                'balance' => $new_balance,
                'loan_status' => 'fully_paid',
                'loan_settlement_file_path' => $data['loan_settlement_file_path'],
            ]);
        } else {
            $loan->update([
                'balance' => $new_balance,
                'loan_status' => 'partially_paid',
            ]);
        }

        return $repayment;
    }


    protected function settlement_form($loan)
    {
        $borrower = \App\Models\Borrower::findOrFail($loan->borrower_id);

        $company_name = env('APP_NAME');
        $company_address = 'Lusaka Zambia';
        $borrower_name = $borrower->first_name . ' ' . $borrower->last_name;
        $borrower_phone = $borrower->mobile ?? '';
        $loan_amount = $loan->repayment_amount;
        $settled_date = date('d, F Y');
        $current_date = date('d, F Y');

        // The original content with placeholders
        $template_content = \App\Models\LoanSettlementForms::latest()->first()->loan_settlement_text;

        // Replace placeholders with actual data
        $template_content = str_replace('{company_name}', $company_name, $template_content);
        $template_content = str_replace('{company_address}', $company_address, $template_content);
        $template_content = str_replace('{customer_name}', $borrower_name, $template_content);
        $template_content = str_replace('{customer_address}', $borrower_phone, $template_content);
        $template_content = str_replace('{loan_amount}', $loan_amount, $template_content);
        $template_content = str_replace('{settled_date}', $settled_date, $template_content);
        $template_content = str_replace('{current_date}', $current_date, $template_content);

        $characters_to_remove = ['<br>', '&nbsp;'];
        $template_content = str_replace($characters_to_remove, '', $template_content);
        // Create a new PhpWord instance
        $phpWord = new PhpWord();

        // dd($template_content);
        // Add content to the document (agenda, summary, key points, sentiments)
        $section = $phpWord->addSection();

        \PhpOffice\PhpWord\Shared\Html::addHtml($section, $template_content, false, false);

        $current_year = date('Y');
        $path = public_path('LOAN_SETTLEMENT_FORMS/' . $current_year . '/DOCX');
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        $file_name = Str::random(40) . '.docx';

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($path . '/' . $file_name);
        $path = 'LOAN_SETTLEMENT_FORMS/' . $current_year . '/DOCX' . '/' . $file_name;
        return $path;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
