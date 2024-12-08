<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\StoreCheckBookingRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Models\BookingTransaction;
use App\Models\Workshop;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    protected $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    public function booking(Workshop $workshop)
    {
        return view('booking.booking', compact('workshop'));
    }

    public function bookingStore(StoreBookingRequest $request, Workshop $workshop)
    {
        $validated = $request->validated();
        $validated['workshop_id'] = $workshop->id;

        try {
            // Simpan booking
            $this->bookingService->storeBooking($validated);

            // Redirect ke halaman pembayaran
            return redirect()->route('front.payment');
        } catch (\Exception $e) {
            Log::error('Error storing booking: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Unable to create booking. Please try again.']);
        }
    }

    public function payment()
    {
        // Pastikan sesi booking tersedia
        if (!$this->bookingService->isBookingSessionAvailable()) {
            return redirect()->route('front.index')->withErrors(['error' => 'Session expired or unavailable.']);
        }

        try {
            // Ambil detail booking
            $data = $this->bookingService->getBookingDetails();

            // Validasi data yang didapat
            if (!$data || !isset($data['orderData'], $data['workshop'])) {
                return redirect()->route('front.index')->withErrors(['error' => 'Booking details not found.']);
            }

            return view('booking.payment', $data);
        } catch (\Exception $e) {
            Log::error('Error retrieving booking details: ' . $e->getMessage());
            return redirect()->route('front.index')->withErrors(['error' => 'Unable to retrieve booking details.']);
        }
    }

    public function paymentStore(StorePaymentRequest $request)
    {
        $validated = $request->validated();

        try {
            // Simpan data pembayaran dan booking
            $bookingTransactionId = $this->bookingService->finalizeBookingAndPayment($validated);

            // Redirect ke halaman booking selesai
            return redirect()->route('front.booking_finished', $bookingTransactionId);
        } catch (\Exception $e) {
            Log::error('Payment storage failed: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Unable to store payment details. Please try again.']);
        }
    }

    public function bookingFinished(BookingTransaction $bookingTransaction)
    {
        return view('booking.booking_finished', compact('bookingTransaction'));
    }

    public function checkBooking()
    {
        return view('booking.my_booking');
    }

    public function checkBookingDetails(StoreCheckBookingRequest $request)
    {
        $validated = $request->validated();

        $myBookingDetails = $this->bookingService->getMyBookingDetails($validated);

        if ($myBookingDetails) {
            return view('booking.my_booking_details', compact('myBookingDetails'));
        }

        return redirect()->route('front.check_booking')->withErrors(['error' => 'Transaction not found']);
    }
}
