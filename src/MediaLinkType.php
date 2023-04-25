<?php

namespace OxygenModule\Media;

use Oxygen\Core\Content\ObjectLinkType;
use Oxygen\Core\Support\Str;
use Oxygen\Data\Exception\NoResultException;
use OxygenModule\Media\Entity\Media;
use OxygenModule\Media\Repository\MediaRepositoryInterface;

class MediaLinkType implements ObjectLinkType {

    private MediaRepositoryInterface $media;
    private array $mediaLookupCache = [];

    public function __construct(MediaRepositoryInterface $media)
    {
        $this->media = $media;
    }

    public function getName(): string {
        return 'media';
    }

    public function getParseConfig(): array {
        return [
            [
                'tag' => 'a',
                'getAttrs' => function(\DOMElement $DOMNode) {
                    return $this->getMediaLink($DOMNode) !== null;
                }
            ]
        ];
    }

    public function getMediaLink(\DOMElement $DOMNode): ?Media
    {
        $url = $DOMNode->getAttribute('href');
        $prefix = '/media/';
        if($DOMNode->tagName == 'a' && $url !== null && Str::startsWith($url, $prefix)) {
            if(!isset($this->mediaLookupCache[$url]))
            {
                try {
                    $this->mediaLookupCache[$url] = $this->media->findByPath(substr($url, strlen($prefix)));
                } catch(NoResultException $e) {
                    $this->mediaLookupCache[$url] = null;
                }
            }
            return $this->mediaLookupCache[$url];
        } else {
            return null;
        }
    }

    public function parse(\DOMElement $DOMNode): ?array {
        $media = $this->getMediaLink($DOMNode);
        if($media === null) {
            return null;
        }
        return [
            'type' => 'media',
            'id' => $media->getId(),
            'content' => $DOMNode->textContent
        ];
    }

    public function resolveLink(int $id): array {
        $media = $this->media->find($id);
        return [
            'url' => '/media/' . $media->getFullPath(),
            'title' => $media->getCaption() ? $media->getCaption() : $media->getName(),
            'object' => $media->toArray()
        ];
    }
}