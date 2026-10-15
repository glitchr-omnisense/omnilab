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
    /** datasets(): the catalogue's datasets by text, one page at a time. */
    case DATASETS = 'datasets';
    /** The source implements JobSourceInterface: submit() a request for a file, job() and jobs() follow it. */
    case JOBS = 'jobs';
    /** open(): a file read with the source's credentials (a token, a key). */
    case OPEN = 'open';
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
    /** series() takes a station's Identifier (ghcn, wigos, nws.station) in place of a Point, and answers that station's records; a source without it refuses a station. */
    case STATION = 'station';
    /** products() applies ProductQuery::$maxCloudCover; a source without it refuses the query rather than answer unfiltered. */
    case CLOUD_COVER = 'cloud_cover';
}
