<?php

namespace fabiomsouto\phuse;

/**
 * The Fuse interface.
 * Every implementation of a fuse must respect this contract.
 *
 * @package fabiomsouto\phuse
 * @author Fabio Souto <fabiomsouto@gmail.com>
 * @license MIT license
 */
interface Fuse extends \SplSubject {

    /**
     * Request the fuse to melt.
     * @return bool
     */
    public function melt();

    /**
     * Is this fuse OK?
     * @return bool
     */
    public function ok();

    /**
     * Is this fuse blown?
     * @return bool
     */
    public function blown();

    /**
     * Return this fuse's name.
     * @return string
     */
    public function getName();

    /**
     * Get the maximum number of melts (restarts) this fuse should tolerate per time-interval.
     * @return integer
     */
    public function getM();

    /**
     * Get the time interval (in ms) during which this fuse tolerates, at maximum, the configured blows (restarts).
     * @return integer
     */
    public function getT();

    /**
     * Get the annealing time (in ms) for the fuse.
     * The fuse will stay in a blown state for at least this time period, then recover at the next check.
     * @return integer
     */
    public function getR();
}