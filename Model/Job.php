<?php

namespace Omnilab\Model;

/**
 * A request for a file of a dataset, as the service has it now: a Product
 * in the making. The dataset is its collection; created, started and
 * finished are its datetime, start and end; once successful, the file made
 * is its "data" asset (file()). It adds the status (Job::ACCEPTED, RUNNING,
 * SUCCESSFUL, FAILED, REJECTED, DISMISSED: the words of OGC API - Processes
 * and of the data stores), the progress in percent when the service says,
 * and why it failed when it says.
 *
 *   while (!$job->isDone()) { sleep(10); $job = $source->job($job->id); }
 *   $job->isSuccessful() && $source->open($job->file());
 */
final readonly class Job extends Product
{
    public const ACCEPTED = 'accepted';
    public const RUNNING = 'running';
    public const SUCCESSFUL = 'successful';
    public const FAILED = 'failed';
    public const REJECTED = 'rejected';
    public const DISMISSED = 'dismissed';

    /** The states a request does not leave. */
    public const ENDS = [self::SUCCESSFUL, self::FAILED, self::REJECTED, self::DISMISSED];

    /**
     * @param string               $dataset    the dataset the file is made of (the Product's collection)
     * @param float|null           $progress   percent, when the service says
     * @param array<string, mixed> $properties the service's own (the request, a checksum...)
     */
    public function __construct(
        string $id,
        string $dataset,
        public string $status,
        public ?float $progress = null,
        public ?string $message = null,
        ?\DateTimeImmutable $created = null,
        ?\DateTimeImmutable $started = null,
        ?\DateTimeImmutable $finished = null,
        array $properties = [],
        ?Asset $file = null,
        ?string $url = null,
        ?string $source = null,
    ) {
        parent::__construct($id, $dataset, $created, $started, $finished, properties: $properties, assets: null === $file ? [] : ['data' => $file], url: $url, source: $source);
    }

    /** Ended, well or not: successful, failed, rejected or dismissed. */
    public function isDone(): bool
    {
        return \in_array($this->status, self::ENDS, true);
    }

    public function isSuccessful(): bool
    {
        return self::SUCCESSFUL === $this->status;
    }

    /** The file made, once successful: open() reads it. */
    public function file(): ?Asset
    {
        return $this->assets['data'] ?? null;
    }
}
