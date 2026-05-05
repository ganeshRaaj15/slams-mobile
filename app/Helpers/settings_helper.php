<?php

use App\Models\SettingsModel;

if (! function_exists('setting')) {
    function setting(string $class, string $key, ?string $context = null)
    {
        $model = new SettingsModel();
        return $model->get($class, $key, $context);
    }
}
