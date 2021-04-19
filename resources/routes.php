<?php

use OxygenModule\Media\Controller\MediaController;
use OxygenModule\Media\Controller\MediaDirectoryController;

$router = app('router');
MediaController::registerCrudRoutes($router, 'media');
MediaController::registerSoftDeleteRoutes($router, 'media');
MediaController::registerVersionableRoutes($router, 'media');

MediaDirectoryController::registerCrudRoutes($router, 'mediaDirectory');
MediaDirectoryController::registerSoftDeleteRoutes($router, 'mediaDirectory');

$router->get('media/{slug}.{extension}', MediaController::class . '@getView')
       ->name('media.getView')
       ->where('slug', '([a-z0-9/\-]+)');
