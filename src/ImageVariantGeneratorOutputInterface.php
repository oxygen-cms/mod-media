<?php


namespace OxygenModule\Media;


interface ImageVariantGeneratorOutputInterface {

    public function setProgressTotal(int $total);
    public function advanceProgress();
    public function writeln(string $line);
    public function clearProgress();


}
