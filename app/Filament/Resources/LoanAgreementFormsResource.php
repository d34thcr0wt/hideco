<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoanAgreementFormsResource\Pages;
use Filament\Forms\Components\RichEditor;
use App\Filament\Resources\LoanAgreementFormsResource\RelationManagers;
use App\Models\LoanAgreementForms;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LoanAgreementFormsResource extends Resource
{
    protected static ?string $model = LoanAgreementForms::class;

    protected static ?string $navigationIcon = 'fas-file';
    protected static ?string $navigationGroup = 'Loan Agreement Forms';
    protected static ?int $navigationSort = 6;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('loan_type_id')->prefixIcon('heroicon-o-wallet')->label('Choose Loan Type')->required()->relationship('loan_type', 'loan_name')->helperText('Please make sure you have added the loan type under loans to create the loan agreement form.')->searchable()->columnSpan(2)->preload(),
            RichEditor::make('loan_agreement_text')
                ->label('Create Form')
                ->required()
                ->disableToolbarButtons(['attachFiles', 'codeBlock'])
                // ->default(
                //     '<p>[Company Name]</p><p>Lusaka Zambia</p><p>P.O BOX 1209,</p><p>Lusaka</p><p>10/09/09&nbsp;</p><p><br></p><p>Dear [Borrower Name],</p><h3><span style="text-decoration: underline;">REF:LOAN AGREEMENT FORM</span></h3><p>Dear [Borrower Name],</p><p>This agreement is made between [Company Name], referred to as the "Lender," and [Borrower Name], whose details are as follows:</p><ul><li>Name: [Borrower Name]</li><li>Email: [Borrower Email]</li><li>Phone: [Borrower Phone]</li><li>Loan Number: [Loan Number]</li><li>Loan Amount: [Loan Amount]</li><li>Loan Tenure: [Loan Tenure]</li><li>Loan Interest Percentage: [Loan Interest Percentage]</li><li>Loan Interest Fee: [Loan Interest Fee]</li></ul><p>The lender agrees to provide a loan of [Loan Amount] to the borrower under the following terms and conditions:............................</p><p>The borrower agrees to repay the loan amount in installments within the loan tenure period. By signing this agreement, the borrower acknowledges and agrees to the terms and conditions set forth herein.&nbsp;</p><p>Sincerely,</p><p>[Company Name]</p>',
                // )
                ->default('<h2><strong style="text-decoration: underline;">LOAN AGREEMENT</strong></h2><p><br></p><p>This Loan Agreement (the "Agreement") is entered into current date (the "<strong>[Loan Release Date]</strong>"), by and between MARGARITA L. HIPOLITO, with an address of 110 BAYANI ST. MARULAS B. CALOOCAN CITY (the "Lender") and <strong>[Borrower Name]</strong> with an address of <strong>[Borrower Address]</strong>, (the "Borrower"), individually referred to as "Party", and collectively the "Parties".</p><p>[Tab][Tab]<strong>WHEREAS</strong>, the Borrower desires to borrow a fixed amount of money, and</p><p>[Tab][Tab]<strong>WHEREAS</strong>, the Lender agrees to lend a fixed amount of money;</p><p>[Tab][Tab]<strong>NOW, THEREFORE</strong>, for and in consideration of the mutual understanding and covenants to be performed, the parties hereby enter into this Loan Agreement under the following terms, and conditions:</p><p><br></p><p>LOAN AMOUNT <strong>(PHP [Loan Amount])</strong> DATE <strong>[Loan Due Date]</strong>;</p><p>[Tab][Tab]I. <strong>THE PARTIES.</strong> For the above value received by [Borrower Name] with an aforementioned information name and mailing address of the above-borrower ("the borrower") agrees to pay MARGARITA L. HIPOLITO with an aforementioned information name and mailing address of the above-lender ("the lender").</p><p>[Tab][Tab]II. <strong>PAYMENT.</strong> This agreement, (the "Note"), shall be due and payable, including the principal and any accrued interest, in one of the following ways:</p><p>[Tab][Tab]Everyday beginning on [Loan Release Date], will pay the amount of [Repay Daily] every day for [Loan Tenure] days.</p><p>[Tab][Tab]III. <strong>LATE PAYMENT.</strong> Payment shall be considered late if received by the Lender 1 day after its due date. The lender will have the option to charge a late fee of 1% per day.</p><p>[Tab][Tab]IV. <strong>DEFAULT.</strong> if the Borrower defaults on its payment and fails to cure said default within a reasonable amount of time, the Lender will have the option to declare the entire remaining amount of principal and any accrued interest immediately due and payable.</p><p>[Tab][Tab]The lender has the right to make any legal action.</p><p>__________________________________________________________________________________________________________________________________________________________________&nbsp;</p><p>[Tab][Tab]V. <strong>PREPAYMENT.</strong> The borrower has the right to payback the loan in full including interest.</p><p><br></p><p><strong>ENTIRE AGREEMENT.</strong> The parties acknowledge and agree that this Agreement represents the entire agreement between the Parties, In the event that the Parties desire to change, add, or otherwise modify the terms, they shall do so in writing to be signed by both parties.</p><p>The Parties agree to the terms and conditions set forth above as demonstrated by their signatures as follows:</p><p><br></p><p><del>Borrower/Signature</del>[Tab][Tab][Tab][Tab][Tab][Tab][Tab][Tab][Tab][Tab][Tab][Tab][Tab][Tab][Tab][Tab][Tab][Tab][Tab][Tab][Tab][Tab][Tab][Tab][Tab][Tab]<del>Lender/Signature</del></p>')
                // ->default(function () {
                //     $currentDate = now()->format('Y-m-d'); // Get current system date
                //     $loan = \App\Models\Loan::latest()->first(); // Get latest loan (modify logic as needed)
                    
                //     // Check if loan exists before accessing properties
                //     $loanAmount = $loan ? number_format($loan->loan_amount, 2) : '[Loan Amount]';
                //     $borrowerName = $loan ? $loan->borrower->name : '[Borrower Name]';
        
                //     return "
                //         <p>LOAN AGREEMENT</p>
                //         <p>This Loan Agreement (the \"Agreement\") is entered into <strong>{$currentDate}</strong> (the \"Effective Date\"), by and between MARGARITA L. HIPOLITO, with an address of 110 BAYANI ST. MARULAS B. CALOOCAN CITY (the \"Lender\") and <strong>{$borrowerName}</strong> with an address of [Borrower Address], (the \"Borrower\"), individually referred to as \"Party\", and collectively the \"Parties\".</p>
                //         <p>WHEREAS, the Borrower desires to borrow a fixed amount of money, and WHEREAS, the Lender agrees to lend a fixed amount of money; NOW THEREFORE, for and in consideration of the mutual understanding and covenants to be performed, the parties hereby enter into this Loan Agreement under the following terms and conditions:</p>
                //         <p>LOAN AMOUNT: <strong>{$loanAmount}</strong></p>
                //     ";
                // })
                ->columnSpan(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([Tables\Columns\TextColumn::make('loan_type.loan_name')->label('Loan Type')->searchable()])
            ->filters([
                //
            ])
            ->actions([Tables\Actions\ViewAction::make(), Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->emptyStateActions([Tables\Actions\CreateAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
                //
            ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoanAgreementForms::route('/'),
            'create' => Pages\CreateLoanAgreementForms::route('/create'),
            'view' => Pages\ViewLoanAgreementForms::route('/{record}'),
            'edit' => Pages\EditLoanAgreementForms::route('/{record}/edit'),
        ];
    }
}
