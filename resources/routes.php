<?php

use OxygenModule\Media\Controller\MediaController;
use OxygenModule\Media\Controller\MediaDirectoryController;

$router = app('router');
MediaController::registerCrudRoutes($router, 'media');
MediaController::registerSoftDeleteRoutes($router, 'media');
MediaController::registerVersionableRoutes($router, 'media');

$router->middleware(['web', 'oxygen.auth', '2fa.require'])->group(function() use ($router) {
    $router->post('/oxygen/api/media/make-responsive', MediaController::class . '@postMakeResponsive')
        ->name("media.postMakeResponsive")
        ->middleware("oxygen.permissions:media.postMakeResponsive");

    $router->get('/oxygen/api/media/{media}/preview', MediaController::class . '@getPreviewImage')
        ->name("media.getPreviewImage")
        ->middleware("oxygen.permissions:media.getPreviewImage");
});

MediaDirectoryController::registerCrudRoutes($router, 'mediaDirectory');
MediaDirectoryController::registerSoftDeleteRoutes($router, 'mediaDirectory');

$router->get('media/{slug}.{extension}', MediaController::class . '@getView')
       ->name('media.getView')
       ->where('slug', '([a-z0-9/\-]+)');
