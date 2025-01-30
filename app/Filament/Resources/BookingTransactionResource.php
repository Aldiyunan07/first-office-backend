<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingTransactionResource\Pages;
use App\Filament\Resources\BookingTransactionResource\RelationManagers;
use App\Models\BookingTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Twilio\Rest\Client;

class BookingTransactionResource extends Resource
{
    protected static ?string $model = BookingTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('booking_trx_id')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('phone_number')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('total_amount')
                    ->required()
                    ->numeric()
                    ->prefix('IDR'),

                Forms\Components\TextInput::make('duration')
                    ->required()
                    ->numeric()
                    ->prefix('Days'),

                Forms\Components\DatePicker::make('started_at')
                    ->required(),

                Forms\Components\DatePicker::make('ended_at')
                    ->required(),

                Forms\Components\Select::make('is_paid')
                    ->options([
                        true => 'Paid',
                        false => 'Not Paid'
                    ])
                    ->required(),

                Forms\Components\Select::make('office_space_id')
                    ->relationship('officeSpace', 'name')
                    ->required()
                    ->preload()
                    ->searchable(),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('booking_trx_id')
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('officeSpace.name'),

                Tables\Columns\TextColumn::make('started_at')
                    ->date(),

                Tables\Columns\IconColumn::make('is_paid')
                    ->label('Sudah bayar?')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueIcon('heroicon-o-check-circle')

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('approve')
                    ->action(function (BookingTransaction $record) {
                        $record->is_paid = true;
                        $record->save();
                        
                        Notification::make()
                        ->title('Booking Approved')
                        ->success()
                        ->body('The booking has been successfully approved.')
                        ->send();

                        $sId = getenv("TWILIO_ACCOUNT_SID");
                        $token = getenv("TWILIO_AUTH_TOKEN");
                        $twilio = new Client($sId, $token);

                        $messageBody = "Hi {$record->name}, Pemesanan anda dengan kode {$record->booking_trx_id} sudah terbayar penuh.\n\n";
                        $messageBody .= "Silahkan datang kepada lokasi kantor {$record->officeSpace->name} untuk mulai menggunakan ruangan kerja tersebut. \n\n";
                        $messageBody .= "Jika anda memiliki pertanyaan silahkan menghubungi CS kami.";

                        $message = $twilio->messages->create(
                            "whatsapp:+6285860256937",
                            [
                                "body" => $messageBody,
                                "from" => "whatsapp:".getenv("TWILIO_PHONE_NUMBER")
                            ]
                        );
                    })
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn(BookingTransaction $record) => !$record->is_paid),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListBookingTransactions::route('/'),
            'create' => Pages\CreateBookingTransaction::route('/create'),
            'edit' => Pages\EditBookingTransaction::route('/{record}/edit'),
        ];
    }
}
