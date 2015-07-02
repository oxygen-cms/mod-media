<?php

namespace OxygenModule\Media\Presenter;

interface PresenterInterface {

    /**
     * Displays the Media.
     *
     * @param string        $slug
     * @param string|null   $template
     * @param array         $attributes
     * @return mixed
     */

    public function display($slug, $template = null, array $attributes = []);

    /**
     * Previews the given media item.
     *
     * @param $media
     * @return string
     */

    public function preview($media);

}