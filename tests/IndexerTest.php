<?php
declare(strict_types=1);

namespace Tests\LoyaltyCorp\Search;

use LoyaltyCorp\Search\Exceptions\AliasNotFoundException;
use LoyaltyCorp\Search\Indexer;
use LoyaltyCorp\Search\Interfaces\ClientInterface;
use LoyaltyCorp\Search\Interfaces\Helpers\EntityManagerHelperInterface;
use LoyaltyCorp\Search\Interfaces\ManagerInterface;
use Tests\LoyaltyCorp\Search\Stubs\ClientStub;
use Tests\LoyaltyCorp\Search\Stubs\Handlers\HandlerStub;
use Tests\LoyaltyCorp\Search\Stubs\Helpers\EntityManagerHelperStub;
use Tests\LoyaltyCorp\Search\Stubs\ManagerStub;

/**
 * @covers \LoyaltyCorp\Search\Indexer
 */
class IndexerTest extends TestCase
{
    /**
     * Ensure the search handler index + '_new' index gets created
     *
     * @return void
     *
     * @throws \EoneoPay\Utils\Exceptions\InvalidDateTimeStringException
     */
    public function testAliasGetsCreated(): void
    {
        $elasticClient = new ClientStub();
        $indexer = $this->createInstance($elasticClient);

        $indexer->create(new HandlerStub());

        self::assertSame(['valid_new'], $elasticClient->getCreatedAliases());
    }

    /**
     * Ensure the cleaning process only disregards indices unrelated to search handlers
     *
     * @return void
     */
    public function testCleaningIndicesDoesNotRemoveUnrelatedIndices(): void
    {
        $client = new ClientStub(
            null,
            null,
            // unrelated-index and irrelevant-index should not be touched, because they are unrelated to search handlers
            [['name' => 'unrelated-index'], ['name' => 'irrelevant-index'], ['name' => 'valid-123']]
        );
        $indexer = $this->createInstance($client);
        $expected = ['valid-123'];

        $indexer->clean([new HandlerStub()]);

        self::assertSame($expected, $client->getDeletedIndices());
    }

    /**
     * Ensure the cleaning process only cares about indices that are related to search handlers
     *
     * @return void
     */
    public function testCleaningIndicesRepectsIndicesFromAliases(): void
    {
        $client = new ClientStub(
            null,
            null,
            [['name' => 'unrelated-index'], ['name' => 'valid-unused']],
            [['index' => 'valid', 'name' => 'anything']]
        );
        $indexer = $this->createInstance($client);
        $expected = ['valid-unused'];

        $indexer->clean([new HandlerStub()]);

        self::assertSame($expected, $client->getDeletedIndices());
    }

    /**
     * Ensure dry running the index swap method does not call anything from elastic client
     *
     * @return void
     */
    public function testIndexSwapperDryRun(): void
    {
        $elasticClient = new ClientStub(
            true,
            null,
            null,
            [['name' => 'valid_new', 'index' => 'valid_201900502']]
        );
        $indexer = $this->createInstance($elasticClient);
        $expected = ['valid_new'];

        $indexer->indexSwap([new HandlerStub()]);

        self::assertSame($expected, $elasticClient->getDeletedAliases());
    }

    /**
     * Ensure the swap method removes the _new alias
     *
     * @return void
     */
    public function testIndexSwapperRemovesNewAlias(): void
    {
        $elasticClient = new ClientStub(
            true,
            null,
            null,
            [['name' => 'valid_new', 'index' => 'valid_201900502']]
        );
        $indexer = $this->createInstance($elasticClient);

        $indexer->indexSwap([new HandlerStub()], true);

        self::assertSame([], $elasticClient->getSwappedAliases());
        self::assertSame([], $elasticClient->getDeletedAliases());
    }

    /**
     * Ensure the index<->alias swap does indeed happen
     *
     * @return void
     */
    public function testIndexSwapperSwapsAlias(): void
    {
        $elasticClient = new ClientStub(
            true,
            null,
            null,
            [['name' => 'valid_new', 'index' => 'valid_201900502']]
        );
        $indexer = $this->createInstance($elasticClient);
        // alias => index
        $expected = ['valid' => 'valid_201900502'];

        $indexer->indexSwap([new HandlerStub()]);

        self::assertSame($expected, $elasticClient->getSwappedAliases());
    }

    /**
     * Ensure the index swap method throws an Exception if no *_new alias can be found
     *
     * @return void
     */
    public function testIndexSwapperThrowsExceptionIfAliasNotFound(): void
    {
        $this->expectException(AliasNotFoundException::class);
        $this->expectExceptionMessage('Could not find expected alias \'valid_new\'');

        $elasticClient = new ClientStub(true);
        $indexer = $this->createInstance($elasticClient);

        $indexer->indexSwap([new HandlerStub()]);
    }

    /**
     * Index population happens in batches, loops are involved, and then whatever is left over unpopulated, outside
     * of these loops should be still handled
     *
     * @return void
     */
    public function testLeftoverIterationsGetUpdated(): void
    {
        $manager = new ManagerStub();

        // 6 documents, that way there is one loop of batched 5, and one left over unhandled
        $entityManagerHelper = new EntityManagerHelperStub(6);
        $indexer = $this->createInstance(null, $entityManagerHelper, $manager);

        $indexer->populate(new HandlerStub(), 5);

        // 2 calls to handleUpdate should be done, one within the batch loop, and one for the left over data
        self::assertSame(2, $manager->getUpdateCount());
    }

    /**
     * Ensure the search handler index + '_new' alias is deleted so it can be re-created, when it pre-exists
     *
     * @return void
     *
     * @throws \EoneoPay\Utils\Exceptions\InvalidDateTimeStringException
     */
    public function testTemporaryAliasDeleted(): void
    {
        $elasticClient = new ClientStub(true);
        $indexer = $this->createInstance($elasticClient);
        $expected = ['valid_new'];

        $indexer->create(new HandlerStub());

        // No deleted aliases because *_new was not existing already
        self::assertSame($expected, $elasticClient->getDeletedAliases());
    }

    /**
     * Instantiate an Indexer
     *
     * @param \LoyaltyCorp\Search\Interfaces\ClientInterface|null $client
     * @param \LoyaltyCorp\Search\Interfaces\Helpers\EntityManagerHelperInterface|null $entityManagerHelper
     * @param \LoyaltyCorp\Search\Interfaces\ManagerInterface|null $manager
     *
     * @return \LoyaltyCorp\Search\Indexer
     */
    private function createInstance(
        ?ClientInterface $client = null,
        ?EntityManagerHelperInterface $entityManagerHelper = null,
        ?ManagerInterface $manager = null
    ): Indexer {
        return new Indexer(
            $client ?? new ClientStub(),
            $entityManagerHelper ?? new EntityManagerHelperStub(),
            $manager ?? new ManagerStub()
        );
    }
}
