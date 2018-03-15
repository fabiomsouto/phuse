<?php

namespace fabiomsouto\phuse;

/**
 * A fuse box is a factory for fuses.
 * @package fabiomsouto\phuse
 * @author Fabio Souto <fabiomsouto@gmail.com>
 * @license MIT license
 */
class FuseBox {

    /**
     * Returns an APC-backed fuse.
     *
     * @param string $name
     * @param int $M
     * @param int $T
     * @param int $R
     * @return UnsafeAPCFuse
     * @internal param string $strategy The fuse strategy
     */
    public static function getUnsafeApcInstance($name, $M = 10, $T = 100, $R = 1000) {
        return new UnsafeAPCFuse($name, $M, $T, $R);
    }
}