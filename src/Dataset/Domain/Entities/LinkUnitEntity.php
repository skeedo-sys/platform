<?php

declare(strict_types=1);

namespace Dataset\Domain\Entities;

use Ai\Domain\ValueObjects\Embedding;
use Dataset\Domain\ValueObjects\Title;
use Dataset\Domain\ValueObjects\Url;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class LinkUnitEntity extends AbstractDataUnitEntity
{
    #[ORM\Embedded(class: Url::class, columnPrefix: false)]
    private Url $url;

    /** 
     * @deprecated Since version 3.5.0 - This database field is deprecated and 
     * will be removed in v4.0.0. Kept temporarily for migration purposes only. 
     * Direct migration from 3.4.x to v4.0.0 is not supported.
     */
    #[ORM\Embedded(class: Embedding::class, columnPrefix: false)]
    public Embedding $embedding;

    public function __construct(Url $url)
    {
        parent::__construct();

        $this->url = $url;
        $this->setTitle($this->urlToTitle($url));
    }

    public function getUrl(): Url
    {
        return $this->url;
    }

    private function urlToTitle(Url $url): Title
    {
        $url = $url->value;

        // Remove protocol (http://, https://) if present
        $url = preg_replace('/^(https?:\/\/)/', '', $url);

        // Split the URL by '/' and remove empty parts
        $parts = array_filter(explode('/', $url));

        $title = null;

        // If there's a filename at the end (e.g., 'about.html'), use it
        if (!empty($parts)) {
            $lastPart = end($parts);
            if (strpos($lastPart, '.') !== false) {
                $title = explode('.', $lastPart)[0];
            }
        }

        // If no filename, use the last meaningful part of the path
        if (!$title) {
            for ($i = count($parts) - 1; $i >= 0; $i--) {
                if ($parts[$i] !== 'index' && !preg_match('/^\d+$/', $parts[$i])) {
                    $title = $parts[$i];
                    break;
                }
            }
        }

        // If no meaningful path parts, use the domain name
        if (!$title) {
            $title = $parts[0] ?? '';
        }

        // Convert raw title to a more readable format
        $title = implode(' ', array_map(function ($word) {
            return ucfirst(strtolower($word));
        }, preg_split('/[-_]/', $title)));

        return new Title($title ?: null);
    }
}
