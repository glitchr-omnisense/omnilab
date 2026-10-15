<?php

namespace Omnilab\Model;

/**
 * One identifier, normalised so that two spellings of it compare equal: a
 * ChEBI number without its "CHEBI:", a CAS number with its check digit
 * checked, an Ensembl id without its version, a Wikidata item upper-cased.
 *
 *   Identifier::parse('CHEBI:27732');                                   // chebi:27732
 *   Identifier::parse('58-08-2');                                       // cas:58-08-2
 *   Identifier::parse('RYYVLZVUVIJVGH-UHFFFAOYSA-N');                   // inchikey:RYYVLZVUVIJVGH-UHFFFAOYSA-N
 *   Identifier::parse('https://pubchem.ncbi.nlm.nih.gov/compound/2519'); // pubchem.cid:2519
 *   Identifier::parse('https://identifiers.org/uniprot:P69905');        // uniprot:P69905
 *   Identifier::of(Scheme::PUBCHEM_CID, '2519')->url();                 // https://pubchem.ncbi.nlm.nih.gov/compound/2519
 *   Identifier::tryOf('cas', '58-08-3');                                // null: wrong check digit
 */
final readonly class Identifier implements \Stringable
{
    private const INCHIKEY = '~^[A-Z]{14}-[A-Z]{10}-[A-Z]$~';
    private const CAS = '~^(\d{2,7})-(\d{2})-(\d)$~';
    private const UNIPROT = '~^(?:[OPQ][0-9][A-Z0-9]{3}[0-9]|[A-NR-Z][0-9](?:[A-Z][A-Z0-9]{2}[0-9]){1,2})(?:-\d+)?$~';
    private const ENSEMBL = '~^(ENS[A-Z]*[EFGPRT]\d{11})(?:\.\d+)?$~';

    /** The other names a "prefix:value" may give a scheme: identifiers.org's prefixes, the bases' own. */
    private const PREFIXES = [
        'cid' => Scheme::PUBCHEM_CID, 'pubchem' => Scheme::PUBCHEM_CID, 'pubchem.compound' => Scheme::PUBCHEM_CID,
        'chembl.compound' => Scheme::CHEMBL,
        'casrn' => Scheme::CAS, 'cas_rn' => Scheme::CAS, 'cas.rn' => Scheme::CAS,
        'kegg.compound' => Scheme::KEGG, 'kegg.drug' => Scheme::KEGG, 'cpd' => Scheme::KEGG, 'dr' => Scheme::KEGG,
        'dtxsid' => Scheme::DSSTOX, 'comptox' => Scheme::DSSTOX,
        'uniprotkb' => Scheme::UNIPROT,
        'ncbigene' => Scheme::NCBI_GENE, 'gene' => Scheme::NCBI_GENE,
        'taxonomy' => Scheme::NCBI_TAXON, 'ncbitaxon' => Scheme::NCBI_TAXON, 'txid' => Scheme::NCBI_TAXON,
        'inaturalist.taxon' => Scheme::INATURALIST,
        'sbdb' => Scheme::JPL_SBDB, 'jpl' => Scheme::JPL_SBDB,
        'usgs' => Scheme::USGS_EVENT,
        'wd' => Scheme::WIKIDATA,
    ];

    private function __construct(
        public Scheme $scheme,
        public string $value,
    ) {
    }

    /**
     * @throws \InvalidArgumentException when the value is not a valid identifier of that scheme
     */
    public static function of(Scheme|string $scheme, string $value): self
    {
        $scheme = $scheme instanceof Scheme ? $scheme : (self::scheme($scheme) ?? throw new \InvalidArgumentException(\sprintf('"%s" is not a scheme Omnilab knows.', $scheme)));

        return self::tryOf($scheme, $value) ?? throw new \InvalidArgumentException(\sprintf('"%s" is not a valid %s.', $value, $scheme->label()));
    }

    /** The identifier, or null when the value is not one of that scheme. */
    public static function tryOf(Scheme|string|null $scheme, int|string|null $value): ?self
    {
        if (null === $scheme || null === $value) {
            return null;
        }
        $scheme = $scheme instanceof Scheme ? $scheme : self::scheme($scheme);
        $value = trim((string) $value);
        if (null === $scheme || '' === $value) {
            return null;
        }
        $upper = strtoupper($value);
        $normalized = match ($scheme) {
            Scheme::PUBCHEM_CID => preg_match('~^(?:CID[:\s]*)?0*([1-9]\d{0,11})$~', $upper, $m) ? $m[1] : null,
            Scheme::CHEBI => preg_match('~^(?:CHEBI:)?0*([1-9]\d*)$~', $upper, $m) ? $m[1] : null,
            Scheme::CHEMBL => preg_match('~^CHEMBL\d+$~', $upper) ? $upper : null,
            Scheme::INCHIKEY => preg_match(self::INCHIKEY, $v = (string) preg_replace('~^INCHIKEY=~', '', $upper)) ? $v : null,
            Scheme::INCHI => preg_match('~^InChI=1S?/\S+$~', $value) ? $value : null,
            Scheme::CAS => self::normalizeCas($value),
            Scheme::UNII => preg_match('~^(?:UNII[-:\s]?)?([A-Z0-9]{10})$~', $upper, $m) ? $m[1] : null,
            Scheme::DSSTOX => preg_match('~^DTXSID\d+$~', $upper) ? $upper : null,
            Scheme::KEGG => preg_match('~^(?:CPD:|DR:)?([CD]\d{5})$~', $upper, $m) ? $m[1] : null,
            Scheme::UNIPROT => preg_match(self::UNIPROT, $upper) ? $upper : null,
            Scheme::NCBI_GENE, Scheme::INATURALIST => preg_match('~^0*([1-9]\d*)$~', $value, $m) ? $m[1] : null,
            Scheme::GBIF => self::normalizeGbif($upper),
            Scheme::NCBI_TAXON => preg_match('~^(?:TXID)?0*([1-9]\d*)$~', $upper, $m) ? $m[1] : null,
            Scheme::ENSEMBL => preg_match(self::ENSEMBL, $upper, $m) ? $m[1] : null,
            Scheme::PDB => preg_match('~^\d[A-Z0-9]{3}$~', $upper) ? $upper : (preg_match('~^PDB_\d{4}[A-Z0-9]{4}$~', $upper) ? strtolower($upper) : null),
            Scheme::SIMBAD, Scheme::JPL_SBDB => self::name($value),
            // A network's code then the event's own (ComCat keeps the catalogues' long ones: official20110311054624120_30, iscgemsup16957911).
            Scheme::USGS_EVENT => preg_match('~^[a-z]{2,10}[a-z0-9_]{6,24}$~', $v = strtolower($value)) ? $v : null,
            Scheme::WIKIDATA => preg_match('~^(?:WD:)?(Q[1-9]\d*)$~', $upper, $m) ? $m[1] : null,
        };

        return null === $normalized ? null : new self($scheme, $normalized);
    }

    /**
     * Reads an identifier from a string: "scheme:value" (the schemes' own
     * names, identifiers.org's prefixes, CHEBI:27732), a page of the base
     * that holds it or an identifiers.org link, or a value whose shape tells
     * its scheme (an InChIKey, a CAS number, CHEMBL113, DTXSID..., an
     * Ensembl id, a Wikidata Q-number). Null when nothing is recognised: a
     * bare number (a CID? a GBIF key? a gene?) needs its scheme, and so does
     * a UniProt accession or a PDB code, whose shapes other values share.
     */
    public static function parse(string $input): ?self
    {
        $input = trim($input);
        if ('' === $input) {
            return null;
        }
        if (preg_match('~^https?://~i', $input)) {
            return self::fromUrl($input);
        }
        // scheme:value
        if (preg_match('~^([a-z][a-z._-]*):\s*(.+)$~i', $input, $m) && null !== ($scheme = self::scheme($m[1]))) {
            return self::tryOf($scheme, $m[2]);
        }
        $upper = strtoupper($input);

        return match (true) {
            str_starts_with($input, 'InChI=') => self::tryOf(Scheme::INCHI, $input),
            (bool) preg_match(self::INCHIKEY, $upper) => self::tryOf(Scheme::INCHIKEY, $upper),
            (bool) preg_match(self::CAS, $input) => self::tryOf(Scheme::CAS, $input),
            (bool) preg_match('~^CID\s*\d+$~', $upper) => self::tryOf(Scheme::PUBCHEM_CID, $upper),
            (bool) preg_match('~^CHEMBL\d+$~', $upper) => self::tryOf(Scheme::CHEMBL, $upper),
            (bool) preg_match('~^DTXSID\d+$~', $upper) => self::tryOf(Scheme::DSSTOX, $upper),
            (bool) preg_match('~^UNII-[A-Z0-9]{10}$~', $upper) => self::tryOf(Scheme::UNII, $upper),
            (bool) preg_match(self::ENSEMBL, $upper) => self::tryOf(Scheme::ENSEMBL, $upper),
            (bool) preg_match('~^Q[1-9]\d*$~', $upper) => self::tryOf(Scheme::WIKIDATA, $upper),
            default => null,
        };
    }

    /** The scheme a name or a prefix stands for: "chebi", "pubchem.cid", "ncbigene", "CHEBI"... */
    public static function scheme(string $name): ?Scheme
    {
        $name = strtolower(trim($name));

        return Scheme::tryFrom($name) ?? self::PREFIXES[$name] ?? null;
    }

    public static function cid(int|string $value): self
    {
        return self::of(Scheme::PUBCHEM_CID, (string) $value);
    }

    public static function chebi(int|string $value): self
    {
        return self::of(Scheme::CHEBI, (string) $value);
    }

    public static function inchikey(string $value): self
    {
        return self::of(Scheme::INCHIKEY, $value);
    }

    public static function cas(string $value): self
    {
        return self::of(Scheme::CAS, $value);
    }

    public static function wikidata(string $value): self
    {
        return self::of(Scheme::WIKIDATA, $value);
    }

    /** "chebi:27732": what the Merger and the collections key on. */
    public function key(): string
    {
        return $this->scheme->value.':'.$this->value;
    }

    /** The identifier as its base writes it: CHEBI:27732, CID 2519, 58-08-2. */
    public function display(): string
    {
        return match ($this->scheme) {
            Scheme::CHEBI => 'CHEBI:'.$this->value,
            Scheme::PUBCHEM_CID => 'CID '.$this->value,
            default => $this->value,
        };
    }

    public function equals(self $other): bool
    {
        return $this->scheme === $other->scheme && $this->value === $other->value;
    }

    /** The page of the base that holds it, when there is one. */
    public function url(): ?string
    {
        $v = rawurlencode($this->value);

        return match ($this->scheme) {
            Scheme::PUBCHEM_CID => 'https://pubchem.ncbi.nlm.nih.gov/compound/'.$v,
            Scheme::CHEBI => 'https://www.ebi.ac.uk/chebi/CHEBI:'.$v,
            Scheme::CHEMBL => 'https://identifiers.org/chembl.compound:'.$v,
            Scheme::INCHIKEY, Scheme::INCHI => null,
            Scheme::CAS => 'https://commonchemistry.cas.org/detail?cas_rn='.$v,
            Scheme::UNII => 'https://precision.fda.gov/uniisearch/srs/unii/'.$v,
            Scheme::DSSTOX => 'https://comptox.epa.gov/dashboard/chemical/details/'.$v,
            Scheme::KEGG => 'https://www.kegg.jp/entry/'.$v,
            Scheme::UNIPROT => 'https://www.uniprot.org/uniprotkb/'.$v,
            Scheme::NCBI_GENE => 'https://www.ncbi.nlm.nih.gov/gene/'.$v,
            Scheme::NCBI_TAXON => 'https://www.ncbi.nlm.nih.gov/Taxonomy/Browser/wwwtax.cgi?id='.$v,
            Scheme::ENSEMBL => 'https://www.ensembl.org/id/'.$v,
            Scheme::PDB => 'https://www.rcsb.org/structure/'.$v,
            Scheme::GBIF => ctype_digit($this->value) ? 'https://www.gbif.org/species/'.$v : 'https://www.gbif.org/taxon/'.$v,
            Scheme::INATURALIST => 'https://www.inaturalist.org/taxa/'.$v,
            Scheme::SIMBAD => 'https://simbad.cds.unistra.fr/simbad/sim-id?Ident='.$v,
            Scheme::JPL_SBDB => 'https://ssd.jpl.nasa.gov/tools/sbdb_lookup.html#/?sstr='.$v,
            Scheme::USGS_EVENT => 'https://earthquake.usgs.gov/earthquakes/eventpage/'.$v,
            Scheme::WIKIDATA => 'https://www.wikidata.org/wiki/'.$v,
        };
    }

    public function __toString(): string
    {
        return $this->key();
    }

    private static function fromUrl(string $url): ?self
    {
        $host = strtolower((string) parse_url($url, \PHP_URL_HOST));
        $path = rawurldecode(trim((string) parse_url($url, \PHP_URL_PATH), '/'));
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        $fragment = rawurldecode((string) parse_url($url, \PHP_URL_FRAGMENT));
        $last = basename($path);
        $found = static fn (string $pattern, Scheme $scheme): ?self => preg_match($pattern, rawurldecode($url), $m) ? self::tryOf($scheme, $m[1]) : null;

        return match (true) {
            str_ends_with($host, 'identifiers.org') => str_contains($path, ':') ? self::parse($path) : null,
            'pubchem.ncbi.nlm.nih.gov' === $host => preg_match('~^compound/(\d+)$~', $path, $m) ? self::tryOf(Scheme::PUBCHEM_CID, $m[1]) : null,
            str_ends_with($host, 'ebi.ac.uk') && str_contains($path, 'chembl') => $found('~(CHEMBL\d+)~i', Scheme::CHEMBL),
            str_ends_with($host, 'ebi.ac.uk') && str_contains($path, 'chebi') => $found('~CHEBI:(\d+)~i', Scheme::CHEBI),
            str_ends_with($host, 'wikidata.org') => $found('~/(?:wiki|entity)/(Q\d+)~i', Scheme::WIKIDATA),
            str_ends_with($host, 'uniprot.org') => self::firstOf(Scheme::UNIPROT, $path),
            str_ends_with($host, 'rcsb.org') => self::tryOf(Scheme::PDB, $last),
            str_ends_with($host, 'gbif.org') => $found('~/(?:species|taxon)/([0-9A-Za-z]+)~', Scheme::GBIF),
            str_ends_with($host, 'inaturalist.org') => $found('~/taxa/(\d+)~', Scheme::INATURALIST),
            'earthquake.usgs.gov' === $host => $found('~/eventpage/([a-z0-9_]+)~i', Scheme::USGS_EVENT),
            str_ends_with($host, 'ncbi.nlm.nih.gov') && str_starts_with($path, 'gene/') => self::tryOf(Scheme::NCBI_GENE, $last),
            str_ends_with($host, 'ncbi.nlm.nih.gov') && str_contains(strtolower($path), 'taxonomy') => self::tryOf(Scheme::NCBI_TAXON, (string) ($query['id'] ?? $last)),
            str_ends_with($host, 'ensembl.org') => $found('~(ENS[A-Z]*[EFGPRT]\d{11})~i', Scheme::ENSEMBL),
            'commonchemistry.cas.org' === $host => self::tryOf(Scheme::CAS, (string) ($query['cas_rn'] ?? '')),
            'comptox.epa.gov' === $host => $found('~(DTXSID\d+)~i', Scheme::DSSTOX),
            'precision.fda.gov' === $host => self::tryOf(Scheme::UNII, $last),
            str_ends_with($host, 'kegg.jp') || str_ends_with($host, 'genome.jp') => $found('~\b([CD]\d{5})\b~', Scheme::KEGG),
            str_contains($host, 'simbad') => self::tryOf(Scheme::SIMBAD, (string) ($query['Ident'] ?? $query['ident'] ?? '')),
            'ssd.jpl.nasa.gov' === $host => preg_match('~sstr=([^&]+)~', $fragment.'&'.http_build_query($query), $m) ? self::tryOf(Scheme::JPL_SBDB, $m[1]) : null,
            default => null,
        };
    }

    /** The first segment of a path that is an identifier of the scheme: uniprotkb/P69905/entry. */
    private static function firstOf(Scheme $scheme, string $path): ?self
    {
        foreach (explode('/', $path) as $segment) {
            if (null !== ($identifier = self::tryOf($scheme, (string) preg_replace('~\.\w+$~', '', $segment)))) {
                return $identifier;
            }
        }

        return null;
    }

    /**
     * A GBIF taxon key in either of its forms: the number of the legacy GBIF
     * Backbone Taxonomy (5219404, frozen since 2023, Wikidata's P846), or the
     * key of the Catalogue of Life eXtended Release GBIF's taxonomy has been
     * since June 2026 (4CGXP: one to six of the digits 2 to 9 and the
     * consonants, Wikidata's P14607). A key of digits alone is read as a
     * legacy number.
     */
    private static function normalizeGbif(string $value): ?string
    {
        $value = (string) preg_replace('~^GBIF:~', '', $value);

        return match (true) {
            (bool) preg_match('~^0*([1-9]\d{0,9})$~', $value, $m) => $m[1],
            (bool) preg_match('~^[2-9BCDFGHJKLMNPQRSTVWXYZ]{1,6}$~', $value) => $value,
            default => null,
        };
    }

    private static function normalizeCas(string $value): ?string
    {
        if (!preg_match(self::CAS, $value, $m)) {
            return null;
        }
        $digits = ltrim($m[1], '0').$m[2];
        if (\strlen(ltrim($m[1], '0')) < 2) {
            return null;
        }
        $sum = 0;
        foreach (array_reverse(str_split($digits)) as $i => $digit) {
            $sum += ($i + 1) * (int) $digit;
        }

        return $sum % 10 === (int) $m[3] ? ltrim($m[1], '0').'-'.$m[2].'-'.$m[3] : null;
    }

    /** A name as an identifier (SIMBAD's, JPL's): its spaces collapsed. */
    private static function name(string $value): ?string
    {
        $value = trim((string) preg_replace('~\s+~u', ' ', $value));

        return '' === $value || \strlen($value) > 240 ? null : $value;
    }
}
