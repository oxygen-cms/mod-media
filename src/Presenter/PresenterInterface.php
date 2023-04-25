<?php

namespace OxygenModule\Media\Presenter;

use OxygenModule\Media\Entity\Media;

interface PresenterInterface {

    public function getTemplate(?string $name, int $type = Media::TYPE_IMAGE): TemplateInterface;

    public function setStyleHint(string $hint, bool $enabled): void;

    public function hasStyleHint(string $hint): bool;

    /**
     * Displays the Media.
     *
     * @param string $slug
     * @param string|null   $template
     * @param array         $attributes
     * @return mixed
     */
    public function present(string $slug, $template = null, array $attributes = []);

    /**
     * Displays the Media.
     *
     * @param Media         $media
     * @param string|null   $template
     * @param array         $attributes
     * @return mixed
     */
    public function display(Media $media, $template = null, array $attributes = []);

    public function getFilename(string $filename, bool $external): string;

}
