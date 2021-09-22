<?php

namespace OxygenModule\Media\Presenter;

use Exception;
use Illuminate\Contracts\Routing\UrlGenerator;
use Oxygen\Data\Exception\NoResultException;
use OxygenModule\Media\Repository\MediaRepositoryInterface;
use OxygenModule\Media\Entity\Media;
use Illuminate\Config\Repository;

class HtmlPresenter implements PresenterInterface {

    const MEDIA_FALLBACK_ORDER = ['image/png', 'image/gif', 'image/jpeg'];
    const MEDIA_LOAD_ORDER = ['image/webp', 'image/png', 'image/gif', 'image/jpeg'];
    const IDEAL_WEB_FALLBACK_SIZE = 1000;
    const IDEAL_EMAIL_FALLBACK_SIZE = 600;

    const MODERN_HTML = 'html5';
    const EMAIL_HTML = 'html4';

    use PresentsResponsiveImages;

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

    protected bool $useAbsoluteURLs;

    protected array $tagStyleStack;

    private Repository $config;

    private MediaRepositoryInterface $entities;

    private UrlGenerator $url;

    /**
     * Constructs the HtmlPresenter.
     *
     * @param Repository                                 $config
     * @param UrlGenerator $url
     * @param MediaRepositoryInterface                   $media
     */
    public function __construct(Repository $config, UrlGenerator $url, MediaRepositoryInterface $media) {
        $this->config = $config;
        $this->entities = $media;
        $this->url = $url;
        $this->tagStyleStack = [self::MODERN_HTML];
        $this->useAbsoluteURLs = false;
        $this->templates = [];
    }

    /**
     * Adds a template.
     *
     * @param string $name
     * @param callable $callback
     */
    public function addTemplate(string $name, callable $callback) {
        $this->templates[$name] = $callback;
    }

    /**
     * Retrieves a template.
     *
     * @param string|null $name
     * @param int $type
     * @return callable
     */
    public function getTemplate(?string $name, int $type = Media::TYPE_IMAGE) {
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
     * @param Media $media
     * @param bool $external
     * @return string[]
     * @throws Exception
     */
    public function getAudioSources(Media $media, bool $external): array {
        if($media->getType() !== Media::TYPE_AUDIO) { throw new Exception('expected image media'); }

        $sources = [];
        foreach($media->getVariants() as $variant) {
            $sources[$variant['mime']] = $this->getFilename($variant['filename'], $external);
        }

        return $sources;
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
        if(!isset($customAttributes['external'])) {
            $customAttributes['external'] = false;
        }

        $template = $this->getTemplate($template, $media->getType());
        return $template($this, $media, $customAttributes);
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
            if($this->useAbsoluteURLs === true) {
                $attributes['external'] = true;
            }
            return $this->display($item, $template, $attributes);
        } catch(NoResultException $e) {
            return 'Media `' . e($slug) . '` Not Found';
        }
    }

    /**
     * Whether the presenter should use absolute URLs to the resource
     *
     * @param boolean $use
     * @return void
     */
    public function setUseAbsoluteURLs(bool $use) {
        $this->useAbsoluteURLs = $use;
    }

    /**
     * Pushes a style onto the stack of styles.
     *
     * @param string $style
     * @return void
     */
    public function pushStyle(string $style) {
        $this->tagStyleStack[] = $style;
    }

    /**
     * Pops a stack from the style, returning to the previous style.
     *
     * @return void
     */
    public function popStyle() {
        array_pop($this->tagStyleStack);
    }

    /**
     * Whether the presenter should use html4/html5 etc
     *
     * @return string
     */
    public function getStyle(): string {
        return last($this->tagStyleStack);
    }
}
