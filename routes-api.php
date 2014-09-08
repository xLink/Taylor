<?php

Route::api(['version' => 'v1', 'prefix' => \Config::get('core::routes.paths.api', 'api')], function () use ($namespace) {
    $namespace .= '\Api\V1';

    Route::group(['prefix' => 'taylor'], function () use ($namespace) {

    });
});

Route::group(['prefix' => \Config::get('core::routes.paths.api', 'api')], function () use ($namespace) {
    $namespace .= '\Module';

    Route::group(['prefix' => 'taylor'], function () use ($namespace) {
        //Route::get('/', ['as' => 'darchoods.qdb.apidoc', 'uses' => $namespace.'\ApiController@getApi']);
    });
});
