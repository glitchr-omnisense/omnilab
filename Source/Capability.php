<?php

namespace Omnilab\Source;

/** What a source can be asked, and what its answers hold. */
enum Capability: string
{
    /** entity(): one thing by an identifier (or a name, where the source reads names). */
    case ENTITY = 'entity';
    /** search(): things by text, one page at a time. */
    case SEARCH = 'search';
    /** series(): a variable at a place over a time. */
    case SERIES = 'series';
    /** products(): the catalogued products over a place and a time, one page at a time. */
    case PRODUCTS = 'products';
    /** occurrences(): the things found or happened at a place in a period - each an entity with its time and its place -, one page at a time. */
    case OCCURRENCES = 'occurrences';
    /** An entity's image is its structure: a 2D depiction of a molecule, a 3D model of a protein. */
    case STRUCTURE = 'structure';
    /** An entity carries identifiers of other bases, which the Collector can follow. */
    case CROSS_REFERENCES = 'cross_references';
    /** series() answers ahead of now: a forecast. */
    case FORECAST = 'forecast';
    /** series() answers for the past: observations, an archive, a reanalysis. */
    case HISTORICAL = 'historical';
    /** series(), products() or occurrences() take a BBox, not only a Point; a source without it refuses a box rather than answer for its centre. */
    case AREA = 'area';
    /** products() applies ProductQuery::$maxCloudCover; a source without it refuses the query rather than answer unfiltered. */
    case CLOUD_COVER = 'cloud_cover';
}
