<?php

namespace OxygenModule\Media\Presenter;

use Illuminate\Contracts\Routing\UrlGenerator;
use Oxygen\Data\Exception\NoResultException;
use OxygenModule\Media\Repository\MediaRepositoryInterface;
use OxygenModule\Media\Entity\Media;
use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;

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
     * @var string
     */

    protected $defaultTemplate;

    /**
     * Constructs the HtmlPresenter.
     *
     * @param CacheManager                               $cache
     * @param Repository                                 $config
     * @param \Illuminate\Contracts\Routing\UrlGenerator $url
     * @param MediaRepositoryInterface                   $media
     */

    public function __construct(CacheManager $cache, Repository $config, UrlGenerator $url, MediaRepositoryInterface $media) {
        $this->cache = $cache;
        $this->config = $config;
        $this->entities = $media;
        $this->url = $url;
        $this->templates = [
            'default.image' => function(Media $media, array $sources, array $customAttributes) {
                $baseAttributes = [
                    'src' => $sources['main'],
                    'srcset' => $this->composeSrcSetAttribute($sources),
                    'alt' => $media->getAlt() ? $media->getAlt() : $media->getName()
                ];

                echo $this->renderImage([$baseAttributes, $customAttributes]);
            },
            'default.audio' => function(Media $media, array $sources, array $customAttributes) {
                echo $this->renderAudio($sources['audioSources'], [['controls' => 'controls'], $customAttributes], 'Audio Not Supported');
            },
            'default.link' => function(Media $media, array $sources, array $customAttributes) {
                echo $this->renderLink(isset($customAttributes['content']) ? $customAttributes['content'] : $media->getCaption(), [['target' => '_blank', 'href' => $sources['main']], $customAttributes]);
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
     * @param          $name
     * @param callable $callback
     */

    public function addTemplate($name, callable $callback) {
        $this->templates[$name] = $callback;
    }

    /**
     * Retrieves a template.
     *
     * @param $name
     * @param $type
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
     * @param $name
     * @param $type
     */

    public function setDefaultTemplate($name, $type = Media::TYPE_IMAGE) {
        $this->defaultTemplate[$type] = $name;
    }

    /**
     * Returns the mime type of the given audio resource.
     *
     * @param $audio
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
     * @return string
     */
    protected function isExternal($attributes) {
        return isset($attributes['external']) && $attributes['external'] === true;
    }

    /**
     * Displays the Media.
     *
     * @param string      $slug
     * @param string|null $template
     * @param array       $customAttributes
     * @return mixed
     */
    public function display($slug, $template = null, array $customAttributes = []) {
        try {
            $media = $this->entities->findBySlug($slug);
        } catch(NoResultException $e) {
            echo 'Media `' . $slug . '` Not Found';
            return;
        }

        $external = $this->isExternal($customAttributes);

        if($media->getType() === Media::TYPE_IMAGE) {
            $versions = array_where($this->getMedia(), function($key, $value) use($slug) {
                return preg_match('/' . preg_quote($slug, '/') . '\/[0-9]+/', $key);
            });

            $srcset = [];
            foreach($versions as $key => $value) {
                unset($versions[$key]);
                preg_match('/' . preg_quote($slug, '/') . '\/([0-9]+)/', $key, $matches);
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
                $media,
                [
                    'main' => $src,
                    'imageSrcSet' => $srcset
                ],
                $customAttributes
            );
        } else if($media->getType() === Media::TYPE_AUDIO) {
            $versions = array_where($this->getMedia(), function($key, $value) use($slug) {
                return preg_match('/' . preg_quote($slug) . '\/[a-z]+/', $key);
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
            $template($media, ['main' => $url], $customAttributes);
        }
    }

    /**
     * Previews the given media item.
     *
     * @param $media
     * @return string
     */
    public function preview($media) {
        switch($media->getType()) {
            case Media::TYPE_IMAGE:
                return '<img src="' . $this->getFilename($media->getFilename(), false) . '">';
                break;
            case Media::TYPE_AUDIO:
                return '<div class="Icon-container"><span class="Icon Icon--gigantic Icon--light Icon-music"></span></div>'; //<audio src="' . $filename . '" preload="none" controls></audio>';
                break;
            case Media::TYPE_DOCUMENT:
                return '<div class="Icon-container"><span class="Icon Icon--gigantic Icon--light Icon-file-text"></span></div>';
        }
    }

}