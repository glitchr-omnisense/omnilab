<?php

namespace Omnilab\Source;

use Omnilab\Model\Domain;

/**
 * What to ask search() for: a text, optionally the domain the caller means
 * (a source that spans several - Wikidata - narrows to it; a source of one
 * domain answers nothing for another), a page size and the cursor of the
 * page before.
 *
 *   new Query('caffeine', limit: 10)
 *   $query->with(['cursor' => $page->next])
 */
final readonly class Query
{
    /**
     * @param int         $limit  entities per page (each source caps it)
     * @param string|null $cursor the Page::$next of the page before
     */
    public function __construct(
        public string $text,
        public ?Domain $domain = null,
        public int $limit = 25,
        public ?string $cursor = null,
    ) {
    }

    public function with(array $changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
