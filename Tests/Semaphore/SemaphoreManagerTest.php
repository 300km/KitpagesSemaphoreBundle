<?php
namespace Kitpages\SemaphoreBundle\Tests\Semaphore;

use Kitpages\SemaphoreBundle\Semaphore\SemaphoreManager;
use Kitpages\SemaphoreBundle\Transaction\TransactionManager;

use Monolog\Handler\TestHandler;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

/**
 * @group plv
 */
class SemaphoreManagerTest
    extends \PHPUnit_Framework_TestCase
{
    /** @var SemaphoreManager */
    protected $manager;

    /** @var TransactionManager */
    protected $transactionManager;

    /** @var  Logger */
    protected $logger;

    /** @var  TestHandler */
    protected $loggerTestHandler;

    protected function setUp()
    {
        parent::setUp();

        $this->logger = new Logger('name');
        $this->loggerTestHandler = new TestHandler(Logger::WARNING);
        $this->logger->pushHandler($this->loggerTestHandler);
        $this->logger->pushHandler(new ErrorLogHandler());
        foreach (glob(__DIR__.'/../app/data/kitpages_semaphore/*.csv') as $file) {
            unlink($file);
        }
        $this->transactionManager = new TransactionManager(
            __DIR__.'/../app/data/kitpages_semaphore',
            $this->logger
        );
        $this->manager = new SemaphoreManager(
            $this->transactionManager,
            100000,
            4000000,
            $this->logger
        );
    }

    protected function tearDown()
    {
        parent::tearDown();
    }

    public function testBasicSemaphore()
    {
        $startTime = microtime(true);
        $this->manager->aquire("my_key");
        $duration = microtime(true) - $startTime;
        // $this->assertTrue($duration < 2);

        $this->manager->release("my_key");

        $this->manager->aquire("my_key");
        $duration2 = microtime(true) - $startTime;
        $this->assertTrue($duration2 < 3);
        $this->manager->release("my_key");

        // check unused release
        $this->manager->release("my_key");
        $loggerRecordList = $this->loggerTestHandler->getRecords();
        $this->assertEquals(1, count($loggerRecordList));
        $record = $loggerRecordList[0];
        $message = $record["message"];
        $this->assertEquals(1, preg_match('/Realease requested, but semaphore not locked/',$message));

    }

    public function testExpiration()
    {
        $startTime = microtime(true);
        $this->manager->aquire("my_key");
        $duration = microtime(true) - $startTime;
        $this->assertTrue($duration < 2);

        $loggerRecordList = $this->loggerTestHandler->getRecords();
        $this->assertEquals(0, count($loggerRecordList));

        $this->manager->aquire("my_key");
        $duration2 = microtime(true) - $startTime;
        $this->assertTrue($duration2 > 4);

        $loggerRecordList = $this->loggerTestHandler->getRecords();
        $this->assertEquals(1, count($loggerRecordList));
        $record = $loggerRecordList[0];
        $message = $record["message"];
        $this->assertEquals(1, preg_match('/Dead lock detected.+\\/Tests\\/Manager\\/ManagerTest\\.php\\(\d+\)/',$message));
    }

}
