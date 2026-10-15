<?php

namespace Omnilab\Model;

/**
 * What an entity is a thing of. Coarse on purpose: a source says what kind
 * of thing within its domain in Entity::$kind ("compound", "protein",
 * "species", "galaxy", "earthquake"), in its own word.
 */
enum Domain: string
{
    /** Compounds, substances, elements, reactions. */
    case CHEMISTRY = 'chemistry';
    /** Genes, proteins, structures, pathways. */
    case BIOLOGY = 'biology';
    /** Species and the other ranks of the tree of life, and where they were found (occurrences). */
    case TAXONOMY = 'taxonomy';
    /** Stars, galaxies, planets, small bodies. */
    case ASTRONOMY = 'astronomy';
    /** The solid earth, its oceans and its atmosphere: earthquakes, volcanoes, stations, places. */
    case EARTH = 'earth';
    /** What none of the above holds. */
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CHEMISTRY => 'Chemistry',
            self::BIOLOGY => 'Biology',
            self::TAXONOMY => 'Taxonomy',
            self::ASTRONOMY => 'Astronomy',
            self::EARTH => 'Earth sciences',
            self::OTHER => 'Other',
        };
    }
}
