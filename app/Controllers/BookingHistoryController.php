<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\BookingModel;
use App\Models\BookingApplicantModel;
use App\Models\BookingAssetModel;

class BookingHistoryController extends BaseController
{
    public function index()
    {
        $userId = auth()->id();

        $bookingModel = new BookingModel();
        $bookings = $bookingModel
            ->where('user_id', $userId)
            ->orderBy('date', 'DESC')
            ->findAll();

        return view('bookings/history', [
            'bookings' => $bookings,
            'user'     => auth()->user()
        ]);
    }

    public function details($id)
    {
        $userId = auth()->id();

        $bookingModel = new BookingModel();
        $booking = $bookingModel->find($id);

        // security: user may only view their own bookings
        if (!$booking || $booking['user_id'] != $userId) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Access denied']);
        }

        return $this->response->setJSON([
            'booking'    => $booking,
            'applicants' => model(BookingApplicantModel::class)->where('booking_id', $id)->findAll(),
            'assets'     => model(BookingAssetModel::class)->where('booking_id', $id)->findAll()
        ]);
    }
}
