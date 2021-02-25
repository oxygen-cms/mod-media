<?php

namespace OxygenModule\Media;

use Illuminate\Cache\Repository;
use Oxygen\Core\Blueprint\BlueprintManager;
use Oxygen\Core\Templating\TwigTemplateCompiler;
use OxygenModule\ImportExport\ImportExportManager;
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

        // Extends Twig compiler
        $this->app->resolving(TwigTemplateCompiler::class, function(TwigTemplateCompiler $compiler) {
            $twig = $compiler->getTwig();

            $twig->addFunction(new \Twig\TwigFunction('media', function($file, array $options = []) {
                $template = null;
                if(isset($options['template'])) {
                    $template = $options['template'];
                    unset($options['template']);
                }
                return $this->app[HtmlPresenter::class]->present($file, $template, $options);// . '\')->present(' . $expression . ');;
            }, ['is_variadic' => true]));

            $compiler->addAllowedFunction('media');
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
        if(config('oxygen.mod-media.backup')) {
            $mediaWorker = function($importExportManager) {
                $importExportManager->addWorker(new MediaWorker($this->app[MediaRepositoryInterface::class], $this->app['files'], $this->app['config']));
            };
            if($this->app->resolved(ImportExportManager::class)) {
                $mediaWorker($this->app[ImportExportManager::class]);
            }
            $this->app->resolving(ImportExportManager::class, $mediaWorker);
        }
    }

}
