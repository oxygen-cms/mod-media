<?php

namespace OxygenModule\Media\Presenter;

use OxygenModule\Media\Entity\Media;

interface TemplateInterface {
    public function present(PresenterInterface $presenter, Media $media, array $customAttributes);
    public function transformToTipTapHtml(PresenterInterface $presenter, Media $media, array $customAttributes);
}