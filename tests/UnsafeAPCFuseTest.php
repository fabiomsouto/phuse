<?php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Assert;

use fabiomsouto\phuse\UnsafeAPCFuse;

/**
 * Class FuseObserver
 * A smart-ass observer for testing purposes.
 */
class FuseObserver extends Assert implements SplObserver {

    private $assertionValue;

    public function __construct($assertionValue)
    {
        $this->assertionValue = $assertionValue;
    }

    /**
     * Receive update from subject
     * @link http://php.net/manual/en/splobserver.update.php
     * @param SplSubject $subject <p>
     * The <b>SplSubject</b> notifying the observer of an update.
     * </p>
     * @return void
     * @since 5.1.0
     */
    public function update(SplSubject $subject)
    {
        self::assertEquals($subject->blown(), $this->assertionValue);
    }
}

/**
 * Class UnsafeAPCFuseTest
 */
class UnsafeAPCFuseTest extends TestCase {
    public function testCreateFuse() {
        $this->assertInstanceOf(UnsafeAPCFuse::class, new UnsafeAPCFuse("testFuse", 10, 100, 1000));
    }

    public function testGetters() {
        $name = "testFuse";
        $M = 10;
        $R = 100;
        $T = 1000;
        $fuse = new UnsafeAPCFuse($name, $M, $T, $R);
        $this->assertEquals($fuse->getName(), $name);
        $this->assertEquals($fuse->getM(), $M);
        $this->assertEquals($fuse->getR(), $R);
        $this->assertEquals($fuse->getT(), $T);
    }

    public function testMeltFuse() {
        $fuse = new UnsafeAPCFuse("testFuse", 10, 100, 1000);
        for ($i = 0; $i < 11; $i++) {
            $fuse->melt();
        }
        $this->assertTrue($fuse->blown());
    }

    public function testMeltRecoverFuse() {
        $fuse = new UnsafeAPCFuse("testFuse", 10, 100, 1000);
        for ($i = 0; $i < 11; $i++) {
            $fuse->melt();
        }
        $this->assertTrue($fuse->blown());
        $this->assertFalse($fuse->ok());
        sleep(1);
        $this->assertFalse($fuse->blown());
        $this->assertTrue($fuse->ok());
    }

    public function testMeltWithMeltsInBetween() {
        $Rs = [250, 500, 1000, 1500, 2000];

        foreach ($Rs as $R) {
            $fuse = new UnsafeAPCFuse("testFuse", 10, 100, $R);
            for ($i = 0; $i < 11; $i++) {
                $fuse->melt();
            }

            // now the fuse is blown. it should stay that way for the duration of the restart period.
            $this->assertTrue($fuse->blown());
            $this->assertFalse($fuse->ok());

            $start = $this->currentTimeMS();
            while (!$fuse->ok()) {
                $this->assertTrue($fuse->blown());
                $fuse->melt();
                usleep(10000);
            }
            $stop = $this->currentTimeMS();

            $this->assertTrue($stop - $start > $R);
            $this->assertTrue($fuse->ok());
        }
    }

    public function testFuseMeltObserver() {
        $fuse = new UnsafeAPCFuse("testFuse", 10, 100, 5000);
        $observer = new FuseObserver(true);
        $fuse->attach($observer);
        for ($i = 0; $i < 11; $i++) {
            $fuse->melt();
        }
    }

    public function testFuseResetObserver() {
        $fuse = new UnsafeAPCFuse("testFuse", 10, 100, 1000);
        $observer = new FuseObserver(false);
        for ($i = 0; $i < 11; $i++) {
            $fuse->melt();
        }
        sleep(1);
        $fuse->attach($observer);
        $this->assertTrue($fuse->ok());
    }

    public function testFuseObserverAttachDetach() {
        $fuse = new UnsafeAPCFuse("testFuse", 10, 100, 1000);
        $observer = new FuseObserver(false);
        $fuse->attach($observer);

        // use reflection to read the private observer array
        $reflector = new ReflectionObject($fuse);
        $observers = $reflector->getProperty('observers');
        $observers->setAccessible(true);
        $this->assertTrue($observers->getValue($fuse)->contains($observer));
        $fuse->detach($observer);
        $this->assertFalse($observers->getValue($fuse)->contains($observer));
    }

    private function currentTimeMS() {
        return (int) round(microtime(true) * 1000);
    }

}