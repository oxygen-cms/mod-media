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
    protected $defaultTemplate;

    protected $useAbsoluteURLs;

    protected $tagStyle;
    /**
     * @var \Illuminate\Cache\Repository
     */
    private $cache;

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
     * @param \Illuminate\Cache\Repository                               $cache
     * @param Repository                                 $config
     * @param \Illuminate\Contracts\Routing\UrlGenerator $url
     * @param MediaRepositoryInterface                   $media
     */
    public function __construct(\Illuminate\Cache\Repository $cache, Repository $config, UrlGenerator $url, MediaRepositoryInterface $media) {
        $this->cache = $cache;
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
                $content = isset($customAttributes['content']) ? $customAttributes['content'] : ($media->getCaption() ? $media->getCaption() : $media->getName());
                unset($customAttributes['content']);
                echo $presenter->renderLink($content, [['target' => '_blank', 'href' => $sources['main']], $customAttributes]);
            }
        ];
        $this->defaultTemplate = [
            Media::TYPE_IMAGE => 'default.image',
            Media::TYPE_AUDIO => 'default.audio',
            Media::TYPE_DOCUMENT => 'default.link'
        ];
    }

    /**
     * Returns all the media items
     *
     * @return array
     */
    public function getMedia() {
        if($this->media === null) {
            $this->media = $this->cache->remember('media.list', 600, function() {
                return $this->entities->listBySlug();
            });
        }

        return $this->media;
    }

    /**
     * Adds a template.
     *
     * @param string $name
     * @param callable $callback
     */

    public function addTemplate($name, callable $callback) {
        $this->templates[$name] = $callback;
    }

    /**
     * Retrieves a template.
     *
     * @param string $name
     * @param int $type
     * @return callable
     */

    public function getTemplate($name, $type = Media::TYPE_IMAGE) {
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

    public function renderImage(array $layeredAttributes) {
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

    public function renderAudio(array $sources, array $layeredAttributes, $fallbackText) {
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

    public function renderLink($content, array $layeredAttributes) {
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
    public function composeSrcSetAttribute(array $sources) {
        if(isset($sources['imageSrcSet']) && !empty($sources['imageSrcSet'])) {
            $srcset = [];
            foreach($sources['imageSrcSet'] as $width => $filename) {
                $srcset[] = $filename . ' ' . $width . 'w';
            }
            return implode(', ', $srcset);
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
     * @param Media $audio
     * @return string
     */
    protected function getMimeTypeFromAudio(Media $audio) {
        $extension = substr(strrchr($audio->getFilename(), "."), 1);

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

    protected function getFilename($filename, $external) {
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
    protected function isExternal($attributes) {
        return isset($attributes['external']) && $attributes['external'] === true;
    }

    /**
     * Displays the Media.
     *
     * @param Media      $media
     * @param string|null $template
     * @param array       $customAttributes
     * @return mixed
     */
    public function display(Media $media, $template = null, array $customAttributes = []) {
        $external = $this->isExternal($customAttributes);
        unset($customAttributes['external']);

        if($media->getType() === Media::TYPE_IMAGE) {
            $versions = Arr::where($this->getMedia(), function($key, $value) use($media) {
                return preg_match('/' . preg_quote($media->getSlug(), '/') . '\/[0-9]+/', $key);
            });

            $srcset = [];
            foreach($versions as $key => $value) {
                unset($versions[$key]);
                preg_match('/' . preg_quote($media->getSlug(), '/') . '\/([0-9]+)/', $key, $matches);
                $versions[$matches[1]] = $value;
                $srcset[$matches[1]] = $this->getFilename($value->getFilename(), $external);
            }

            if($media->getDefault()) {
                $src = $this->getFilename($versions[$media['default']]->getFilename(), $external);
            } else {
                $src = $this->getFilename($media->getFilename(), $external);
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
            $versions = Arr::where($this->getMedia(), function($key, $value) use($media) {
                return preg_match('/' . preg_quote($media->getSlug()) . '\/[a-z]+/', $key);
            });

            $sources = [
                $this->getMimeTypeFromAudio($media) => $this->getFilename($media->getFilename(), $external)
            ];
            foreach($versions as $key => $value) {
                if($value->getType() === Media::TYPE_AUDIO) {
                    $sources[$this->getMimeTypeFromAudio($value)] = $this->getFilename($value->getFilename(), $external);
                }
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
        }
    }

    /**
     * Displays the Media.
     *
     * @param string      $slug
     * @param string|null $template
     * @param array       $customAttributes
     * @return mixed
     */
    public function present($slug, $template = null, array $customAttributes = []) {
        global $__env;
        try {
            $item = $this->entities->findBySlug($slug);
            if(isset($__env) && method_exists($__env, 'viewDependsOnEntity')) {
                $__env->viewDependsOnEntity($item);
            }
            if($this->useAbsoluteURLs = true) {
                $customAttributes['external'] = true;
            }
            return $this->display($item, $template, $customAttributes);
        } catch(NoResultException $e) {
            if(isset($__env) && method_exists($__env, 'viewDependsOnAllEntities')) {
                $__env->viewDependsOnAllEntities(Media::class);
            }
            return 'Media `' . e($slug) . '`` Not Found';
        }
    }

    /**
     * Previews the given media item.
     *
     * @param Media $media
     * @return string
     */
    public function preview(Media $media) {
        switch($media->getType()) {
            case Media::TYPE_IMAGE:
                return '<img src="' . $this->getFilename($media->getFilename(), false) . '">';
                break;
            case Media::TYPE_AUDIO:
                return '<div class="Icon-container"><span class="Icon Icon--gigantic Icon--light Icon-music"></span></div>'; //<audio src="' . $filename . '" preload="none" controls></audio>';
                break;
            case Media::TYPE_DOCUMENT:
            default:
                return '<div class="Icon-container"><span class="Icon Icon--gigantic Icon--light Icon-file-text"></span></div>';
        }
    }

    /**
     * Whether the presenter should use absolute URLs to the resource
     *
     * @param boolean $use
     * @return void
     */
    public function setUseAbsoluteURLs($use) {
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