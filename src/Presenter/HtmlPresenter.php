<?php

namespace OxygenModule\Media\Presenter;

use Illuminate\Contracts\Routing\UrlGenerator;
use Oxygen\Data\Exception\NoResultException;
use OxygenModule\Media\Repository\MediaRepositoryInterface;
use OxygenModule\Media\Entity\Media;
use Illuminate\Config\Repository;
use Illuminate\Support\Arr;

class HtmlPresenter implements PresenterInterface {

    /**
     * Media items.
     *
     * @var array
     */

    protected $media;

    /**
     * Templates
     *
     * @var array
     */

    protected $templates;

    /**
     * Default Template
     *
     * @var array
     */
    protected $defaultTemplate = [
        Media::TYPE_IMAGE => 'default.image',
        Media::TYPE_AUDIO => 'default.audio',
        Media::TYPE_DOCUMENT => 'default.link'
    ];

    protected $useAbsoluteURLs;

    protected $tagStyle;

    /**
     * @var Repository
     */
    private $config;
    /**
     * @var MediaRepositoryInterface
     */
    private $entities;
    /**
     * @var UrlGenerator
     */
    private $url;

    /**
     * Constructs the HtmlPresenter.
     *
     * @param Repository                                 $config
     * @param \Illuminate\Contracts\Routing\UrlGenerator $url
     * @param MediaRepositoryInterface                   $media
     */
    public function __construct(Repository $config, UrlGenerator $url, MediaRepositoryInterface $media) {
        $this->config = $config;
        $this->entities = $media;
        $this->url = $url;
        $this->tagStyle = 'html5';
        $this->useAbsoluteURLs = false;
        $this->templates = [
            'default.image' => function(HtmlPresenter $presenter, Media $media, array $sources, array $customAttributes) {
                $baseAttributes = [
                    'src' => $sources['main'],
                    'srcset' => $presenter->composeSrcSetAttribute($sources),
                    'alt' => $media->getCaption() ? $media->getCaption() : $media->getName()
                ];

                echo $presenter->renderImage([$baseAttributes, $customAttributes]);
            },
            'default.audio' => function(HtmlPresenter $presenter, Media $media, array $sources, array $customAttributes) {
                echo $presenter->renderAudio($sources['audioSources'], [['controls' => 'controls'], $customAttributes], 'Audio Not Supported');
            },
            'default.link' => function(HtmlPresenter $presenter, Media $media, array $sources, array $customAttributes) {
                $content = $customAttributes['content'] ?? ($media->getCaption() ? $media->getCaption() : $media->getName());
                unset($customAttributes['content']);
                echo $presenter->renderLink($content, [['target' => '_blank', 'href' => $sources['main']], $customAttributes]);
            }
        ];
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
    public function getTemplate(?string $name, $type = Media::TYPE_IMAGE) {
        if($name === null) {
            return $this->templates[$this->defaultTemplate[$type]];
        } else {
            return $this->templates[$name];
        }
    }

    /**
     * Renders an image using the default attributes.
     *
     * @param array $layeredAttributes
     * @return string
     */
    public function renderImage(array $layeredAttributes): string {
        $attr = [];
        foreach($layeredAttributes as $attributes) {
            $attr = array_merge($attr, $attributes);
        }
        return '<img ' . html_attributes($attr) . '>';
    }

    /**
     * Renders an audio element using the default attributes.
     *
     * @param array $sources
     * @param array $layeredAttributes
     * @param string $fallbackText
     * @return string
     */
    public function renderAudio(array $sources, array $layeredAttributes, $fallbackText): string {
        $attr = [];
        foreach($layeredAttributes as $attributes) {
            $attr = array_merge($attr, $attributes);
        }
        $return = '<audio ' . html_attributes($attr) . '>';
        foreach($sources as $mime => $src) {
            $return .= '<source ' . html_attributes(['src' => $src, 'type' => $mime]) . '>';
        }
        $return .= $fallbackText . '</audio>';
        return $return;
    }

    /**
     * Renders a link to a document.
     *
     * @param string $content
     * @param array  $layeredAttributes
     * @return string
     */
    public function renderLink(string $content, array $layeredAttributes) {
        $attr = [];
        foreach($layeredAttributes as $attributes) {
            $attr = array_merge($attr, $attributes);
        }
        return '<a ' . html_attributes($attr) . '>' . $content . '</a>';
    }

    /**
     * Composes the `srcset` attribute from the array of sources
     *
     * @param array $sources
     * @return null|string
     */
    public function composeSrcSetAttribute(array $sources): ?string {
        if(isset($sources['imageSrcSet']) && !empty($sources['imageSrcSet'])) {
            return implode(', ', $sources['imageSrcSet']);
        }

        return null;
    }

    /**
     * Sets a template as default.
     *
     * @param string $name
     * @param int $type
     */
    public function setDefaultTemplate($name, $type) {
        $this->defaultTemplate[$type] = $name;
    }

    /**
     * Returns the mime type of the given audio resource.
     *
     * @param string $filename
     * @return string
     */
    protected function getMimeTypeFromAudio(string $filename): string {
        $extension = substr(strrchr($filename, "."), 1);

        switch($extension) {
            case 'ogx':
                return 'audio/ogg';
            case 'mp4a':
            case 'mp3':
            case 'mpga':
                return 'audio/mp3';
        }
        return 'application/unknown';
    }

    /**
     * Returns a web accessible filename to the resource.
     *
     * @param string $filename
     * @param boolean $external
     * @return string
     */

    protected function getFilename(string $filename, bool $external): string {
        $filename = $this->config->get('oxygen.mod-media.directory.web') . '/' . $filename;
        if($external) {
            return $this->url->to($filename);
        }
        return $filename;
    }

    /**
     * Determines if this resource should be accessed externally
     *
     * @param array $attributes
     * @return boolean
     */
    protected function isExternal($attributes): bool {
        return isset($attributes['external']) && $attributes['external'] === true;
    }

    /**
     * Displays the Media.
     *
     * @param Media $media
     * @param string|null $template
     * @param array $customAttributes
     * @throws \Exception
     */
    public function display(Media $media, $template = null, array $customAttributes = []) {
        $external = $this->isExternal($customAttributes);
        unset($customAttributes['external']);

        if($media->getType() === Media::TYPE_IMAGE) {
            $src = $this->getFilename($media->getFilename(), $external);

            $srcset = [];
            foreach($media->getVariants() as $variant) {
                $srcset[] = $this->getFilename($variant['filename'], $external) . ' ' . $variant['width'] . 'w';
            }

            $template = $this->getTemplate($template, Media::TYPE_IMAGE);

            $template(
                $this,
                $media,
                [
                    'main' => $src,
                    'imageSrcSet' => $srcset
                ],
                $customAttributes
            );
        } else if($media->getType() === Media::TYPE_AUDIO) {
            $sources = [
                $this->getMimeTypeFromAudio($media->getFilename()) => $this->getFilename($media->getFilename(), $external)
            ];

            foreach($media->getVariants() as $variant) {
                $sources[$this->getMimeTypeFromAudio($variant['filename'])] = $this->getFilename($variant['filename'], $external);
            }

            $template = $this->getTemplate($template, Media::TYPE_AUDIO);

            $template(
                $this,
                $media,
                [
                    'main' => $this->getFilename($media->getFilename(), $external),
                    'audioSources' => [$sources]
                ],
                $customAttributes
            );
        } else if($media->getType() === Media::TYPE_DOCUMENT) {
            $url = $this->getFilename($media->getFilename(), $external);
            $template = $this->getTemplate($template, Media::TYPE_DOCUMENT);
            $template($this, $media, ['main' => $url], $customAttributes);
        } else {
            throw new \Exception('Unknown media type ' . $media->getType());
        }
    }

    /**
     * Displays the Media.
     *
     * @param string $slug
     * @param string|null $template
     * @param array $attributes
     * @return mixed
     * @throws \Exception
     */
    public function present(string $slug, $template = null, array $attributes = []) {
        global $__env;

        try {
            $item = $this->entities->findByPath($slug);
            if(isset($__env) && method_exists($__env, 'viewDependsOnEntity')) {
                $__env->viewDependsOnEntity($item);
            }
            if($this->useAbsoluteURLs = true) {
                $attributes['external'] = true;
            }
            return $this->display($item, $template, $attributes);
        } catch(NoResultException $e) {
            if(isset($__env) && method_exists($__env, 'viewDependsOnAllEntities')) {
                $__env->viewDependsOnAllEntities(Media::class);
            }
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
     * Whether the presenter should use html4/html5 etc
     *
     * @param string $style
     * @return void
     */
    public function setStyle($style) {
        $this->tagStyle = $style;
    }

    /**
     * Whether the presenter should use html4/html5 etc
     *
     * @return string
     */
    public function getStyle() {
        return $this->tagStyle;
    }
}
