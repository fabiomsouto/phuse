<?php
use PHPUnit\Framework\TestCase;
use fabiomsouto\phuse\UnsafeAPCFuse;

/**
 * Class UnsafeAPCFuseTest
 * @covers UnsafeAPCFuse
 */
class UnsafeAPCFuseTest extends TestCase {
    public function testCreateFuse() {
        $this->assertInstanceOf(UnsafeAPCFuse::class, new UnsafeAPCFuse("testFuse", 10, 100, 1000));
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

            // now the fuse is melted. it should stay that way for the duration of the restart period.
            $this->assertTrue($fuse->blown());
            $this->assertFalse($fuse->ok());

            $start = $this->currentTimeMS();
            while (!$fuse->ok()) {
                $fuse->melt();
                usleep(10000);
            }
            $stop = $this->currentTimeMS();

            $this->assertTrue($stop - $start > $R);
        }
    }

    private function currentTimeMS() {
        return (int) round(microtime(true) * 1000);
    }

}