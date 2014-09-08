<?php

Route::group(['prefix' => \Config::get('core::routes.paths.admin', 'admin')], function () use ($namespace) {
    $namespace .= '\Admin';


});
