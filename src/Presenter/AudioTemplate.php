<?php

namespace OxygenModule\Media\Presenter;

use Exception;
use OxygenModule\Media\Entity\Media;

class AudioTemplate implements TemplateInterface {

    /**
     * @throws Exception
     */
    public function present(PresenterInterface $presenter, Media $media, array $customAttributes) {
        if($media->getType() !== Media::TYPE_AUDIO) { throw new Exception('expected image media'); }

        $sources = [];
        foreach($media->getVariants() as $variant) {
            $sources[$variant['mime']] = $presenter->getFilename($variant['filename'], $customAttributes['external']);
        }

        unset($customAttributes['external']);

        return HtmlHelper::audio(
            $sources,
            array_merge_recursive_distinct(['controls' => 'controls'], $customAttributes),
            'Audio Not Supported'
        );
    }

    public function transformToTipTapHtml(PresenterInterface $presenter, Media $media, array $customAttributes) {
        return '<media-item id="' . $media->getId() . '"></media-item>';
    }
}