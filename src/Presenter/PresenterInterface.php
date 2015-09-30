<?php

namespace OxygenModule\Media\Presenter;

use OxygenModule\Media\Entity\Media;

interface PresenterInterface {

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