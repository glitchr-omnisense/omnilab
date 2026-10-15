<?php

namespace Omnilab\Source;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Exception\ProviderException;
use Omnilab\Exception\UnavailableException;
use Omnilab\Model\Asset;
use Omnilab\Model\Entity;
use Omnilab\Model\Identifier;
use Omnilab\Model\Product;
use Omnilab\Model\Series;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * A service that publishes what science knows or measures: PubChem,
 * Open-Meteo, the Copernicus Data Space... whatever its domain. Its answers
 * take one of four shapes:
 *
 * - an Entity: one thing, named and identified, with its properties and its
 *   identifiers in other bases (entity(), search());
 * - a Series: one variable at a place over a time (series());
 * - a Product: a catalogued scene or file, its footprint and its assets
 *   (products());
 * - occurrences: the entities found or happened at a place in a period,
 *   each with its time and its place - the observations of a species, the
 *   earthquakes of a region (occurrences()).
 *
 * A catalogue's datasets are Products too (datasets()), and its files are
 * read with the source's credentials (open()); a source that makes files
 * on request implements JobSourceInterface besides.
 *
 * A source answers what it can, says what that is (capabilities()), and
 * throws NotSupportedException for the rest: an operation it does not do,
 * an identifier of a scheme it cannot read - before any call, so that the
 * Collector can offer a source an identifier without cost.
 *
 * An entity is named by an Identifier, or by a string the source reads:
 * one of its identifiers ("2519", "CHEBI:27732"), a page of a base
 * ("https://pubchem.ncbi.nlm.nih.gov/compound/2519") or, for the sources
 * that look things up by name, a name ("caffeine").
 *
 * Unknown is null (or an empty page, or a series without points); a
 * service that is down or refuses is an UnavailableException
 * (RateLimitedException when asked to slow down); any other error answer a
 * ProviderException.
 *
 * @throws NotSupportedException
 * @throws UnavailableException
 * @throws ProviderException
 */
interface SourceInterface
{
    /** The name it is configured as: "pubchem", "open-meteo", "copernicus"... */
    public function getName(): string;

    /** @return list<Capability> */
    public function capabilities(): array;

    /** One entity, or null when unknown. */
    public function entity(Identifier|string $id): ?Entity;

    /**
     * Entities matching a text, one page at a time, the best match first.
     *
     * @return Page<Entity>
     */
    public function search(Query $query): Page;

    /**
     * One series per variable asked, in the order asked.
     *
     * @return list<Series>
     */
    public function series(SeriesQuery $query): array;

    /**
     * Products over the place and the period, one page at a time, newest first unless the query sorts otherwise.
     *
     * @return Page<Product>
     */
    public function products(ProductQuery $query): Page;

    /**
     * The things found or happened at the place in the period, one page at a
     * time, each an Entity with its time and its place (a Point); in the
     * source's order unless the query sorts. An entity of a page may hold
     * less than entity() gives for it (its README says what): entity($it->id)
     * reads it whole.
     *
     * @return Page<Entity>
     */
    public function occurrences(OccurrenceQuery $query): Page;

    /**
     * The datasets of the source's catalogue that match a text, the best
     * match first, one page at a time: each a Product whose id is what
     * ProductQuery::$collections takes.
     *
     * @return Page<Product>
     */
    public function datasets(Query $query): Page;

    /**
     * A file (an asset, or its address) opened with the source's
     * credentials: the response, streamed, not read. Nothing is decoded.
     */
    public function open(Asset|string $file): ResponseInterface;
}
