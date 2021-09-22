<?php

namespace OxygenModule\Media\Presenter;

use Exception;
use OxygenModule\Media\Entity\Media;

trait PresentsResponsiveImages {

    /**
     * @throws Exception
     */
    public function renderResponsivePicture(Media $media, array $customAttributes, callable $sizesFunc, int $idealFallbackSize) {
        if($media->getType() !== Media::TYPE_IMAGE) { throw new Exception('expected image'); }

        $sources = $this->getImageSources($media, $customAttributes['external']);
        unset($customAttributes['external']);

        $baseAttributes = [
            'src' => $this->getImageFallbackSource($media, $sources, $idealFallbackSize),
            'alt' => $media->getCaption() ?: $media->getName()
        ];
        $imgTag = HtmlHelper::img(array_merge_recursive_distinct($baseAttributes, $customAttributes));

        if($this->getStyle() === self::EMAIL_HTML) {
            return $imgTag;
        }

        $html = '<picture>';
        foreach(HtmlPresenter::MEDIA_LOAD_ORDER as $mimeType) {
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
     * @param Media $media
     * @param bool $external
     * @return array
     * @throws Exception
     */
    public function getImageSources(Media $media, bool $external): array {
        if($media->getType() !== Media::TYPE_IMAGE) { throw new Exception('expected image'); }
        $sources = [];
        foreach($media->getVariants() as $variant) {
            $sources[$variant['mime']][] = [
                'filename' => $this->getFilename($variant['filename'], $external),
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
    public function getImageFallbackSource(Media $media, array $sources, int $idealMinSize): string {
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