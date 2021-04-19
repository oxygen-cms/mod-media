<?php

namespace OxygenModule\Media\Presenter;

use OxygenModule\Media\Entity\Media;

interface PresenterInterface {

    /**
     * Whether the presenter should use absolute URLs to the resource
     *
     * @param boolean $use
     * @return void
     */
    public function setUseAbsoluteURLs(bool $use);

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

}
