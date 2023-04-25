<?php

namespace OxygenModule\Media\Presenter;

use OxygenModule\Media\Entity\Media;

class HtmlHelper {

    const MEDIA_FALLBACK_ORDER = ['image/png', 'image/gif', 'image/jpeg'];

    /**
     * Renders an image using the default attributes.
     *
     * @param array $attrs
     * @return string
     */
    public static function img(array $attrs): string {
        return '<img ' . html_attributes($attrs) . ' />';
    }

    /**
     * Renders an audio element using the default attributes.
     *
     * @param array $sources
     * @param array $attrs
     * @param string $fallbackText
     * @return string
     */
    public static function audio(array $sources, array $attrs, string $fallbackText): string {
        $return = '<audio ' . html_attributes($attrs) . '>';
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
     * @param array  $attrs
     * @return string
     */
    public static function a(string $content, array $attrs): string {
        return '<a ' . html_attributes($attrs) . '>' . $content . '</a>';
    }

    /**
     * Composes the `srcset` attribute from the array of sources
     *
     * @param array $sources
     * @return string
     */
    public static function srcset(array $sources): string {
        $srcset = [];
        foreach($sources as $source) {
            $src = e($source['filename']);
            if($source['width'] !== null) {
                $src .= ' ' . e($source['width']) . 'w';
            }
            $srcset[] = $src;

        }
        return implode(', ', $srcset);
    }

    /**
     * @throws \Exception
     */
    public static function renderResponsivePicture(PresenterInterface $presenter, Media $media, array $customAttributes, callable $sizesFunc, int $idealFallbackSize, bool $useHtml4 = false) {
        if($media->getType() !== Media::TYPE_IMAGE) { throw new \Exception('expected image'); }

        $sources = self::getImageSources($presenter, $media, $customAttributes['external']);
        unset($customAttributes['external']);

        $baseAttributes = [
            'src' => self::getImageFallbackSource($media, $sources, $idealFallbackSize),
            'alt' => $media->getCaption() ?: $media->getName()
        ];
        $imgTag = HtmlHelper::img(array_merge_recursive_distinct($baseAttributes, $customAttributes));

        if($presenter->hasStyleHint(MediaPresenter::HTML4)) {
            return $imgTag;
        }

        $html = '<picture>';
        foreach(MediaPresenter::MEDIA_LOAD_ORDER as $mimeType) {
            if(!isset($sources[$mimeType])) { continue; }
            $source = $sources[$mimeType];
            $sizes = $sizesFunc($source);
            if(is_array($sizes)) {
                $sizes = implode(', ', $sizes);
            }
            $html .= '<source ' . html_attributes([
                    'type' => $mimeType,
                    'srcset' => HtmlHelper::srcset($source),
                    'sizes' => $sizes
                ]) . '></source>';
        }

        $html .= $imgTag;
        $html .= '</picture>';
        return $html;
    }

    /**
     * @param PresenterInterface $presenter
     * @param Media $media
     * @param bool $external
     * @return array
     */
    public static function getImageSources(PresenterInterface $presenter, Media $media, bool $external): array {
        if($media->getType() !== Media::TYPE_IMAGE) { throw new Exception('expected image'); }
        $sources = [];
        foreach($media->getVariants() as $variant) {
            $sources[$variant['mime']][] = [
                'filename' => $presenter->getFilename($variant['filename'], $external),
                'width' => $variant['width']
            ];
        }
        return $sources;
    }

    /**
     * Selects an appropriate source to be used as a fallback.
     *
     * The format of this fallback image should be one of the broadly-compatible formats (png, jpeg, gif)
     * @param Media $media
     * @param array $sources
     * @param int $idealMinSize
     * @return string
     */
    public static function getImageFallbackSource(Media $media, array $sources, int $idealMinSize): string {
        foreach(self::MEDIA_FALLBACK_ORDER as $mime) {
            if(!isset($sources[$mime])) {
                continue;
            }
            $fallbackSources = $sources[$mime];
            // we find the smallest variant which is greater than or equal to a specified `idealMinSize`
            $smallestOverMinSize = ['width' => null];
            foreach($fallbackSources as $source) {
                if(($source['width'] === null && $smallestOverMinSize['width'] === null)
                    || ($source['width'] >= $idealMinSize && ($smallestOverMinSize['width'] === null || $source['width'] < $smallestOverMinSize['width']))) {
                    $smallestOverMinSize = $source;
                }
            }
            if(isset($smallestOverMinSize['filename'])) {
                return $smallestOverMinSize['filename'];
            }
        }
        logger()->warning('image missing appropriate fallback format, instead only got: ' . print_r($sources, true) . ' for item ' . $media->getFullPath());
        return '';
    }

}
