<?php

namespace OxygenModule\Media;

use Exception;
use Intervention\Image\Image;

class MacroProcessor {
    /**
     * @var array
     */
    private $macro;

    /**
     * Constructs the MacroProcessor
     *
     * @param array $macro
     */
    public function __construct($macro) {
        $this->macro = $macro;
    }

    /**
     * Processes the given image with the macro.
     *
     * @param Image $image
     * @throws Exception if the macro is invalid
     * @return Image
     */
    public function process(Image $image) {
        foreach($this->macro as $key => $args) {
            $method = 'process' . ucfirst($key);
            if(method_exists($this, $method)) {
                $image = $this->{$method}($image, $args);
            } else {
                throw new Exception('Filter "' . $key . '" doesn\'t exist');
            }
        }
        return $image;
    }

    public function processBlur(Image $image, $args) {
        return $image->blur($args);
    }

    public function processBrightness(Image $image, $args) {
        return $image->brightness($args);
    }

    /**
     * @param Image $image
     * @param array $args
     * @return Image
     * @throws Exception
     */
    public function processColorize(Image $image, $args) {
        if(count($args) !== 3) {
            throw new Exception('Filter "colorize" requires 3 parameters');
        }
        return $image->colorize($args[0], $args[1], $args[2]);
    }

    public function processContrast(Image $image, $args) {
        return $image->contrast($args);
    }

    /**
     * @param Image $image
     * @param array $args
     * @return Image
     * @throws Exception
     */
    public function processCrop(Image $image, $args) {
        if(!isset($args['width']) || !isset($args['height'])) {
            throw new Exception('Filter "crop" requires the "width" and "height" parameters');
        } else if(isset($args['x']) && isset($args['y'])) {
            return $image->crop($args['width'], $args['height'], $args['x'], $args['y']);
        } else {
            return $image->crop($args['width'], $args['height']);
        }
    }

    public function processFlip(Image $image, $args) {
        return $image->flip($args);
    }

    /**
     * @param Image $image
     * @param array $args
     * @return Image
     * @throws Exception
     */
    public function processFit(Image $image, $args) {
        if(!isset($args['width']) && !isset($args['height'])) {
            throw new Exception('Filter "fit" requires the "width" and "height" parameters');
        } else if(isset($args['position'])) {
            return $image->fit($args['width'], $args['height'], null, $args['position']);
        } else {
            return $image->fit($args['width'], $args['height']);
        }
    }

    public function processGamma(Image $image, $args) {
        return $image->gamma($args);
    }

    public function processGreyscale(Image $image, $args) {
        return $args === 'true' ? $image->greyscale() : $image;
    }

    public function processInvert(Image $image, $args) {
        return $args === 'true' ? $image->invert() : $image;
    }

    public function processPixelate(Image $image, $args) {
        return $image->pixelate($args);
    }

    /**
     * @param Image $image
     * @param array $args
     * @return Image
     * @throws Exception
     */
    public function processResize(Image $image, $args) {
        $constraint = function($constraint) use($args) {
            if(isset($args['keepAspectRatio']) && $args['keepAspectRatio'] == true) {
                $constraint->aspectRatio();
            }
            if(isset($args['preventUpsize']) && $args['preventUpsize'] == true) {
                $constraint->upsize();
            }
        };

        if(isset($args['width'])) {
            if(isset($args['height'])) {
                return $image->resize($args['width'], $args['height'], $constraint);
            } else {
                return $image->resize($args['width'], null, $constraint);
            }
        } else {
            if(isset($args['height'])) {
                return $image->resize(null, $args['height'], $constraint);
            } else {
                throw new Exception('Filter "resize" requires either the "width" or "height" parameters');
            }
        }
    }

    /**
     * @param Image $image
     * @param array $args
     * @return Image
     * @throws Exception
     */
    public function processRotate(Image $image, $args) {
        if(!isset($args['angle'])) {
            throw new Exception('Filter "rotate" requires the "angle" parameter');
        } else if(isset($args['backgroundColor'])) {
            return $image->rotate($args['angle'], $args['backgroundColor']);
        } else {
            return $image->rotate($args['angle']);
        }
    }

    public function processSharpen(Image $image, $args) {
        return $image->sharpen($args);
    }

}