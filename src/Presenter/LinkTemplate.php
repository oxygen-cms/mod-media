<?php

namespace OxygenModule\Media\Presenter;

use OxygenModule\Media\Entity\Media;

class LinkTemplate implements TemplateInterface {

    /**
     * @throws \Exception
     */
    public function present(PresenterInterface $presenter, Media $media, array $customAttributes) {
        $href = $presenter->getFilename($media->getFilename(), $customAttributes['external']);
        $content = $customAttributes['content'] ?? ($media->getCaption() ? $media->getCaption() : $media->getName());
        unset($customAttributes['external']);
        unset($customAttributes['content']);
        return HtmlHelper::a(
            $content,
            array_merge_recursive_distinct(['target' => '_blank', 'href' => $href], $customAttributes)
        );
    }

    public function transformToTipTapHtml(PresenterInterface $presenter, Media $media, array $customAttributes) {
        $content = $customAttributes['content'] ?? ($media->getCaption() ? $media->getCaption() : $media->getName());
        return '<object-link type="media" id="' . $media->getId() . '" target="_blank">' . e($content) . '</object-link>';
    }
}