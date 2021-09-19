<?php

use Illuminate\Routing\Router;
use OxygenModule\Media\Controller\MediaController;
use OxygenModule\Media\Controller\MediaDirectoryController;

$router = app('router');

$router->prefix('/oxygen/api')
    ->middleware('api_auth')
    ->group(function(Router $router) {

        // media
        $router->prefix('media')->group(function(Router $router) {
            MediaController::registerCrudRoutes($router);
            MediaController::registerSoftDeleteRoutes($router);
            MediaController::registerVersionableRoutes($router);

            $router->post('/make-responsive', MediaController::class . '@postMakeResponsive')
                ->name("media.postMakeResponsive")
                ->middleware("oxygen.permissions:media.postMakeResponsive");

            $router->get('/{media}/preview', MediaController::class . '@getPreviewImage')
                ->name("media.getPreviewImage")
                ->middleware("oxygen.permissions:media.getPreviewImage");
        });

        // media directory
        $router->prefix('media-directory')->group(function(Router $router) {
            MediaDirectoryController::registerCrudRoutes($router);
            MediaDirectoryController::registerSoftDeleteRoutes($router);
        });

    });

$router->get('media/{slug}.{extension}', MediaController::class . '@getView')
       ->name('media.getView')
       ->where('slug', '([a-z0-9/\-]+)');
