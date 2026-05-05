<?php

namespace App\Controllers\Public;

use App\Controllers\BaseController;
use App\Models\AssetModel;

class AssetBrowseController extends BaseController
{
    public function index()
    {
        $assetModel = new AssetModel();

        $search = $this->request->getGet('q');

        $builder = $assetModel
            ->select('assets.*, laboratories.name AS lab_name, laboratories.room AS lab_room')
            ->join('laboratories', 'laboratories.id = assets.lab_id', 'left');

        if (! empty($search)) {
            $builder->groupStart()
                ->like('assets.name', $search)
                ->orLike('assets.model', $search)
                ->orLike('assets.category', $search)
                ->orLike('laboratories.name', $search)
                ->groupEnd();
        }

        $assets = $builder
            ->orderBy('laboratories.name', 'ASC')
            ->orderBy('assets.name', 'ASC')
            ->findAll();

        return view('public/assets/index', [
            'assets' => $assets,
            'search' => $search,
        ]);
    }
}
