<?php

namespace OxygenModule\Media\Presenter;

use Exception;
use Illuminate\Contracts\Routing\UrlGenerator;
use Oxygen\Data\Exception\NoResultException;
use OxygenModule\Media\Repository\MediaRepositoryInterface;
use OxygenModule\Media\Entity\Media;
use Illuminate\Config\Repository;

class MediaPresenter implements PresenterInterface {
    const MEDIA_LOAD_ORDER = ['image/webp', 'image/png', 'image/gif', 'image/jpeg'];
    const IDEAL_WEB_FALLBACK_SIZE = 1000;
    const IDEAL_EMAIL_FALLBACK_SIZE = 600;

    /**
     * Templates
     *
     * @var array
     */
    protected array $templates = [];

    /**
     * Default Template
     *
     * @var array
     */
    protected array $defaultTemplate = [
        Media::TYPE_IMAGE => null,
        Media::TYPE_AUDIO => null,
        Media::TYPE_DOCUMENT => null
    ];

    const USE_ABSOLUTE_URLS = 'use_absolute_urls';
    const HTML4 = 'html4';

    protected array $styleHints;

    private Repository $config;

    private MediaRepositoryInterface $entities;

    private UrlGenerator $url;

    /**
     * Constructs the MediaPresenter.
     *
     * @param Repository $config
     * @param UrlGenerator $url
     * @param MediaRepositoryInterface $media
     */
    public function __construct(Repository $config, UrlGenerator $url, MediaRepositoryInterface $media) {
        $this->config = $config;
        $this->entities = $media;
        $this->url = $url;
        $this->styleHints = [];
        $this->templates = [];
    }

    /**
     * Adds a template.
     *
     * @param string $name
     * @param TemplateInterface $template
     */
    public function addTemplate(string $name, TemplateInterface $template) {
        $this->templates[$name] = $template;
    }

    /**
     * Retrieves a template.
     *
     * @param string|null $name
     * @param int $type
     * @return TemplateInterface
     */
    public function getTemplate(?string $name, int $type = Media::TYPE_IMAGE): TemplateInterface {
        if($name === null) {
            return $this->templates[$this->defaultTemplate[$type]];
        } else {
            return $this->templates[$name];
        }
    }

    /**
     * Sets a template as default.
     *
     * @param string $name
     * @param int $type
     */
    public function setDefaultTemplate(string $name, int $type) {
        $this->defaultTemplate[$type] = $name;
    }

    /**
     * Returns a web accessible filename to the resource.
     *
     * @param string $filename
     * @param boolean $external
     * @return string
     */
    public function getFilename(string $filename, bool $external): string {
        $filename = $this->config->get('oxygen.mod-media.directory.web') . '/' . basename($filename);
        if($external) {
            return $this->url->to($filename);
        }
        return $filename;
    }

    /**
     * Displays the Media.
     *
     * @param Media $media
     * @param string|null $template
     * @param array $customAttributes
     * @return string
     * @throws Exception
     */
    public function display(Media $media, $template = null, array $customAttributes = []): string {
        if($this->hasStyleHint(self::USE_ABSOLUTE_URLS)) {
            $customAttributes['external'] = true;
        }
        if(!isset($customAttributes['external'])) {
            $customAttributes['external'] = false;
        }

        $template = $this->getTemplate($template, $media->getType());
        return $template->present($this, $media, $customAttributes);
    }

    /**
     * Displays the Media.
     *
     * @param string $slug
     * @param string|null $template
     * @param array $attributes
     * @return string
     * @throws Exception
     */
    public function present(string $slug, $template = null, array $attributes = []): string {
        try {
            $item = $this->entities->findByPath($slug);
            return $this->display($item, $template, $attributes);
        } catch(NoResultException $e) {
            return 'Media `' . e($slug) . '` Not Found';
        }
    }

    public function setStyleHint(string $hint, bool $enabled): void {
        $this->styleHints[$hint] = $enabled;
    }

    public function hasStyleHint(string $hint): bool {
        return isset($this->styleHints[$hint]) && $this->styleHints[$hint];
    }

}
