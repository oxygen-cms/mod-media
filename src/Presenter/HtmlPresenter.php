<?php

namespace OxygenModule\Media\Presenter;

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
     * @param CacheManager               $cache
     * @param Repository                 $config
     * @param MediaRepositoryInterface   $media
     */

    public function __construct(CacheManager $cache, Repository $config, MediaRepositoryInterface $media) {
        $this->cache = $cache;
        $this->config = $config;
        $this->entities = $media;
        $this->templates = [
            'default.image' => function($media, $baseAttributes, $customAttributes) {
                echo $this->renderImage([$baseAttributes, $customAttributes]);
            },
            'default.audio' => function($media, $sources, $customAttributes) {
                echo $this->renderAudio($sources, [['controls' => 'controls'], $customAttributes], 'Audio Not Supported');
            },
            'default.document' => function($media, $url, $customAttributes) {
                echo $this->renderLink($media['caption'], [['target' => '_blank', 'href' => $url], $customAttributes]);
            }
        ];
        $this->defaultTemplate = [
            Media::TYPE_IMAGE => 'default.image',
            Media::TYPE_AUDIO => 'default.audio',
            Media::TYPE_DOCUMENT => 'default.document'
        ];
    }

    /**
     * Returns an array representation of the media item.
     *
     * @param $slug
     * @return array
     */

    public function getMedia($slug) {
        if($this->media === null) {
            $this->media = $this->cache->remember('media.list', 600, function() {
                return $this->entities->listBySlug();
            });
        }

        if(isset($this->media[$slug])) {
            return $this->media[$slug];
        } else {
            return [
                'type' => 'notFound'
            ];
        }
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

    protected function getMimeTypeFromAudio($audio) {
        $extension = substr(strrchr($audio['filename'], "."), 1);

        switch($extension) {
            case 'ogx':
                return 'audio/ogg';
            case 'mp4a':
            case 'mp3':
            case 'mpga':
                return 'audio/mp3';
        }
    }

    /**
     * Returns a web accessible filename to the resource.
     *
     * @param $filename
     * @return string
     */

    protected function getFilename($filename) {
        return $this->config->get('oxygen.mod-media.directory.web') . '/' . $filename;
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
        $media = $this->getMedia($slug);

        if($media['type'] === 'notFound') {
            echo 'Media `' . $slug . '` Not Found';
        } else if($media['type'] === Media::TYPE_IMAGE) {
            $versions = array_where($this->media, function($key, $value) use($slug) {
                return preg_match('/' . preg_quote($slug, '/') . '\/[0-9]+/', $key);
            });

            $srcset = [];
            foreach($versions as $key => $value) {
                unset($versions[$key]);
                preg_match('/' . preg_quote($slug, '/') . '\/([0-9]+)/', $key, $matches);
                $versions[$matches[1]] = $value;
                $srcset[] = $this->getFilename($value['filename']) . ' ' . $matches[1] . 'w';
            }

            if($media['default']) {
                $src = $this->getFilename($versions[$media['default']]['filename']);
            } else {
                $src = $this->getFilename($media['filename']);
            }

            $baseAttributes = [
                'src' => $src,
                'srcset' => empty($srcset) ? null : implode(', ', $srcset),
                'alt' => $media['alt'] ? $media['alt'] : $media['name']
            ];

            $template = $this->getTemplate($template, Media::TYPE_IMAGE);

            $template($media, $baseAttributes, $customAttributes);
        } else if($media['type'] === Media::TYPE_AUDIO) {
            $versions = array_where($this->media, function($key, $value) use($slug) {
                return preg_match('/' . preg_quote($slug) . '\/[a-z]+/', $key);
            });

            $sources = [
                $this->getMimeTypeFromAudio($media) => $this->getFilename($media['filename'])
            ];
            foreach($versions as $key => $value) {
                if($value['type'] === Media::TYPE_AUDIO) {
                    $sources[$this->getMimeTypeFromAudio($value)] = $this->getFilename($value['filename']);
                }
            }

            $template = $this->getTemplate($template, Media::TYPE_AUDIO);

            $template($media, $sources, $customAttributes);
        } else if($media['type'] === Media::TYPE_DOCUMENT) {
            $url = $this->getFilename($media['filename']);
            $template = $this->getTemplate($template, Media::TYPE_DOCUMENT);
            $template($media, $url, $customAttributes);
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
                return '<img src="' . $this->getFilename($media->getFilename()) . '">';
                break;
            case Media::TYPE_AUDIO:
                return '<div class="Icon-container"><span class="Icon Icon--gigantic Icon--light Icon-music"></span></div>'; //<audio src="' . $filename . '" preload="none" controls></audio>';
                break;
            case Media::TYPE_DOCUMENT:
                return '<div class="Icon-container"><span class="Icon Icon--gigantic Icon--light Icon-file-text"></span></div>';
        }
    }

}