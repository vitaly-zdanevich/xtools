<?php

declare(strict_types = 1);

namespace AppBundle\Model;

/**
 * A TransclusionCounter provides counts of how many pages transclude the given page.
 */
class TransclusionCounter extends Model
{
    /**
     * Completely arbitrary and debatable; allows admin a quick way to
     * protect the template if it has over this number of transclusions.
     * @var int
     */
    public const UNSAFE_TRANSCLUSION_COUNT = 500;

    /** @var string Language for localization. */
    private $lang;

    /** @var int|string Used t count only transclusions on pages in this namespace. */
    private $transNamespace;

    /**
     * TransclusionCounter constructor.
     * @param Page $page
     * @param string|int $transNamespace Namespace ID or 'all', to count only pages that transclude in this namespace.
     * @param string $lang For localizing the protection types (translations are fetched from MediaWiki).
     */
    public function __construct(Page $page, $transNamespace = 'all', $lang = 'en')
    {
        $this->page = $page;
        $this->transNamespace = 'all' === $transNamespace ? 'all' : (int)$transNamespace;
        $this->lang = $lang;
    }

    /**
     * Get the number of transclusions.
     * @return int
     */
    public function getCount(): int
    {
        return $this->getRepository()
            ->getTransclusionCounts($this->page, $this->transNamespace);
    }

    /**
     * Get the value of the transclusion namespace option.
     * @return int|string
     */
    public function getTransNamespace()
    {
        return $this->transNamespace;
    }

    /**
     * Get the current protection types.
     * @return string[]
     */
    public function getProtectionTypes(): array
    {
        return $this->getRepository()->getProtectionTypes($this->page, $this->lang);
    }

    /**
     * Returns true if the page has more transclusions than what is considered safe, hence it
     * should probably be protected. This is used to conditionally show a 'protect' link in the UI.
     * @return bool
     */
    public function shouldBeProtected(): bool
    {
        return $this->getCount() > self::UNSAFE_TRANSCLUSION_COUNT;
    }
}
