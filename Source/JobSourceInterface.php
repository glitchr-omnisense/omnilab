<?php

namespace Omnilab\Source;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Exception\ProviderException;
use Omnilab\Exception\UnavailableException;
use Omnilab\Model\Job;

/**
 * A source that makes files on request (Capability::JOBS): the Copernicus
 * Climate and Atmosphere Data Stores cut a dataset to the variables, days
 * and area asked, queue the request, then keep the file for a while. The
 * source never waits: the application asks job() again until the job is
 * done, then reads its file with open().
 *
 * Beside SourceInterface rather than in it: a source written before kept
 * methods of these names that answered a Product, which a Job extends.
 *
 * @throws NotSupportedException without the account's key, or for a query it cannot list by
 * @throws UnavailableException
 * @throws ProviderException     a request the service refuses (an unknown dataset, a licence not accepted)
 */
interface JobSourceInterface extends SourceInterface
{
    /**
     * Submits a request for a file of a dataset; the service queues it.
     *
     * @param array<string, mixed> $request the dataset's inputs, in its own names
     */
    public function submit(string $dataset, array $request): Job;

    /** A request as the service has it now; null when it does not know it (or no longer). */
    public function job(string $id): ?Job;

    /**
     * The account's requests, a page at a time, newest first unless the
     * query sorts otherwise.
     *
     * @return Page<Job>
     */
    public function jobs(ProductQuery $query = new ProductQuery()): Page;
}
