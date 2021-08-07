<?php

namespace OxygenModule\Media\Presenter;

class HtmlHelper {

    /**
     * Renders an image using the default attributes.
     *
     * @param array $attrs
     * @return string
     */
    public static function img(array $attrs): string {
        return '<img ' . html_attributes($attrs) . ' />';
    }

    /**
     * Renders an audio element using the default attributes.
     *
     * @param array $sources
     * @param array $attrs
     * @param string $fallbackText
     * @return string
     */
    public static function audio(array $sources, array $attrs, string $fallbackText): string {
        $return = '<audio ' . html_attributes($attrs) . '>';
        foreach($sources as $mime => $src) {
            $return .= '<source ' . html_attributes(['src' => $src, 'type' => $mime]) . '>';
        }
        $return .= $fallbackText . '</audio>';
        return $return;
    }

    /**
     * Renders a link to a document.
     *
     * @param string $content
     * @param array  $attrs
     * @return string
     */
    public static function a(string $content, array $attrs): string {
        return '<a ' . html_attributes($attrs) . '>' . $content . '</a>';
    }

    /**
     * Composes the `srcset` attribute from the array of sources
     *
     * @param array $sources
     * @return string
     */
    public static function srcset(array $sources): string {
        $srcset = [];
        foreach($sources as $source) {
            $src = e($source['filename']);
            if($source['width'] !== null) {
                $src .= ' ' . e($source['width']) . 'w';
            }
            $srcset[] = $src;

        }
        return implode(', ', $srcset);
    }

}
