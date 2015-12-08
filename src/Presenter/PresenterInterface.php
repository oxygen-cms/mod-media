<?php

namespace OxygenModule\Media\Presenter;

use OxygenModule\Media\Entity\Media;

interface PresenterInterface {

    /**
     * Whether the presenter should use absolute URLs to the resource
     *
     * @param $use
     * @return void
     */
    public function setUseAbsoluteURLs($use);

    /**
     * Displays the Media.
     *
     * @param $slug         $media
     * @param string|null   $template
     * @param array         $attributes
     * @return mixed
     */
    public function present($slug, $template = null, array $attributes = []);

    /**
     * Displays the Media.
     *
     * @param Media         $media
     * @param string|null   $template
     * @param array         $attributes
     * @return mixed
     */
    public function display(Media $media, $template = null, array $attributes = []);

    /**
     * Previews the given media item.
     *
     * @param $media
     * @return string
     */
    public function preview($media);

}