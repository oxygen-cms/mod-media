<?php

namespace OxygenModule\Media;

use Oxygen\Core\Content\ObjectLinkRegistry;
use Oxygen\Core\Templating\TwigTemplateCompiler;
use OxygenModule\ImportExport\ImportExportManager;
use OxygenModule\Media\Console\CollectGarbageCommand;
use OxygenModule\Media\Console\GenerateImageVariantsCommand;
use OxygenModule\Media\Entity\Media;
use OxygenModule\Media\Presenter\AudioTemplate;
use OxygenModule\Media\Presenter\MediaPresenter;
use OxygenModule\Media\Presenter\ImageTemplate;
use OxygenModule\Media\Presenter\LinkTemplate;
use OxygenModule\Media\Presenter\PresenterInterface;
use OxygenModule\Media\Repository\DoctrineMediaDirectoryRepository;
use OxygenModule\Media\Repository\DoctrineMediaRepository;
use OxygenModule\Media\Repository\MediaDirectoryRepositoryInterface;
use OxygenModule\Media\Repository\MediaRepositoryInterface;
use Oxygen\Data\BaseServiceProvider;
use Twig\TwigFunction;

class MediaServiceProvider extends BaseServiceProvider {

    /**
     * Boots the package.
     *
     * @return void
     * @throws \Exception
     */
    public function boot() {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'oxygen.mod-media');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'oxygen/mod-media');
        $this->loadRoutesFrom(__DIR__ . '/../resources/routes.php');
        $this->commands(GenerateImageVariantsCommand::class);
        $this->commands(CollectGarbageCommand::class);

        $this->publishes([
            __DIR__ . '/../resources/lang' => base_path('resources/lang/vendor/oxygen/mod-media'),
            __DIR__ . '/../resources/views' => base_path('resources/views/vendor/oxygen/mod-media'),
            __DIR__ . '/../config/config.php' => config_path('oxygen/mod-media.php')
        ]);

        // Extends Twig compiler
        $this->app->resolving(TwigTemplateCompiler::class, function(TwigTemplateCompiler $compiler) {
            $twig = $compiler->getTwig();

            $twig->addFunction(new TwigFunction('media', function($slug, array $options = []) use($compiler) {
                $template = null;
                if(isset($options['template'])) {
                    $template = $options['template'];
                    unset($options['template']);
                }
                $presenter = $this->app[MediaPresenter::class];
                if($compiler->shouldConvertToTipTap())
                {
                    $media = $this->app[MediaRepositoryInterface::class]->findByPath($slug);
                    $template = $presenter->getTemplate($template, $media->getType());
                    return $template->transformToTipTapHtml($presenter, $media, $options);
                }
                echo $presenter->present($slug, $template, $options);
            }, ['is_variadic' => true, 'is_safe' => ['html']]));

            $compiler->addAllowedFunction('media');
        });

        $this->app[MediaPresenter::class]->addTemplate('default.image', new ImageTemplate());
        $this->app[MediaPresenter::class]->addTemplate('default.audio', new AudioTemplate());
        $this->app[MediaPresenter::class]->addTemplate('default.link', new LinkTemplate());
        $this->app[MediaPresenter::class]->setDefaultTemplate('default.image', Media::TYPE_IMAGE);
        $this->app[MediaPresenter::class]->setDefaultTemplate('default.audio', Media::TYPE_AUDIO);
        $this->app[MediaPresenter::class]->setDefaultTemplate('default.link', Media::TYPE_DOCUMENT);

        $this->app[ObjectLinkRegistry::class]->addType(new MediaLinkType($this->app[MediaRepositoryInterface::class]));

        $this->loadMigrationsFrom(__DIR__ . '/../migrations');
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
        $this->app->bind(MediaDirectoryRepositoryInterface::class, DoctrineMediaDirectoryRepository::class);

        $this->app->bind(PresenterInterface::class, MediaPresenter::class);
        $this->app->singleton(MediaPresenter::class, function($app) {
            return new MediaPresenter($app['config'], $app['url'], $app[MediaRepositoryInterface::class]);
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

    /**
     * @return string[]
     */
    public function provides(): array {
        return [
            MediaRepositoryInterface::class,
            GenerateImageVariantsCommand::class
        ];
    }

}
