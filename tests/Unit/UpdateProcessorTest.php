<?php
declare(strict_types=1);

namespace Tests\LoyaltyCorp\Search\Unit;

use LoyaltyCorp\Search\Access\AnonymousAccessPopulator;
use LoyaltyCorp\Search\DataTransferObjects\DocumentUpdate;
use LoyaltyCorp\Search\DataTransferObjects\Handlers\ObjectForUpdate;
use LoyaltyCorp\Search\DataTransferObjects\IndexAction;
use LoyaltyCorp\Search\DataTransferObjects\Workers\HandlerObjectForChange;
use LoyaltyCorp\Search\Interfaces\Access\AccessPopulatorInterface;
use LoyaltyCorp\Search\Transformers\DefaultIndexNameTransformer;
use LoyaltyCorp\Search\UpdateProcessor;
use stdClass;
use Tests\LoyaltyCorp\Search\Stubs\ClientStub;
use Tests\LoyaltyCorp\Search\Stubs\Handlers\TransformableHandlerStub;
use Tests\LoyaltyCorp\Search\Stubs\Helpers\RegisteredSearchHandlersStub;
use Tests\LoyaltyCorp\Search\TestCases\UnitTestCase;

/**
 * @covers \LoyaltyCorp\Search\UpdateProcessor
 */
final class UpdateProcessorTest extends UnitTestCase
{
    /**
     * Tests the processor calls bulk with the actions.
     *
     * @return void
     */
    public function testActionsSentToBulk(): void
    {
        $action = new DocumentUpdate(
            'docu-id',
            'document-body'
        );

        $expected = new IndexAction(
            $action,
            'index-suffix'
        );

        $client = new ClientStub();
        $handler = new TransformableHandlerStub('index', [
            'transform' => [$action],
        ]);
        $registeredHandlers = new RegisteredSearchHandlersStub([
            'getTransformableHandlerByKey' => [
                $handler,
            ],
        ]);

        $processor = $this->getUpdateProcessor($client, $registeredHandlers);

        $processor->process('-suffix', [new HandlerObjectForChange(
            'handler',
            new ObjectForUpdate(stdClass::class, ['id' => 7])
        )]);

        self::assertEquals([['actions' => [$expected]]], $client->getBulkCalls());
    }

    /**
     * Tests the processor calls bulk with the actions with actions generated by multiple handlers.
     *
     * @return void
     */
    public function testMultiHandlerActionsSentToBulk(): void
    {
        $action = new DocumentUpdate(
            'docu-id',
            'document-body'
        );
        $action2 = new DocumentUpdate(
            'docu-id2',
            'document-body'
        );

        $expected = new IndexAction(
            $action,
            'index-suffix'
        );
        $expected2 = new IndexAction(
            $action2,
            'index2-suffix'
        );

        $client = new ClientStub();
        $handler = new TransformableHandlerStub('index', [
            'transform' => [$action],
        ]);
        $handler2 = new TransformableHandlerStub('index2', [
            'transform' => [$action2],
        ]);
        $registeredHandlers = new RegisteredSearchHandlersStub([
            'getTransformableHandlerByKey' => [
                $handler,
                $handler2,
            ],
        ]);

        $processor = $this->getUpdateProcessor($client, $registeredHandlers);

        $processor->process('-suffix', [
            new HandlerObjectForChange(
                'handler',
                new ObjectForUpdate(stdClass::class, ['id' => 7])
            ),
            new HandlerObjectForChange(
                'handler2',
                new ObjectForUpdate(stdClass::class, ['id' => 7])
            ),
        ]);

        self::assertEquals([['actions' => [$expected, $expected2]]], $client->getBulkCalls());
    }

    /**
     * Tests the processor does not call bulk when no actions are generated.
     *
     * @return void
     */
    public function testNoActionsGenerated(): void
    {
        $client = new ClientStub();
        $handler = new TransformableHandlerStub('index', [
            'transform' => [null],
        ]);
        $registeredHandlers = new RegisteredSearchHandlersStub([
            'getTransformableHandlerByKey' => [
                $handler,
            ],
        ]);

        $processor = $this->getUpdateProcessor($client, $registeredHandlers);

        $processor->process('', [new HandlerObjectForChange(
            'handler',
            new ObjectForUpdate(stdClass::class, ['id' => 7])
        )]);

        self::assertSame([], $client->getBulkCalls());
    }

    /**
     * Tests the processor does nothing when there are no updates.
     *
     * @return void
     */
    public function testNoUpdates(): void
    {
        $client = new ClientStub();
        $registeredHandlers = new RegisteredSearchHandlersStub();

        $processor = $this->getUpdateProcessor($client, $registeredHandlers);

        $processor->process('', []);

        self::assertSame([], $client->getBulkCalls());
    }

    /**
     * Build update processor.
     *
     * @param \Tests\LoyaltyCorp\Search\Stubs\ClientStub $client
     * @param \Tests\LoyaltyCorp\Search\Stubs\Helpers\RegisteredSearchHandlersStub $registeredHandlers
     * @param \LoyaltyCorp\Search\Interfaces\Access\AccessPopulatorInterface|null $accessPopulator
     * @param \LoyaltyCorp\Search\Transformers\DefaultIndexNameTransformer|null $nameTransformer
     *
     * @return \LoyaltyCorp\Search\UpdateProcessor
     */
    private function getUpdateProcessor(
        ClientStub $client,
        RegisteredSearchHandlersStub $registeredHandlers,
        ?AccessPopulatorInterface $accessPopulator = null,
        ?DefaultIndexNameTransformer $nameTransformer = null
    ): UpdateProcessor {
        return new UpdateProcessor(
            $accessPopulator ?? new AnonymousAccessPopulator(),
            $client,
            $nameTransformer ?? new DefaultIndexNameTransformer(),
            $registeredHandlers
        );
    }
}
