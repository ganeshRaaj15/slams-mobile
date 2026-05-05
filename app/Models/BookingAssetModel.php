<?php

namespace App\Models;

use CodeIgniter\Model;

class BookingAssetModel extends Model
{
    protected $table            = 'booking_assets';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;

    protected $returnType       = 'array';

    protected $allowedFields = [
        'booking_id',
        'asset_id',
        'quantity_used',
    ];

    // Get assets for a booking
    public function getForBooking($bookingId)
    {
        return $this->select('booking_assets.*, assets.name AS asset_name, assets.image AS asset_image')
                    ->join('assets', 'assets.id = booking_assets.asset_id')
                    ->where('booking_assets.booking_id', $bookingId)
                    ->findAll();
    }

    // Bulk insert for assets
    public function insertBookingAssets($bookingId, $assets)
    {
        $dataToInsert = [];

        foreach ($assets as $a) {
            $dataToInsert[] = [
                'booking_id'    => $bookingId,
                'asset_id'      => $a['asset_id'],
                'quantity_used' => $a['quantity'],
            ];
        }

        return $this->insertBatch($dataToInsert);
    }
}
