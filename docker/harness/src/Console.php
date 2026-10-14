<?php

namespace Omnilab\Harness;

use Omnilab\Collector;
use Omnilab\Exception\InvalidConfigException;
use Omnilab\Exception\OmnilabException;
use Omnilab\Model\BBox;
use Omnilab\Model\Domain;
use Omnilab\Model\Entity;
use Omnilab\Model\Identifier;
use Omnilab\Model\Identifiers;
use Omnilab\Model\Point;
use Omnilab\Model\Product;
use Omnilab\Model\Property;
use Omnilab\Registry;
use Omnilab\Source\Capability;
use Omnilab\Source\ProductQuery;
use Omnilab\Source\Query;
use Omnilab\Source\SeriesQuery;
use Omnilab\Source\SourceFactoryInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * The console that asks every source, for real: sources, entity, search,
 * collect, series, products. A source that cannot be reached is skipped,
 * and said so on stderr.
 */
final class Console
{
    /** @var array<string, array{factory: string, needs: list<string>, options: array<string, mixed>}> */
    private array $config;

    /** @var array<string, SourceFactoryInterface> */
    private array $factories = [];

    private Registry $registry;

    private function __construct()
    {
        $this->config = require __DIR__.'/../config/sources.php';
        $http = HttpClient::create(['timeout' => 60]);
        foreach (require __DIR__.'/../plugins.php' as [, $class]) {
            if (class_exists($class)) {
                $factory = new $class($http);
                $this->factories[$factory->getName()] = $factory;
            }
        }
        $usable = array_filter($this->config, fn (array $c) => isset($this->factories[$c['factory']]) && !$this->missing($c));
        $this->registry = new Registry($this->factories, array_map(static fn (array $c) => ['factory' => $c['factory'], 'options' => array_filter($c['options'], static fn ($v) => null !== $v)], $usable));
    }

    public static function create(): Application
    {
        $self = new self();
        $source = new InputArgument('source', InputArgument::REQUIRED, 'A configured source: pubchem, open-meteo, copernicus');
        $period = [
            new InputOption('from', null, InputOption::VALUE_REQUIRED, 'From then: a date, a time, "tomorrow", "-10days"'),
            new InputOption('to', null, InputOption::VALUE_REQUIRED, 'Up to then'),
        ];
        $app = new Application('omnilab', '1.x');
        $app->addCommand($self->command('sources', 'Which sources are installed, configured, and what each can do', [], fn ($in, $out) => $self->sources($out)));
        $app->addCommand($self->command('entity', 'One entity by an identifier or a name (JSON)', [$source, new InputArgument('id', InputArgument::REQUIRED, 'An identifier (CID, InChIKey, CAS, CHEBI:..., Q..., a URL) or, where the source reads names, a name')], fn ($in, $out) => $self->entity($in, $out)));
        $app->addCommand($self->command('search', 'Entities matching a text', [$source, new InputArgument('text', InputArgument::REQUIRED), new InputOption('domain', 'd', InputOption::VALUE_REQUIRED, implode(', ', array_map(static fn (Domain $d) => $d->value, Domain::cases()))), new InputOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Entities per page', '10')], fn ($in, $out) => $self->search($in, $out)));
        $app->addCommand($self->command('collect', 'One entity, its cross-references followed into the other sources and merged (JSON)', [$source, new InputArgument('id', InputArgument::REQUIRED), new InputOption('depth', null, InputOption::VALUE_REQUIRED, 'Rounds of following', '1')], fn ($in, $out) => $self->collect($in, $out)));
        $app->addCommand($self->command('series', 'Variables at a point over a period', [
            $source,
            new InputArgument('place', InputArgument::REQUIRED, 'latitude,longitude (a point) or west,south,east,north (a box)'),
            new InputArgument('variables', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'The source\'s names: temperature_2m precipitation...'),
            ...$period,
            new InputOption('daily', null, InputOption::VALUE_REQUIRED, 'Daily, aggregated: mean, max, min, sum'),
            new InputOption('step', null, InputOption::VALUE_REQUIRED, 'An ISO 8601 step: PT1H, PT15M, P1D', SeriesQuery::HOURLY),
            new InputOption('timezone', null, InputOption::VALUE_REQUIRED, 'The times\' zone: auto, GMT, Europe/Paris...'),
        ], fn ($in, $out) => $self->series($in, $out)));
        $app->addCommand($self->command('products', 'Catalogued products over a place and a period, every page up to --max', [
            $source,
            new InputOption('point', null, InputOption::VALUE_REQUIRED, 'latitude,longitude'),
            new InputOption('bbox', null, InputOption::VALUE_REQUIRED, 'west,south,east,north'),
            new InputOption('collection', 'c', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A collection id: sentinel-2-l2a, sentinel-1-grd...'),
            ...$period,
            new InputOption('cloud', null, InputOption::VALUE_REQUIRED, 'The most cloud cover, in percent'),
            new InputOption('oldest', null, InputOption::VALUE_NONE, 'Oldest first'),
            new InputOption('max', 'm', InputOption::VALUE_REQUIRED, 'The most products read', '50'),
            new InputOption('json', null, InputOption::VALUE_NONE, 'JSON instead of a table'),
        ], fn ($in, $out) => $self->products($in, $out)));

        return $app;
    }

    /** @param list<InputArgument|InputOption> $definition */
    private function command(string $name, string $description, array $definition, \Closure $code): Command
    {
        $command = new Command($name);
        $command->setDescription($description)->setDefinition($definition);
        $command->setCode(function (InputInterface $in, OutputInterface $out) use ($code): int {
            try {
                return $code($in, $out) ?? Command::SUCCESS;
            } catch (InvalidConfigException|\InvalidArgumentException|\ValueError $e) {
                $out->writeln('<error>'.$e->getMessage().'</error>');

                return Command::INVALID;
            } catch (OmnilabException $e) {
                $out->writeln('<error>'.$e->getMessage().'</error>');

                return Command::FAILURE;
            }
        });

        return $command;
    }

    private function sources(OutputInterface $out): void
    {
        $table = new Table($out);
        $table->setHeaders(['Source', 'Factory', 'Installed', 'Configured', 'Can', 'Options']);
        foreach ($this->config as $name => $source) {
            $installed = isset($this->factories[$source['factory']]);
            $missing = $this->missing($source);
            $can = $installed && !$missing ? implode(', ', array_map(static fn (Capability $c) => $c->value, $this->registry->get($name)->capabilities())) : '';
            $options = [];
            foreach ($source['options'] as $key => $value) {
                $options[] = $key.': '.(null === $value ? '-' : (str_contains($key, 'key') ? substr((string) $value, 0, 4).'…' : (\is_array($value) ? implode(', ', $value) : (string) $value)));
            }
            $table->addRow([$name, $source['factory'], $installed ? '<info>yes</info>' : '<comment>no</comment>', $missing ? '<comment>needs '.implode(', ', $missing).'</comment>' : ($installed ? '<info>yes</info>' : ''), $can, implode(', ', $options)]);
        }
        $table->render();
        $out->writeln('Factories installed: '.(implode(', ', array_keys($this->factories)) ?: 'none'));
    }

    private function entity(InputInterface $in, OutputInterface $out): int
    {
        $entity = $this->registry->get((string) $in->getArgument('source'))->entity((string) $in->getArgument('id'));
        if (null === $entity) {
            $this->stderr($out)->writeln('<comment>Not found.</comment>');

            return Command::FAILURE;
        }
        $out->writeln(self::json($entity));

        return Command::SUCCESS;
    }

    private function search(InputInterface $in, OutputInterface $out): void
    {
        $domain = $in->getOption('domain');
        $page = $this->registry->get((string) $in->getArgument('source'))->search(new Query((string) $in->getArgument('text'), null === $domain ? null : Domain::from((string) $domain), max(1, (int) $in->getOption('limit'))));
        $table = new Table($out);
        $table->setHeaders(['Id', 'Label', 'Kind', 'Properties']);
        foreach ($page as $entity) {
            $table->addRow([$entity->id->key(), self::cut($entity->label, 50), $entity->kind, self::cut(implode('; ', array_map(static fn (Property $p) => $p->key.' '.$p, \array_slice($entity->properties, 0, 3))), 70)]);
        }
        $table->render();
        $this->stderr($out)->writeln(\sprintf('<info>%d of %s; next page: %s.</info>', \count($page), $page->total ?? '?', $page->next ?? 'none'));
    }

    private function collect(InputInterface $in, OutputInterface $out): int
    {
        $collector = new Collector($this->registry);
        $entity = $collector->collect((string) $in->getArgument('source'), (string) $in->getArgument('id'), max(1, (int) $in->getOption('depth')));
        foreach ($collector->incomplete as $name => $why) {
            $this->stderr($out)->writeln(\sprintf('<comment>Incomplete: "%s" skipped (%s).</comment>', $name, $why));
        }
        if (null === $entity) {
            $this->stderr($out)->writeln('<comment>Not found.</comment>');

            return Command::FAILURE;
        }
        $out->writeln(self::json($entity));
        $this->stderr($out)->writeln(\sprintf('<info>From %s.</info>', implode(', ', $entity->sources)));

        return Command::SUCCESS;
    }

    private function series(InputInterface $in, OutputInterface $out): void
    {
        $daily = $in->getOption('daily');
        $query = new SeriesQuery(
            array_values((array) $in->getArgument('variables')),
            self::place((string) $in->getArgument('place')),
            self::time($in->getOption('from')),
            self::time($in->getOption('to')),
            null !== $daily ? SeriesQuery::DAILY : (string) $in->getOption('step'),
            $daily,
            $in->getOption('timezone'),
        );
        $all = $this->registry->get((string) $in->getArgument('source'))->series($query);
        $table = new Table($out);
        $table->setHeaders(['Time', ...array_map(static fn ($s) => $s->variable.' ('.$s->unit.')', $all)]);
        foreach ($all[0]->points ?? [] as $i => [$time]) {
            $table->addRow([$time->format('Y-m-d H:i P'), ...array_map(static fn ($s) => $s->points[$i][1] ?? '', $all)]);
        }
        $table->render();
        $first = $all[0] ?? null;
        $this->stderr($out)->writeln(\sprintf('<info>%d points at %s%s.</info>', \count($first ?? []), $first?->place, null === $first?->forecast ? '' : ($first->forecast ? ', forecast' : ', observed or reanalysed')));
    }

    private function products(InputInterface $in, OutputInterface $out): void
    {
        $point = $in->getOption('point');
        $bbox = $in->getOption('bbox');
        $cloud = $in->getOption('cloud');
        $max = max(1, (int) $in->getOption('max'));
        $query = new ProductQuery(
            array_values((array) $in->getOption('collection')),
            null !== $bbox ? BBox::parse((string) $bbox) : (null !== $point ? Point::parse((string) $point) : null),
            self::time($in->getOption('from')),
            self::time($in->getOption('to')),
            null === $cloud ? null : (float) $cloud,
            min(100, $max),
            sort: $in->getOption('oldest') ? ProductQuery::OLDEST : ProductQuery::NEWEST,
        );
        $source = $this->registry->get((string) $in->getArgument('source'));
        $products = [];
        do {
            $page = $source->products($query);
            array_push($products, ...$page->items);
            $query = $query->with(['cursor' => $page->next]);
        } while (null !== $page->next && \count($products) < $max);
        $products = \array_slice($products, 0, $max);
        if ($in->getOption('json')) {
            $out->writeln(self::json($products));

            return;
        }
        $table = new Table($out);
        $table->setHeaders(['Datetime (UTC)', 'Collection', 'Cloud %', 'Platform', 'Id', 'Assets']);
        foreach ($products as $product) {
            $table->addRow([$product->datetime?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i'), $product->collection, $product->cloudCover(), $product->platform(), $product->id, \count($product->assets)]);
        }
        $table->render();
        $this->stderr($out)->writeln(\sprintf('<info>%d products.</info>', \count($products)));
    }

    private static function place(string $text): Point|BBox
    {
        return 4 === \count(explode(',', $text)) ? BBox::parse($text) : Point::parse($text);
    }

    private static function time(?string $text): ?\DateTimeImmutable
    {
        return null === $text || '' === $text ? null : new \DateTimeImmutable($text);
    }

    private static function cut(string $text, int $width): string
    {
        return mb_strlen($text) > $width ? mb_substr($text, 0, $width - 1).'…' : $text;
    }

    /** @param array{needs: list<string>} $source */
    private function missing(array $source): array
    {
        return array_values(array_filter($source['needs'], static fn (string $key) => false === getenv($key) || '' === getenv($key)));
    }

    private function stderr(OutputInterface $out): OutputInterface
    {
        return $out instanceof ConsoleOutputInterface ? $out->getErrorOutput() : $out;
    }

    private static function json(mixed $value): string
    {
        return (string) json_encode(self::normalize($value), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /** Models as JSON shows them: identifiers by scheme, enums as their value, times in ISO 8601. */
    private static function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof Identifiers => $value->toArray(),
            $value instanceof Identifier => $value->key(),
            $value instanceof \DateTimeInterface => $value->format(\DATE_ATOM),
            $value instanceof \BackedEnum => $value->value,
            $value instanceof Entity, $value instanceof Product, \is_object($value) => array_map(self::normalize(...), get_object_vars($value)),
            \is_array($value) => array_map(self::normalize(...), $value),
            default => $value,
        };
    }
}
