<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingTransactionRequest;
use App\Http\Resources\Api\BookingTransactionResource;
use App\Http\Resources\Api\ViewBookingResource;
use App\Models\BookingTransaction;
use App\Models\OfficeSpace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class BookingTransactionController extends Controller
{
    public function store(StoreBookingTransactionRequest $request)
    {
        $validatedData = $request->validated();

        $officeSpace = OfficeSpace::find($validatedData['office_space_id']);

        $validatedData['is_paid'] = false;
        $validatedData['booking_trx_id'] = BookingTransaction::generateUniqueTrxId();
        $validatedData['duration'] = $officeSpace->duration;

        $validatedData['ended_at'] = (new \DateTime($validatedData['started_at']))
            ->modify("+{$officeSpace->duration} days")
            ->format('Y-m-d');
        $bookingTransaction = BookingTransaction::create($validatedData);

        // Mengirim notifikasi sms atau whatsapp dengan twilio

        if (getenv("TWILIO_ACCOUND_SID") !== null && getenv("TWILIO_AUTH_TOKEN") !== null) {
            $sId = getenv("TWILIO_ACCOUND_SID");
            $token = getenv("TWILIO_AUTH_TOKEN");
            $twilio = new Client($sId, $token);

            $messageBody = "Hi {$bookingTransaction->name}, Terima kasih telah booking kantor di FirstOffice. \n";
            $messageBody .= "Pesanan kantor {$bookingTransaction->officeSpace->name} Anda sedang kami proses dengan Booking TRX ID : {$bookingTransaction->booking_trx_id}.\n";
            $messageBody .= "Kami akan mengonfirmasi kembali status pemesanan anda secepat mungkin.";

            $message = $twilio->messages->create(
                "whatsapp:+6285860256937",
                [
                    "body" => $messageBody,
                    "from" => "whatsapp:".getenv("TWILIO_PHONE_NUMBER")
                ]
            );
        }


        // Mengembalikan response berhasil
        $bookingTransaction->load('officeSpace');
        return new BookingTransactionResource($bookingTransaction);
    }

    public function booking_details(Request $request)
    {
        $request->validate([
            'booking_trx_id' => 'required|string|max:255',
            'phone_number' => 'required|string|max:255',
        ]);

        $booking = BookingTransaction::where('booking_trx_id', $request->booking_trx_id)
            ->where('phone_number', $request->phone_number)
            ->with(['officeSpace', 'officeSpace.city'])
            ->first();

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        return new ViewBookingResource($booking);
    }
}
