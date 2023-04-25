<?php

namespace OxygenModule\Media\Presenter;

use OxygenModule\Media\Entity\Media;

class ImageTemplate implements TemplateInterface {

    /**
     * @throws \Exception
     */
    public function present(PresenterInterface $presenter, Media $media, array $customAttributes): string {
        return HtmlHelper::renderResponsivePicture(
            $presenter,
            $media,
            $customAttributes,
            function(array $sources) { return null; },
            $presenter->hasStyleHint(MediaPresenter::HTML4) ? MediaPresenter::IDEAL_EMAIL_FALLBACK_SIZE : MediaPresenter::IDEAL_WEB_FALLBACK_SIZE
        );
    }

    public function transformToTipTapHtml(PresenterInterface $presenter, Media $media, array $customAttributes) {
        return '<media-item id="' . $media->getId() . '"></media-item>';
    }
}