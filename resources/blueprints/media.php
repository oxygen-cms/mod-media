<?php

use Oxygen\Core\Action\Group;
use Oxygen\Core\Action\Action;
use Oxygen\Core\Action\Factory\ActionFactory;
use Oxygen\Core\Http\Method;
use OxygenModule\Media\Entity\Media;
use Oxygen\Core\Html\Dialog\Dialog;
use Oxygen\Core\Html\Toolbar\ActionToolbarItem;
use Oxygen\Core\Html\Toolbar\Factory\VoidButtonToolbarItemFactory;
use Oxygen\Crud\BlueprintTrait\VersionableCrudTrait;
use OxygenModule\Media\Controller\MediaController;

Blueprint::make('Media', function($blueprint) {
    $blueprint->disablePluralForm();
    $blueprint->setController(MediaController::class);
    $blueprint->setIcon('picture-o');

    $blueprint->setToolbarOrders([
        'section' => [
            'getUpload', 'getTrash',
        ],
        'item' => [
            'getUse',
            'getRaw',
            'getUpdate,More' => ['getView', 'getInfo', 'getEditImage', 'postMakeResponsive', 'deleteDelete', 'postRestore', 'deleteForce'],
            'Versions' => ['postNewVersion', 'postMakeHeadVersion']
        ],
        'versionList' => [
            'deleteVersions'
        ]
    ]);

    $blueprint->useTrait(new VersionableCrudTrait());

    $blueprint->getToolbarItem('getUpdate')->label = 'Edit Info';
    $blueprint->removeAction('getCreate');
    $blueprint->removeToolbarItem('getCreate');

    $blueprint->makeAction([
        'name'        => 'getUpload',
        'pattern'     => 'upload'
    ]);
    $blueprint->makeToolbarItem([
        'action'    => 'getUpload',
        'label'     => 'Upload',
        'icon'      => 'upload',
        'color'     => 'green'
    ]);

    $blueprint->makeAction([
        'name'      => 'postUpload',
        'pattern'   => 'upload',
        'method'    => Method::POST
    ]);

    $blueprint->makeAction([
        'name'        => 'getEditImage',
        'pattern'     => '{id}/editImage'
    ]);
    $blueprint->makeToolbarItem([
        'action'    => 'getEditImage',
        'label'     => 'Edit',
        'icon'      => 'paint-brush',
        'color'     => 'white',
        'shouldRenderCallback' => function($item, array $arguments) {
        return
            $item->shouldRenderBasic($arguments) &&
            $arguments['model']->getType() === Media::TYPE_IMAGE;
    }
    ]);

    $blueprint->makeAction(
        [
            'name'          => 'getView',
            'pattern'       => 'media/{slug}.{extension}',
            'group'         => new Group('media'),
            'routeParametersCallback' => function(Action $action, array $options) {
                return [
                    $options['model']->getSlug()
                ];
            },
            'customRouteCallback' => function(Action $action, $route) {
                $route->where('slug', '([a-z0-9/\-]+)');
            }
        ],
        new ActionFactory()
    );
    $blueprint->makeToolbarItem([
        'action'        => 'getView',
        'label'         => 'View',
        'icon'          => 'file-image-o',
        'shouldRenderCallback' => function(ActionToolbarItem $item, array $arguments) {
            return
                $item->shouldRenderBasic($arguments) &&
                $arguments['model']->isHead();
        }
    ]);

    $blueprint->makeAction([
        'name'      => 'getRaw',
        'pattern'   => '{id}/raw',
        'useSmoothState' => false
    ]);
    $blueprint->makeToolbarItem([
        'action'        => 'getRaw',
        'label'         => 'View',
        'icon'          => 'camera-retro'
    ])->addDynamicCallback(function($toolbarItem, $arguments) {
        switch($arguments['model']->getType()) {
            case Media::TYPE_AUDIO:
                $toolbarItem->icon = 'volume-up';
                $toolbarItem->label = 'Listen';
                break;
            case Media::TYPE_DOCUMENT:
                $toolbarItem->icon = 'file-o';
                break;
        }
    });

    $blueprint->makeAction([
        'name'      => 'postMakeResponsive',
        'pattern'   => '{id}/makeResponsive',
        'method'    => 'POST'
    ]);
    $blueprint->makeToolbarItem([
        'action'        => 'postMakeResponsive',
        'label'         => 'Make Responsive',
        'icon'          => 'crop',
        'shouldRenderCallback' => function($item, array $arguments) {
            return
                $item->shouldRenderBasic($arguments) &&
                $arguments['model']->getType() === Media::TYPE_IMAGE;
        }
    ]);

    $blueprint->makeAction([
        'name'      => 'getUse',
        'pattern'   => '{id}/use',
        'register'  => false
    ]);
    $blueprint->makeToolbarItem([
        'action'        => 'getUse',
        'label'         => 'Use',
        'icon'          => 'magic',
        'dialog'        => new Dialog(Lang::get('oxygen/mod-media::dialogs.use'), Dialog::TYPE_ALERT)
    ], new VoidButtonToolbarItemFactory())->addDynamicCallback(function($toolbarItem, $arguments) {
        $slug = $arguments['model']->getSlug();
        $link = \Config::get('app.url') . "/media/$slug." . $arguments['model']->getExtension();
        $msg = Lang::get(
            'oxygen/mod-media::dialogs.use',
            [
                'internal-code' => "@media('$slug')",
                'link' => $link
            ]
        );
        $toolbarItem->dialog = new Dialog($msg, Dialog::TYPE_ALERT);
    });
});
