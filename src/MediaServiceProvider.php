<?php

namespace OxygenModule\Media;

use Illuminate\Cache\Repository;
use Oxygen\Core\Blueprint\BlueprintManager;
use OxygenModule\Media\Presenter\HtmlPresenter;
use OxygenModule\Media\Presenter\PresenterInterface;
use OxygenModule\Media\Repository\DoctrineMediaRepository;
use OxygenModule\Media\Repository\MediaRepositoryInterface;
use OxygenModule\Media\Repository\MediaSubscriber;
use Oxygen\Data\BaseServiceProvider;

class MediaServiceProvider extends BaseServiceProvider {

    /**
     * Boots the package.
     *
     * @return void
     */
    public function boot() {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'oxygen.mod-media');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'oxygen/mod-media');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'oxygen/mod-media');

        $this->publishes([
            __DIR__ . '/../resources/lang' => base_path('resources/lang/vendor/oxygen/mod-media'),
            __DIR__ . '/../resources/views' => base_path('resources/views/vendor/oxygen/mod-media'),
            __DIR__ . '/../config/config.php' => config_path('oxygen/mod-media.php')
        ]);

        $this->app[BlueprintManager::class]->loadDirectory(__DIR__ . '/../resources/blueprints');

        // Extends Blade compiler
        $this->app['blade.compiler']->directive('media', function($expression) {
            return '<?php echo app(\'' . HtmlPresenter::class . '\')->present(' . $expression . '); ?>';
        });
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() {
        $this->loadEntitiesFrom(__DIR__ . '/Entity');

        // Repositories
        $this->app->bind(MediaRepositoryInterface::class, DoctrineMediaRepository::class);

        $this->extendEntityManager(function($entities) {
            $entities->getEventManager()
                     ->addEventSubscriber(new MediaSubscriber($this->app['files'], $this->app['config'], $this->app[Repository::class]));
        });

        $this->app->bind(PresenterInterface::class, HtmlPresenter::class);
        $this->app->singleton(HtmlPresenter::class, function($app) {
            return new HtmlPresenter($app[Repository::class], $app['config'], $app['url'], $app[MediaRepositoryInterface::class]);
        });

        // extend backup functionality
        if(class_exists('OxygenModule\ImportExport\ImportExportManager')) {
            $mediaWorker = function($importExportManager) {
                $importExportManager->addWorker(new MediaWorker($this->app[MediaRepositoryInterface::class], $this->app['files'], $this->app['config']));
            };
            if($this->app->resolved('OxygenModule\ImportExport\ImportExportManager')) {
                $mediaWorker($this->app['OxygenModule\ImportExport\ImportExportManager']);
            }
            $this->app->resolving('OxygenModule\ImportExport\ImportExportManager', $mediaWorker);
        }
    }

}
